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

namespace Fossology\Lib\Data\Upload;

use Fossology\Lib\Data\UploadStatus;

class UploadProgress extends Upload
{
  /** @var int */
  protected $groupId;
  /** @var int */
  protected $assignee;
  /** @var int */
  protected $status;
  /** @var string */
  protected $comment;

  /**
   * @param $row
   * @return Upload
   */
  public static function createFromTable($row) {
    return new UploadProgress(intval($row['upload_pk']), $row['upload_filename'], $row['upload_desc'],
            $row['uploadtree_tablename'], strtotime($row['upload_ts']),
            intval($row['group_fk']),intval($row['assignee']),intval($row['status_fk']),$row['status_comment']);
  }

  /**
   * @param int $id
   * @param string $filename
   * @param string $description
   * @param string $treeTableName
   * @param int $timestamp
   */
  public function __construct($id, $filename, $description, $treeTableName, $timestamp, $groupId, $assignee, $status, $comment)
  {
    $this->groupId = $groupId;
    $this->assignee = $assignee;
    $this->status = $status;
    $this->commen = $comment;
    
    parent::__construct($id, $filename, $description, $treeTableName, $timestamp);
  }

  /**
   * @return int
   */
  public function getGroupId()
  {
    return $this->groupId;
  }

  /**
   * @return int
   */
  public function getAssignee()
  {
    return $this->assignee;
  }

  /**
   * @return int
   */
  public function getStatusId()
  {
    return $this->status;
  }
  
  /**
   * @return string
   */
  public function getStatusString()
  {
    $status = new UploadStatus();
    return $status->getTypeName($this->status);
  }

  /**
   * @return string
   */
  public function getComment()
  {
    return $this->comment;
  }

}