<?php
/*
 SPDX-FileCopyrightText: © 2019 Sandip Kumar Bhuyan
 Author: Sandip Kumar Bhuyan<sandipbhuyan@gmail.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Dao;

use Fossology\Lib\Data\Tree\ItemTreeBounds;
use Fossology\Lib\Db\DbManager;
use Monolog\Logger;

/**
 * Class SoftwareHeritageDao
 * @package Fossology\Lib\Dao
 */
class SoftwareHeritageDao
{

  const SWH_STATUS_OK = 200;
  const SWH_BAD_REQUEST = 400;
  const SWH_NOT_FOUND = 404;
  const SWH_RATELIMIT_EXCEED = 429;

  /** @var DbManager */
  private $dbManager;
  /** @var Logger */
  private $logger;
  /** @var UploadDao */
  private $uploadDao;

  public function __construct(DbManager $dbManager, Logger $logger,UploadDao $uploadDao)
  {
    $this->dbManager = $dbManager;
    $this->logger = $logger;
    $this->uploadDao = $uploadDao;
  }

  /**
  * @brief Get all the pfile_fk stored in software heritage table
  * @param Integer $uploadId
  * @return array
  */
  public function getSoftwareHeritagePfileFk($uploadId)
  {
    $uploadTreeTableName = $this->uploadDao->getUploadtreeTableName($uploadId);
    $stmt = __METHOD__.$uploadTreeTableName;
    $sql = "SELECT DISTINCT(SWH.pfile_fk) FROM $uploadTreeTableName UT
              INNER JOIN software_heritage SWH ON SWH.pfile_fk = UT.pfile_fk
            WHERE UT.upload_fk = $1";
    return $this->dbManager->getRows($sql,array($uploadId),$stmt);
  }


  /**
  * @brief Store a record of Software Heritage license info in table
  * @param Integer $pfileId
  * @param String  $licenseDetails
  * @return bool
  */
  public function setSoftwareHeritageDetails($pfileId, $licenseDetails, $status)
  {
    if (!empty($this->dbManager->insertTableRow('software_heritage',['pfile_fk' => $pfileId, 'swh_shortnames' => $licenseDetails, 'swh_status' => $status]))) {
        return true;
    }
    return false;
  }

  /**
   * @brief Get a record from Software Heritage schema from the PfileId
   * @param int $pfileId
   * @return array
   */
  public function getSoftwareHetiageRecord($pfileId)
  {
    $stmt = __METHOD__ . "getSoftwareHeritageRecord";
    $row = $this->dbManager->getSingleRow(
      "SELECT swh_shortnames, swh_status FROM software_heritage WHERE pfile_fk = $1",
      array($pfileId), $stmt);
    if (empty($row)) {
      $row = [
        'swh_status' => null,
        'swh_shortnames' => null
      ];
    }
    $img = '<img alt="done" src="images/red.png" class="icon-small"/>';
    if (self::SWH_STATUS_OK == $row['swh_status']) {
      $img = '<img alt="done" src="images/green.png" class="icon-small"/>';
    }
    return ["license" => $row['swh_shortnames'], "img" => $img];
  }

  /**
   * @brief Get aggregated SWH status for all files under a folder
   * @param ItemTreeBounds $itemTreeBounds
   * @return array
   */
  public function getAggregatedSWHRecord(ItemTreeBounds $itemTreeBounds)
  {
    $uploadTreeTableName = $itemTreeBounds->getUploadTreeTableName();
    $uploadId = $itemTreeBounds->getUploadId();
    $left = $itemTreeBounds->getLeft();
    $right = $itemTreeBounds->getRight();

    $stmt = __METHOD__ . $uploadTreeTableName;
    $sql = "SELECT
              COUNT(*) AS total_files,
              COUNT(SWH.pfile_fk) AS checked_files,
              SUM(CASE WHEN SWH.swh_status = $1 THEN 1 ELSE 0 END) AS ok_files
            FROM $uploadTreeTableName UT
            LEFT JOIN software_heritage SWH ON SWH.pfile_fk = UT.pfile_fk
            WHERE UT.upload_fk = $2
              AND UT.lft BETWEEN $3 AND $4
              AND UT.ufile_mode & (1<<29) = 0";

    $row = $this->dbManager->getSingleRow($sql, array(self::SWH_STATUS_OK, $uploadId, $left, $right), $stmt);

    $totalFiles = intval($row['total_files']);
    $checkedFiles = intval($row['checked_files']);
    $okFiles = intval($row['ok_files']);

    if ($totalFiles == 0) {
      return ["license" => null, "img" => ""];
    }

    $licenseNames = [];
    if ($checkedFiles > 0) {
      $licenseStmt = __METHOD__ . 'licenses' . $uploadTreeTableName;
      $licenseSql = "SELECT DISTINCT SWH.swh_shortnames
                     FROM $uploadTreeTableName UT
                     INNER JOIN software_heritage SWH ON SWH.pfile_fk = UT.pfile_fk
                     WHERE UT.upload_fk = $1
                       AND UT.lft BETWEEN $2 AND $3
                       AND UT.ufile_mode & (1<<29) = 0
                       AND SWH.swh_shortnames IS NOT NULL
                       AND SWH.swh_shortnames != ''";
      $licenseRows = $this->dbManager->getRows($licenseSql, array($uploadId, $left, $right), $licenseStmt);
      foreach ($licenseRows as $licenseRow) {
        $parts = explode(',', $licenseRow['swh_shortnames']);
        foreach ($parts as $part) {
          $trimmed = trim($part);
          if (!empty($trimmed)) {
            $licenseNames[$trimmed] = true;
          }
        }
      }
    }

    if ($checkedFiles == 0) {
      return [
        "license" => null,
        "img" => '<img alt="pending" src="images/grey.png" class="icon-small"/>'
      ];
    }

    if ($okFiles == $totalFiles) {
      $img = '<img alt="done" src="images/green.png" class="icon-small"/>';
    } else {
      $img = '<img alt="needs-attention" src="images/red.png" class="icon-small"/>';
    }

    $licenseStr = !empty($licenseNames) ? implode(', ', array_keys($licenseNames)) : null;
    return ["license" => $licenseStr, "img" => $img];
  }
}
