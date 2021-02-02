<?php
/***************************************************************
 * Copyright (C) 2020 Siemens AG
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
 * @brief Findings model
 */
namespace Fossology\UI\Api\Models;

/**
 * @class Findings
 * @brief Model holding information about license findings and conclusions
 */
class Findings
{

  /**
   * @var array $scanner
   * List of scanner findings
   */
  private $scanner;

  /**
   * @var array $conclusion
   * List of user conclusions
   */
  private $conclusion;

  /**
   * @var array $copyright
   * List of copyright
   */
  private $copyright;

  /**
   * Findings constructor.
   *
   * @param array $scanner    Licenses found by scanners
   * @param array $conclusion Licenses concluded by users
   * @param array $copyright  Copyright for the file
   */
  public function __construct($scanner = null, $conclusion = null, $copyright = null)
  {
    $this->setScanner($scanner);
    $this->setConclusion($conclusion);
    $this->setCopyright($copyright);
  }

  /**
   * @return array
   */
  public function getScanner()
  {
    return $this->scanner;
  }

  /**
   * @return array
   */
  public function getConclusion()
  {
    return $this->conclusion;
  }

  /**
   * @return array
   */
  public function getCopyright()
  {
    return $this->copyright;
  }

  /**
   * @param array $scanner
   */
  public function setScanner($scanner)
  {
    if (is_array($scanner)) {
      $this->scanner = $scanner;
    } elseif (is_string($scanner)) {
      $this->scanner = [$scanner];
    } elseif ($scanner === null && empty($this->scanner)) {
      $this->scanner = null;
    }
  }

  /**
   * @param array $conclusion
   */
  public function setConclusion($conclusion)
  {
    if (is_array($conclusion)) {
      $this->conclusion = $conclusion;
    } elseif (is_string($conclusion)) {
      $this->conclusion = [$conclusion];
    } elseif ($conclusion === null && empty($this->conclusion)) {
      $this->conclusion = null;
    }
  }

  /**
   * @param array $copyrights
   */
  public function setCopyright($copyright)
  {
    if (is_array($copyright)) {
      $this->copyright = $copyright;
    } elseif (is_string($copyright)) {
      $this->copyright = [$copyright];
    } elseif ($copyright === null && empty($this->copyright)) {
      $this->copyright = null;
    }
  }

  /**
   * Get the object as associative array
   *
   * @return array
   */
  public function getArray()
  {
    return [
      'scanner'     => $this->getScanner(),
      'conclusion'  => $this->getConclusion(),
      'copyright'  => $this->getCopyright()
    ];
  }
}
