<?php
/*
 * Copyright (C) 2017, Siemens AG
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
 */
namespace Fossology\SpdxTwoImport;

class SpdxTwoImportData
{

  /** @var  array */
  protected $licenseInfosInFile;
  /** @var  array */
  protected $licensesConcluded;
  /** @var  array */
  protected $copyrightTexts;
  /** @var  array */
  protected $pfiles;

  function __construct($licenseInfosInFile, $licensesConcluded, $copyrightTexts)
  {
    $this->licenseInfosInFile = $licenseInfosInFile;
    $this->licensesConcluded = $licensesConcluded;
    $this->copyrightTexts = $copyrightTexts;
  }

  /**
   * @return mixed
   */
  public function getLicenseInfosInFile()
  {
    return $this->licenseInfosInFile;
  }

  /**
   * @return mixed
   */
  public function getLicensesConcluded()
  {
    return $this->licensesConcluded;
  }

  /**
   * @return mixed
   */
  public function getCopyrightTexts()
  {
    return $this->copyrightTexts;
  }

  /**
   * @return mixed
   */
  public function getPfiles()
  {
    return $this->pfiles;
  }

  /**
   * @param mixed $pfiles
   * @return SpdxTwoImportData
   */
  public function setPfiles($pfiles)
  {
    $this->pfiles = $pfiles;
    return $this;
  }
}
