<?php
/*
 SPDX-FileCopyrightText: © 2017, 2020 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/
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
   * @var string $assigneeDate
   * Date when a user was assigned to the upload.
   */
  private $assigneeDate;
  /**
   * @var string $closingDate
   * Date when the upload was closed or rejected.
   */
  private $closingDate;
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
    $this->assigneeDate = null;
    $this->closingDate = null;
    $this->hash = $hash;
  }

  /**
   * Get current upload in JSON representation
   * @return string
   */
  public function getJSON($version=ApiVersion::V1)
  {
    return json_encode($this->getArray($version));
  }

  /**
   * Get the upload element as an associative array
   * @return array
   */
  public function getArray($version=ApiVersion::V1)
  {
    if ($version==ApiVersion::V2) {
      return [
        "folderId"    => $this->folderId,
        "folderName"  => $this->folderName,
        "id"          => $this->uploadId,
        "description" => $this->description,
        "uploadName"  => $this->uploadName,
        "uploadDate"  => $this->uploadDate,
        "assignee"    => $this->assignee,
        "assigneeDate" => $this->assigneeDate,
        "closingDate" => $this->closingDate,
        "hash"        => $this->hash->getArray()
      ];
    } else {
      return [
        "folderid"    => $this->folderId,
        "foldername"  => $this->folderName,
        "id"          => $this->uploadId,
        "description" => $this->description,
        "uploadname"  => $this->uploadName,
        "uploaddate"  => $this->uploadDate,
        "assignee"    => $this->assignee,
        "assigneeDate" => $this->assigneeDate,
        "closingDate" => $this->closingDate,
        "hash"        => $this->hash->getArray()
      ];
    }
  }

  /**
   * @param string|null $assigneeDate
   * @return Upload
   */
  public function setAssigneeDate(?string $assigneeDate): Upload
  {
    $this->assigneeDate = $assigneeDate;
    return $this;
  }

  /**
   * @param string|null $closingDate
   * @return Upload
   */
  public function setClosingDate(?string $closingDate): Upload
  {
    $this->closingDate = $closingDate;
    return $this;
  }
}
