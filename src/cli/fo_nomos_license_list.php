<?php
/*
 SPDX-FileCopyrightText: © 2013-2014 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Dao\LicenseDao;
use Fossology\Lib\Dao\CopyrightDao;
use Fossology\Lib\Dao\TreeDao;
use Fossology\Lib\Proxy\ScanJobProxy;
use Fossology\Lib\Data\AgentRef;
use Fossology\Lib\Auth\Auth;

require_once("$MODDIR/lib/php/common-cli.php");
cli_Init();

$Usage = "Usage: " . basename($argv[0]) . "
  -u upload id        :: upload id
  -t uploadtree id    :: uploadtree id
  -c sysconfdir       :: Specify the directory for the system configuration
  --type export type  :: For License: license (default), For Copyright: copyright
  --username username :: username
  --password password :: password
  --container         :: include container or not, 1: yes, 0: no (default)
  -x                  :: License from files which do not have unuseful license 'No_license_found' or no license
  -y                  :: Copyrights from files which do not have license
                         Files without license refers to:
                         File had no license finding by agents and no license was added by users.
                         File had license findings by agents but were either removed or file was marked as irrelevant.
  -X excluding        :: Exclude files containing [free text] in the path
                         'mac/' should exclude all files in the mac directory.
                         'mac' and it should exclude all files in any directory containing the substring 'mac'
                         '/mac' and it should exclude all files in any directory that starts with 'mac'
  -h  help, this message
";
$upload = ""; // upload id
$item = ""; // uploadtree id
$showContainer = 0; // include container or not, 1: yes, 0: no (default)
$ignoreFilesWithoutLicense = 0; // do not show files which have no license, 1: yes, 0: no (default)
$ignoreFilesWithLicense = 0; // Copyrights from files which do not have license, 1: yes, 0: no (default)
$excluding = '';

$longopts = array("username:", "password:", "container:", "type:");
$options = getopt("c:u:t:hxyX:", $longopts);
if (empty($options) || !is_array($options)) {
  print $Usage;
  return 1;
}

$user = $passwd = "";
$type = "license";
foreach ($options as $option => $value) {
  switch ($option) {
    case 'c': // handled in fo_wrapper
      break;
    case 'u':
      $upload = $value;
      break;
    case 't':
      $item = $value;
      break;
    case 'h':
      print $Usage;
      return 1;
    case 'type':
      $type = $value;
      break;
    case 'username':
      $user = $value;
      break;
    case 'password':
      $passwd = $value;
      break;
    case 'container':
      $showContainer = $value;
      break;
    case 'x':
      $ignoreFilesWithoutLicense = 1;
      break;
    case 'y':
      $ignoreFilesWithLicense = 1;
      break;
    case 'X':
      $excluding = $value;
      break;
    default:
      print "unknown option $option\n";
      print $Usage;
  }
}

/** get upload id through uploadtree id */
if (is_numeric($item) && !is_numeric($upload)) {
  $upload = GetUploadID($item);
}

/** check if parameters are valid */
if (!is_numeric($upload) || (!empty($item) && !is_numeric($item))) {
  print "Upload ID or Uploadtree ID is not digital number\n";
  print $Usage;
  return 1;
}

account_check($user, $passwd); // check username/password

$return_value = read_permission($upload, $user); // check if the user has the permission to read this upload
if (empty($return_value)) {
  $text = _("The user '$user' has no permission to read the information of upload $upload\n");
  echo $text;
  return 1;
}

/**
 * @brief get nomos license list of one specified uploadtree_id
 */
function GetLicenseList($uploadtree_pk, $upload_pk, $showContainer, $excluding, $ignore)
{
  /* @var $dbManager DbManager */
  $dbManager = $GLOBALS['container']->get('db.manager');
  /* @var $uploadDao UploadDao */
  $uploadDao = $GLOBALS['container']->get("dao.upload");
  /* @var $licenseDao LicenseDao */
  $licenseDao = $GLOBALS['container']->get("dao.license");

  if (empty($uploadtree_pk)) {
      $uploadtreeRec = $dbManager->getSingleRow('SELECT uploadtree_pk FROM uploadtree WHERE parent IS NULL AND upload_fk=$1',
              array($upload_pk),
              __METHOD__.'.find.uploadtree.to.use.in.browse.link' );
      $uploadtree_pk = $uploadtreeRec['uploadtree_pk'];
  }

  /* get last nomos agent_pk that has data for this upload */
  $AgentRec = AgentARSList("nomos_ars", $upload_pk, 1);
  if ($AgentRec === false) {
    echo _("No data available\n");
    return;
  }
  $agent_pk = $AgentRec[0]["agent_fk"];

  $uploadtreeTablename = getUploadtreeTableName($upload_pk);
  $itemTreeBounds = $uploadDao->getItemTreeBounds($uploadtree_pk, $uploadtreeTablename);
  $licensesPerFileName = $licenseDao->getLicensesPerFileNameForAgentId(
    $itemTreeBounds, array($agent_pk), true, $excluding, $ignore);

  // Prepare CSV output
  $csvLines = [];
  foreach ($licensesPerFileName as $fileName => $licenseData) {
    if ($licenseData == false) {
      if ($showContainer) {
        print($fileName."\n");
        $csvLines[] = [$fileName, ""];
      }
      continue;
    }

    if (! array_key_exists('scanResults', $licenseData) || empty($licenseData['scanResults'])) {
      continue;
    }

    $licenseNames = $licenseData['scanResults'];
    if (($ignore && (empty($licenseNames) || in_array("No_license_found", $licenseNames) || in_array("Void", $licenseNames)))) {
      continue;
    }

    print($fileName .': '.implode($licenseNames,', ')."\n");
    $csvLines[] = [$fileName, implode(',', $licenseNames)];
  }

  // Write CSV
  if (!empty($csvLines)) {
    $fp = fopen("license_export.csv", "w");
    foreach ($csvLines as $line) {
      fputcsv($fp, $line);
    }
    fclose($fp);
  }
}

/**
 * @brief get copyright list of one specified uploadtree_id
 */
function GetCopyrightList($uploadtree_pk, $upload_pk, $exclude, $ignore)
{
  /* @var $dbManager DbManager */
  $dbManager = $GLOBALS['container']->get('db.manager');
  /* @var $uploadDao UploadDao */
  $uploadDao = $GLOBALS['container']->get("dao.upload");
  /* @var $copyrightDao CopyrightDao */
  $copyrightDao = $GLOBALS['container']->get("dao.copyright");
  /* @var $treeDao TreeDao */
  $treeDao = $GLOBALS['container']->get("dao.tree");

  if (empty($uploadtree_pk)) {
    try {
      $uploadtree_pk = $uploadDao->getUploadParent($upload_pk);
    } catch(Exception $e) {
      print($e);
      return;
    }
  }

  $agentName = "copyright";
  $scanJobProxy = new ScanJobProxy($GLOBALS['container']->get('dao.agent'), $upload_pk);
  $scanJobProxy->createAgentStatus([$agentName]);
  $selectedScanners = $scanJobProxy->getLatestSuccessfulAgentIds();
  $latestAgentId = $selectedScanners[$agentName];
  $agentFilter = ' AND C.agent_fk='.$latestAgentId;
  $uploadtreeTablename = getUploadtreeTableName($upload_pk);
  $itemTreeBounds = $uploadDao->getItemTreeBounds($uploadtree_pk,$uploadtreeTablename);
  $extrawhere = "UT.lft BETWEEN " . $itemTreeBounds->getLeft() . " AND " .
  $itemTreeBounds->getRight();

  $lines = [];
  $csvLines = [];
  $copyrights = $copyrightDao->getScannerEntries($agentName, $uploadtreeTablename, $upload_pk, null, $extrawhere . $agentFilter);
  foreach ($copyrights as $copyright) {
    $row = [];
    $row["content"] = $copyright["content"];
    $row["filePath"] = $treeDao->getFullPath($copyright["uploadtree_pk"], $uploadtreeTablename);
    $lines[$row["filePath"]][] = $row;
  }
  $copyrights = $copyrightDao->getEditedEntries('copyright_decision', $uploadtreeTablename, $upload_pk, [], $extrawhere);
  foreach ($copyrights as $copyright) {
    $row = [];
    $row["content"] = $copyright["textfinding"];
    $row["filePath"] = $treeDao->getFullPath($copyright["uploadtree_pk"], $uploadtreeTablename);
    $lines[$row["filePath"]][] = $row;
  }

  if ($ignore) {
    $agentList = [];
    foreach (AgentRef::AGENT_LIST as $agentname => $value) {
      $AgentRec = AgentARSList($agentname."_ars", $upload_pk, 1);
      if (!empty($AgentRec)) {
        $agentList[] = $AgentRec[0]["agent_fk"];
      }
    }
    removeCopyrightWithLicense($lines, $itemTreeBounds, $agentList, $exclude);
  }

  $reducedLines = array();
  foreach ($lines as $line) {
    foreach ($line as $copyright) {
      $reducedLines[] = $copyright;
    }
  }

  foreach ($reducedLines as $row) {
    if (!empty($exclude) && false!==strpos("$row[filePath]", $exclude)) {
      continue;
    } else {
      print($row['filePath'] . ": " . ($row['content']) . "\n");
      $csvLines[] = [$row['filePath'], $row['content']]; // store for CSV
    }
  }

  // Write CSV
  if (!empty($csvLines)) {
    $fp = fopen("copyright_export.csv", "w");
    foreach ($csvLines as $line) {
      fputcsv($fp, $line);
    }
    fclose($fp);
  }
}

// Remove all files which either have license findings or concluded licenses
function removeCopyrightWithLicense(&$lines, $itemTreeBounds, $agentList, $exclude)
{
  $clearingDao = $GLOBALS['container']->get("dao.clearing");
  $clearingFilter = $GLOBALS['container']->get("businessrules.clearing_decision_filter");
  $licenseDao = $GLOBALS['container']->get("dao.license");

  $licensesPerFileName = array();
  $allDecisions = $clearingDao->getFileClearingsFolder($itemTreeBounds, Auth::getGroupId());
  $editedMappedLicenses = $clearingFilter->filterCurrentClearingDecisionsForCopyrightList($allDecisions);
  $licensesPerFileName = $licenseDao->getLicensesPerFileNameForAgentId($itemTreeBounds, $agentList, true, $exclude, true, $editedMappedLicenses);
  foreach ($licensesPerFileName as $fileName => $licenseNames) {
    if ($licenseNames !== false && count($licenseNames) > 0) {
      if (array_key_exists('concludedResults', $licenseNames)) {
        $consolidatedConclusions = array();
        foreach ($licenseNames['concludedResults'] as $conclusion) {
          $consolidatedConclusions = array_merge($consolidatedConclusions, $conclusion);
        }
        $conclusions = array_unique($consolidatedConclusions);
        if (in_array("Void", $conclusions)) {
            continue;
        }
        foreach (array_keys($lines) as $file) {
          if (strpos($file, $fileName) !== false) {
            unset($lines[$file]);
            break;
          }
        }
      }
      if ((! empty($licenseNames['scanResults'])) && ! (in_array("No_license_found", $licenseNames['scanResults']) || in_array("Void", $licenseNames['scanResults']))) {
        foreach (array_keys($lines) as $file) {
          if (strpos($file, $fileName) !== false) {
            unset($lines[$file]);
            break;
          }
        }
      }
    }
  }
}

/** get license and copyright information for this uploadtree */
if ($type=="license") {
  GetLicenseList($item, $upload, $showContainer, $excluding, $ignoreFilesWithoutLicense);
} else {
  GetCopyrightList($item, $upload, $excluding, $ignoreFilesWithLicense);
}
return 0;
