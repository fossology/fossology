<?php

/***************************************************************
Copyright (C) 2021 HH Partners

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
 * @brief License
 */

namespace Fossology\UI\Api\Models;

/**
 * @class License
 * @package Fossology\UI\Api\Models
 * @brief License model to hold license related info
 */
class License
{
  /**
   * @var integer $id
   * License id
   */
  private $id;
  /**
   * @var string $shortName
   * Short name of the license
   */
  private $shortName;
  /**
   * @var string $fullName
   * Full name of the license
   */
  private $fullName;
  /**
   * @var string $text
   * The text of the license
   */
  private $text;
  /**
   * @var integer|null $license
   * The risk level of the license
   */
  private $risk;

  /**
   * License constructor.
   *
   * @param integer $id
   * @param string $shortName
   * @param string $fullName
   * @param string $text
   * @param integer|null $risk
   */
  public function __construct(
    $id,
    $shortName = "",
    $fullName = "",
    $text = "",
    $risk = null
  )
  {
    $this->id = intval($id);
    $this->shortName = $shortName;
    $this->fullName = $fullName;
    $this->text = $text;

    // invtval returns 0 for null, so check for nullness to preserve the
    // difference in the response.
    if (!is_null($risk)) {
      $this->risk = intval($risk);
    } else {
      $this->risk = $risk;
    }
  }

  /**
   * JSON representation of the license
   * @return string
   */
  public function getJSON()
  {
    return json_encode($this->getArray());
  }

  /**
   * Get License element as associative array
   * @return array
   */
  public function getArray()
  {
    return [
      'id' => $this->id,
      'shortName' => $this->shortName,
      'fullName' => $this->fullName,
      'text' => $this->text,
      'risk' => $this->risk
    ];
  }

  /**
   * Get the license's short name
   * @return string License's short name
   */
  public function getShortName()
  {
    return $this->shortName;
  }

  /**
   * Get the license's full name
   * @return string License's short name
   */
  public function getFullName()
  {
    return $this->fullName;
  }

  /**
   * Get the license's text
   * @return string License's text
   */
  public function getText()
  {
    return $this->text;
  }

  /**
   * Get the license's risk level
   * @return int|null License's risk level if set, null if not set
   */
  public function getRisk()
  {
    return $this->risk;
  }

  /**
   * Set the license's short name
   * @param string $shortName License's short name
   */
  public function setShortName($shortName)
  {
    $this->shortName = $shortName;
  }

  /**
   * Set the license's full name
   * @param string $fullName License's full name
   */
  public function setFullName($fullName)
  {
    $this->$fullName = $fullName;
  }

  /**
   * Set the license's text
   * @param string $text License's text
   */
  public function setText($text)
  {
    $this->$text = $text;
  }

  /**
   * Set the license's risk level
   * @param int|null $risk License's risk level or null
   */
  public function setRisk($risk)
  {
    $this->$risk = $risk;
  }
}
