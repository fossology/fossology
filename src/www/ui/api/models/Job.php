<?php
/***************************************************************
Copyright (C) 2017 Siemens AG

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
 ***************************************************************/
/**
 * @file
 * @brief Job model
 */

namespace Fossology\UI\Api\Models;

/**
 * @class Job
 * @package Fossology\UI\Api\Models
 * @brief Job model to hold job related info
 */
class Job
{
  /**
   * @var integer $id
   * Job id
   */
  private $id;
  /**
   * @var string $name
   * Job name
   */
  private $name;
  /**
   * @var string $queueDate
   * Job queue date
   */
  private $queueDate;
  /**
   * @var integer $uploadId
   * Upload id for current job
   */
  private $uploadId;
  /**
   * @var integer $userId
   * User id for current job
   */
  private $userId;
  /**
   * @var integer $groupId
   * Group id for current job
   */
  private $groupId;

  /**
   * Job constructor.
   * @param integer $id
   * @param string $name
   * @param string $queueDate
   * @param integer $uploadId
   * @param integer $userId
   * @param integer $groupId
   */
  public function __construct($id, $name, $queueDate, $uploadId, $userId, $groupId)
  {
    $this->id = $id;
    $this->name = $name;
    $this->queueDate = $queueDate;
    $this->uploadId = $uploadId;
    $this->userId = $userId;
    $this->groupId = $groupId;
  }

  /**
   * JSON representation of current job
   * @return string
   */
  public function getJSON()
  {
    return json_encode($this->getArray());
  }

  /**
   * Get Job element as associative array
   * @return array
   */
  public function getArray()
  {
    return [
      'id'        => $this->id,
      'name'      => $this->name,
      'queueDate' => $this->queueDate,
      'uploadId'  => $this->uploadId,
      'userId'    => $this->userId,
      'groupId'   => $this->groupId
    ];
  }
}
