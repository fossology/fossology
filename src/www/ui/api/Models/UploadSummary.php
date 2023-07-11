<?php
/*
 SPDX-FileCopyrightText: © 2020 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @file
 * @brief Upload summary model
 */
namespace Fossology\UI\Api\Models;

use Fossology\Lib\Data\UploadStatus;

/**
 * @class UploadSummary
 * @brief Model class to hold Upload info
 */
class UploadSummary
{

  /**
   * @var string $mainLicense
   * Main license selected on upload
   */
  private $mainLicense;
  /**
   * @var integer $uniqueLicenses
   * Number of unique licenses found in upload
   */
  private $uniqueLicenses;
  /**
   * @var integer $uploadId
   * Current upload id
   */
  private $uploadId;
  /**
   * @var integer $totalLicenses
   * Total licenses found in upload
   */
  private $totalLicenses;
  /**
   * @var string $uploadName
   * Upload name
   */
  private $uploadName;
  /**
   * @var integer $assignee
   * Upload assignee Id
   */
  private $assignee;
  /**
   * @var integer $uniqueConcludedLicenses
   * No of unique licenses concluded for upload
   */
  private $uniqueConcludedLicenses;
  /**
   * @var integer $totalConcludedLicenses
   * Total licenses concluded for upload
   */
  private $totalConcludedLicenses;
  /**
   * @var integer $filesToBeCleared
   * Files without clearing
   */
  private $filesToBeCleared;
  /**
   * @var integer $filesCleared
   * Files with clearing
   */
  private $filesCleared;
  /**
   * @var UploadStatus $clearingStatus
   * Clearing status
   */
  private $clearingStatus;
  /**
   * @var integer $copyrightCount
   * No of files with copyrights
   */
  private $copyrightCount;
  /**
   * @var integer $concludedNoLicenseFoundCount
   * No of concluded files with no license found
   */
  private $concludedNoLicenseFoundCount;
  /**
   * @var integer $fileCount
   * No of files in upload
   */
  private $fileCount;
  /**
   * @var integer $noScannerLicenseFoundCount
   * No of files with no license found by scanner
   */
  private $noScannerLicenseFoundCount;
  /**
   * @var integer $scannerUniqueLicenseCount
   * No of unique licenses found by scanner
   */
  private $scannerUniqueLicenseCount;

  public function __construct()
  {
    $this->mainLicense = null;
    $this->uniqueLicenses = 0;
    $this->uploadId = null;
    $this->totalLicenses = 0;
    $this->uploadName = null;
    $this->uniqueConcludedLicenses = 0;
    $this->totalConcludedLicenses = 0;
    $this->filesToBeCleared = 0;
    $this->filesCleared = 0;
    $this->clearingStatus = UploadStatus::OPEN;
    $this->copyrightCount = 0;
    $this->assignee = null;
    $this->concludedNoLicenseFoundCount = 0;
    $this->fileCount = 0;
    $this->noScannerLicenseFoundCount = 0;
    $this->scannerUniqueLicenseCount = 0;
  }

  /**
   * Get current upload in JSON representation
   * @return string
   */
  public function getJSON()
  {
    return json_encode($this->getArray());
  }

  /**
   * Get the upload element as an associative array
   * @return array
   */
  public function getArray()
  {
    return [
      "id"                      => $this->uploadId,
      "uploadName"              => $this->uploadName,
      "assignee"                => $this->assignee,
      "mainLicense"             => $this->mainLicense,
      "uniqueLicenses"          => $this->uniqueLicenses,
      "totalLicenses"           => $this->totalLicenses,
      "uniqueConcludedLicenses" => $this->uniqueConcludedLicenses,
      "totalConcludedLicenses"  => $this->totalConcludedLicenses,
      "filesToBeCleared"        => $this->filesToBeCleared,
      "filesCleared"            => $this->filesCleared,
      "clearingStatus"          => self::statusToString($this->clearingStatus),
      "copyrightCount"          => $this->copyrightCount,
      "concludedNoLicenseFoundCount" => $this->concludedNoLicenseFoundCount,
      "fileCount"               => $this->fileCount,
      "noScannerLicenseFoundCount" => $this->noScannerLicenseFoundCount,
      "scannerUniqueLicenseCount" => $this->scannerUniqueLicenseCount
    ];
  }

  /**
   * @param string $mainLicense
   */
  public function setMainLicense($mainLicense)
  {
    $this->mainLicense = $mainLicense;
  }

  /**
   * @param number $uniqueLicenses
   */
  public function setUniqueLicenses($uniqueLicenses)
  {
    $this->uniqueLicenses = intval($uniqueLicenses);
  }

  /**
   * @param number $uploadId
   */
  public function setUploadId($uploadId)
  {
    $this->uploadId = intval($uploadId);
  }

  /**
   * @param number $totalLicenses
   */
  public function setTotalLicenses($totalLicenses)
  {
    $this->totalLicenses = intval($totalLicenses);
  }

  /**
   * @param string $uploadName
   */
  public function setUploadName($uploadName)
  {
    $this->uploadName = $uploadName;
  }

  /**
   * @param integer $assignee
   */
  public function setAssignee($assignee)
  {
    $this->assignee = $assignee == 1 ? null : intval($assignee);
  }

  /**
   * @param number $uniqueConcludedLicenses
   */
  public function setUniqueConcludedLicenses($uniqueConcludedLicenses)
  {
    $this->uniqueConcludedLicenses = intval($uniqueConcludedLicenses);
  }

  /**
   * @param number $totalConcludedLicenses
   */
  public function setTotalConcludedLicenses($totalConcludedLicenses)
  {
    $this->totalConcludedLicenses = intval($totalConcludedLicenses);
  }

  /**
   * @param number $filesToBeCleared
   */
  public function setFilesToBeCleared($filesToBeCleared)
  {
    $this->filesToBeCleared = intval($filesToBeCleared);
  }

  /**
   * @param number $filesCleared
   */
  public function setFilesCleared($filesCleared)
  {
    $this->filesCleared = intval($filesCleared);
  }

  /**
   * @param Fossology::Lib::Data::UploadStatus $clearingStatus
   */
  public function setClearingStatus($clearingStatus)
  {
    $this->clearingStatus = $clearingStatus;
  }

  /**
   * @param number $copyrightCount
   */
  public function setCopyrightCount($copyrightCount)
  {
    $this->copyrightCount = intval($copyrightCount);
  }

  /**
   * @param number $concludedNoLicenseFoundCount
   */
  public function setConcludedNoLicenseFoundCount($concludedNoLicenseFoundCount)
  {
    $this->concludedNoLicenseFoundCount = intval($concludedNoLicenseFoundCount);
  }

  /**
   * @param number $fileCount
   */
  public function setFileCount($fileCount)
  {
    $this->fileCount = intval($fileCount);
  }

  /**
   * @param number $noScannerLicenseFoundCount
   */
  public function setNoScannerLicenseFoundCount($noScannerLicenseFoundCount)
  {
    $this->noScannerLicenseFoundCount = intval($noScannerLicenseFoundCount);
  }

  /**
   * @param number $scannerUniqueLicenseCount
   */
  public function setScannerUniqueLicenseCount($scannerUniqueLicenseCount)
  {
    $this->scannerUniqueLicenseCount = intval($scannerUniqueLicenseCount);
  }

  /**
   * Convert internal clearing status to strings
   * @param Fossology::Lib::Data::UploadStatus $status
   * @return string
   */
  public static function statusToString($status)
  {
    $string = null;
    switch ($status) {
      case UploadStatus::OPEN:
        $string = "Open";
        break;
      case UploadStatus::IN_PROGRESS:
        $string = "InProgress";
        break;
      case UploadStatus::CLOSED:
        $string = "Closed";
        break;
      case UploadStatus::REJECTED:
        $string = "Rejected";
        break;
      default:
        $string = "NA";
    }
    return $string;
  }
}
