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
   * @var integer $fileSize
   * Upload size
   */
  private $fileSize;
  /**
   * @var string $fileSha1
   * SHA1 checksum of the uploaded file
   */
  private $fileSha1;
  /**
   * Upload constructor.
   * @param integer $folderId
   * @param string $folderName
   * @param integer $uploadId
   * @param string $description
   * @param string $uploadName
   * @param string $uploadDate
   * @param integer $fileSize
   * @param string $fileSha1
   * @param string $tag
   */
  public function __construct($folderId, $folderName, $uploadId, $description, $uploadName, $uploadDate, $fileSize, $fileSha1, $tag = NULL)
  {
    $this->folderId = intval($folderId);
    $this->folderName = $folderName;
    $this->uploadId = intval($uploadId);
    $this->description = $description;
    $this->uploadName = $uploadName;
    $this->uploadDate = $uploadDate;
    $this->fileSize = intval($fileSize);
    $this->fileSha1 = $fileSha1;
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
      "filesize"    => $this->fileSize,
      "filesha1"    => $this->fileSha1,
    ];
  }
}
