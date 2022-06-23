<?php
/*
 SPDX-FileCopyrightText: © 2015 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/


use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Dao\FolderDao;
use Fossology\Lib\Dao\UserDao;

require_once("$MODDIR/lib/php/common-cli.php");
cli_Init();
require_once("$MODDIR/lib/php/common-users.php");

error_reporting(E_ALL);

$usage = "Usage: " . basename($argv[0]) . " [options]
  --username  = admin/user with user-creation permissions
  --password  = admin/user password
  --uname     = username to create if not exists
  --gname     = groupname to create if not exists
  --upasswd   = password of created user
  --permlvl   = group permission level (-1: None, ".UserDao::USER.": User, ".UserDao::ADMIN.": Admin, ".UserDao::ADVISOR.": Advisor)
  --accesslvl   = user database permission level (".Auth::PERM_NONE.": None, ".Auth::PERM_READ.": Read, ".Auth::PERM_WRITE.": Write, ".Auth::PERM_ADMIN.": Admin)
  --folder  = root folder
";
$opts = getopt("h", array('username:', 'password:', 'uname:', 'gname:', 'upasswd:', 'permlvl:', 'accesslvl:', 'folder:'));

if (array_key_exists('h',$opts)) {
  print "$usage\n";
  return 0;
}

$adminName = array_key_exists("username", $opts) ? $opts["username"] : null;
$passwd = array_key_exists("password", $opts) ? $opts["password"] : null;
if (!account_check($adminName, $passwd, $group)) {
  print "Fossology login failure\n";
  return 2;
} else {
  print "Logged in as user $adminName\n";
}

/** @var UserDao */
$userDao = $GLOBALS['container']->get("dao.user");
/** @var FolderDao */
$folderDao = $GLOBALS['container']->get("dao.folder");

$adminRow = $userDao->getUserByName($adminName);
if ($adminRow["user_perm"] < PLUGIN_DB_ADMIN) {
  print "You have no permission to admin the user group thingy\n";
  return 1;
}

$uName = array_key_exists("uname", $opts) ? $opts["uname"] : '';
$user = $uName ? $userDao->getUserByName($uName) : false;
if ($user !== false) {
    print "The user already exists, and updates in permissions not done from the commandline, we will only add group rights\n";
}

if ($uName && !$user) {
  $pass = array_key_exists('upasswd', $opts) ? $opts['upasswd'] : '';
  $options = array('cost' => 10);
  $hash = password_hash($pass, PASSWORD_DEFAULT, $options);
  $desc = 'created via cli';
  $perm = array_key_exists('accesslvl', $opts) ? intval($opts['accesslvl']) : 0;
  if (array_key_exists('folder', $opts)) {
    $folder =  $opts['folder'];
    $folderid = $folderDao->getFolderId($folder);

    if ($folderid == null) {
      $folderid = $folderDao->insertFolder($folder, 'Cli generated folder');
    }

  } else {
    $folderid=1;
  }
  $agentList = userAgents();
  $email = $emailNotify = '';
  add_user($uName, $desc, $hash, $perm, $email, $emailNotify, $agentList, $folderid);
  $user = $userDao->getUserByName($uName);
  print "added user $uName\n";
}

$gName = array_key_exists("gname", $opts) ? $opts["gname"] : '';
if ($gName) {
  $sql = "SELECT group_pk FROM groups WHERE group_name=$1";
  $groupRow = $dbManager->getSingleRow($sql, array($gName), __FILE__ . __LINE__);
  $groupId = $groupRow ? $groupRow['group_pk'] : $userDao->addGroup($gName);
} else {
  $groupId = false;
}

$permLvl = array_key_exists("permlvl", $opts) ? intval($opts["permlvl"]) : 0;
if ($user && $groupId) {
  $sql = "SELECT group_user_member_pk id FROM group_user_member WHERE user_fk=$1 AND group_fk=$2";
  $gumRow = $dbManager->getSingleRow($sql,array($user['user_pk'],$groupId),__FILE__.__LINE__);
}

if ($user && $groupId && $permLvl<0 && $gumRow) {
  $dbManager->prepare($stmt = __FILE__.__LINE__,
      "delete from group_user_member where group_user_member_pk=$1");
  $dbManager->freeResult($dbManager->execute($stmt, array($gumRow['id'])));
  print "deleted membership of $uName in $gName\n";
} else if ($user && $groupId && $permLvl>=0 && $gumRow) {
  $dbManager->getSingleRow("update group_user_member set group_perm=$1 where group_user_member_pk=$2",
      array($permLvl, $gumRow['id']), __FILE__.__LINE__);
  print "update membership of $uName in $gName\n";
} else if ($user && $groupId && $permLvl>=0) {
  $dbManager->insertTableRow('group_user_member',
          array('group_perm'=>$permLvl,'user_fk'=>$user['user_pk'],'group_fk'=>$groupId));
  print "inserted membership of $uName in $gName\n";
} else {
  print ".\n";
}
