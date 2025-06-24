<?php
/*
 SPDX-FileCopyrightText: © 2025 Harshit Gandhi <gandhiharshit716@gmail.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Application;

use Fossology\Lib\Db\DbManager;

/**
 * @file
 * @brief Helper class to export custom text phrases as CSV from the DB
 */

/**
 * @class CustomTextCsvExport
 * @brief Helper class to export custom text phrases as CSV from the DB
 */
class CustomTextCsvExport
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
   * @param int $cp_pk Set the custom phrase ID to get only one phrase, set 0 to get all
   * @param bool $generateJson Whether to generate JSON format instead of CSV
   * @return string csv or json content
   */
  public function createCsv($cp_pk=0, $generateJson=false)
  {
    $whereClause = "";
    $params = array();

    if ($cp_pk > 0) {
      $whereClause = "WHERE cp.cp_pk = $1";
      $params[] = $cp_pk;
    }

    $sql = "SELECT
              cp.cp_pk,
              cp.text,
              cp.acknowledgement,
              cp.comments,
              cp.created_date,
              cp.is_active,
              u.user_name,
              g.group_name,
              STRING_AGG(CASE WHEN cplm.removing = false THEN lr.rf_shortname END, ', ' ORDER BY lr.rf_shortname) as licenses_to_add,
              STRING_AGG(CASE WHEN cplm.removing = true THEN lr.rf_shortname END, ', ' ORDER BY lr.rf_shortname) as licenses_to_remove
            FROM custom_phrase cp
            LEFT JOIN users u ON cp.user_fk = u.user_pk
            LEFT JOIN groups g ON cp.group_fk = g.group_pk
            LEFT JOIN custom_phrase_license_map cplm ON cp.cp_pk = cplm.cp_fk
            LEFT JOIN license_ref lr ON cplm.rf_fk = lr.rf_pk
            $whereClause
            GROUP BY cp.cp_pk, cp.text, cp.acknowledgement, cp.comments,
                     cp.created_date, cp.is_active, u.user_name, g.group_name
            ORDER BY cp.created_date DESC";

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

    // Add header row
    $headers = array(
      'ID',
      'Text',
      'Acknowledgement',
      'Comments',
      'Created Date',
      'Is Active',
      'Created By',
      'Group',
      'Licenses To Add',
      'Licenses To Remove'
    );
    $csv .= $this->arrayToCsvLine($headers);

    // Add data rows
    foreach ($result as $row) {
      $csvRow = array(
        $row['cp_pk'],
        $row['text'],
        $row['acknowledgement'] ?: '',
        $row['comments'] ?: '',
        $row['created_date'],
        $row['is_active'] ? 'true' : 'false',
        $row['user_name'] ?: '',
        $row['group_name'] ?: '',
        $row['licenses_to_add'] ?: '',
        $row['licenses_to_remove'] ?: ''
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
        'id' => intval($row['cp_pk']),
        'text' => $row['text'],
        'acknowledgement' => $row['acknowledgement'] ?: '',
        'comments' => $row['comments'] ?: '',
        'created_date' => $row['created_date'],
        'is_active' => $row['is_active'] ? true : false,
        'created_by' => $row['user_name'] ?: '',
        'group' => $row['group_name'] ?: '',
        'licenses_to_add' => $row['licenses_to_add'] ? explode(', ', $row['licenses_to_add']) : array(),
        'licenses_to_remove' => $row['licenses_to_remove'] ? explode(', ', $row['licenses_to_remove']) : array()
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

      // Escape the value if it contains delimiter, enclosure, or newline
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
