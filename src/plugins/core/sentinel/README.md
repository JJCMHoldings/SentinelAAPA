# Sentinel ministry operations

Sentinel adds ministry workflows to ChurchCRM 7.8.5. The default vocabulary is
for Abolish Abortion Pennsylvania (AAPA); organizations can change it in plugin
settings. ChurchCRM retains its normal people, groups, ministries, events,
permissions, finance, fundraising, donation and import behavior. Internal names,
record IDs and core tables are unchanged.

## Install and enable

1. Back up the ChurchCRM database and files. Rehearse restoration on a disposable
   installation before entering staff or volunteer data.
2. Build the fork normally. Sentinel is bundled at `src/plugins/core/sentinel`.
   To add it to another compatible installation, copy that complete directory to
   `plugins/core/sentinel`. It uses the normal plugin loader; it patches no core
   files. The generated plugin ORM files are included.
3. In **Admin → Plugins**, enable **Sentinel**. Activation creates the plugin's
   `sentinel_record` table using an idempotent schema. The database account needs
   CREATE privileges for this step. Failed schema installation prevents enablement.
4. Open **Contacts → Sentinel** (People → Sentinel before terminology is loaded).
   Configure the organization name, optional vocabulary and Mailchimp settings
   through **Plugin Settings**.

Disabling the plugin restores core wording and removes its navigation entry.
It preserves plugin records. Uninstall also preserves data; administrators decide
whether to remove the plugin table after retaining a backup. This bundled plugin
does not claim approval in ChurchCRM's public community registry.

## Daily operations

- Create contacts with ChurchCRM's normal contact editor. Select a contact in
  Sentinel to mark staff, volunteer or both. These labels do not grant login
  permissions. Use ChurchCRM user administration to grant actual access.
- Teams use existing ChurchCRM groups. Programs use its ministries. The ordinary
  rosters, schedules and assignments continue to work. Sentinel additionally
  records event assignment roles and invited, confirmed, declined, completed or
  cancelled status.
- Partner organizations hold name, type, location, contact IDs, private notes and
  linked event/training history. References are checked against existing records.
- Create private events in ChurchCRM. In Sentinel, save their separate public
  title, description, location, start/end and optional HTTPS image. An
  administrator reviews the displayed draft and approves it. A later draft edit
  leaves the prior approved snapshot public until approved. Withdrawal removes
  it from the next successful export. Deleted and inactive core events are
  omitted even if an old approval remains in plugin storage.
- Operational email previews eligible recipients from an existing team or event
  assignment roster, then queues individual messages through ChurchCRM's normal
  composer. Declined/cancelled assignments are omitted. Core rules handle missing
  addresses, duplicate addresses, deceased contacts and do-not-email flags.
  ChurchCRM's email history, sender settings, queue worker and retry behavior
  remain authoritative. Configure SMTP and the organization sender identity first.

Saving uses revision checks. A stale page receives HTTP 409 instead of replacing
another staff member's changes. The last editor/approver and timestamps are
recorded. Consent retains an append-only history within its versioned record.
Partner edits retain the current version, not a full change history.

## Permissions

| Action                                                                     | ChurchCRM access                     |
| -------------------------------------------------------------------------- | ------------------------------------ |
| Open Sentinel / read staff and volunteer labels                            | Ordinary authenticated staff access  |
| Update contact labels                                                      | Edit Records                         |
| Read partner records and notes                                             | Notes                                |
| Edit partner records                                                       | Edit Records and Notes               |
| View event publication and assignment information / export approved events | View Events                          |
| Edit public drafts and Sentinel assignments                                | Global Add Events (or administrator) |
| Approve or withdraw publication                                            | Administrator                        |
| Record newsletter consent and configure plugin settings                    | Administrator                        |
| Preview/queue operational email or sync a mapped team                      | Email                                |

Portal-only users cannot use Sentinel. Ministry-scoped
coordinators continue to use ChurchCRM's own scoped scheduling surface; this
version reserves Sentinel event changes for global event managers. Every plugin
write checks the normal session CSRF token as well as permissions. No public
write endpoint is introduced.

## Terminology

The terminology setting accepts a JSON object overriding these display mappings:

```json
{
  "People": "Contacts",
  "Person": "Contact",
  "Persons": "Contacts",
  "Groups": "Teams",
  "Group": "Team",
  "Ministries": "Programs",
  "Ministry": "Program",
  "Events": "AAPA Events",
  "Communication": "Email",
  "Add New Person": "Add New Contact",
  "Person Listing": "Contact Listing"
}
```

Labels are plain text, at most 80 characters. Use the same key and value to keep
an original label. This English display layer targets navigation, page headings,
form labels, breadcrumbs, table headings and alerts. It changes text nodes only;
it leaves form values, URLs, database keys and contact data alone. Legacy copy
outside those surfaces and translated core strings may retain ChurchCRM wording.
Screens remain functional when JavaScript is unavailable. Finance and church
features remain visible and usable, following the revised project scope.

## Mailchimp and website newsletter signup

Set a Mailchimp API key in Sentinel's masked password field and provide a mapping
from existing team/group IDs to audience IDs, for example:

```json
{ "12": "a1b2c3d4e5", "27": "a1b2c3d4e5" }
```

Record explicit address-level consent and its evidence/source before syncing a
team. Staff/volunteer status or team membership alone never grants consent.
Duplicate addresses share one consent record. New addresses use Mailchimp
`status_if_new: pending` to request confirmation. Existing pending, unsubscribed,
cleaned or transactional records are never changed into subscribed records. A
recorded local withdrawal changes an existing subscribed member to unsubscribed
on the next manual sync. A missing local consent record does not change an
existing Mailchimp subscriber. Partial failures are counted and can be retried;
no remote error details or API keys are returned to the browser.

The Campaigns button opens Mailchimp, where staff manage newsletter templates,
campaigns, unsubscribes and delivery reports. Set sender name, reply address,
postal address and unsubscribe footer in Mailchimp before sending campaigns.
Use a disposable audience and SMTP test sink for acceptance tests.

For website signups, use the audience's hosted or embedded Mailchimp form with
explicit opt-in and double confirmation. That form sends directly to Mailchimp,
so the website needs no API key. An optional hosted signup URL can be stored in
plugin settings. The existing website form has not been changed by this plugin;
the website owner must connect it to the intended audience. Sentinel does not
automatically import website subscribers into ChurchCRM or trust arbitrary form
submissions as CRM consent. Avoid configuring competing automatic subscriptions
for the same team in another plugin.

API behavior follows [Mailchimp's member upsert documentation](https://mailchimp.com/developer/marketing/api/lists/members/upsert-member)
and [its import guidance](https://mailchimp.com/developer/marketing/build/quickstarts/import-and-export-contacts).

## Website event feed

`GET /plugins/sentinel/api/public-events` returns approved fields only, under
normal ChurchCRM authentication. The website server uses a dedicated staff API
key with read access to events. Keep that key on the server, out of browser
JavaScript and generated public files.

The repository includes `scripts/sentinel-export-events.mjs`:

```sh
# Supply these through the website server's secret/environment configuration.
export SENTINEL_URL=https://crm.example.org/crm/
export SENTINEL_API_KEY=your-server-side-key
node scripts/sentinel-export-events.mjs /path/to/site/public/sentinel-events.json
```

Run the exporter during the website build or from the website's existing job
runner. Render `events` from this JSON alongside the site's historical archive.
Treat descriptions as text. Each successful export replaces the current managed
event list, including withdrawals and an empty list. Failed requests, redirects
to login and malformed responses leave the previous JSON intact. The exporter
never edits archived event pages. Default artwork URLs are resolved to the CRM
origin. To keep that artwork available during a CRM outage, copy the bundled
`assets/aapa-event.svg` into the website's own assets during its build.

The website's rendering/build integration and refresh interval are deployment
choices; this plugin does not publish to a website or schedule a job by itself.

## Verification and release gates

```sh
node tests/php/run.js unit Plugins/Sentinel
node --test tests/tooling/sentinel-export.test.mjs
npx cypress run --config-file cypress/configs/docker-admin.config.ts --spec cypress/e2e/ui-admin/admin.sentinel.spec.js
npm run lint
npm run build
```

Unit tests exercise publication privacy, approval revision checks, withdrawal,
partner references, address consent, Mailchimp suppression/failures, direct HTTP
permission/CSRF checks and actual plugin ORM compare-and-swap storage using an
isolated SQLite database. The SQLite storage test is skipped if `pdo_sqlite` is
absent; run the seeded MySQL/MariaDB acceptance stack before release.

Before rollout, check each real role, light/dark themes at desktop/tablet/phone,
SMTP failures and retry history, a disposable Mailchimp audience, and website
refresh/withdrawal plus archive fallback. Rehearse full database and file restore,
including `sentinel_record`. Retain ChurchCRM's MIT attribution and audit the
fork's bundled third-party notices when distributing a full build. The included
`LICENSE` preserves ChurchCRM attribution; `PROPEL-LICENSE` covers the generated
ORM's underlying library.
