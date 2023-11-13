<?php
/*
 SPDX-FileCopyrightText: © 2023 Soham Banerjee <sohambanerjee4abc@hotmail.com>
 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * @file
 * @brief FileInfo model
 */

namespace Fossology\UI\Api\Models;

use Fossology\Lib\Data\Package\ComponentType;


/**
 * @class FileInfo
 * @brief FileInfo model to contain general error and return values
 */
class FileInfo
{
  /**
   * @var object $view_info
   * data for view_info
   */
  private $view_info;

  /**
   * @var object $meta_info
   * data for meta_info
   */
  private $meta_info;

  /**
   * @var object $package_info
   * data for package_info
   */
  private $package_info;

  /**
   * @var object $tag_info
   * data for tag_info
   */
  private $tag_info;

  /**
   * @var object $reuse_info
   * data for reuse_info
   */
  private $reuse_info;

  /**
   * FileInfo constructor.
   * @param $view_info
   * @param $meta_info
   * @param $package_info
   * @param $tag_info
   * @param $reuse_info
   */
  public function __construct($view_info, $meta_info, $package_info, $tag_info, $reuse_info)
  {
    $this->view_info = $view_info;
    $this->meta_info = $meta_info;
    $this->package_info = $package_info;
    $this->tag_info = $tag_info;
    $this->reuse_info = $reuse_info;
  }

  ////// Getters //////

  /**
   * Get the info as JSON representation
   * @return string
   */
  public function getJSON($version=ApiVersion::V1)
  {
    return json_encode($this->getArray($version));
  }

  /**
   * Get info as associative array
   * @return array
   */
  public function getArray($version=ApiVersion::V1)
  {
    if ($version==ApiVersion::V2) {
      return array(
        'viewInfo' => $this->view_info,
        'metaInfo' => $this->meta_info,
        'packageInfo' => $this->package_info,
        'tagInfo' => $this->tag_info,
        'reuseInfo' => $this->reuse_info
      );
    } else {
      return array(
      'view_info' => $this->view_info,
      'meta_info' => $this->meta_info,
      'package_info' => $this->package_info,
      'tag_info' => $this->tag_info,
      'reuse_info' => $this->reuse_info
      );
    }
  }
}
