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
 * @brief Model for search results
 */

namespace Fossology\UI\Api\Models;

/**
 * @class SearchResult
 * @brief Model to hold search results
 */
class SearchResult
{
  /**
   * @var array $upload
   * Upload object from the search
   */
  private $upload;
  /**
   * @var integer $uploadTreeId
   * Upload tree id of current result
   */
  private $uploadTreeId;
  /**
   * @var string $filename
   * File name of current result
   */
  private $filename;

  /**
   * SearchResult constructor.
   * @param Upload $upload
   * @param integer $uploadTreeId
   * @param string $filename
   */
  public function __construct($upload, $uploadTreeId, $filename)
  {
    $this->upload = $upload;
    $this->uploadTreeId = intval($uploadTreeId);
    $this->filename = $filename;
  }

  /**
   * Get current result as JSON
   * @return string
   */
  public function getJSON()
  {
    return json_encode($this->getArray());
  }

  /**
   * Get Search result element as an associative array
   * @return array
   */
  public function getArray()
  {
    return [
      'upload'        => $this->upload,
      'uploadTreeId'  => $this->uploadTreeId,
      'filename'      => $this->filename
    ];
  }
}
