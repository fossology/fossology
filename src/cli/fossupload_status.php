<?php
/***********************************************************
 Copyright (C) 2015 Siemens AG

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

use Fossology\Lib\Dao\JobDao;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Data\JobStatus;
use Fossology\Lib\Data\UploadStatus;

require_once("$MODDIR/lib/php/common-cli.php");
cli_Init();

global $Plugins;
error_reporting(E_NOTICE & E_STRICT);

$Usage = "Usage: " . basename($argv[0]) . " [options]
  --help      = display this help text
  --username  = user name
  --password  = password
  --groupname = a group the user belongs to (default active group)
  --uploadId  = id of upload
";

$opts = getopt("c:", array("help", "username:", "groupname:", "uploadId:", "password:"));

if (array_key_exists("help", $opts)) {
  echo $Usage;
  exit (1);
}

if (!array_key_exists("uploadId", $opts)) {
  echo "no uploadId supplied\n";
  echo $Usage;
  exit (1);
}
$uploadId = $opts["uploadId"];

$user = array_key_exists("username", $opts) ? $opts["username"] : '';
$group = array_key_exists("groupname", $opts) ? $opts["groupname"] : '';
$passwd = array_key_exists("password", $opts) ? $opts["password"] : null;
account_check($user, $passwd, $group);

global $SysConf;
$userId = $SysConf['auth']['UserId'];
$groupId = $SysConf['auth']['GroupId'];

/** @var JobDao */
$jobDao = $GLOBALS['container']->get("dao.job");
$jobStatuses = $jobDao->getAllJobStatus($uploadId, $userId, $groupId);
$runningJobs = false;
foreach($jobStatuses as $jobStatus)
{
  switch ($jobStatus) {
    case JobStatus::FAILED:
      print "status=ERROR\n";
      exit(0);
    case JobStatus::RUNNING;
      $runningJobs = true;
      break;
    default:
      break;
  }
}

if ($runningJobs) {
  print "status=SCANNING\n";
  exit(0);
}

/** @var DbManager */
$dbManager = $GLOBALS['container']->get("db.manager");
$userPerm = 0;
$uploadBrowseProxy = new Fossology\Lib\Proxy\UploadBrowseProxy($groupId, $userPerm, $dbManager);

/** @var UploadDao */
$uploadDao = $GLOBALS['container']->get("dao.upload");

if ($uploadDao->getUpload($uploadId) == null) {
  $status = "NON_EXISTENT";
} else if (!$uploadDao->isAccessible($uploadId, $groupId)) {
  $status = "INACCESSIBLE";
} else {
  try {
    switch($uploadBrowseProxy->getStatus($uploadId)) {
      case UploadStatus::OPEN:
        $status = "OPEN";
        break;
      case UploadStatus::IN_PROGRESS:
        $status = "IN_PROGRESS";
        break;
      case UploadStatus::CLOSED:
        $status = "CLOSED";
        break;
      case UploadStatus::REJECTED:
        $status = "REJECTED";
        break;
      default:
        $status = "ERROR: invalid status";
    }
  }
  catch(Exception $e)
  {
    $status = "ERROR: ".$e->getMessage();
  }
}
print "status=$status\n";
