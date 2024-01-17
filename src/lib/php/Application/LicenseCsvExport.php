<?php
/*
 SPDX-FileCopyrightText: © 2015, 2021 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/


namespace Fossology\Lib\Application;

use Fossology\Lib\BusinessRules\LicenseMap;
use Fossology\Lib\Data\LicenseRef;
use Fossology\Lib\Db\DbManager;

/**
 * @file
 * @brief Helper class to export license list as a CSV from the DB
 */

/**
 * @class LicenseCsvExport
 * @brief Helper class to export license list as a CSV from the DB
 */
class LicenseCsvExport
{
  /** @var DbManager $dbManager
   * DB manager in use */
  protected $dbManager;
  /** @var string $delimiter
   * Delimiter for CSV */
  protected $delimiter = ',';
  /** @var string $enclosure
   * Enclosure for CSV strings */
  protected $enclosure = '"';

  /**
   * Constructor
   * @param DbManager $dbManager DB manager to use.
   */
  public function __construct(DbManager $dbManager)
  {
    $this->dbManager = $dbManager;
  }

  /**
   * @brief Update the delimiter
   * @param string $delimiter New delimiter to use.
   */
  public function setDelimiter($delimiter=',')
  {
    $this->delimiter = substr($delimiter,0,1);
  }

  /**
   * @brief Update the enclosure
   * @param string $enclosure New enclosure to use.
   */
  public function setEnclosure($enclosure='"')
  {
    $this->enclosure = substr($enclosure,0,1);
  }

  /**
   * @brief Create the CSV from the DB
   * @param int $rf Set the license ID to get only one license, set 0 to get all
   * @return string csv
   */
  public function createCsv($rf=0, $allCandidates=false)
  {
    $forAllCandidates = "WHERE marydone = true";
    if ($allCandidates) {
      $forAllCandidates = "";
    }
    $forGroupBy = " GROUP BY rf.rf_shortname, rf.rf_fullname, rf.rf_spdx_id, rf.rf_text, rc.rf_shortname, rr.rf_shortname, rf.rf_url, rf.rf_notes, rf.rf_source, rf.rf_risk, gp.group_name";
    $sql = "WITH marydoneCand AS (
  SELECT * FROM license_candidate
  $forAllCandidates
), allLicenses AS (
SELECT DISTINCT ON(rf_pk) * FROM
  ONLY license_ref
  NATURAL FULL JOIN marydoneCand)
SELECT
  rf.rf_shortname, rf.rf_fullname, rf.rf_spdx_id, rf.rf_text,
  rc.rf_shortname parent_shortname, rr.rf_shortname report_shortname, rf.rf_url,
  rf.rf_notes, rf.rf_source, rf.rf_risk, gp.group_name,
  string_agg(ob_topic, ', ') obligations
FROM allLicenses AS rf
  LEFT OUTER JOIN obligation_map om ON om.rf_fk = rf.rf_pk
  LEFT OUTER JOIN obligation_ref ON ob_fk = ob_pk
  FULL JOIN groups AS gp ON gp.group_pk = rf.group_fk
  LEFT JOIN license_map mc ON mc.rf_fk=rf.rf_pk AND mc.usage=$2
  LEFT JOIN license_ref rc ON mc.rf_parent=rc.rf_pk
  LEFT JOIN license_map mr ON mr.rf_fk=rf.rf_pk AND mr.usage=$3
  LEFT JOIN license_ref rr ON mr.rf_parent=rr.rf_pk
WHERE rf.rf_detector_type=$1";

    $param = array(1, LicenseMap::CONCLUSION, LicenseMap::REPORT);
    if ($rf > 0) {
      $stmt = __METHOD__ . '.rf';
      $param[] = $rf;
      $sql .= ' AND rf.rf_pk = $'.count($param).$forGroupBy;
      $row = $this->dbManager->getSingleRow($sql,$param,$stmt);
      $vars = $row ? array( $row ) : array();
    } else {
      $stmt = __METHOD__;
      $sql .= $forGroupBy . ', rf.rf_pk ORDER BY rf.rf_pk';
      $this->dbManager->prepare($stmt,$sql);
      $res = $this->dbManager->execute($stmt,$param);
      $vars = $this->dbManager->fetchAll( $res );
      $this->dbManager->freeResult($res);
    }
    $out = fopen('php://output', 'w');
    ob_start();
    $head = array(
      'shortname', 'fullname', 'spdx_id', 'text', 'parent_shortname',
      'report_shortname', 'url', 'notes', 'source', 'risk', 'group',
      'obligations');
    fputs($out, $bom =( chr(0xEF) . chr(0xBB) . chr(0xBF) ));
    fputcsv($out, $head, $this->delimiter, $this->enclosure);
    foreach ($vars as $row) {
      $row['rf_spdx_id'] = LicenseRef::convertToSpdxId($row['rf_shortname'],
        $row['rf_spdx_id']);
      if (strlen($row['rf_text']) > LicenseMap::MAX_CHAR_LIMIT) {
        $row['rf_text'] = LicenseMap::TEXT_MAX_CHAR_LIMIT;
      }
      fputcsv($out, $row, $this->delimiter, $this->enclosure);
    }
    $content = ob_get_contents();
    ob_end_clean();
    return $content;
  }
}
