<?php
/**
 * *************************************************************
 * Copyright (C) 2017 Siemens AG
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
 * *************************************************************
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
   * Decider constructor.
   *
   * @param boolean $nomosMonk
   * @param boolean $bulkReused
   * @param boolean $newScanner
   * @param boolean $ojoDecider
   */
  public function __construct($nomosMonk = false, $bulkReused = false, $newScanner = false, $ojoDecider = false)
  {
    $this->nomosMonk  = $nomosMonk;
    $this->bulkReused = $bulkReused;
    $this->newScanner = $newScanner;
    $this->ojoDecider = $ojoDecider;
  }

  /**
   * Set the values of Analysis based on associative array
   * @param array $deciderArray Associative boolean array
   * @return Decider Current object
   */
  public function setUsingArray($deciderArray)
  {
    if (array_key_exists("nomos_monk", $deciderArray)) {
      $this->nomosMonk = filter_var($deciderArray["nomos_monk"],
        FILTER_VALIDATE_BOOLEAN);
    }
    if (array_key_exists("bulk_reused", $deciderArray)) {
      $this->bulkReused = filter_var($deciderArray["bulk_reused"],
        FILTER_VALIDATE_BOOLEAN);
    }
    if (array_key_exists("new_scanner", $deciderArray)) {
      $this->newScanner = filter_var($deciderArray["new_scanner"],
        FILTER_VALIDATE_BOOLEAN);
    }
    if (array_key_exists("ojo_decider", $deciderArray)) {
      $this->ojoDecider = filter_var($deciderArray["ojo_decider"],
        FILTER_VALIDATE_BOOLEAN);
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
   * Get decider as an array
   * @return array
   */
  public function getArray()
  {
    return [
      "nomos_monk"  => $this->nomosMonk,
      "bulk_reused" => $this->bulkReused,
      "new_scanner" => $this->newScanner,
      "ojo_decider" => $this->ojoDecider
    ];
  }
}
