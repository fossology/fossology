<?php
/*
 SPDX-FileCopyrightText: © 2015 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Dao\FolderDao;
use Fossology\Lib\Data\Folder\Folder;

require_once("$MODDIR/lib/php/common-cli.php");
cli_Init();

global $Plugins;
error_reporting(E_NOTICE & E_STRICT);

$Usage = "Usage: " . basename($argv[0]) . " [options]
  --username  = user name
  --password  = password
  --groupname = a group the user belongs to (default active group)
  --folderId  = id of folder (default root folder of user)
  --linkFolder= create a link to this folder (id)
  --linkUpload= create a link to this upload (id)\n";

$opts = getopt("hc:", array("username:", "groupname:",
    "folderId:",
    "password:", "linkFolder:", "linkUpload:"));
if (array_key_exists('h', $opts)) {
  print $Usage;
  exit(0);
}

$user = array_key_exists("username", $opts) ? $opts["username"] : '';
$group = array_key_exists("groupname", $opts) ? $opts["groupname"] : '';
$passwd = array_key_exists("password", $opts) ? $opts["password"] : null;
account_check($user, $passwd, $group);

global $SysConf;
$userId = $_SESSION[Auth::USER_ID] = $SysConf['auth'][Auth::USER_ID];
$groupId = $_SESSION[Auth::GROUP_ID] = $SysConf['auth'][Auth::GROUP_ID];

/* @var $folderDao FolderDao */
$folderDao = $GLOBALS['container']->get("dao.folder");

if (array_key_exists("folderId", $opts)) {
  $folderId = $opts["folderId"];
} else {
  $folderId = $folderDao->getRootFolder($userId)->getId();
}

$linkFolder = array_key_exists("linkFolder", $opts) ? $opts["linkFolder"] : null;
$linkUpload = array_key_exists("linkUpload", $opts) ? $opts["linkUpload"] : null;
if (!empty($linkFolder)) {
  $folderDao->insertFolderContents($folderId,FolderDao::MODE_FOLDER,$linkFolder);
} elseif (!empty($linkUpload)) {
  $folderDao->insertFolderContents($folderId,FolderDao::MODE_UPLOAD,$linkUpload);
} else {
  $structure = $folderDao->getFolderStructure($folderId);
  foreach ($structure as $folder) {
    for ($i = 0; $i < $folder[FolderDao::DEPTH_KEY]; $i++) {
      echo '-';
    }
    /* @var $theFolder Folder */
    $theFolder = $folder[FolderDao::FOLDER_KEY];
    echo $theFolder->getName().' (id='.$theFolder->getId().")\n";
  }

}
