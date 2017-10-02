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

namespace www\ui\api\models;

/**
 * Class Job
 * @package www\ui\api\models
 */
class Job
{
  private $id;
  private $name;
  private $queueDate;
  private $uploadId;

  /**
   * Job constructor.
   * @param $id integer
   * @param $name string
   * @param $queueDate string
   * @param $uploadId integer
   * @param $userId integer
   * @param $groupId integer
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
   * @return array
   */
  public function getJSON()
  {
    return array(
      'id' => $this->id,
      'name' => $this->name,
      'queueDate' => $this->queueDate,
      'uploadId' => $this->uploadId,
      'userId' => $this->userId,
      'groupId' => $this->groupId
    );
  }

}
