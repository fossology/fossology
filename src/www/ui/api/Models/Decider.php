<?php
/*
 SPDX-FileCopyrightText: © 2017 Siemens AG
 SPDX-FileCopyrightText: © 2025 Tiyasa Kundu <tiyasakundu20@gmail.com>

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
   * @var bool $copyrightDeactivation
   * Run the copyright deactivation?
   */
  private $copyrightDeactivation;
  /**
   * @var bool $copyrightClutterRemoval
   * Run the copyright clutter removal
   */
  private $copyrightClutterRemoval;

  /**
   * @var DeciderAgentPlugin $deciderAgentPlugin
   */
  private $deciderAgentPlugin;

  /**
   * Decider constructor.
   *
   * @param boolean $nomosMonk
   * @param boolean $bulkReused
   * @param boolean $newScanner
   * @param boolean $ojoDecider
   * @param string  $concludeLicenseType
   * @param boolean $copyrightDeactivation
   * @param boolean $copyrightClutterRemoval
   */
  public function __construct($nomosMonk = false, $bulkReused = false,
                              $newScanner = false, $ojoDecider = false,
                              $concludeLicenseType = "",
                              $copyrightDeactivation = false,
                              $copyrightClutterRemoval = false)
  {
    $this->setNomosMonk($nomosMonk);
    $this->setBulkReused($bulkReused);
    $this->setNewScanner($newScanner);
    $this->setOjoDecider($ojoDecider);
    $this->setConcludeLicenseType($concludeLicenseType);
    $this->setCopyrightDeactivation($copyrightDeactivation);
    $this->setCopyrightClutterRemoval($copyrightClutterRemoval);
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
    if (array_key_exists(($version == ApiVersion::V2? "copyrightDeactivation" : "copyright_deactivation"), $deciderArray)) {
      $this->setCopyrightDeactivation($deciderArray[$version == ApiVersion::V2? "copyrightDeactivation" : "copyright_deactivation"]);
    }
    if (array_key_exists(($version == ApiVersion::V2? "copyrightClutterRemoval" : "copyright_clutter_removal"), $deciderArray)) {
      $this->setCopyrightClutterRemoval($deciderArray[$version == ApiVersion::V2? "copyrightClutterRemoval" : "copyright_clutter_removal"]);
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

  /**
   * @return bool
   */
  public function getCopyrightDeactivation()
  {
    return $this->copyrightDeactivation;
  }

  /**
   * @return bool
   */
  public function getCopyrightClutterRemoval()
  {
    return $this->copyrightClutterRemoval;
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
   * @param DeciderAgentPlugin $deciderAgentPlugin
   */
  public function setDeciderAgentPlugin($deciderAgentPlugin)
  {
    $this->deciderAgentPlugin = $deciderAgentPlugin;
  }

  /**
   * @param bool $copyrightDeactivation
   */
  public function setCopyrightDeactivation($copyrightDeactivation)
  {
    if ($this->deciderAgentPlugin && $this->deciderAgentPlugin->isSpacyInstalled()) {
      $this->copyrightDeactivation = filter_var($copyrightDeactivation, FILTER_VALIDATE_BOOLEAN);
    } else {
      $this->copyrightDeactivation = false;
    }
  }

  /**
   * @param bool $copyrightClutterRemoval
   */
  public function setCopyrightClutterRemoval($copyrightClutterRemoval)
  {
    if ($this->deciderAgentPlugin && $this->deciderAgentPlugin->isSpacyInstalled()) {
      $this->copyrightClutterRemoval = filter_var($copyrightClutterRemoval, FILTER_VALIDATE_BOOLEAN);
    } else {
      $this->copyrightClutterRemoval = false;
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
        "concludeLicenseType" => $this->concludeLicenseType,
        "copyrightDeactivation" => $this->copyrightDeactivation,
        "copyrightClutterRemoval" => $this->copyrightClutterRemoval
      ];
    } else {
      return [
        "nomos_monk"  => $this->nomosMonk,
        "bulk_reused" => $this->bulkReused,
        "new_scanner" => $this->newScanner,
        "ojo_decider" => $this->ojoDecider,
        "conclude_license_type" => $this->concludeLicenseType,
        "copyright_deactivation" => $this->copyrightDeactivation,
        "copyright_clutter_removal" => $this->copyrightClutterRemoval
      ];
    }
  }
}
