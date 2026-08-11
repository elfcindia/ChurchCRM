<?php

use ChurchCRM\dto\SystemURLs;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';

?>

<div class="page-body">
    <div class="container-xl worship-attendance-dashboard-page">
        <div class="row g-3 mb-3">
            <div class="col-md-6 col-lg-4">
                <label class="form-label" for="worshipDashDate"><?= gettext('Date') ?></label>
                <div class="input-group">
                    <input type="date" class="form-control" id="worshipDashDate" value="<?= htmlspecialchars($defaultDate, ENT_QUOTES, 'UTF-8') ?>">
                    <button type="button" class="btn btn-outline-secondary" id="worshipDashDatePickerBtn" aria-label="<?= gettext('Choose date from calendar') ?>">
                        <i class="fa-solid fa-calendar-days"></i>
                    </button>
                </div>
            </div>
            <div class="col-md-6 col-lg-8 d-flex align-items-end flex-wrap" style="gap:.5rem;">
                <button type="button" class="btn btn-primary" id="worshipDashRefresh">
                    <i class="fa-solid fa-rotate me-1"></i><?= gettext('Refresh') ?>
                </button>
                <a class="btn btn-outline-secondary" id="worshipDashExport" href="#">
                    <i class="fa-solid fa-file-csv me-1"></i><?= gettext('Export absent (CSV)') ?>
                </a>
                <a class="btn btn-outline-secondary" id="worshipDashExportPdf" href="#">
                    <i class="fa-solid fa-file-pdf me-1"></i><?= gettext('Export absent (PDF)') ?>
                </a>
            </div>
        </div>

        <div class="row row-cards mb-3 g-2" id="worshipDashStats"></div>

        <div class="row g-3">
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><?= gettext('Attended') ?></h3>
                    </div>
                    <div class="card-body p-0 table-responsive">
                        <table class="table table-vcenter card-table table-striped mb-0" id="worshipDashAttendedTable">
                            <thead>
                                <tr>
                                    <th><?= gettext('Name') ?></th>
                                    <th><?= gettext('Family') ?></th>
                                    <th><?= gettext('Time') ?></th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card border-warning">
                    <div class="card-header bg-warning-lt">
                        <h3 class="card-title"><?= gettext('Absent (expected list)') ?></h3>
                    </div>
                    <div class="card-body p-0 table-responsive">
                        <table class="table table-vcenter card-table table-striped mb-0" id="worshipDashAbsentTable">
                            <thead>
                                <tr>
                                    <th><?= gettext('Name') ?></th>
                                    <th><?= gettext('Cell') ?></th>
                                    <th><?= gettext('Home') ?></th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script nonce="<?= SystemURLs::getCSPNonce() ?>">
window.CRM = window.CRM || {};
window.CRM.worshipDashboard = <?= json_encode(['defaultDate' => $defaultDate], JSON_THROW_ON_ERROR) ?>;
</script>
<script src="<?= SystemURLs::assetVersioned('/skin/v2/worship-attendance-dashboard.min.js') ?>"></script>

<?php
require SystemURLs::getDocumentRoot() . '/Include/Footer.php';
