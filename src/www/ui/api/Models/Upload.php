<?php
/***************************************************************
Copyright (C) 2017,2020 Siemens AG

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
 * @brief Upload model
 */
namespace Fossology\UI\Api\Models;

/**
 * @class Upload
 * @brief Model class to hold Upload info
 */
class Upload
{
  /**
   * @var integer $folderId
   * Folder id holding the upload
   */
  private $folderId;
  /**
   * @var string $folderName
   * Folder name holding the upload
   */
  private $folderName;
  /**
   * @var integer $uploadId
   * Current upload id
   */
  private $uploadId;
  /**
   * @var string $description
   * Upload description
   */
  private $description;
  /**
   * @var string $uploadName
   * Upload name
   */
  private $uploadName;
  /**
   * @var string $uploadDate
   * Creation date of upload
   */
  private $uploadDate;
  /**
   * @var integer $assignee
   * Upload assignee id
   */
  private $assignee;
  /**
   * @var Hash $hash
   * Hash information of the upload
   */
  private $hash;

  /**
   * Upload constructor.
   * @param integer $folderId
   * @param string $folderName
   * @param integer $uploadId
   * @param string $description
   * @param string $uploadName
   * @param string $uploadDate
   * @param Hash $hash
   */
  public function __construct($folderId, $folderName, $uploadId, $description,
    $uploadName, $uploadDate, $assignee, $hash)
  {
    $this->folderId = intval($folderId);
    $this->folderName = $folderName;
    $this->uploadId = intval($uploadId);
    $this->description = $description;
    $this->uploadName = $uploadName;
    $this->uploadDate = $uploadDate;
    $this->assignee = $assignee == 1 ? null : intval($assignee);
    $this->hash = $hash;
  }

  /**
   * Get current upload in JSON representation
   * @return string
   */
  public function getJSON()
  {
    return json_encode($this->getArray());
  }

  /**
   * Get the upload element as an associative array
   * @return array
   */
  public function getArray()
  {
    return [
      "folderid"    => $this->folderId,
      "foldername"  => $this->folderName,
      "id"          => $this->uploadId,
      "description" => $this->description,
      "uploadname"  => $this->uploadName,
      "uploaddate"  => $this->uploadDate,
      "assignee"    => $this->assignee,
      "hash"        => $this->hash->getArray()
    ];
  }
}
