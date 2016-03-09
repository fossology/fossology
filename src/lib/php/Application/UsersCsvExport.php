<?php
/*
Copyright (C) 2015, Siemens AG

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

class UsersCsvExport {
  
  /** @var DbManager */
  protected $dbManager;
  
  /** @var string */
  protected $delimiter = ',';
  
  /** @var string */
  protected $enclosure = '"';
 
  /** @var array */
  protected $groupPermissions = array("none" => -1 , "user" => UserDao::USER, "admin" => UserDao::ADMIN, "advisor" => UserDao::ADVISOR);

  /** @var array */
  protected $userPermissions = array("none" => 0, "read" => 1, "write" => 3, "admin" => 10);

  public function __construct(DbManager $dbManager)
  {
    $this->dbManager = $dbManager;
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
   * @param int $rf
   * @return string csv
   */
  public function createCsv($rf=0)
  {
    $sql = "SELECT user_name username, user_desc description, user_email email, email_notify, 
            user_perm userlevel, folder_name root_folder, user_pass as password, group_name as group, group_perm group_permission
            FROM users 
            JOIN groups ON users.group_fk=groups.group_pk 
            JOIN group_user_member ON  users.group_fk=group_user_member.group_fk 
            JOIN folder ON users.root_folder_fk=folder.folder_pk";
 
    $stmt = __METHOD__;
    $this->dbManager->prepare($stmt, $sql);
    $res = $this->dbManager->execute($stmt, array());
    $vars = $this->dbManager->fetchAll($res);
    $this->dbManager->freeResult($res);
    
    $out = fopen('php://output', 'w');
    ob_start();
    $head = array('username', 'description', 'email', 'email_notify', 'userlevel', 'root_folder', 'password', 'group', 'group_permission');
    fputcsv($out, $head, $this->delimiter, $this->enclosure);
    foreach($vars as $row){
      if(!empty($row['userlevel'])){
        $row['userlevel'] = array_search($row['userlevel'], $this->userPermissions); 
      }
      if(!empty($row['group_permission'])){
        $row['group_permission'] = array_search($row['group_permission'], $this->groupPermissions); 
      }
      fputcsv($out, $row, $this->delimiter, $this->enclosure);
    }
    $content = ob_get_contents();
    ob_end_clean();
    return $content;
  }

} 
