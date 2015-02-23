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
use Fossology\Lib\Data\JobStatus;
use Fossology\Lib\Data\UploadStatus;

require_once("$MODDIR/lib/php/common-cli.php");
cli_Init();

global $Plugins;
error_reporting(E_NOTICE & E_STRICT);

$Usage = "Usage: " . basename($argv[0]) . " [options] [archives]
TODO

  ";

global $container;

$opts = getopt("c:", array("username:", "groupname:", "uploadId:"));

/** @var UploadDao */
$uploadDao = $container->get("dao.upload");

/** @var JobDao */
$jobDao = $container->get("dao.job");

if (!array_key_exists("uploadId", $opts)) {
  echo "no uploadId supplied";
  exit (1);
}
$uploadId = $opts["uploadId"];

$user = "";
$group = "";

if (array_key_exists("username", $opts)) {
  $user = $opts["username"];
}

if (array_key_exists("groupname", $opts)) {
  $group = $opts["groupname"];
}

$passwd = null;
account_check($user, $passwd, $group);

global $SysConf;
$userId = $SysConf['auth']['UserId'];
$groupId = $SysConf['auth']['GroupId'];

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

$status="ERROR";

switch($uploadDao->getStatus($uploadId, $userId)) {
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
}
print "status=$status\n";
