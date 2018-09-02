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

namespace api\models;


class Upload
{

  /**
   * Upload constructor.
   * @param $folderId integer
   * @param $folderName string
   * @param $uploadId integer
   * @param $description string
   * @param $uploadName string
   * @param $uploadDate string
   * @param $fileSize integer
   * @param $tag string
   */
  public function __construct($folderId, $folderName, $uploadId, $description, $uploadName, $uploadDate, $fileSize, $tag = NULL)
  {
    $this->folderId = $folderId;
    $this->folderName = $folderName;
    $this->uploadId = $uploadId;
    $this->description = $description;
    $this->uploadName = $uploadName;
    $this->uploadDate = $uploadDate;
    $this->fileSize = $fileSize;
  }

  /**
   * @return Json string
   */
  public function getJSON()
  {
    return json_encode(array(
      'folderId' => $this->folderId,
      'folderName' => $this->folderName,
      'uploadId' => $this->uploadId,
      "description" => $this->description,
      "uploadName" => $this->uploadName,
      "uploadDate" => $this->uploadDate,
      "fileSize" => $this->fileSize
    ));
  }

  /**
   * Get the upload element as an associative array
   * @return Associative array
   */
  public function getArray()
  {
    return [
      "folderId"    => $this->folderId,
      "folderName"  => $this->folderName,
      "uploadId"    => $this->uploadId,
      "description" => $this->description,
      "uploadName"  => $this->uploadName,
      "uploadDate"  => $this->uploadDate,
      "fileSize"    => $this->fileSize
    ];
  }
}
