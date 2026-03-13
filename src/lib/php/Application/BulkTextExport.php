<?php
/*
 SPDX-FileCopyrightText: © 2026 Kaushlendra Pratap <kaushlendra-pratap.singh@siemens.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Application;

use Fossology\Lib\Db\DbManager;

/**
 * @file
 * @brief Helper class to export license reference bulk data as CSV or JSON from the DB
 */

/**
 * @class BulkTextExport
 * @brief Helper class to export license reference bulk data as CSV or JSON from the DB
 */
class BulkTextExport
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
   * @brief Export license reference bulk data from the DB as CSV or JSON
   * @param int $user_pk Filter by user ID, set 0 to export all
   * @param int $group_pk Filter by group ID, set 0 to export all
   * @param bool $generateJson Whether to generate JSON format instead of CSV
   * @return string CSV or JSON content
   */
  public function exportBulkText($user_pk=0, $group_pk=0, $generateJson=false)
  {
    $whereClause = "";
    $params = array();

    if ($user_pk > 0) {
      $whereClause = "WHERE lrb.user_fk = $1";
      $params[] = $user_pk;
    } elseif ($group_pk > 0) {
      $whereClause = "WHERE lrb.group_fk = $1";
      $params[] = $group_pk;
    }

    $sql = "SELECT
              lrb.lrb_pk,
              lrb.rf_text
            FROM license_ref_bulk lrb
            $whereClause";

    $result = $this->dbManager->getRows($sql, $params);

    if ($generateJson) {
      return $this->createJson($result);
    } else {
      return $this->createCsvContent($result);
    }
  }

  /**
   * @brief Create CSV content from result array
   * @param array $result Database result array
   * @return string CSV content
   */
  private function createCsvContent($result)
  {
    $csv = '';

    $headers = array(
      'ID',
      'Bulk Text'
    );
    $csv .= $this->arrayToCsvLine($headers);

    foreach ($result as $row) {
      $csvRow = array(
        $row['lrb_pk'],
        $row['rf_text'] ?: ''
      );
      $csv .= $this->arrayToCsvLine($csvRow);
    }

    return $csv;
  }

  /**
   * @brief Create JSON content from result array
   * @param array $result Database result array
   * @return string JSON content
   */
  private function createJson($result)
  {
    $data = array();

    foreach ($result as $row) {
      $data[] = array(
        'id' => intval($row['lrb_pk']),
        'text' => $row['rf_text'] ?: ''
      );
    }

    return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
  }

  /**
   * @brief Convert array to CSV line
   * @param array $array Array to convert
   * @return string CSV line
   */
  private function arrayToCsvLine($array)
  {
    $csvLine = '';
    foreach ($array as $key => $value) {
      if ($key > 0) {
        $csvLine .= $this->delimiter;
      }
      if (strpos($value, $this->delimiter) !== false ||
          strpos($value, $this->enclosure) !== false ||
          strpos($value, "\n") !== false ||
          strpos($value, "\r") !== false) {
        $value = $this->enclosure . str_replace($this->enclosure, $this->enclosure . $this->enclosure, $value) . $this->enclosure;
      }

      $csvLine .= $value;
    }
    $csvLine .= "\n";

    return $csvLine;
  }
}