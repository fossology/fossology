<?php
/*
Copyright (C) 2014, Siemens AG

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

namespace Fossology\Lib\Data\Clearing;


use Fossology\Lib\Data\License\LicenseAlike;
use Fossology\Lib\Data\LicenseRef;

class ClearingLicense implements LicenseAlike {

  /** @var LicenseRef */
  private $licenseRef;

  /** @var bool */
  private $removed;

  /**
   * @param LicenseRef $licenseRef
   * @param boolean $removed
   */
  public function __construct(LicenseRef $licenseRef, $removed) {

    $this->licenseRef = $licenseRef;
    $this->removed = $removed;
  }

  /**
   * @return int
   */
  function getId()
  {
    return $this->licenseRef->getId();
  }

  /**
   * @return string
   */
  function getFullName()
  {
    return $this->licenseRef->getFullName();
  }

  /**
   * @return string
   */
  function getShortName()
  {
    return $this->licenseRef->getShortName();
  }

  /**
   * @return boolean
   */
  public function isRemoved()
  {
    return $this->removed;
  }
}