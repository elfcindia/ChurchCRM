<?php

use ChurchCRM\dto\SystemURLs;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';

?>

<div class="page-body">
    <div class="container-xl worship-greeter-page">
        <div class="row g-3">
            <div class="col-lg-7">
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title mb-0"><i class="fa-solid fa-user-check me-2"></i><?= gettext('Check someone in') ?></h2>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label" for="worshipGreeterDate"><?= gettext('Service date') ?></label>
                            <div class="input-group input-group-lg">
                                <input type="date" class="form-control" id="worshipGreeterDate" value="<?= htmlspecialchars($defaultDate, ENT_QUOTES, 'UTF-8') ?>">
                                <button type="button" class="btn btn-outline-secondary" id="worshipGreeterDatePickerBtn" aria-label="<?= gettext('Choose date from calendar') ?>">
                                    <i class="fa-solid fa-calendar-days"></i>
                                </button>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="worshipGreeterSearch"><?= gettext('Search name or family') ?></label>
                            <input type="search" class="form-control form-control-lg" id="worshipGreeterSearch" autocomplete="off"
                                   placeholder="<?= gettext('Type at least 2 characters…') ?>" autofocus>
                        </div>
                        <div id="worshipGreeterSearchResults" class="list-group worship-greeter-results"></div>
                        <p class="text-secondary small mb-0 mt-2" id="worshipGreeterSearchHint"><?= gettext('Results appear as you type.') ?></p>
                    </div>
                </div>
                <div class="card mt-3">
                    <div class="card-header d-flex align-items-center">
                        <h3 class="card-title mb-0"><i class="fa-solid fa-user-plus me-2"></i><?= gettext('New visitor (quick)') ?></h3>
                        <button type="button" class="btn btn-sm btn-outline-primary ms-auto" data-bs-toggle="modal" data-bs-target="#worshipQuickVisitorModal">
                            <?= gettext('Open form') ?>
                        </button>
                    </div>
                    <div class="card-body">
                        <p class="text-secondary mb-0"><?= gettext('Creates a family and person, then checks them in for the selected date.') ?></p>
                    </div>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title mb-0"><i class="fa-solid fa-list-check me-2"></i><?= gettext('Checked in today') ?></h3>
                    </div>
                    <div class="card-body p-0">
                        <ul class="list-group list-group-flush" id="worshipGreeterTodayList"></ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="worshipQuickVisitorModal" tabindex="-1" aria-labelledby="worshipQuickVisitorTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="worshipQuickVisitorTitle"><?= gettext('Quick visitor') ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-2">
                    <label class="form-label" for="wqFirst"><?= gettext('First name') ?></label>
                    <input type="text" class="form-control" id="wqFirst" required minlength="2" autocomplete="given-name">
                </div>
                <div class="mb-2">
                    <label class="form-label" for="wqLast"><?= gettext('Last name') ?></label>
                    <input type="text" class="form-control" id="wqLast" required minlength="2" autocomplete="family-name">
                </div>
                <div class="mb-2">
                    <label class="form-label" for="wqCell"><?= gettext('Cell phone') ?></label>
                    <input type="tel" class="form-control" id="wqCell" autocomplete="tel">
                </div>
                <div class="mb-0">
                    <label class="form-label" for="wqFam"><?= gettext('Family name (optional)') ?></label>
                    <input type="text" class="form-control" id="wqFam" autocomplete="organization">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= gettext('Cancel') ?></button>
                <button type="button" class="btn btn-primary" id="wqSubmit"><?= gettext('Save & check in') ?></button>
            </div>
        </div>
    </div>
</div>

<script nonce="<?= SystemURLs::getCSPNonce() ?>">
window.CRM = window.CRM || {};
window.CRM.worshipGreeter = <?= json_encode(['defaultDate' => $defaultDate], JSON_THROW_ON_ERROR) ?>;
</script>
<script src="<?= SystemURLs::assetVersioned('/skin/v2/worship-attendance-greeter.min.js') ?>"></script>

<?php
require SystemURLs::getDocumentRoot() . '/Include/Footer.php';
