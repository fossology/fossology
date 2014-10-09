<?php
/*
Copyright (C) 2014, Siemens AG
Author: Johannes Najjar

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
*/

namespace Fossology\Lib\Data;


use Fossology\Lib\Data\License\LicenseAlike;

class LicenseRef implements LicenseAlike
{
  /** @var int */
  private $id;

  /** @var string */
  private $shortName;

  /** @var string */
  private $fullName;

  /**
   * @param $licenseId
   * @param $licenseShortName
   * @param $licenseName
   */
  function __construct($licenseId, $licenseShortName, $licenseName)
  {
    $this->id = $licenseId;
    $this->shortName = $licenseShortName;
    $this->fullName = $licenseName ? : $licenseShortName;
  }

  /**
   * @return int
   */
  public function getId()
  {
    return $this->id;
  }

  /**
   * @return string
   */
  public function getFullName()
  {
    return $this->fullName;
  }

  /**
   * @return string
   */
  public function getShortName()
  {
    return $this->shortName;
  }
  
  public function __toString()
  {
    return 'LicenseRef('.$this->id.')';
  }

}