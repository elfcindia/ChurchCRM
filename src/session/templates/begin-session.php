<?php

use ChurchCRM\dto\ChurchMetaData;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;

$sPageTitle = gettext('Login');
$sBodyClass = 'page-auth page-login';
require SystemURLs::getDocumentRoot() . '/Include/HeaderNotLoggedIn.php';

?>

<?php
$hasSelfReg = SystemConfig::getBooleanValue('bEnableSelfRegistration');
?>

<div class="login-container<?= $hasSelfReg ? ' login-container-split' : '' ?>">
  <div class="login-wrapper<?= $hasSelfReg ? ' login-wrapper-split' : '' ?>">
    <!-- Login Form Section -->
    <div class="<?= $hasSelfReg ? 'login-form-column' : 'login-form-section' ?>">
      <div class="login-form-inner">
        <!-- Header with Logo and Church Name -->
        <div class="login-form-header">
          <div class="login-header-logo">
            <img src="<?= SystemURLs::getRootPath() ?>/Images/elfc-logo.png" alt="<?= InputUtils::escapeAttribute(ChurchMetaData::getChurchName()) ?>" />
          </div>
          <h2 class="login-header-church-name"><?= ChurchMetaData::getChurchName() ?></h2>
          <p class="login-header-tagline"><?= gettext('Community Management Platform') ?></p>
        </div>

        <!-- Form Title -->
        <div class="login-form-title">
          <h1><i class="fa-solid fa-right-to-bracket"></i><?= gettext('Login to your account') ?></h1>
          <p><?= gettext('Welcome back! Please enter your details to continue') ?></p>
        </div>

        <?php
        if (isset($_GET['Timeout'])) {
            $loginPageMsg = gettext('Your previous session timed out. Please login again.');
        }

        // output warning and error messages
        if (isset($sErrorText)) {
            echo '<div class="alert alert-danger">' . htmlspecialchars($sErrorText) . '</div>';
        }
        if (isset($loginPageMsg)) {
            echo '<div class="alert alert-warning">' . htmlspecialchars($loginPageMsg) . '</div>';
        }
        ?>

        <form method="post" name="LoginForm" action="<?= $localAuthNextStepURL ?>" class="needs-validation" novalidate>
          <div class="mb-3">
            <label for="UserBox" class="form-label"><?= gettext('Username') ?></label>
            <div class="input-group">
              <span class="input-group-text"><i class="fa-solid fa-user"></i></span>
              <input type="text" id="UserBox" name="User" class="form-control" placeholder="<?= gettext('Username') ?>" value="<?= htmlspecialchars($prefilledUserName) ?>" required autofocus autocomplete="username">
            </div>
          </div>

          <div class="mb-3">
            <label for="PasswordBox" class="form-label"><?= gettext('Password') ?></label>
            <div class="input-group">
              <span class="input-group-text"><i class="fa-solid fa-lock"></i></span>
              <input type="password" id="PasswordBox" name="Password" class="form-control" placeholder="<?= gettext('Enter your password') ?>" required autocomplete="current-password">
            </div>
          </div>

          <div class="d-flex justify-content-end mb-3">
            <?php if (SystemConfig::getBooleanValue('bEnableLostPassword')) { ?>
              <a href="<?= htmlspecialchars($forgotPasswordURL) ?>" class="link-secondary small"><?= gettext('Forgot password?') ?></a>
            <?php } ?>
          </div>

          <button type="submit" class="btn btn-primary w-100">
            <i class="fa-solid fa-right-to-bracket me-2"></i><?= gettext('Sign in') ?>
          </button>
        </form>

        <?php if (!$hasSelfReg) { ?>
          <div class="signup-link">
            <p><?= gettext('Need help?') ?> <a href="<?= SystemURLs::getRootPath() ?>/external/register/"><?= gettext('Contact us') ?></a></p>
          </div>
        <?php } ?>
      </div>
    </div><!-- end login-form-column / login-form-section -->

    <?php if ($hasSelfReg) { ?>
    <div class="login-register-column login-register-column--compact" aria-label="<?= gettext('Family registration') ?>">
      <div class="register-content-compact">
        <div class="register-content-compact-icon" aria-hidden="true">
          <i class="fa-solid fa-people-roof"></i>
        </div>
        <h2 class="register-title-compact"><?= gettext('Register your family') ?></h2>
        <p class="register-lead-compact"><?= gettext('No account yet? Start here to add your household.') ?></p>
        <a href="<?= SystemURLs::getRootPath() ?>/external/register/" class="btn-register btn-register--prominent">
          <i class="fa-solid fa-user-plus me-2"></i><?= gettext('Register Your Family') ?>
        </a>
      </div>
    </div>
    <?php } ?>
  </div><!-- end login-wrapper -->
</div><!-- end login-container -->
<?php
$bOmitAuthFooterBranding = true;
require SystemURLs::getDocumentRoot() . '/Include/FooterNotLoggedIn.php';
