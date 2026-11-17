(() => {
  const start = async () => {
    const config = window.CRM?.plugins?.sentinel;
    const mapping = config?.terminology || {};
    const originals = new WeakMap();
    const escapePattern = (value) => value.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
    const terms = Object.keys(mapping).sort((a, b) => b.length - a.length);
    const pattern = terms.length ? new RegExp(`\\b(${terms.map(escapePattern).join("|")})\\b`, "g") : null;
    const rename = (root) => {
      if (!pattern) return;
      const selectors =
        ".nav-link, .dropdown-item, .page-title, h1, h2, .form-label, .breadcrumb-item, th, [role=alert], title";
      for (const element of root.querySelectorAll(selectors)) {
        if (element.closest("#sentinel-app, [data-sentinel-preserve]")) continue;
        const walker = document.createTreeWalker(element, NodeFilter.SHOW_TEXT);
        while (walker.nextNode()) {
          const node = walker.currentNode;
          if (node.parentElement.closest("script, style, input, textarea, code, pre")) continue;
          const old = originals.get(node);
          if (old?.output === node.nodeValue) continue;
          const source = node.nodeValue;
          const output = source.replace(pattern, (term) => mapping[term]);
          originals.set(node, { source, output });
          if (output !== source) node.nodeValue = output;
        }
      }
    };
    rename(document);
    let renameScheduled = false;
    new MutationObserver(() => {
      if (renameScheduled) return;
      renameScheduled = true;
      requestAnimationFrame(() => {
        renameScheduled = false;
        rename(document);
      });
    }).observe(document.body, { childList: true, subtree: true, characterData: true });

    const app = document.getElementById("sentinel-app");
    if (!app) return;
    const t = (text) => window.i18next?.t(text, { ns: "sentinel", defaultValue: text }) || text;
    const message = (text, danger = false) => {
      const box = document.getElementById("sentinel-message");
      box.textContent = text;
      box.className = `alert ${danger ? "alert-danger" : "alert-success"}`;
    };
    const api = async (path, data) => {
      const response = await fetch(`${app.dataset.root}/${path}`, {
        method: data ? "POST" : "GET",
        credentials: "same-origin",
        headers: { Accept: "application/json", "Content-Type": "application/json", "X-CSRF-Token": app.dataset.csrf },
        ...(data ? { body: JSON.stringify(data) } : {}),
      });
      if (!response.headers.get("content-type")?.includes("application/json"))
        throw new Error(t("Sign in again and reload this page."));
      const result = await response.json();
      if (!response.ok) throw new Error(result.error || t("The request failed."));
      return result;
    };
    const el = (tag, text, classes = "") => {
      const element = document.createElement(tag);
      element.textContent = text;
      element.className = classes;
      return element;
    };
    const form = (name) => app.querySelector(`[data-sentinel-form="${name}"]`);
    const field = (target, name) => target.elements.namedItem(name);
    const values = (target) => Object.fromEntries(new FormData(target));
    const fill = (target, data) => {
      for (const [name, value] of Object.entries(data)) {
        if (field(target, name)) field(target, name).value = value ?? "";
      }
    };
    const action = (label, callback) => {
      const button = el("button", t(label), "btn btn-sm btn-outline-primary me-2");
      button.type = "button";
      button.addEventListener("click", async () => {
        button.disabled = true;
        try {
          await callback();
        } catch (error) {
          message(error.message, true);
        } finally {
          button.disabled = false;
        }
      });
      return button;
    };
    const state = { contacts: [], partners: [], publications: [], assignments: [], consents: [] };
    form("partner")?.addEventListener("reset", () => {
      queueMicrotask(() => fill(form("partner"), { id: "", revision: 0 }));
    });
    const lookups = { contacts: [], teams: [], events: [] };
    const loadLookups = async (type, query = "", initial = null) => {
      const rows = initial || (await api(`lookups/${type}?q=${encodeURIComponent(query)}`)).data;
      lookups[type] = rows;
      for (const select of app.querySelectorAll(`[data-lookup="${type}"]`)) {
        const selected = select.value;
        const oldOption = select.selectedOptions[0];
        const options = [new Option(t("Choose a record"), "")];
        for (const row of rows) options.push(new Option(`${row.name} (#${row.id})`, row.id));
        if (selected && !rows.some((row) => String(row.id) === selected) && oldOption)
          options.push(new Option(oldOption.textContent, selected));
        select.replaceChildren(...options);
        select.value = selected;
      }
    };
    for (const search of app.querySelectorAll("[data-lookup-search]")) {
      let timer;
      search.addEventListener("input", () => {
        clearTimeout(timer);
        timer = setTimeout(
          () => loadLookups(search.dataset.lookupSearch, search.value).catch((error) => message(error.message, true)),
          300,
        );
      });
    }
    const syncContact = () => {
      if (!form("contact")) return;
      const target = form("contact");
      const row = state.contacts.find((item) => item.personId === Number(field(target, "personId").value));
      field(target, "staff").checked = row?.roles.includes("staff") || false;
      field(target, "volunteer").checked = row?.roles.includes("volunteer") || false;
    };
    form("contact")?.addEventListener("change", (event) => {
      if (event.target.name === "personId") syncContact();
    });
    const refreshContacts = async (initial = null) => {
      state.contacts = initial || (await api("contacts")).data;
      syncContact();
    };
    const refreshPartners = async (initial = null) => {
      const container = document.getElementById("sentinel-partners");
      if (!container) return;
      state.partners = initial || (await api("partners")).data;
      container.replaceChildren();
      for (const row of state.partners) {
        const card = el("div", "", "border rounded p-3 mb-2");
        card.append(el("h3", row.name, "h4"), el("p", `${row.type} · ${row.location}`), el("p", row.notes));
        for (const id of row.contactIds) card.append(el("p", `${t("Contact")} #${id}`));
        for (const entry of row.history) card.append(el("p", `${t("Event")} #${entry.eventId}: ${entry.description}`));
        if (form("partner"))
          card.append(
            action("Edit", () => {
              fill(form("partner"), {
                ...row,
                contactIds: row.contactIds.join(", "),
                history: row.history.map((entry) => `${entry.eventId} | ${entry.description}`).join("\n"),
              });
              field(form("partner"), "name").focus();
            }),
          );
        container.append(card);
      }
      if (!state.partners.length) container.append(el("p", t("No partner organizations yet."), "text-secondary"));
    };
    const localDate = (iso) => {
      const date = new Date(iso);
      return new Date(date.getTime() - date.getTimezoneOffset() * 60000).toISOString().slice(0, 16);
    };
    const syncEvent = () => {
      const target = form("event");
      if (!target) return;
      const row = state.publications.find((item) => item.eventId === Number(field(target, "eventId").value));
      fill(target, {
        revision: row?.revision || 0,
        title: row?.draft.title || "",
        description: row?.draft.description || "",
        location: row?.draft.location || "",
        imageUrl: row?.draft.imageUrl || "",
        start: row ? localDate(row.draft.start) : "",
        end: row ? localDate(row.draft.end) : "",
      });
    };
    form("event")?.addEventListener("change", (event) => {
      if (event.target.name === "eventId") syncEvent();
    });
    const refreshEvents = async (initial = null) => {
      const container = document.getElementById("sentinel-publications");
      if (!container) return;
      state.publications = initial || (await api("publications")).data;
      container.replaceChildren();
      for (const row of state.publications) {
        const card = el("article", "", "border rounded p-3 mb-2");
        card.append(
          el("h3", `${row.draft.title} · ${t(row.state)}`, "h4"),
          el(
            "p",
            `${new Date(row.draft.start).toLocaleString()} – ${new Date(row.draft.end).toLocaleString()} · ${row.draft.location}`,
          ),
          el("p", row.draft.description),
        );
        if (row.draft.imageUrl) {
          const imageLink = el("a", t("Review image"), "d-block mb-2");
          imageLink.href = row.draft.imageUrl;
          imageLink.target = "_blank";
          imageLink.rel = "noopener noreferrer";
          card.append(imageLink);
        } else card.append(el("p", t("Default AAPA event artwork"), "text-secondary"));
        card.append(
          el("p", row.approved ? t("An approved version is public.") : t("This event is private."), "text-secondary"),
        );
        if (form("event"))
          card.append(
            action("Edit Public Draft", () => {
              const select = field(form("event"), "eventId");
              if (![...select.options].some((option) => option.value === String(row.eventId)))
                select.add(new Option(row.draft.title, row.eventId));
              select.value = row.eventId;
              syncEvent();
              field(form("event"), "title").focus();
            }),
          );
        if (container.dataset.canPublish === "1") {
          for (const operation of ["approve", "withdraw"])
            card.append(
              action(operation === "approve" ? "Approve Public Version" : "Withdraw", async () => {
                await api(`events/${row.eventId}/${operation}`, { revision: row.revision });
                await refreshEvents();
                message(t("Publication updated."));
              }),
            );
        }
        container.append(card);
      }
      syncEvent();
    };
    const syncAssignment = () => {
      const target = form("assignment");
      if (!target) return;
      const row = state.assignments.find((item) => item.personId === Number(field(target, "personId").value));
      fill(target, { role: row?.role || "", status: row?.status || "invited" });
    };
    const refreshAssignments = async () => {
      const target = form("assignment");
      if (!target) return;
      const id = field(target, "eventId").value;
      state.assignments = id ? (await api(`events/${id}/assignments`)).data : [];
      const container = document.getElementById("sentinel-assignments");
      container.replaceChildren(
        ...state.assignments.map((row) => el("p", `#${row.personId}: ${row.role} · ${t(row.status)}`)),
      );
      syncAssignment();
    };
    form("assignment")?.addEventListener("change", (event) => {
      if (event.target.name === "eventId") refreshAssignments().catch((error) => message(error.message, true));
      if (event.target.name === "personId") syncAssignment();
    });
    const refreshConsents = async (initial = null) => {
      const container = document.getElementById("sentinel-consents");
      if (!container) return;
      state.consents = initial || (await api("consents")).data;
      container.replaceChildren(
        ...state.consents.map((row) =>
          el(
            "p",
            `${row.email}: ${t(row.granted ? "Permission granted" : "Permission withdrawn")} · ${row.history.at(-1).source}`,
          ),
        ),
      );
    };
    form("email")?.addEventListener("change", async (event) => {
      if (event.target.name !== "scope") return;
      const type = event.target.value === "team" ? "teams" : "events";
      field(form("email"), "id").dataset.lookup = type;
      field(form("email"), "id").replaceChildren();
      try {
        await loadLookups(type);
      } catch (error) {
        message(error.message, true);
      }
    });
    for (const target of app.querySelectorAll("form[data-sentinel-form]")) {
      target.addEventListener("submit", async (event) => {
        event.preventDefault();
        const buttons = [...target.querySelectorAll("button")];
        buttons.forEach((button) => {
          button.disabled = true;
        });
        try {
          const data = values(target);
          switch (target.dataset.sentinelForm) {
            case "contact": {
              const row = state.contacts.find((item) => item.personId === Number(data.personId));
              await api(`contacts/${data.personId}`, {
                revision: row?.revision || 0,
                roles: ["staff", "volunteer"].filter((role) => field(target, role).checked),
              });
              await refreshContacts();
              break;
            }
            case "partner":
              await api(`partners/${data.id || crypto.randomUUID().replaceAll("-", "")}`, {
                ...data,
                revision: Number(data.revision),
                contactIds: data.contactIds
                  .split(",")
                  .map((id) => id.trim())
                  .filter(Boolean),
                history: data.history
                  .split("\n")
                  .filter((line) => line.trim())
                  .map((line) => {
                    const [eventId, ...description] = line.split("|");
                    return { eventId: eventId.trim(), description: description.join("|").trim() };
                  }),
              });
              target.reset();
              await refreshPartners();
              break;
            case "event":
              await api(`events/${data.eventId}/draft`, {
                ...data,
                revision: Number(data.revision),
                start: new Date(data.start).toISOString().replace(".000Z", "Z"),
                end: new Date(data.end).toISOString().replace(".000Z", "Z"),
              });
              await refreshEvents();
              break;
            case "assignment": {
              const row = state.assignments.find((item) => item.personId === Number(data.personId));
              await api(`events/${data.eventId}/assignments/${data.personId}`, {
                ...data,
                revision: row?.revision || 0,
              });
              await refreshAssignments();
              break;
            }
            case "consent": {
              const row = state.consents.find((item) => item.email === data.email.trim().toLowerCase());
              await api("consents", { ...data, granted: data.granted === "true", revision: row?.revision || 0 });
              await refreshConsents();
              break;
            }
            case "email": {
              const result = await api("email", { ...data, preview: event.submitter?.name !== "send" });
              const container = document.getElementById("sentinel-email-result");
              container.replaceChildren(
                el("p", result.batch ? `${t("Queued batch")}: ${result.batch}` : t("Recipient preview")),
                ...result.recipients.map((row) => el("p", `${row.name} <${row.email}>`)),
                ...result.skipped.map((row) => el("p", `${row.name}: ${row.reason}`, "text-secondary")),
              );
              break;
            }
            case "sync": {
              const result = await api("mailchimp/sync", { id: data.id });
              message(
                `${t("Mailchimp sync")}: ${Object.entries(result)
                  .map(([key, value]) => `${t(key)}: ${value}`)
                  .join(", ")}`,
                result.failed > 0,
              );
              return;
            }
          }
          message(t("Saved."));
        } catch (error) {
          message(error.message, true);
        } finally {
          buttons.forEach((button) => {
            button.disabled = false;
          });
        }
      });
    }
    try {
      const initial = await api("bootstrap");
      const jobs = [
        refreshContacts(initial.contacts),
        refreshPartners(initial.partners),
        refreshEvents(initial.publications),
        refreshConsents(initial.consents),
      ];
      for (const type of new Set([...app.querySelectorAll("[data-lookup]")].map((select) => select.dataset.lookup)))
        jobs.push(loadLookups(type, "", initial.lookups[type]));
      const results = await Promise.allSettled(jobs);
      for (const result of results) if (result.status === "rejected") message(result.reason.message, true);
    } catch (error) {
      message(error.message, true);
    }
  };
  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", start, { once: true });
  else start();
})();
