<?php

use ChurchCRM\Utils\InputUtils;

require __DIR__ . '/../../../../Include/Header.php';
$t = static fn (string $text): string => InputUtils::escapeHTML(dgettext('sentinel', $text));
$e = static fn (string $text): string => InputUtils::escapeAttribute($text);
?>
<div id="sentinel-app" data-root="<?= $e($sRootPath . '/plugins/sentinel/api') ?>" data-csrf="<?= $e($csrf) ?>">
    <p class="text-secondary"><?= InputUtils::escapeHTML($organization) ?></p>
    <div id="sentinel-message" class="alert d-none" role="status" aria-live="polite"></div>
    <div class="d-flex flex-wrap gap-2 mb-3">
        <?php if ($permissions['add-contacts']): ?><a class="btn btn-outline-primary" href="<?= $e($sRootPath) ?>/PersonEditor.php"><?= $t('Add New Contact') ?></a><?php endif; ?>
        <?php if ($permissions['teams']): ?><a class="btn btn-outline-primary" href="<?= $e($sRootPath) ?>/groups/dashboard"><?= $t('Teams') ?></a><?php endif; ?>
        <?php if ($permissions['programs']): ?><a class="btn btn-outline-primary" href="<?= $e($sRootPath) ?>/ministries/dashboard"><?= $t('Programs') ?></a><?php endif; ?>
        <a class="btn btn-outline-primary" href="https://us1.admin.mailchimp.com/" target="_blank" rel="noopener noreferrer"><?= $t('Campaigns') ?></a>
        <?php if ($permissions['settings']): ?>
        <a class="btn btn-outline-secondary" href="<?= $e($sRootPath) ?>/plugins/management/<?= $e('sentinel') ?>"><?= $t('Plugin Settings') ?></a>
        <?php endif; ?>
    </div>
    <div class="row g-3">
        <?php if ($permissions['contacts']): ?>
        <section class="col-12 col-xl-6" aria-labelledby="sentinel-contacts-title">
            <div class="card h-100"><div class="card-body">
                <h2 id="sentinel-contacts-title" class="card-title"><?= $t('Contact Roles') ?></h2>
                <form data-sentinel-form="contact">
                    <label class="form-label" for="contact-search"><?= $t('Find a contact') ?></label>
                    <input id="contact-search" class="form-control mb-2" type="search" data-lookup-search="contacts" placeholder="<?= $t('Search by name') ?>">
                    <label class="form-label" for="contact-person"><?= $t('Contact') ?></label>
                    <select id="contact-person" name="personId" class="form-select mb-3" data-lookup="contacts" required></select>
                    <label class="form-check"><input class="form-check-input" name="staff" type="checkbox"><span class="form-check-label"><?= $t('Staff') ?></span></label>
                    <label class="form-check mb-3"><input class="form-check-input" name="volunteer" type="checkbox"><span class="form-check-label"><?= $t('Volunteer') ?></span></label>
                    <button class="btn btn-primary" type="submit"><?= $t('Save Roles') ?></button>
                </form>
            </div></div>
        </section>
        <?php endif; ?>
        <?php if ($permissions['partner-read']): ?>
        <section class="col-12 col-xl-6" aria-labelledby="sentinel-partners-title">
            <div class="card h-100"><div class="card-body">
                <h2 id="sentinel-partners-title" class="card-title"><?= $t('Partner Organizations') ?></h2>
                <div id="sentinel-partners" class="mb-3"></div>
                <?php if ($permissions['partners']): ?>
                <form data-sentinel-form="partner">
                    <input name="id" type="hidden"><input name="revision" type="hidden" value="0">
                    <label class="form-label" for="partner-name"><?= $t('Organization name') ?></label><input id="partner-name" name="name" class="form-control mb-2" maxlength="160" required>
                    <label class="form-label" for="partner-type"><?= $t('Organization type') ?></label><input id="partner-type" name="type" class="form-control mb-2" maxlength="80" required placeholder="<?= $t('Church, ministry or community organization') ?>">
                    <label class="form-label" for="partner-location"><?= $t('Location') ?></label><input id="partner-location" name="location" class="form-control mb-2" maxlength="255">
                    <label class="form-label" for="partner-contacts"><?= $t('Contact IDs (comma separated)') ?></label><input id="partner-contacts" name="contactIds" class="form-control mb-2">
                    <label class="form-label" for="partner-notes"><?= $t('Private Notes') ?></label><textarea id="partner-notes" name="notes" class="form-control mb-2" maxlength="10000" rows="3"></textarea>
                    <label class="form-label" for="partner-history"><?= $t('Training and event history') ?></label>
                    <textarea id="partner-history" name="history" class="form-control mb-2" rows="3" placeholder="<?= $t('One entry per line: event ID | description') ?>"></textarea>
                    <div class="d-flex gap-2"><button class="btn btn-primary" type="submit"><?= $t('Save Partner') ?></button><button class="btn btn-outline-secondary" type="reset"><?= $t('New Partner') ?></button></div>
                </form>
                <?php endif; ?>
            </div></div>
        </section>
        <?php endif; ?>
        <?php if ($permissions['events']): ?>
        <section class="col-12" aria-labelledby="sentinel-events-title">
            <div class="card"><div class="card-body">
                <h2 id="sentinel-events-title" class="card-title"><?= $t('Event Publication') ?></h2>
                <p class="text-secondary"><?= $t('Create and plan events in ChurchCRM. Public details are separate and require administrator approval. Draft edits keep the last approved version public.') ?></p>
                <a class="btn btn-outline-primary mb-3" href="<?= $e($sRootPath) ?>/event/dashboard"><?= $t('Manage Events') ?></a>
                <div id="sentinel-publications" class="mb-3" data-can-publish="<?= $permissions['publish'] ? '1' : '0' ?>" data-can-edit="<?= $permissions['event-write'] ? '1' : '0' ?>"></div>
                <?php if ($permissions['event-write']): ?>
                <form data-sentinel-form="event" class="row g-3">
                    <input name="revision" type="hidden" value="0">
                    <div class="col-12"><label class="form-label" for="event-search"><?= $t('Find an event') ?></label><input id="event-search" class="form-control mb-2" type="search" data-lookup-search="events"><select name="eventId" aria-label="<?= $t('Event') ?>" class="form-select" data-lookup="events" required></select></div>
                    <div class="col-12 col-md-6"><label class="form-label" for="event-title"><?= $t('Public Title') ?></label><input id="event-title" name="title" class="form-control" maxlength="160" required></div>
                    <div class="col-12 col-md-6"><label class="form-label" for="event-location"><?= $t('Public Location') ?></label><input id="event-location" name="location" class="form-control" maxlength="500"></div>
                    <div class="col-12 col-md-6"><label class="form-label" for="event-start"><?= $t('Starts') ?></label><input id="event-start" name="start" class="form-control" type="datetime-local" required></div>
                    <div class="col-12 col-md-6"><label class="form-label" for="event-end"><?= $t('Ends') ?></label><input id="event-end" name="end" class="form-control" type="datetime-local" required></div>
                    <div class="col-12"><label class="form-label" for="event-description"><?= $t('Public Description') ?></label><textarea id="event-description" name="description" class="form-control" maxlength="10000" rows="3"></textarea></div>
                    <div class="col-12"><label class="form-label" for="event-image"><?= $t('Image URL (optional)') ?></label><input id="event-image" name="imageUrl" class="form-control" type="url" maxlength="2048"><div class="form-text"><?= $t('Leave blank to use the default AAPA event artwork.') ?></div></div>
                    <div class="col-12"><button class="btn btn-primary" type="submit"><?= $t('Save Public Draft') ?></button></div>
                </form>
                <?php endif; ?>
            </div></div>
        </section>
        <?php endif; ?>
        <?php if ($permissions['assignments']): ?>
        <section class="col-12 col-xl-6" aria-labelledby="sentinel-assignments-title">
            <div class="card h-100"><div class="card-body">
                <h2 id="sentinel-assignments-title" class="card-title"><?= $t('Volunteer Assignments') ?></h2>
                <form data-sentinel-form="assignment">
                    <label class="form-label" for="assignment-event"><?= $t('Event') ?></label><select id="assignment-event" name="eventId" class="form-select mb-2" data-lookup="events" required></select>
                    <div id="sentinel-assignments" class="mb-3"></div>
                    <label class="form-label" for="assignment-contact"><?= $t('Contact') ?></label><select id="assignment-contact" name="personId" class="form-select mb-2" data-lookup="contacts" required></select>
                    <label class="form-label" for="assignment-role"><?= $t('Assignment') ?></label><input id="assignment-role" name="role" class="form-control mb-2" maxlength="160" required>
                    <label class="form-label" for="assignment-status"><?= $t('Status') ?></label><select id="assignment-status" name="status" class="form-select mb-3">
                        <?php foreach (['invited', 'confirmed', 'declined', 'completed', 'cancelled'] as $status): ?><option value="<?= $e($status) ?>"><?= $t(ucfirst($status)) ?></option><?php endforeach; ?>
                    </select>
                    <button class="btn btn-primary" type="submit"><?= $t('Save Assignment') ?></button>
                </form>
            </div></div>
        </section>
        <?php endif; ?>
        <?php if ($permissions['email']): ?>
        <section class="col-12 col-xl-6" aria-labelledby="sentinel-email-title">
            <div class="card h-100"><div class="card-body">
                <h2 id="sentinel-email-title" class="card-title"><?= $t('Operational Email') ?></h2>
                <form data-sentinel-form="email">
                    <label class="form-label" for="email-scope"><?= $t('Recipients') ?></label><select id="email-scope" name="scope" class="form-select mb-2"><option value="team"><?= $t('Team') ?></option><?php if ($permissions['events']): ?><option value="event"><?= $t('Event assignments') ?></option><?php endif; ?></select>
                    <select name="id" aria-label="<?= $t('Recipient team or event') ?>" class="form-select mb-2" data-lookup="teams" required></select>
                    <label class="form-label" for="email-subject"><?= $t('Subject') ?></label><input id="email-subject" name="subject" class="form-control mb-2" maxlength="255" required>
                    <label class="form-label" for="email-body"><?= $t('Message') ?></label><textarea id="email-body" name="body" class="form-control mb-3" maxlength="20000" rows="5" required></textarea>
                    <div class="d-flex flex-wrap gap-2"><button class="btn btn-outline-primary" type="submit" name="preview" value="1"><?= $t('Preview Recipients') ?></button><button class="btn btn-primary" type="submit" name="send" value="1"><?= $t('Queue Email') ?></button></div>
                    <div id="sentinel-email-result" class="mt-3" aria-live="polite"></div>
                </form>
                <hr>
                <form data-sentinel-form="sync"><label class="form-label" for="sync-team"><?= $t('Newsletter Team') ?></label><select id="sync-team" name="id" class="form-select mb-2" data-lookup="teams" required></select><button class="btn btn-outline-primary" type="submit"><?= $t('Sync Consented Contacts to Mailchimp') ?></button></form>
            </div></div>
        </section>
        <?php endif; ?>
        <?php if ($permissions['consent']): ?>
        <section class="col-12" aria-labelledby="sentinel-consent-title">
            <div class="card"><div class="card-body">
                <h2 id="sentinel-consent-title" class="card-title"><?= $t('Newsletter Consent') ?></h2>
                <p class="text-secondary"><?= $t('Record permission for this address and its source. New addresses receive a Mailchimp confirmation request. Mailchimp unsubscribes remain authoritative.') ?></p>
                <form data-sentinel-form="consent" class="row g-3">
                    <div class="col-12 col-md-4"><label class="form-label" for="consent-email"><?= $t('Email') ?></label><input id="consent-email" name="email" class="form-control" type="email" required maxlength="254"></div>
                    <div class="col-12 col-md-5"><label class="form-label" for="consent-source"><?= $t('Consent Source') ?></label><input id="consent-source" name="source" class="form-control" required maxlength="1000"></div>
                    <div class="col-12 col-md-3"><label class="form-label" for="consent-granted"><?= $t('Choice') ?></label><select id="consent-granted" name="granted" class="form-select"><option value="true"><?= $t('Permission granted') ?></option><option value="false"><?= $t('Permission withdrawn') ?></option></select></div>
                    <div class="col-12"><button class="btn btn-primary" type="submit"><?= $t('Record Consent') ?></button></div>
                </form>
                <div id="sentinel-consents" class="mt-3"></div>
            </div></div>
        </section>
        <?php endif; ?>
    </div>
</div>
<?php require __DIR__ . '/../../../../Include/Footer.php'; ?>
