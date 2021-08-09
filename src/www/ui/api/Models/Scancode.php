<?php
/*****************************************************************************
 * SPDX-License-Identifier: GPL-2.0
 * SPDX-FileCopyrightText: 2021 Sarita Singh <saritasingh.0425@gmail.com>
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * version 2 as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 ****************************************************************************/

/**
 * @file
 * @brief Scancode model
 */
namespace Fossology\UI\Api\Models;

/**
 * @class Scancode
 * @brief Scancode model
 */
class Scancode
{
  /**
   * @var boolean $scanLicense
   */
  private $scanLicense;
  /**
   * @var boolean $scanCopyright
   */
  private $scanCopyright;
  /**
   * @var boolean $scanEmail
   */
  private $scanEmail;
  /**
   * @var boolean $scanUrl
   */
  private $scanUrl;

  /**
   * Scancode constructor.
   *
   * @param boolean $scanLicense
   * @param boolean $scanCopyright
   * @param boolean $scanEmail
   * @param boolean $scanUrl
   */
  public function __construct($scanLicense = false, $scanCopyright = false, $scanEmail = false, $scanUrl = false)
  {
    $this->scanLicense  = $scanLicense;
    $this->scanCopyright = $scanCopyright;
    $this->scanEmail = $scanEmail;
    $this->scanUrl = $scanUrl;
  }

  /**
   * Set the values of Analysis based on associative array
   * @param array $scancodeArray Associative boolean array
   * @return Scancode Current object
   */
  public function setUsingArray($scancodeArray)
  {
    if (array_key_exists("license", $scancodeArray)) {
      $this->scanLicense = filter_var($scancodeArray["license"],
        FILTER_VALIDATE_BOOLEAN);
    }
    if (array_key_exists("copyright", $scancodeArray)) {
      $this->scanCopyright = filter_var($scancodeArray["copyright"],
        FILTER_VALIDATE_BOOLEAN);
    }
    if (array_key_exists("email", $scancodeArray)) {
      $this->scanEmail = filter_var($scancodeArray["email"],
        FILTER_VALIDATE_BOOLEAN);
    }
    if (array_key_exists("url", $scancodeArray)) {
      $this->scanUrl = filter_var($scancodeArray["url"],
        FILTER_VALIDATE_BOOLEAN);
    }
    return $this;
  }

  ////// Getters //////
  /**
   * @return boolean
   */
  public function getScanLicense()
  {
    return $this->scanLicense;
  }

  /**
   * @return boolean
   */
  public function getScanCopyright()
  {
    return $this->scanCopyright;
  }

  /**
   * @return boolean
   */
  public function getScanEmail()
  {
    return $this->scanEmail;
  }

  /**
   * @return boolean
   */
  public function getScanUrl()
  {
    return $this->scanUrl;
  }

  ////// Setters //////
  /**
   * @param boolean $scanLicense
   */
  public function setScanLicense($scanLicense)
  {
    $this->scanLicense = filter_var($scanLicense, FILTER_VALIDATE_BOOLEAN);
  }

  /**
   * @param boolean $scanCopyright
   */
  public function setScanCopyright($scanCopyright)
  {
    $this->scanCopyright = filter_var($scanCopyright, FILTER_VALIDATE_BOOLEAN);
  }

  /**
   * @param boolean $scanEmail
   */
  public function setScanEmail($scanEmail)
  {
    $this->scanEmail = filter_var($scanEmail, FILTER_VALIDATE_BOOLEAN);
  }

  /**
   * @param boolean $scanUrl
   */
  public function setScanUrl($scanUrl)
  {
    $this->scanUrl = filter_var($scanUrl, FILTER_VALIDATE_BOOLEAN);
  }

  /**
   * Get scancode as an array
   * @return array
   */
  public function getArray()
  {
    return [
      "license"  => $this->scanLicense,
      "copyright" => $this->scanCopyright,
      "email" => $this->scanEmail,
      "url" => $this->scanUrl
    ];
  }
}
