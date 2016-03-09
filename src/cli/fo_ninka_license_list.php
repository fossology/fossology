<?php
/***********************************************************
 Copyright (C) 2013-2014 Hewlett-Packard Development Company, L.P.

 This program is free software; you can redistribute it and/or
 modify it under the terms of the GNU General Public License
 version 2 as published by the Free Software Foundation.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License along
 with this program; if not, write to the Free Software Foundation, Inc.,
 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 ***********************************************************/

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
if (empty($options) || !is_array($options))
{
  print $Usage;
  return 1;
}

$user = $passwd = "";
foreach($options as $option => $value)
{
  switch($option)
  {
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
if (is_numeric($item) && !is_numeric($upload)) $upload = GetUploadID($item);

/** check if parameters are valid */
if (!is_numeric($upload) || (!empty($item) && !is_numeric($item)))
{
  print "Upload ID or Uploadtree ID is not digital number\n";
  print $Usage;
  return 1;
}

account_check($user, $passwd); // check username/password

$return_value = read_permission($upload, $user); // check if the user has the permission to read this upload
if (empty($return_value))
{
  $text = _("The user '$user' has no permission to read the information of upload $upload\n");
  echo $text;
  return 1;
}


/**
 * @brief get monk license list of one specified uploadtree_id
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

  /* get last monk agent_pk that has data for this upload */
  $AgentRec = AgentARSList("monk_ars", $upload_pk, 1);
  if ($AgentRec === false)
  {
    echo _("No data available \n");
    return;
  }
  $agent_pk = $AgentRec[0]["agent_fk"];

  $uploadtreeTablename = GetUploadtreeTableName($upload_pk);
  /** @var ItemTreeBounds */
  $itemTreeBounds = $uploadDao->getItemTreeBounds($uploadtree_pk, $uploadtreeTablename);
  $licensesPerFileName = $licenseDao->getLicensesPerFileNameForAgentId(
    $itemTreeBounds, array($agent_pk), true, array(), $excluding, $ignore);

  foreach($licensesPerFileName as $fileName => $licenseNames)
  {
    if ((!$ignore || $licenseNames !== false && $licenseNames !== array()))
    {
      if ($licenseNames !== false)
      {
        print($fileName .': '.implode($licenseNames,', ')."\n");
      }
      else
      {
        if ($showContainer)
        {
          print($filename."\n");
        }
      }
    }
  }
}

/** get license information for this uploadtree */
GetLicenseList($item, $upload, $showContainer, $excluding, $ignoreFilesWithoutLicense);
return 0;
