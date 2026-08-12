<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';

?>

<!-- Vonage Integration Status -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fa-solid fa-plug me-2"></i><?= gettext('SMS Integration') ?></h3>
    </div>
    <div class="card-body">
        <?php if ($vonageConfigured): ?>
        <div class="alert alert-success mb-0">
            <div class="d-flex align-items-center">
                <i class="fa-solid fa-circle-check me-2 fs-3"></i>
                <div>
                    <h4 class="alert-title mb-0"><?= gettext('Vonage SMS Connected') ?></h4>
                    <div class="text-secondary"><?= gettext('SMS messages can be sent directly from ChurchCRM via the Vonage API.') ?></div>
                </div>
                <?php if (AuthenticationManager::getCurrentUser()->isAdmin()): ?>
                <a href="<?= SystemURLs::getRootPath() ?>/admin/system/plugins#plugin-vonage" class="btn btn-outline-success ms-auto">
                    <i class="fa-solid fa-gear me-1"></i><?= gettext('Plugin Settings') ?>
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php else: ?>
        <div class="alert alert-warning mb-0">
            <div class="d-flex align-items-center">
                <i class="fa-solid fa-triangle-exclamation me-2 fs-3"></i>
                <div>
                    <h4 class="alert-title mb-0"><?= gettext('Vonage SMS Not Configured') ?></h4>
                    <div class="text-secondary"><?= gettext('Without Vonage, text actions will open your device\'s native SMS app or copy phone numbers to clipboard.') ?></div>
                </div>
                <?php if (AuthenticationManager::getCurrentUser()->isAdmin()): ?>
                <a href="<?= SystemURLs::getRootPath() ?>/admin/system/plugins#plugin-vonage" class="btn btn-outline-warning ms-auto">
                    <i class="fa-solid fa-gear me-1"></i><?= gettext('Configure Vonage') ?>
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Text Tools Card -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fa-solid fa-comment-sms me-2"></i><?= gettext('Text Tools') ?></h3>
    </div>
    <div class="card-body">
        <div class="text-secondary">
            <?= gettext('Text messaging actions are available from Group and Sunday School class views. Use the "Text" dropdown to copy phone numbers or send SMS messages to group members.') ?>
            <?php if (AuthenticationManager::getCurrentUser()->isAdmin()): ?>
                <?= gettext('To message the whole congregation or a group at once, use') ?> <a href="<?= SystemURLs::getRootPath() ?>/v2/text/announcements"><?= gettext('Announcements') ?></a>.
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($smsAutomation !== null): ?>
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fa-solid fa-cake-candles me-2"></i><?= gettext('Birthday & Anniversary Automation') ?></h3>
    </div>
    <div class="card-body">
        <div class="row mb-3">
            <div class="col-md-6">
                <div class="text-muted small"><?= gettext('Last birthday run') ?></div>
                <div><?= $smsAutomation['lastBirthdayRun'] !== '' ? InputUtils::escapeHTML($smsAutomation['lastBirthdayRun']) : gettext('Never') ?></div>
            </div>
            <div class="col-md-6">
                <div class="text-muted small"><?= gettext('Last anniversary run') ?></div>
                <div><?= $smsAutomation['lastAnniversaryRun'] !== '' ? InputUtils::escapeHTML($smsAutomation['lastAnniversaryRun']) : gettext('Never') ?></div>
            </div>
        </div>

        <button type="button" class="btn btn-outline-primary btn-sm mb-3" id="runScheduledSmsNow">
            <i class="fa-solid fa-play me-1"></i><?= gettext("Send today's messages now") ?>
        </button>

        <div class="mb-2 fw-bold small"><?= gettext('Cron command') ?></div>
        <p class="text-secondary small">
            <?= gettext('Enable the toggles above, then add this to the server crontab so birthdays/anniversaries are texted automatically every day (this URL includes a secret token — keep it private):') ?>
        </p>
        <div class="input-group">
            <input type="text" class="form-control font-monospace" style="font-size: 12px;" readonly
                   value="0 8 * * * curl -fsS -X POST '<?= InputUtils::escapeAttribute($smsAutomation['cronUrl']) ?>' >/dev/null 2>&1"
                   id="cronCommandInput">
            <button type="button" class="btn btn-outline-secondary" id="copyCronCommand"><i class="ti ti-copy"></i></button>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (AuthenticationManager::getCurrentUser()->isAdmin()): ?>
<link rel="stylesheet" href="<?= SystemURLs::assetVersioned('/skin/v2/system-settings-panel.min.css') ?>">
<script src="<?= SystemURLs::assetVersioned('/skin/v2/system-settings-panel.min.js') ?>"></script>
<script nonce="<?= SystemURLs::getCSPNonce() ?>">
$(document).ready(function() {
    window.CRM.settingsPanel.init({
        container: '#textSettings',
        title: <?= json_encode(gettext('Text Settings'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
        icon: 'fa-solid fa-comment-sms',
        settings: [
            {
                name: 'iDoNotSmsPropertyId',
                type: 'ajax',
                label: <?= json_encode(gettext('Do Not SMS Property'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
                ajaxUrl: '/api/system/properties/person',
                tooltip: <?= json_encode(SystemConfig::getTooltip('iDoNotSmsPropertyId'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
            },
            {
                name: 'bSendBirthdaySms',
                type: 'boolean',
                label: <?= json_encode(gettext('Text birthday greetings automatically'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
                tooltip: <?= json_encode(SystemConfig::getTooltip('bSendBirthdaySms'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
            },
            {
                name: 'sBirthdaySmsTemplate',
                type: 'text',
                label: <?= json_encode(gettext('Birthday message'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
                tooltip: <?= json_encode(SystemConfig::getTooltip('sBirthdaySmsTemplate'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
            },
            {
                name: 'bSendAnniversarySms',
                type: 'boolean',
                label: <?= json_encode(gettext('Text anniversary greetings automatically'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
                tooltip: <?= json_encode(SystemConfig::getTooltip('bSendAnniversarySms'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
            },
            {
                name: 'sAnniversarySmsTemplate',
                type: 'text',
                label: <?= json_encode(gettext('Anniversary message'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
                tooltip: <?= json_encode(SystemConfig::getTooltip('sAnniversarySmsTemplate'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
            }
        ],
        showAllSettingsLink: true
    });
<?php if (isset($_GET['settings']) && $_GET['settings'] === 'open'): ?>
    var textSettingsEl = document.getElementById('textSettings');
    if (textSettingsEl) {
        new bootstrap.Collapse(textSettingsEl, { toggle: false }).show();
    }
<?php endif; ?>

    $('#runScheduledSmsNow').on('click', function() {
        var $btn = $(this);
        $btn.prop('disabled', true);
        $.ajax({
            url: window.CRM.root + '/v2/text/scheduled/run',
            method: 'POST',
            dataType: 'json'
        }).always(function() {
            $btn.prop('disabled', false);
        }).done(function(data) {
            var summary = [];
            ['birthday', 'anniversary'].forEach(function(key) {
                if (data[key] && data[key].ran) {
                    summary.push(key + ': ' + data[key].sent + ' sent, ' + data[key].failed + ' failed, ' + data[key].skipped + ' skipped');
                }
            });
            window.CRM.notify(summary.length ? summary.join(' | ') : <?= json_encode(gettext('Nothing to send — automation is off or SMS is not configured'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>, { type: 'success', delay: 6000 });
        });
    });

    $('#copyCronCommand').on('click', function() {
        window.CRM.copyToClipboard(document.getElementById('cronCommandInput').value);
    });
});
</script>
<?php endif; ?>

<?php
require SystemURLs::getDocumentRoot() . '/Include/Footer.php';
