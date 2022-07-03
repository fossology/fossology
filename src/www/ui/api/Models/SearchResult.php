<?php
/*
 SPDX-FileCopyrightText: © 2017 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/
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
