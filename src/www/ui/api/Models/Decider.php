<?php
/*
 SPDX-FileCopyrightText: © 2017 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * @file
 * @brief Decider model
 */
namespace Fossology\UI\Api\Models;

/**
 * @class Decider
 * @brief Decider model
 */
class Decider
{
  /**
   * @var boolean $nomosMonk
   * Scanners matches if all Nomos findings are within the Monk findings
   */
  private $nomosMonk;
  /**
   * @var boolean $bulkReused
   * Decide bulk phrases from reused packages
   */
  private $bulkReused;
  /**
   * @var boolean $newScanner
   * New scanner results, i.e., decisions were marked as work in progress if
   * new scanner finds additional licenses
   */
  private $newScanner;
  /**
   * @var boolean $ojoDecider
   * Scanners matches if Ojo or Reso findings are no contradiction with other findings
   */
  private $ojoDecider;
  /**
   * @var string $concludeLicenseType
   * Use this license type to create conclusions.
   */
  private $concludeLicenseType;

  /**
   * Decider constructor.
   *
   * @param boolean $nomosMonk
   * @param boolean $bulkReused
   * @param boolean $newScanner
   * @param boolean $ojoDecider
   * @param string  $concludeLicenseType
   */
  public function __construct($nomosMonk = false, $bulkReused = false,
                              $newScanner = false, $ojoDecider = false,
                              $concludeLicenseType = "")
  {
    $this->setNomosMonk($nomosMonk);
    $this->setBulkReused($bulkReused);
    $this->setNewScanner($newScanner);
    $this->setOjoDecider($ojoDecider);
    $this->setConcludeLicenseType($concludeLicenseType);
  }

  /**
   * Set the values of Analysis based on associative array
   * @param array $deciderArray Associative boolean array
   * @return Decider Current object
   */
  public function setUsingArray($deciderArray, $version = ApiVersion::V1)
  {
    if (array_key_exists(($version == ApiVersion::V2? "nomosMonk" : "nomos_monk"), $deciderArray)) {
      $this->setNomosMonk($deciderArray[$version == ApiVersion::V2? "nomosMonk" : "nomos_monk"]);
    }
    if (array_key_exists(($version == ApiVersion::V2? "bulkReused" : "bulk_reused"), $deciderArray)) {
      $this->setBulkReused($deciderArray[$version == ApiVersion::V2? "bulkReused" : "bulk_reused"]);
    }
    if (array_key_exists(($version == ApiVersion::V2? "newScanner" : "new_scanner"), $deciderArray)) {
      $this->setNewScanner($deciderArray[$version == ApiVersion::V2? "newScanner" : "new_scanner"]);
    }
    if (array_key_exists(($version == ApiVersion::V2? "ojoDecider" : "ojo_decider"), $deciderArray)) {
      $this->setOjoDecider($deciderArray[$version == ApiVersion::V2? "ojoDecider" : "ojo_decider"]);
    }
    if (array_key_exists(($version == ApiVersion::V2? "concludeLicenseType" : "conclude_license_type"), $deciderArray)) {
      $this->setConcludeLicenseType($deciderArray[$version == ApiVersion::V2? "concludeLicenseType" : "conclude_license_type"]);
    }
    return $this;
  }

  ////// Getters //////
  /**
   * @return boolean
   */
  public function getNomosMonk()
  {
    return $this->nomosMonk;
  }

  /**
   * @return boolean
   */
  public function getBulkReused()
  {
    return $this->bulkReused;
  }

  /**
   * @return boolean
   */
  public function getNewScanner()
  {
    return $this->newScanner;
  }

  /**
   * @return boolean
   */
  public function getOjoDecider()
  {
    return $this->ojoDecider;
  }

  /**
   * @return string
   */
  public function getConcludeLicenseType()
  {
    return $this->concludeLicenseType;
  }

  ////// Setters //////
  /**
   * @param boolean $nomosMonk
   */
  public function setNomosMonk($nomosMonk)
  {
    $this->nomosMonk = filter_var($nomosMonk, FILTER_VALIDATE_BOOLEAN);
  }

  /**
   * @param boolean $bulkReused
   */
  public function setBulkReused($bulkReused)
  {
    $this->bulkReused = filter_var($bulkReused, FILTER_VALIDATE_BOOLEAN);
  }

  /**
   * @param boolean $newScanner
   */
  public function setNewScanner($newScanner)
  {
    $this->newScanner = filter_var($newScanner, FILTER_VALIDATE_BOOLEAN);
  }

  /**
   * @param boolean $ojoDecider
   */
  public function setOjoDecider($ojoDecider)
  {
    $this->ojoDecider = filter_var($ojoDecider, FILTER_VALIDATE_BOOLEAN);
  }

  /**
   * @param string $concludeLicenseType
   */
  public function setConcludeLicenseType($concludeLicenseType)
  {
    if ($concludeLicenseType !== null) {
      $this->concludeLicenseType = trim($concludeLicenseType);
    } else {
      $this->concludeLicenseType = "";
    }
  }

  /**
   * Get decider as an array
   * @return array
   */
  public function getArray($version = ApiVersion::V1)
  {
    if ($version == ApiVersion::V2) {
      return [
        "nomosMonk"  => $this->nomosMonk,
        "bulkReused" => $this->bulkReused,
        "newScanner" => $this->newScanner,
        "ojoDecider" => $this->ojoDecider,
        "concludeLicenseType" => $this->concludeLicenseType
      ];
    } else {
      return [
        "nomos_monk"  => $this->nomosMonk,
        "bulk_reused" => $this->bulkReused,
        "new_scanner" => $this->newScanner,
        "ojo_decider" => $this->ojoDecider,
        "conclude_license_type" => $this->concludeLicenseType
      ];
    }
  }
}
