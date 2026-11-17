/// <reference types="cypress" />

describe("Administrator publishes and withdraws a Sentinel ministry event", () => {
    let eventId;
    let wasEnabled;
    const marker = `Sentinel publication ${Date.now()}`;

    before(() => {
        cy.makePrivateAdminAPICall("GET", "/plugins/status/sentinel", null, 200).then((response) => {
            wasEnabled = response.body.isActive;
        });
        cy.makePrivateAdminAPICall("POST", "/plugins/api/plugins/sentinel/enable", {}, 200);
        cy.makePrivateAdminAPICall("POST", "/api/events", {
            Title: marker, Type: 1, Desc: "Private planning information", Text: "Internal staff roster",
            Start: "2030-12-31 17:00:00", End: "2030-12-31 19:00:00", PinnedCalendars: [],
        }, 200);
        cy.makePrivateAdminAPICall("GET", "/api/events", null, 200).then((response) => {
            const matches = response.body.Events.filter((event) => event.Title === marker);
            expect(matches).to.have.length(1);
            eventId = matches[0].Id;
        });
    });

    after(() => {
        if (eventId) cy.makePrivateAdminAPICall("DELETE", `/api/events/${eventId}`, null, 200);
        if (!wasEnabled) cy.makePrivateAdminAPICall("POST", "/plugins/api/plugins/sentinel/disable", {}, 200);
    });

    const feed = () => cy.window().then(async (win) => {
        const api = win.document.getElementById("sentinel-app").dataset.root;
        const response = await win.fetch(`${api}/public-events`, { credentials: "same-origin" });
        expect(response.status).to.equal(200);
        return (await response.json()).events.filter((event) => Number(event.id) === Number(eventId));
    });

    it("keeps drafts private, requires approval for edits, and removes withdrawn events", () => {
        cy.freshAdminFormLogin();
        cy.intercept("GET", "**/plugins/sentinel/api/bootstrap").as("bootstrap");
        cy.intercept("POST", "**/plugins/sentinel/api/events/*/approve").as("approve");
        cy.intercept("POST", "**/plugins/sentinel/api/events/*/withdraw").as("withdraw");
        cy.visit("/plugins/sentinel");
        cy.wait("@bootstrap");
        cy.get('[data-sentinel-form="event"] [name="eventId"]').select(String(eventId));
        cy.get("#event-title").type(marker);
        cy.get("#event-location").type("Example public hall");
        cy.get("#event-start").type("2030-12-31T17:00");
        cy.get("#event-end").type("2030-12-31T19:00");
        cy.get("#event-description").type("Public training description");
        cy.intercept("POST", "**/plugins/sentinel/api/events/*/draft").as("draft");
        cy.get('[data-sentinel-form="event"]').submit();
        cy.wait("@draft").its("response.statusCode").should("equal", 200);
        feed().should("deep.equal", []);
        cy.get("#sentinel-publications").contains("article", marker).contains("button", "Approve Public Version").click();
        cy.wait("@approve").its("response.statusCode").should("equal", 200);
        cy.contains("#sentinel-message", "Publication updated.");
        feed().then((events) => {
            expect(events).to.have.length(1);
            expect(events[0].title).to.equal(marker);
            expect(events[0].imageUrl).to.contain("aapa-event.svg");
            expect(JSON.stringify(events)).not.to.contain("Private planning information");
            expect(JSON.stringify(events)).not.to.contain("Internal staff roster");
        });
        cy.get("#event-title").clear().type(`${marker} revised`);
        cy.get('[data-sentinel-form="event"]').submit();
        cy.wait("@draft");
        feed().then((events) => expect(events[0].title).to.equal(marker));
        cy.reload();
        cy.get("#sentinel-publications").contains("article", `${marker} revised`).contains("button", "Approve Public Version").click();
        cy.wait("@approve").its("response.statusCode").should("equal", 200);
        cy.contains("#sentinel-message", "Publication updated.");
        feed().then((events) => expect(events[0].title).to.equal(`${marker} revised`));
        for (const width of [1440, 820, 390]) {
            cy.viewport(width, 900);
            for (const theme of ["light", "dark"]) {
                cy.document().then((document) => {
                    document.documentElement.setAttribute("data-bs-theme", theme);
                    expect(document.documentElement.scrollWidth).to.be.at.most(document.documentElement.clientWidth);
                });
                cy.scrollTo(0, 0);
                cy.screenshot(`sentinel-${width}-${theme}`, { capture: "viewport" });
            }
        }
        cy.get("#sentinel-publications").contains("article", `${marker} revised`).contains("button", "Withdraw").click();
        cy.wait("@withdraw").its("response.statusCode").should("equal", 200);
        cy.contains("#sentinel-message", "Publication updated.");
        feed().should("deep.equal", []);
    });
});
