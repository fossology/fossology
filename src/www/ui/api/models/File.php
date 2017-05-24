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


class File
{
  private $filename;
  private $contentType;
  private $fileContent;

  /**
   * File constructor.
   * @param $filename
   * @param $contentType
   * @param $fileContent
   */
  public function __construct($filename, $contentType, $fileContent)
  {
    $this->filename = $filename;
    $this->contentType = $contentType;
    $this->fileContent = $fileContent;
  }

  /**
   * @return string
   */
  public function getFilename()
  {
    return $this->filename;
  }

  /**
   * @return string
   */
  public function getContentType()
  {
    return $this->contentType;
  }

  /**
   * @return string
   */
  public function getFileContent()
  {
    return $this->fileContent;
  }

  /**
   * @return string json
   */
  public function getJSON()
  {
    return json_encode(array(
      'filename' => $this->filename,
      'contentType' => $this->contentType,
      'fileContent' => $this->fileContent
    ));
  }



}
