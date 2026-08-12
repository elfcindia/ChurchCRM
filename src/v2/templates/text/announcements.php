<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';

?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fa-solid fa-users me-2"></i><?= gettext('Recipients') ?></h3>
    </div>
    <div class="card-body">
        <div class="mb-3">
            <div class="form-selectgroup">
                <label class="form-selectgroup-item">
                    <input type="radio" name="recipientType" value="congregation" class="form-selectgroup-input" checked>
                    <span class="form-selectgroup-label"><i class="fa-solid fa-church me-1"></i><?= gettext('Whole congregation') ?></span>
                </label>
                <label class="form-selectgroup-item">
                    <input type="radio" name="recipientType" value="group" class="form-selectgroup-input">
                    <span class="form-selectgroup-label"><i class="fa-solid fa-people-group me-1"></i><?= gettext('A specific group') ?></span>
                </label>
            </div>
        </div>
        <div class="mb-3" id="groupPickerRow" style="display: none;">
            <label class="form-label" for="announcementGroupId"><?= gettext('Group') ?></label>
            <select class="form-select" id="announcementGroupId" style="max-width: 360px;"></select>
        </div>
        <div class="text-secondary" id="recipientCountLabel">
            <i class="fa-solid fa-spinner fa-spin me-1"></i><?= gettext('Counting recipients…') ?>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fa-solid fa-comment-sms me-2"></i><?= gettext('Message') ?></h3>
    </div>
    <div class="card-body">
        <textarea class="form-control" id="announcementMessage" rows="4" maxlength="640"
                  placeholder="<?= InputUtils::escapeAttribute(gettext('e.g. Special prayer meeting this Friday at 6:30 PM. All are welcome!')) ?>"></textarea>
        <div class="d-flex justify-content-between mt-1">
            <small class="text-muted"><?= gettext('This exact wording must match a template already approved on your DLT portal.') ?></small>
            <small class="text-muted" id="messageCharCount">0 / 160 · 1 <?= gettext('segment') ?></small>
        </div>
    </div>
    <div class="card-footer text-end">
        <button type="button" class="btn btn-primary" id="sendAnnouncement">
            <i class="fa-solid fa-paper-plane me-1"></i><?= gettext('Send') ?>
        </button>
    </div>
</div>

<script src="<?= SystemURLs::assetVersioned('/skin/js/announcements.js') ?>"></script>

<?php
require SystemURLs::getDocumentRoot() . '/Include/Footer.php';
