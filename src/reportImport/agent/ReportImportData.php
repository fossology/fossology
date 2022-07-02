<?php
/*
 SPDX-FileCopyrightText: © 2017 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
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
