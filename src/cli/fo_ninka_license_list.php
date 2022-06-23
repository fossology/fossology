<?php
/*
 SPDX-FileCopyrightText: © 2013-2014 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Dao\LicenseDao;

require_once("$MODDIR/lib/php/common-cli.php");
cli_Init();

$Usage = "Usage: " . basename($argv[0]) . "
  -u upload id        :: upload id
  -t uploadtree id    :: uploadtree id
  -c sysconfdir       :: Specify the directory for the system configuration
  --username username :: username
  --password password :: password
  --container         :: include container or not, 1: yes, 0: no (default)
  -x                  :: do not show files which have unuseful license 'No_license_found' or no license
  -X excluding        :: Exclude files containing [free text] in the path.
                         'mac/' should exclude all files in the mac directory.
                         'mac' and it should exclude all files in any directory containing the substring 'mac'
                         '/mac' and it should exclude all files in any directory that starts with 'mac'
  -h  help, this message
";
$upload = ""; // upload id
$item = ""; // uploadtree id
$showContainer = 0; // include container or not, 1: yes, 0: no (default)
$ignoreFilesWithoutLicense = 0; // do not show files which have no license, 1: yes, 0: no (default)
$excluding = '';

$longopts = array("username:", "password:", "container:");
$options = getopt("c:u:t:hxX:", $longopts);
if (empty($options) || !is_array($options)) {
  print $Usage;
  return 1;
}

$user = $passwd = "";
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
 * @brief get ninka license list of one specified uploadtree_id
 *
 * @param int $uploadtree_pk - uploadtree id
 * @param int $upload_pk - upload id
 * @param int $showContainer - include container or not, 1: yes, 0: no
 * @param string $excluding
 * @param bool $ignore ignore files without license
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

  /* get last ninka agent_pk that has data for this upload */
  $AgentRec = AgentARSList("ninka_ars", $upload_pk, 1);
  if ($AgentRec === false) {
    echo _("No data available \n");
    return;
  }
  $agent_pk = $AgentRec[0]["agent_fk"];

  $uploadtreeTablename = GetUploadtreeTableName($upload_pk);
  /** @var ItemTreeBounds */
  $itemTreeBounds = $uploadDao->getItemTreeBounds($uploadtree_pk, $uploadtreeTablename);
  $licensesPerFileName = $licenseDao->getLicensesPerFileNameForAgentId(
  $itemTreeBounds, array($agent_pk), true, $excluding, $ignore);

  foreach ($licensesPerFileName as $fileName => $licenseData) {
    if ($licenseData == false) {
      if ($showContainer) {
        print($fileName."\n");
      }
      continue;
    }

    if (! array_key_exists('scanResults', $licenseData) || empty($licenseData['scanResults'])) {
      continue;
    }

    $licenseNames = $licenseData['scanResults'];
    if ($ignore && $licenseNames !== array()) {
      continue;
    }

    print($fileName .': '.implode($licenseNames,', ')."\n");
  }
}

/** get license information for this uploadtree */
GetLicenseList($item, $upload, $showContainer, $excluding, $ignoreFilesWithoutLicense);
return 0;
