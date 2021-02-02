<?php
/***************************************************************
 * Copyright (C) 2018 Siemens AG
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * version 2 as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 ***************************************************************/
/**
 * @file
 * @brief Reuser model
 */
namespace Fossology\UI\Api\Models;

/**
 * @class Reuser
 * @brief Model to hold info required by Reuser agent
 */
class Reuser
{
  /**
   * @var integer $reuseUpload
   * Upload id to reuse
   */
  private $reuseUpload;
  /**
   * @var string $reuseGroup
   * Group name to reuse from
   */
  private $reuseGroup;
  /**
   * @var boolean $reuseMain
   * Reuse main license
   */
  private $reuseMain;
  /**
   * @var boolean $reuseEnhanced
   * Use enhanced reuse
   */
  private $reuseEnhanced;

  /**
   * Reuser constructor.
   *
   * @param integer $reuseUpload
   * @param string $reuseGroup
   * @param boolean $reuseMain
   * @param boolean $reuseEnhanced
   * @throws \UnexpectedValueException If reuse upload of reuse group are non
   * integers
   */
  public function __construct($reuseUpload, $reuseGroup, $reuseMain = false,
    $reuseEnhanced = false)
  {
    if (is_numeric($reuseUpload)) {
      $this->reuseUpload = $reuseUpload;
      $this->reuseGroup = $reuseGroup;
      $this->reuseMain = $reuseMain;
      $this->reuseEnhanced = $reuseEnhanced;
    } else {
      throw new \UnexpectedValueException(
        "reuse_upload should be integer", 400);
    }
  }

  /**
   * Set the values of Reuser based on associative array
   *
   * @param array $reuserArray Associative boolean array
   * @return Reuser Current object
   * @throws \UnexpectedValueException If reuse upload of reuse group are non
   * integers
   */
  public function setUsingArray($reuserArray)
  {
    if (array_key_exists("reuse_upload", $reuserArray)) {
      $this->reuseUpload = filter_var($reuserArray["reuse_upload"],
        FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
    }
    if (array_key_exists("reuse_group", $reuserArray)) {
      $this->reuseGroup = $reuserArray["reuse_group"];
    }
    if (array_key_exists("reuse_main", $reuserArray)) {
      $this->reuseMain = filter_var($reuserArray["reuse_main"],
        FILTER_VALIDATE_BOOLEAN);
    }
    if (array_key_exists("reuse_enhanced", $reuserArray)) {
      $this->reuseEnhanced = filter_var($reuserArray["reuse_enhanced"],
        FILTER_VALIDATE_BOOLEAN);
    }
    if ($this->reuseUpload === null) {
      throw new \UnexpectedValueException(
        "reuse_upload should be integer", 400);
    }
    if ($this->reuseGroup === null) {
      throw new \UnexpectedValueException(
        "reuse_group should be a string", 400);
    }
    return $this;
  }

  ////// Getters //////
  /**
   * @return integer
   */
  public function getReuseUpload()
  {
    return $this->reuseUpload;
  }

  /**
   * @return string
   */
  public function getReuseGroup()
  {
    return $this->reuseGroup;
  }

  /**
   * @return boolean
   */
  public function getReuseMain()
  {
    return $this->reuseMain;
  }

  /**
   * @return boolean
   */
  public function getReuseEnhanced()
  {
    return $this->reuseEnhanced;
  }

  ////// Setters //////
  /**
   * @param integer $reuseUpload
   */
  public function setReuseUpload($reuseUpload)
  {
    $this->reuseUpload = filter_var($reuseUpload,
      FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
    if ($this->reuseUpload === null) {
      throw new \UnexpectedValueException("Reuse upload should be an integer!", 400);
    }
  }

  /**
   * @param string $reuseGroup
   */
  public function setReuseGroup($reuseGroup)
  {
    $this->reuseGroup = $reuseGroup;
    if ($this->reuseGroup === null) {
      throw new \UnexpectedValueException("Reuse group should be a string!", 400);
    }
  }

  /**
   * @param boolean $reuseMain
   */
  public function setReuseMain($reuseMain)
  {
    $this->reuseMain = filter_var($reuseMain,
      FILTER_VALIDATE_BOOLEAN);
  }

  /**
   * @param boolean $reuseEnhanced
   */
  public function setReuseEnhanced($reuseEnhanced)
  {
    $this->reuseEnhanced = filter_var($reuseEnhanced,
      FILTER_VALIDATE_BOOLEAN);
  }

  /**
   * Get reuser info as an associative array
   * @return array
   */
  public function getArray()
  {
    return [
      "reuse_upload"   => $this->reuseUpload,
      "reuse_group"    => $this->reuseGroup,
      "reuse_main"     => $this->reuseMain,
      "reuse_enhanced" => $this->reuseEnhanced
    ];
  }
}
