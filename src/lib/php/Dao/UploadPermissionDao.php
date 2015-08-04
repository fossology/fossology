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

namespace Fossology\Lib\Dao;

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Util\Object;
use Monolog\Logger;


class UploadPermissionDao extends Object
{
  /** @var DbManager */
  private $dbManager;
  /** @var Logger */
  private $logger;

  public function __construct(DbManager $dbManager, Logger $logger)
  {
    $this->dbManager = $dbManager;
    $this->logger = $logger;
  }
  
  public function isAccessible($uploadId, $groupId) 
  {
    $perm = $this->dbManager->getSingleRow('SELECT perm FROM perm_upload WHERE upload_fk=$1 AND group_fk=$2',
        array($uploadId, $groupId), __METHOD__.'.group_perm');
    if ($perm && $perm['perm'] > Auth::PERM_NONE) {
      return true;
    }
    
    if (!isset($_SESSION) || !array_key_exists(Auth::USER_LEVEL, $_SESSION) || $_SESSION[Auth::USER_LEVEL] === Auth::PERM_NONE) {
      return false;
    }

    $uploadPub = $this->dbManager->getSingleRow('SELECT public_perm FROM upload WHERE upload_pk=$1 AND public_perm>$2',
            array($uploadId,Auth::PERM_NONE),__METHOD__.'.public_perm');
    return !empty($uploadPub);
  }
  
  public function isEditable($uploadId, $groupId) 
  {
    if ($_SESSION[Auth::USER_LEVEL] == PLUGIN_DB_ADMIN) {
      return true;
    }

    $perm = $this->dbManager->getSingleRow('SELECT perm FROM perm_upload WHERE upload_fk=$1 AND group_fk=$2',
        array($uploadId, $groupId), __METHOD__);
    return $perm['perm']>=Auth::PERM_WRITE;
  }

  public function makeAccessibleToGroup($uploadId, $groupId, $perm=null)
  {
    if (null === $perm) {
      $perm = Auth::PERM_ADMIN;
    }
    $this->dbManager->getSingleRow("INSERT INTO perm_upload (perm, upload_fk, group_fk) "
            . " VALUES($1,$2,$3)",
               array($perm, $uploadId, $groupId), __METHOD__);
  }

  public function makeAccessibleToAllGroupsOf($uploadId, $userId, $perm=null)
  {
    if (null === $perm) {
      $perm = Auth::PERM_ADMIN;
    }
    $this->dbManager->getSingleRow("INSERT INTO perm_upload (perm, upload_fk, group_fk) "
            . "SELECT $1 perm, $2 upload_fk, gum.group_fk"
            . " FROM group_user_member gum LEFT JOIN perm_upload ON perm_upload.group_fk=gum.group_fk AND upload_fk=$2"
            . " WHERE perm_upload IS NULL AND gum.user_fk=$3",
               array($perm, $uploadId, $userId), __METHOD__.'.insert');
  }
 
  public function updatePermissionId($permId, $permLevel)
  {
    if (empty($permLevel)) {
      $this->dbManager->getSingleRow('DELETE FROM perm_upload WHERE perm_upload_pk=$1',
              array($permId),
              __METHOD__ . '.delete');
    }
    else {
      $this->dbManager->getSingleRow('UPDATE perm_upload SET perm=$2 WHERE perm_upload_pk=$1', 
              array($permId, $permLevel),
              __METHOD__ . '.update');
    }
  }
  
  public function insertPermission($uploadId, $groupId, $permLevel)
  { 
    $this->dbManager->getSingleRow("DELETE FROM perm_upload WHERE upload_fk=$1 AND group_fk=$2",
            array($uploadId,$groupId),
            __METHOD__.'.avoid_doublet');
    if ($permLevel == Auth::PERM_NONE) {
      return;
    }
    $this->dbManager->insertTableRow('perm_upload', array('perm'=>$permLevel,'upload_fk'=>$uploadId,'group_fk'=>$groupId));
  }
  
  public function setPublicPermission($uploadId, $permLevel)
  {
    $this->dbManager->getSingleRow('UPDATE upload SET public_perm=$2 WHERE upload_pk=$1', array($uploadId, $permLevel));
  }
  
  public function getPublicPermission($uploadId)
  {
    $row = $this->dbManager->getSingleRow('SELECT public_perm FROM upload WHERE upload_pk=$1 LIMIT 1',array($uploadId),__METHOD__);
    return $row['public_perm'];
  }
  
  public function getPermissionGroups($uploadId)
  {
    $this->dbManager->prepare($stmt=__METHOD__,
        "SELECT perm_upload_pk, perm, group_pk, group_name
           FROM groups, perm_upload
           WHERE group_fk=group_pk AND upload_fk=$1
           ORDER BY group_name");
    $res = $this->dbManager->execute($stmt, array($uploadId));
    $groupMap = array();
    while($row=$this->dbManager->fetchArray($res))
    {
      $groupMap[$row['group_pk']] = $row;
    }
    $this->dbManager->freeResult($res);
    return $groupMap;
  }
}
