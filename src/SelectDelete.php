<?php

require_once __DIR__ . '/Include/Config.php';
require_once __DIR__ . '/Include/PageInit.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;
use ChurchCRM\view\PageHeader;

// Security: User must have Delete records permission
// Otherwise, re-direct them to the main menu.
AuthenticationManager::redirectHomeIfFalse(AuthenticationManager::getCurrentUser()->isDeleteRecordsEnabled(), 'DeleteRecords');

$iFamilyID = 0;
$sMode = 'family';

if (!empty($_GET['FamilyID'])) {
    $iFamilyID = InputUtils::legacyFilterInput($_GET['FamilyID'], 'int');
}

if (!empty($_GET['mode'])) {
    $sMode = $_GET['mode'];
}

if (isset($_GET['CancelFamily'])) {
    RedirectUtils::redirect("v2/family/$iFamilyID");
}

//Set the Page Title
$sPageTitle = gettext('Delete Confirmation') . ': ' . gettext('Family');

//Do we have deletion confirmation?
if (isset($_GET['Confirmed'])) {
    // Delete Family
    // Delete all associated Notes associated with this Family record
    $sSQL = 'DELETE FROM note_nte WHERE nte_fam_ID = ' . $iFamilyID;
    RunQuery($sSQL);

    // Delete Family pledges
    $sSQL ="DELETE FROM pledge_plg WHERE plg_PledgeOrPayment = 'Pledge' AND plg_FamID =" . $iFamilyID;
    RunQuery($sSQL);

    // Remove family property data
    $sSQL ="SELECT pro_ID FROM property_pro WHERE pro_Class='f'";
    $rsProps = RunQuery($sSQL);

    while ($aRow = mysqli_fetch_row($rsProps)) {
        $sSQL = 'DELETE FROM record2property_r2p WHERE r2p_pro_ID = ' . $aRow[0] . ' AND r2p_record_ID = ' . $iFamilyID;
        RunQuery($sSQL);
    }

    if (isset($_GET['Members'])) {
        // Delete all persons that were in this family
        PersonQuery::create()->filterByFamId($iFamilyID)->find()->delete();
    } else {
        // Reset previous members' family ID to 0 (undefined)
        $sSQL = 'UPDATE person_per SET per_fam_ID = 0 WHERE per_fam_ID = ' . $iFamilyID;
        RunQuery($sSQL);
    }

    // Delete the specified Family record
    $sSQL = 'DELETE FROM family_fam WHERE fam_ID = ' . $iFamilyID;
    RunQuery($sSQL);

    // Remove custom field data
    $sSQL = 'DELETE FROM family_custom WHERE fam_ID = ' . $iFamilyID;
    RunQuery($sSQL);

    // Delete the photo files, if they exist
    $photoFile = 'Images/Family/' . $iFamilyID . '.png';
    if (file_exists($photoFile)) {
        unlink($photoFile);
    }

    // Redirect back to the family listing
    RedirectUtils::redirect(SystemURLs::getRootPath() . '/v2/family');
}

//Get the family record in question
$sSQL = 'SELECT * FROM family_fam WHERE fam_ID = ' . $iFamilyID;
$rsFamily = RunQuery($sSQL);
extract(mysqli_fetch_array($rsFamily));

$aBreadcrumbs = PageHeader::breadcrumbs([
    [gettext('Delete Confirmation')],
]);
require_once __DIR__ . '/Include/Header.php';

?>
<div class="card">
    <div class="card-body">
        <?php
        // Delete Family Confirmation
        echo"<div class='alert alert-warning'><b>" . gettext('Please confirm deletion of this family record:') . '</b><br/>';
        echo gettext('Note: This will also delete all Notes associated with this Family record.');
        echo gettext('(this action cannot be undone)') . '</div>';
        echo '<div>';
        echo '<strong>' . gettext('Family Name') . ':</strong>';
        echo '&nbsp;' . InputUtils::escapeHTML($fam_Name);
        echo '</div><br/>';
        echo '<div><strong>' . gettext('Family Members:') . '</strong><ul>';
        //List Family Members
        $sSQL = 'SELECT * FROM person_per WHERE per_fam_ID = ' . (int)$iFamilyID;
        $rsPerson = RunQuery($sSQL);
        while ($aRow = mysqli_fetch_array($rsPerson)) {
            extract($aRow);
            echo '<li>' . InputUtils::escapeHTML($per_FirstName) . ' ' . InputUtils::escapeHTML($per_LastName) . '</li>';
            RunQuery($sSQL);
        }
        echo '</ul></div>';
        echo"<p id=\"deleteFamilyOnlyBtn\" class=\"text-center\"><a class='btn btn-danger' href=\"SelectDelete.php?Confirmed=Yes&FamilyID=" . $iFamilyID . '">' . gettext('Delete Family Record ONLY') . '</a> ';
        echo"<a id=\"deleteFamilyAndMembersBtn\" class='btn btn-danger' href=\"SelectDelete.php?Confirmed=Yes&Members=Yes&FamilyID=" . $iFamilyID . '">' . gettext('Delete Family Record AND Family Members') . '</a> ';
        echo"<a class='btn btn-secondary ms-2' href=\"v2/family/" . $iFamilyID . '">' . gettext('No, cancel this deletion') . '</a></p>';
        ?>
    </div>
</div>
<?php
require_once __DIR__ . '/Include/Footer.php';
