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

use Fossology\Lib\Dao\UserDao;
use Fossology\Lib\Dao\UploadDao;

require_once("$MODDIR/lib/php/common-cli.php");
cli_Init();

global $Plugins;
error_reporting(E_NOTICE & E_STRICT);

$Usage = "Usage: " . basename($argv[0]) . " [options]
  --username  = user name
  --password  = password
  --groupname = a group the user belongs to (default active group)
  --uploadId  = id of upload
  --destgroup = group which will become admin of the upload
  ";

// TODO support much more command line options

$opts = getopt("c:", array("username:", "groupname:", "uploadId:", "password:", "destgroup:"));

if (!array_key_exists("uploadId", $opts)) {
  echo "no uploadId supplied";
  exit (1);
}
if (!array_key_exists("destgroup", $opts)) {
  echo "no destgroup supplied";
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

/** @var UserDao */
$userDao = $GLOBALS['container']->get("dao.user");
$destGroupId = $userDao->getGroupIdByName($opts["destgroup"]);

/** @var UploadDao */
$uploadDao = $GLOBALS['container']->get("dao.upload");
$uploadDao->makeAccessibleToGroup($uploadId, $destGroupId);
