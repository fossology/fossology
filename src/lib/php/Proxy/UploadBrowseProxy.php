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

namespace Fossology\Lib\Proxy;

use Fossology\Lib\Util\Object;
use Fossology\Lib\Data\UploadStatus;


class UploadTreeViewProxy extends Object
{
  const PRIO_COLUMN = 'priority';
  
  protected $groupId;
  protected $userPerm;
  /** @var DbManager */
  protected $dbManager;

  public function __construct($groupId, $userPerm, \Fossology\Lib\Db\DbManager $dbManager)
  {
    $this->groupId = $groupId;
    $this->userPerm = $userPerm;
    $this->dbManager = $dbManager;
  }

  protected function sanity()
  {
    $params = array($this->groupId, UploadStatus::OPEN, PERM_READ);
    $sql = 'INSERT INTO upload_clearing (upload_fk,group_fk,status_fk) '
         . ' SELECT upload_pk,$1,$2 FROM upload LEFT JOIN upload_clearing ON upload_pk=upload_fk AND group_fk=$1'
         . ' WHERE upload_clearing.upload_fk IS NULL'
         . ' AND (public_perm>=$3 OR EXISTS(SELECT * FROM perm_upload WHERE perm_upload.upload_fk = upload_pk AND group_fk=$1))';
    $this->dbManager->getSingleRow($sql, $params);
  }
  
  
  public function updateTable($columnName, $uploadId, $value)
  {
    if ($columnName == 'status_fk')
    {
      $this->changeStatus($uploadId, $value);
    }
    else if ($columnName == 'assignee' && $this->userPerm)
    {
      $sql = "UPDATE upload_clearing SET assignee=$1 WHERE group_fk=$2 AND upload_pk=$3";
      $this->dbManager->getSingleRow($sql, array($value, $this->groupId, $uploadId), $sqlLog = __METHOD__);
    }
    else
    {
      throw new \Exception('invalid column');
    }
  }  
  
  protected function changeStatus($uploadId, $newStatus)
  {
    if ($newStatus == UploadStatus::REJECTED && $this->userPerm)
    {
      $this->setStatusAndComment($uploadId, $newStatus, $commentText = '');
    }
    else if ($newStatus == UploadStatus::REJECTED)
    {
      throw new \Exception('missing permission');
    }
    else if ($this->userPerm)
    {
      $sql = "UPDATE upload SET status_fk=$1 WHERE group_fk=$2 AND upload_pk=$3";
      $this->dbManager->getSingleRow($sql, array($newStatus, $this->groupId, $uploadId), __METHOD__ . '.advisor');
    }
    else
    {
      $sql = "UPDATE upload_clearing SET status_fk=$1 WHERE group_fk=$2 AND upload_pk=$3 AND status_fk<$4";
      $params = array($newStatus, $this->groupId, $uploadId, UploadStatus::REJECTED);
      $this->dbManager->getSingleRow($sql, $params,  __METHOD__ . '.user');
    }
  }
  
  public function setStatusAndComment($uploadId, $statusId, $commentText)
  {
    $sql = "UPDATE upload_clearing SET status_fk=$1, status_comment=$2 WHERE upload_pk=$3";
    $this->dbManager->getSingleRow($sql, array($statusId, $commentText, $uploadId), __METHOD__);
    // $sel = $this->dbManager->getSingleRow("SELECT status_comment FROM upload_clearing WHERE group_fk=$1 AND upload_pk=$2", array($this->groupId,$uploadId), __METHOD__ . '.question');
    // print_r("$statusId, $commentText, $uploadId");
    // print_r('#' . $sel['status_comment']);
  }
  
  public function moveUploadToInfinity($uploadId, $top)
  {
    $fun = $top ? 'MAX('.self::PRIO_COLUMN.')+1' : 'MIN('.self::PRIO_COLUMN.')-1';
    $sql = "UPDATE upload_clearing SET ".self::PRIO_COLUMN."=(SELECT $fun FROM upload_clearing WHERE group_fk=$1)"
            . " WHERE group_fk=$1 AND upload_fk=$2";
    $this->dbManager->getSingleRow($sql,
            array($this->groupId,$uploadId),
            __METHOD__.($top?'+':'-'));
  }
  
  public function moveUploadBeyond($moveUpload, $beyondUpload)
  {
    $this->dbManager->begin();
    $this->dbManager->prepare($stmt = __METHOD__ . '.get.single.Upload',
        $sql='SELECT upload_fk,'.self::PRIO_COLUMN.' FROM upload_clearing WHERE group_fk=$1 AND upload_fk=$2');
    $movePoint = $this->dbManager->getSingleRow($sql, array($this->groupId,$moveUpload), $stmt);
    $beyondPoint = $this->dbManager->getSingleRow($sql, array($this->groupId,$beyondUpload), $stmt);
    if ($movePoint[self::PRIO_COLUMN] > $beyondPoint[self::PRIO_COLUMN])
    {
      $farPoint = $this->dbManager->getSingleRow("SELECT MAX(".self::PRIO_COLUMN.") m FROM upload_clearing WHERE group_fk=$1 AND ".self::PRIO_COLUMN."<$2",
              array($this->groupId,$beyondPoint[self::PRIO_COLUMN]), 'get.upload.with.lower.priority');
      $farPrio = $farPoint ? $farPoint['m'] : $beyondPoint[self::PRIO_COLUMN]-1;
    } else
    {
      $farPoint = $this->dbManager->getSingleRow("SELECT MIN(".self::PRIO_COLUMN.") m FROM upload_clearing WHERE group_fk=$1 AND ".self::PRIO_COLUMN.">$2",
              array($this->groupId,$beyondPoint[self::PRIO_COLUMN]), 'get.upload.with.higher.priority');
      $farPrio = $farPoint ? $farPoint['m'] : $beyondPoint[self::PRIO_COLUMN]+1;
    }
    
    $newPriority = ($farPrio + $beyondPoint[self::PRIO_COLUMN]) / 2;
    $this->dbManager->getSingleRow('UPDATE upload_clearing SET '.self::PRIO_COLUMN.'=$1 WHERE group_fk=$2 AND upload_fk=$2',
            array($newPriority, $this->groupId, $moveUpload),
            __METHOD__.'.update.priority');
    $this->dbManager->commit();
  }
  
} 