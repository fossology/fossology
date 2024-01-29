<?php
/*
 SPDX-FileCopyrightText: © 2023 Siemens AG
 Author: Gaurav Mishra <mishra.gaurav@siemens.com>

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @file
 * @breif Model to hold license and copyright information about a file
 */
namespace Fossology\UI\Api\Models;

/**
 * @class FileLicenses
 * @breif License and copyright information about a file
 */
class FileLicenses
{
  /**
   * @var string $filePath
   * Path of the file in file tree.
   */
  private $filePath;

  /**
   * @var Findings $findings
   * Findings of the file.
   */
  private $findings;

  /**
   * @var string $clearing_status
   * Clearing status of the file.
   */
  private $clearing_status;

  /**
   * @param string|null $filePath
   * @param Findings|null $findings
   * @param string|null $clearing_status
   */
  public function __construct(string $filePath = null, Findings $findings = null,
                              string $clearing_status = null)
  {
    $this->setFilePath($filePath);
    $this->setFindings($findings);
    $this->setClearingStatus($clearing_status);
  }

  /**
   * @return string|null
   */
  public function getFilePath()
  {
    return $this->filePath;
  }

  /**
   * @param string $filePath
   * @return FileLicenses
   */
  public function setFilePath($filePath): FileLicenses
  {
    $this->filePath = $filePath;
    return $this;
  }

  /**
   * @return Findings|null
   */
  public function getFindings()
  {
    return $this->findings;
  }

  /**
   * @param Findings $findings
   * @return FileLicenses
   */
  public function setFindings($findings): FileLicenses
  {
    $this->findings = $findings;
    return $this;
  }

  /**
   * @return string|null
   */
  public function getClearingStatus()
  {
    return $this->clearing_status;
  }

  /**
   * @param string $clearing_status
   * @return FileLicenses
   */
  public function setClearingStatus($clearing_status): FileLicenses
  {
    $this->clearing_status = $clearing_status;
    return $this;
  }

  /**
   * Get the object as associative array
   *
   * @return array
   */
  public function getArray($apiVersion = ApiVersion::V1)
  {
    if ($apiVersion == ApiVersion::V2) {
      return [
      'filePath'         => $this->getFilePath(),
      'findings'         => $this->getFindings()->getArray(),
      'clearingStatus'   => $this->getClearingStatus()
      ];
    } else {
      return [
      'filePath'         => $this->getFilePath(),
      'findings'         => $this->getFindings()->getArray(),
      'clearing_status'  => $this->getClearingStatus()
      ];
    }
  }
}
