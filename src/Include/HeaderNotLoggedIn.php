<?php

use ChurchCRM\Bootstrapper;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Plugin\PluginManager;
use ChurchCRM\Utils\InputUtils;

require_once __DIR__ . '/Header-Security.php';

// Initialize plugin system so active plugins (e.g. GA4) can inject head content
$pluginsPath = SystemURLs::getDocumentRoot() . '/plugins';
PluginManager::init($pluginsPath);

$localeInfo = Bootstrapper::getCurrentLocale(); // always returns a LocaleInfo object
?>
<!DOCTYPE html>
<html<?= $localeInfo->isRTL() ? ' dir="rtl"' : '' ?>>
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta http-equiv="Content-Type" content="text/html">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Core ChurchCRM bundle (includes jQuery) -->
    <script src="<?= SystemURLs::assetVersioned('/skin/v2/churchcrm.min.js') ?>"></script>
    <?php if ($localeInfo->isRTL()): ?>
    <link rel="stylesheet" href="<?= SystemURLs::assetVersioned('/skin/v2/churchcrm-rtl.min.css') ?>">
    <?php else: ?>
    <link rel="stylesheet" href="<?= SystemURLs::assetVersioned('/skin/v2/churchcrm.min.css') ?>">
    <?php endif; ?>

    <script src="<?= SystemURLs::assetVersioned('/skin/external/moment/moment.min.js') ?>"></script>

    <title>ChurchCRM: <?= $sPageTitle ?></title>

    <?= PluginManager::getPluginHeadContent() ?>

</head>
<body class="antialiased <?= InputUtils::escapeAttribute($sBodyClass ?? 'page-auth') ?>">

  <!-- Global loading overlay (removed once locale + page scripts initialize) -->
  <div id="crmGlobalLoading" class="crm-global-loading" role="status" aria-live="polite" aria-label="<?= gettext('Loading') ?>">
    <div class="crm-global-loading-card">
      <div class="spinner-border text-primary" role="presentation"></div>
      <div class="crm-global-loading-text">
        <div class="fw-semibold"><?= gettext('Loading') ?></div>
        <div class="text-secondary small"><?= gettext('Preparing your workspace…') ?></div>
      </div>
    </div>
  </div>

  <script nonce="<?= SystemURLs::getCSPNonce() ?>"  >
    // Initialize window.CRM if not already created by webpack bundles
    if (!window.CRM) {
        window.CRM = {};
    }
    
    // Extend window.CRM with server-side configuration (preserving existing properties like notify).
    // Match logged-in Header.php locale fields so locale-loader / i18next work on auth-only pages.
    Object.assign(window.CRM, {
      root:"<?= SystemURLs::getRootPath() ?>",
      lang:"<?= $localeInfo->getLanguageCode() ?>",
      isRTL:<?= $localeInfo->isRTL() ? 'true' : 'false' ?>,
      version:"<?= $_SESSION['sSoftwareInstalledVersion'] ?? 'unknown' ?>",
      systemLocale:"<?= $localeInfo->getSystemLocale() ?>",
      locale:"<?= $localeInfo->getLocale() ?>",
      shortLocale:"<?= $localeInfo->getShortLocale() ?>",
      churchWebSite:<?= SystemConfig::getValueForJs('sChurchWebSite') ?>
    });
    if (typeof moment !== "undefined" && window.CRM.shortLocale) {
        moment.locale(window.CRM.shortLocale);
    }
  </script>
