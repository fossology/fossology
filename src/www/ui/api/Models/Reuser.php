<?php
/*
 SPDX-FileCopyrightText: © 2018, 2021 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/
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
   * @var integer|integer[] $reuseUpload
   * Upload id(s) to reuse
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
   * @var boolean $reuseReport
   * Use enhanced reuse
   */
  private $reuseReport;
  /**
   * @var boolean $reuseCopyright
   * Use enhanced reuse
   */
  private $reuseCopyright;
  /**
   * @var boolean $reuseBulk
   * Reuse bulk decisions
   */
  private $reuseBulk;

  /**
   * Reuser constructor.
   *
   * @param integer|integer[] $reuseUpload
   * @param string $reuseGroup
   * @param boolean $reuseMain
   * @param boolean $reuseEnhanced
   * @throws \UnexpectedValueException If reuse upload of reuse group are non
   * integers
   */
  public function __construct($reuseUpload, $reuseGroup, $reuseMain = false,
    $reuseEnhanced = false)
  {
    if (is_array($reuseUpload)) {
      if (empty($reuseUpload)) {
        throw new \UnexpectedValueException(
          "reuse_upload should be integer or array of integers", 400);
      }
      $filtered = array_filter($reuseUpload, function ($val) {
        return filter_var($val, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE) !== null;
      });
      if (count($filtered) !== count($reuseUpload)) {
        throw new \UnexpectedValueException(
          "reuse_upload should be integer or array of integers", 400);
      }
      $this->reuseUpload = array_map('intval', $reuseUpload);
    } elseif (is_numeric($reuseUpload)) {
      $this->reuseUpload = $reuseUpload;
    } else {
      throw new \UnexpectedValueException(
        "reuse_upload should be integer or array of integers", 400);
    }
    $this->reuseGroup = $reuseGroup;
    $this->reuseMain = $reuseMain;
    $this->reuseEnhanced = $reuseEnhanced;
    $this->reuseReport = false;
    $this->reuseCopyright = false;
    $this->reuseBulk = false;
  }

  /**
   * Set the values of Reuser based on associative array
   *
   * @param array $reuserArray Associative boolean array
   * @return Reuser Current object
   * @throws \UnexpectedValueException If reuse upload of reuse group are non
   * integers
   */
  public function setUsingArray($reuserArray, $version = ApiVersion::V1)
  {
    if (array_key_exists(($version == ApiVersion::V2? "reuseUpload" : "reuse_upload"), $reuserArray)) {
      $this->setReuseUpload($reuserArray[$version == ApiVersion::V2? "reuseUpload" : "reuse_upload"]);
    }
    if (array_key_exists(($version == ApiVersion::V2? "reuseGroup" : "reuse_group"), $reuserArray)) {
      $this->reuseGroup = $reuserArray[$version == ApiVersion::V2? "reuseGroup" : "reuse_group"];
    }
    if (array_key_exists(($version == ApiVersion::V2? "reuseMain" : "reuse_main"), $reuserArray)) {
      $this->setReuseMain($reuserArray[$version == ApiVersion::V2? "reuseMain" : "reuse_main"]);
    }
    if (array_key_exists(($version == ApiVersion::V2? "reuseEnhanced" : "reuse_enhanced"), $reuserArray)) {
      $this->setReuseEnhanced($reuserArray[$version == ApiVersion::V2? "reuseEnhanced" : "reuse_enhanced"]);
    }
    if (array_key_exists(($version == ApiVersion::V2? "reuseReport" : "reuse_report"), $reuserArray)) {
      $this->setReuseReport($reuserArray[$version == ApiVersion::V2? "reuseReport" : "reuse_report"]);
    }
    if (array_key_exists(($version == ApiVersion::V2? "reuseCopyright" : "reuse_copyright"), $reuserArray)) {
      $this->setReuseCopyright($reuserArray[$version == ApiVersion::V2? "reuseCopyright" : "reuse_copyright"]);
    }
    if (array_key_exists(($version == ApiVersion::V2? "reuseBulk" : "reuse_bulk"), $reuserArray)) {
      $this->setReuseBulk($reuserArray[$version == ApiVersion::V2? "reuseBulk" : "reuse_bulk"]);
    }
    if ($this->reuseUpload === null || (is_array($this->reuseUpload) && empty($this->reuseUpload))) {
      throw new \UnexpectedValueException(
        "reuse_upload should be integer or array of integers", 400);
    }
    if ($this->reuseGroup === null) {
      throw new \UnexpectedValueException(
        "reuse_group should be a string", 400);
    }
    return $this;
  }

  ////// Getters //////
  /**
   * @return integer|integer[]
   */
  public function getReuseUpload()
  {
    return $this->reuseUpload;
  }

  /**
   * Always return reuse upload IDs as an array.
   * @return integer[]
   */
  public function getReuseUploads()
  {
    if (is_array($this->reuseUpload)) {
      return $this->reuseUpload;
    }
    return [$this->reuseUpload];
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

  /**
   * @return boolean
   */
  public function getReuseReport()
  {
    return $this->reuseReport;
  }

  /**
   * @return boolean
   */
  public function getReuseCopyright()
  {
    return $this->reuseCopyright;
  }

  /**
   * @return boolean
   */
  public function getReuseBulk()
  {
    return $this->reuseBulk;
  }

  ////// Setters //////
  /**
   * @param integer|integer[] $reuseUpload
   */
  public function setReuseUpload($reuseUpload)
  {
    if (is_array($reuseUpload)) {
      if (empty($reuseUpload)) {
        throw new \UnexpectedValueException("Reuse upload should be an integer or array of integers!", 400);
      }
      $filtered = array_filter($reuseUpload, function ($val) {
        return filter_var($val, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE) !== null;
      });
      if (count($filtered) !== count($reuseUpload)) {
        throw new \UnexpectedValueException("Reuse upload should be an integer or array of integers!", 400);
      }
      $this->reuseUpload = array_map('intval', $reuseUpload);
    } else {
      $this->reuseUpload = filter_var($reuseUpload,
        FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
      if ($this->reuseUpload === null) {
        throw new \UnexpectedValueException("Reuse upload should be an integer or array of integers!", 400);
      }
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
   * @param boolean $reuseReport
   */
  public function setReuseReport($reuseReport)
  {
    $this->reuseReport = filter_var($reuseReport,
      FILTER_VALIDATE_BOOLEAN);
  }

  /**
   * @param boolean $reuseCopyright
   */
  public function setReuseCopyright($reuseCopyright)
  {
    $this->reuseCopyright = filter_var($reuseCopyright,
      FILTER_VALIDATE_BOOLEAN);
  }

  /**
   * @param boolean $reuseBulk
   */
  public function setReuseBulk($reuseBulk)
  {
    $this->reuseBulk = filter_var($reuseBulk,
      FILTER_VALIDATE_BOOLEAN);
  }

  /**
   * Get reuser info as an associative array
   * @return array
   */
  public function getArray($version = ApiVersion::V1)
  {
    if ($version == ApiVersion::V2) {
      return [
        "reuseUpload"    => $this->reuseUpload,
        "reuseGroup"     => $this->reuseGroup,
        "reuseMain"      => $this->reuseMain,
        "reuseEnhanced"  => $this->reuseEnhanced,
        "reuseReport"    => $this->reuseReport,
        "reuseCopyright" => $this->reuseCopyright,
        "reuseBulk"      => $this->reuseBulk
      ];
    } else {
      return [
        "reuse_upload"    => $this->reuseUpload,
        "reuse_group"     => $this->reuseGroup,
        "reuse_main"      => $this->reuseMain,
        "reuse_enhanced"  => $this->reuseEnhanced,
        "reuse_report"    => $this->reuseReport,
        "reuse_copyright" => $this->reuseCopyright,
        "reuse_bulk"      => $this->reuseBulk
      ];
    }
  }
}
