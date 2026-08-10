<?php
/*
 SPDX-FileCopyrightText: © 2026 FOSSology contributors

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @file
 * @brief BucketPool
 */
namespace Fossology\UI\Api\Models;

/**
 * @class BucketPool
 * @package Fossology\UI\Api\Models
 * @brief BucketPool model to hold bucket pool related info
 */
class BucketPool
{
  /**
   * @var integer $id
   * Bucket pool id
   */
  private $id;
  /**
   * @var string $name
   * Name of the bucket pool
   */
  private $name;
  /**
   * @var integer $version
   * Version of the bucket pool
   */
  private $version;
  /**
   * @var boolean $active
   * Is the bucket pool active?
   */
  private $active;
  /**
   * @var string $description
   * Description of the bucket pool
   */
  private $description;

  /**
   * BucketPool constructor.
   *
   * @param integer $id
   * @param string $name
   * @param integer $version
   * @param boolean $active
   * @param string $description
   */
  public function __construct($id, $name = "", $version = 1, $active = true,
    $description = "")
  {
    $this->id = intval($id);
    $this->name = $name;
    $this->version = intval($version);
    $this->active = $active;
    $this->description = $description;
  }

  /**
   * Get BucketPool element as associative array
   * @return array
   */
  public function getArray()
  {
    return [
      'id' => $this->id,
      'name' => $this->name,
      'version' => $this->version,
      'active' => $this->active,
      'description' => $this->description
    ];
  }
}
