<?php
/*
Copyright (C) 2014-2016, Siemens AG

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
*/

namespace Fossology\Lib\Application;

use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Dao\UserDao;
use Fossology\Lib\Util\ArrayOperation;

class UsersCsvImport {

  /** @var DbManager */
  protected $dbManager;

  /** @var DbManager */
  protected $folderDao;

  /** @var DbManager */
  protected $userDao;

  /** @var string */
  protected $delimiter = ',';

  /** @var string */
  protected $enclosure = '"';

  /** @var null|array */
  protected $headrow = null;

  /** @var array */
  protected $groupPermissions = array("none" => -1 , "user" => UserDao::USER, "admin" => UserDao::ADMIN, "advisor" => UserDao::ADVISOR);

  /** @var array */
  protected $userPermissions = array("none" => 0, "read" => 1, "write" => 3, "admin" => 10);

  /** @var array */
  protected $nkMap = array();

  /** @var array */
  protected $alias = array(
      'userName'=>array('username','User Name'),
      'description'=>array('description','Description'),
      'userEmail'=>array('email','Email'),
      'emailNotify'=>array('email_notify', 'emailnotify'),
      'userPerm'=>array('userlevel','User Level'),
      'rootFolder'=>array('root_folder','Root Folder', 'rootfolder'),
      'userPass'=>array('password','Password'),
      'group'=>array('group','groupname'),
      'groupPermission'=>array('group_permission', 'group permission','grouppermission'),
      );

  public function __construct(DbManager $dbManager)
  {
    $this->dbManager = $dbManager;
    $this->folderDao = $GLOBALS['container']->get('dao.folder');
    $this->userDao = $GLOBALS['container']->get('dao.user');
  }
  
  public function setDelimiter($delimiter=',')
  {
    $this->delimiter = substr($delimiter,0,1);
  }

  public function setEnclosure($enclosure='"')
  {
    $this->enclosure = substr($enclosure,0,1);
  }
  
  /**
   * @param string $filename
   * @return string message
   */
  public function handleFile($filename)
  {
    if (!is_file($filename) || ($handle = fopen($filename, 'r')) === FALSE) {
      return _('Internal error');
    }
    $cnt = -1;
    $msg = '';
    try
    {
      while(($row = fgetcsv($handle,0,$this->delimiter,$this->enclosure)) !== FALSE) {
        $log = $this->handleCsv($row);
        if (!empty($log))
        {
          $msg .= "$log\n";
        }
        $cnt++;
      }
      $msg .= _('Read csv').(": $cnt ")._('users');
    }
    catch(\Exception $e)
    {
      fclose($handle);
      return $msg .= _('Error while parsing file').': '.$e->getMessage();
    }
    fclose($handle);
    return $msg;
  }

  /**
   * @param array $row
   * @return string $log
   */
  private function handleCsv($row)
  {
    if($this->headrow===null)
    {
      $this->headrow = $this->handleHeadCsv($row);
      return 'head okay';
    }

    $mRow = array();
    foreach( array('userName','description','userEmail','emailNotify','userPerm','rootFolder','userPass','group','groupPermission') as $needle){
      $mRow[$needle] = $row[$this->headrow[$needle]];
    }    
    return $this->handleCsvUsers($mRow);
  }
  
  private function handleHeadCsv($row)
  {
    $headrow = array();
    foreach( array('userName','description','userEmail','emailNotify','userPerm','rootFolder','userPass','group','groupPermission') as $needle){
      $col = ArrayOperation::multiSearch($this->alias[$needle], $row);
      if (false === $col)
      {
        throw new \Exception("Undetermined position of $needle");
      }
      $headrow[$needle] = $col;
    }
    return $headrow;
  }

  /**
   * @param $userName, $groupName, $groupPermission
   * @return string
   */
  private function handleCsvGroups($userName, $groupName, $groupPermission)
  {
    $groupPerm = $this->groupPermissions[$groupPermission];
    if(empty($groupPerm)){
      $groupPerm = $this->groupPermissions['none'];
    }
    $checkGroupAlreadyExists = $this->dbManager->getSingleRow(
      'SELECT group_pk FROM groups WHERE group_name = $1 LIMIT 1;',
      array($groupName),'group.get'.rand());
    if(empty($checkGroupAlreadyExists)){
      $groupId = $this->userDao->addGroup($groupName);
    }else{
      $groupId = $checkGroupAlreadyExists['group_pk'];
    }

    $userId = $this->dbManager->getSingleRow(
      'SELECT user_pk FROM users WHERE user_name = $1 LIMIT 1;',
      array($userName),'userPk.get'.rand());
    $checkGroupPermAlreadyExists = $this->dbManager->getSingleRow(
      "SELECT group_user_member_pk FROM group_user_member WHERE group_fk = $1 AND user_fk = $2 AND group_perm = $3",
      array($groupId, $userId['user_pk'], $groupPerm),__METHOD__.'.gPermExists'.rand());
    if(empty($checkGroupPermAlreadyExists)){
      $this->userDao->addGroupMembership($groupId, $userId['user_pk'], $groupPerm);
    }
  }

  /**
   * @param $rootFolder
   * @return int
   */
  private function handleCsvFolders($rootFolder)
  {
    $rootFolder = trim($rootFolder);
    /* Get Top Folder */
    $topFolder = FolderGetTop();
    if(!empty($rootFolder)){
      $checkIfTopFolder = $this->dbManager->getSingleRow(
        "SELECT folder_pk FROM folder WHERE folder_name = $1",
        array($rootFolder),__METHOD__.'.CheckIfTopFolder'.$rootFolder);
      if($topFolder != $checkIfTopFolder['folder_pk']){
        /* Check if the same folder already exist top folder */
        $folderWithSameNameUnderParent = $this->folderDao->getFolderId($rootFolder, $topFolder);
        if (empty($folderWithSameNameUnderParent)){
          $rootFolderFk = $this->folderDao->createFolder($rootFolder, $rootFolder, $topFolder);
        }else{
          $rootFolderFk = $folderWithSameNameUnderParent;
        }
        return $rootFolderFk;
      }
    }
    return $topFolder;
  }

  /**
   * @param array $row
   * @return string
   */
  private function handleCsvUsers($row)
  {
    $logMessage = "";
    $agentList = "agent_copyright,agent_ecc,agent_mimetype,agent_monk,agent_nomos,agent_pkgagent";

    if(!empty($row['userName'])){

      $userData = array();
      $userData['user_perm'] = $this->userPermissions[$row['userPerm']];
      if(empty($userData['user_perm'])){
        $userData['user_perm'] = $this->userPermissions['none'];
      }
      $userData['root_folder_fk'] = $this->handleCsvFolders($row['rootFolder']);

      $getUserIdIfUserExists = $this->dbManager->getSingleRow('SELECT user_pk FROM users WHERE user_name = $1 LIMIT 1;',
        array($row['userName']),'userName.check'.rand());
      if(empty($getUserIdIfUserExists)){
        $userData['user_seed'] = rand() . rand();
        $userData['user_pass'] = sha1($userData['user_seed'] . $row['userPass']);
        $addUser = add_user($row['userName'], $row['description'], $userData['user_seed'], $userData['user_pass'], $userData['user_perm'], $row['userEmail'], $row['emailNotify'], $agentList, $userData['root_folder_fk'], $default_bucketpool_fk='');
        if(empty($addUser)){
          $logMessage.= "User record ".$row['userName']." added";
        }else{
          $logMessage.= "For user ".$row['userName'] ." :: ".$addUser;
        }
      }else{
        $userData['user_desc'] = $row['description'];
        $userData['user_email'] = $row['userEmail'];
        /* Build the sql update */
        $psql = "UPDATE users SET ";
        $first = TRUE;
        $i=0;
        foreach($userData as $key => $value){
          if (!$first){ 
            $psql .= ",";
          }
            $i=$i+1;
            $psql .= "$key ='" . pg_escape_string($value) . "'";
            $first = FALSE;
        }
        $psql .= " WHERE user_pk=$getUserIdIfUserExists[user_pk]";
        $updateUser = __METHOD__.".update".$row['userName']."Data".rand();
        $this->dbManager->getSingleRow($psql, array(), $updateUser);
        $logMessage.= "User record ".$row['userName']." updated";
      }
      $this->handleCsvGroups($row['userName'], $row['group'], $row['groupPermission']);
      return $logMessage;
    }else{
      throw new \Exception("Username must be specified.");
    }
  }
}
