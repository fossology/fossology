<?php
/*
 SPDX-FileCopyrightText: © 2020 Siemens AG
 Author: Gaurav Mishra <mishra.gaurav@siemens.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Dao;

use Fossology\Lib\Data\DecisionScopes;
use Fossology\Lib\Data\DecisionTypes;
use Fossology\Lib\Db\DbManager;
use Monolog\Logger;

class PfileDao
{

  /**
   * @var DbManager $dbManager
   * DbManager
   */
  private $dbManager;
  /**
   * @var Logger $logger
   * Logger
   */
  private $logger;
  public function __construct(DbManager $dbManager, Logger $logger)
  {
    $this->dbManager = $dbManager;
    $this->logger = $logger;
  }

  /**
   * Get the pfile row matching given checksums
   *
   * @param string  $sha1   SHA1 checksum
   * @param string  $md5    MD5 checksum
   * @param string  $sha256 SHA256 checksum
   * @param integer $size   Size in bytes
   * @return NULL|array DB row if found, null otherwise
   */
  public function getPfile($sha1 = null, $md5 = null, $sha256 = null,
    $size = null)
  {
    $statement = __METHOD__ . ".getPfileId";
    $sql = "SELECT * FROM pfile WHERE ";
    $params = [];
    $conds = [];
    if (! empty($sha1)) {
      $statement .= ".sha1";
      $params[] = strtoupper($sha1);
      $conds[] = "pfile_sha1 = $" . count($params);
    }
    if (! empty($md5)) {
      $statement .= ".md5";
      $params[] = strtoupper($md5);
      $conds[] = "pfile_md5 = $" . count($params);
    }
    if (! empty($sha256)) {
      $statement .= ".sha256";
      $params[] = strtoupper($sha256);
      $conds[] = "pfile_sha256 = $" . count($params);
    }
    if (! empty($size)) {
      $statement .= ".size";
      $params[] = $size;
      $conds[] = "pfile_size = $" . count($params);
    }
    $sql .= join(" AND ", $conds);
    $row = $this->dbManager->getSingleRow($sql, $params, $statement);
    return (! empty($row)) ? $row : null;
  }

  /**
   * Get the list of licenses scanned for given pfile
   * @param integer $pfileId Pfile to search
   * @return array Unique, sorted array of licenses found
   */
  public function getScannerFindings($pfileId)
  {
    $statement = __METHOD__ . ".getScannerFindings";
    $sql = "SELECT DISTINCT ON(rf_pk) rf_shortname " .
      "FROM license_file AS lf " .
      "INNER JOIN ONLY license_ref AS lr ON lf.rf_fk = lr.rf_pk " .
      "WHERE lf.pfile_fk = $1;";
    $params = [$pfileId];
    $rows = $this->dbManager->getRows($sql, $params, $statement);
    if (! empty($rows) && array_key_exists('rf_shortname', $rows[0])) {
      $licenses = array_column($rows, 'rf_shortname');
      natcasesort($licenses);
      return array_values($licenses);
    } else {
      return [];
    }
  }

  /**
   * Get the list of licenses councluded for given pfile
   *
   * @param integer $groupId Group to filter for
   * @param integer $pfileId Pfile to search for
   * @return array Unique, sorted array of latest license decisions or
   *         value NONE if all the licenses were removed by user, or value
   *         NOASSERTION if no decisions were made
   */
  public function getConclusions($groupId, $pfileId)
  {
    if (! $this->haveConclusions($groupId, $pfileId)) {
      return ["NOASSERTION"];
    }
    $statement = __METHOD__ . ".getScannerFindings";
    $sql = "WITH allDecsPfile AS (
  SELECT cd.pfile_fk, cd.clearing_decision_pk, cd.decision_type,
    lr.rf_shortname, ce.removed
  FROM clearing_decision AS cd
  INNER JOIN clearing_decision_event AS cde
    ON cde.clearing_decision_fk = cd.clearing_decision_pk
  INNER JOIN clearing_event AS ce
    ON cde.clearing_event_fk = ce.clearing_event_pk
  INNER JOIN license_ref AS lr
    ON ce.rf_fk = lr.rf_pk
  WHERE cd.pfile_fk = $1 AND (cd.group_fk = $2 OR cd.scope = " .
    DecisionScopes::REPO . ")
  ORDER BY cd.clearing_decision_pk DESC
),
rankedDecs AS (
  SELECT *, rank() OVER (
    PARTITION BY pfile_fk, rf_shortname ORDER BY clearing_decision_pk DESC
  ) rnk FROM allDecsPfile
)
SELECT * FROM rankedDecs
WHERE rnk = 1 AND removed = false AND decision_type = " .
    DecisionTypes::IDENTIFIED . ";";
    $params = [$pfileId, $groupId];
    $rows = $this->dbManager->getRows($sql, $params, $statement);
    if (! empty($rows) && array_key_exists('rf_shortname', $rows[0])) {
      $licenses = array_column($rows, 'rf_shortname');
      natcasesort($licenses);
      return array_values($licenses);
    } else {
      return ["NONE"];
    }
  }

  /**
   * Get the upload ids where pfile was uploaded as package
   *
   * @param integer $pfileId Pfile to filter
   * @return array|NULL Array of uploads or null if none found
   */
  public function getUploadForPackage($pfileId)
  {
    $statement = __METHOD__ . ".getUploadForPackage";
    $sql = "SELECT upload_pk FROM upload WHERE pfile_fk = $1;";
    $params = [$pfileId];
    $rows = $this->dbManager->getRows($sql, $params, $statement);
    if (! empty($rows) && array_key_exists('upload_pk', $rows[0])) {
      $uploads = array_column($rows, 'upload_pk');
      return array_map(function ($upload) {
          return intval($upload);
      }, $uploads);
    } else {
      return null;
    }
  }

  /**
   * Check if pfile have at least one clearing decision
   *
   * @param integer $groupId Group to filter results for
   * @param integer $pfileId Pfile to check
   * @return boolean True if have at least one decision, false otherwise
   */
  public function haveConclusions($groupId, $pfileId)
  {
    $statement = __METHOD__ . ".pfileHaveConclusions";
    $sql = "SELECT count(*) AS cnt FROM clearing_decision " .
      "WHERE pfile_fk = $1 AND (group_fk = $2 OR scope = " .
      DecisionScopes::REPO . ");";
    $params = [$pfileId, $groupId];
    $row = $this->dbManager->getSingleRow($sql, $params, $statement);
    if (! empty($row['cnt']) && $row['cnt'] > 0) {
      return true;
    }
    return false;
  }

  /**
   * Get the list of copyrights for given pfile
   * @param integer $pfileId Pfile to search
   * @return array Array of copyrights found and not disabled
   */
  public function getCopyright($pfileId)
  {
    $statement = __METHOD__ . ".getCopyright";
    $sql = "SELECT content " .
      "FROM copyright " .
      "WHERE (pfile_fk = $1) AND (is_enabled = TRUE) " .
      "UNION " .
      "SELECT textfinding " .
      "FROM copyright_decision " .
      "WHERE (pfile_fk = $1) AND (is_enabled = TRUE);";
    $params = [$pfileId];
    $rows = $this->dbManager->getRows($sql, $params, $statement);
    if (!empty($rows) && array_key_exists('content', $rows[0])) {
      $copyright = array_column($rows, 'content');
      natcasesort($copyright);
      return array_values($copyright);
    } else {
      return [];
    }
  }
}

