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
namespace Fossology\ReportImport;

class ReportImportData
{

  /** @var  array */
  protected $licenseInfosInFile;
  /** @var  array */
  protected $licensesConcluded;
  /** @var  array */
  protected $copyrightTexts;
  /** @var  array */
  protected $pfiles;

  function __construct($licenseInfosInFile = array(), $licensesConcluded = array(), $copyrightTexts = array())
  {
    $this->licenseInfosInFile = $licenseInfosInFile;
    $this->licensesConcluded = $licensesConcluded;
    $this->copyrightTexts = $copyrightTexts;
  }

  /**
   * @param $reportImportDataItem
   * @return $this
   */
  public function addLicenseInfoInFile($reportImportDataItem)
  {
    $this->licenseInfosInFile[] = $reportImportDataItem;
    return $this;
  }

  /**
   * @param $copyrightText
   * @return $this
   */
  public function addCopyrightText($copyrightText)
  {
    $this->copyrightTexts[] = $copyrightText;
    return $this;
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
   * @return ReportImportData
   */
  public function setPfiles($pfiles)
  {
    $this->pfiles = $pfiles;
    return $this;
  }
}
