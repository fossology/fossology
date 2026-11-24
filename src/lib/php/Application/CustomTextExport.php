<?php
/*
 SPDX-FileCopyrightText: © 2025 Harshit Gandhi <gandhiharshit716@gmail.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Application;

use Fossology\Lib\Db\DbManager;

/**
 * @file
 * @brief Helper class to export custom text phrases as CSV/JSON from the DB
 */

/**
 * @class CustomTextExport
 * @brief Helper class to export custom text phrases as CSV/JSON from the DB
 */
class CustomTextExport
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
   * @brief Create the CSV/JSON export from the DB
   * @param int $cp_pk Set the custom phrase ID to get only one phrase, set 0 to get all
   * @param bool $generateJson Whether to generate JSON format instead of CSV
   * @return string csv or json content
   */
  public function export($cp_pk=0, $generateJson=false)
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
              cp.created_date,
              cp.is_active,
              u.user_name,
              g.group_name,
              lr.rf_shortname,
              cplm.removing,
              cplm.comment,
              cplm.reportinfo,
              cplm.acknowledgement
            FROM custom_phrase cp
            LEFT JOIN users u ON cp.user_fk = u.user_pk
            LEFT JOIN groups g ON cp.group_fk = g.group_pk
            LEFT JOIN custom_phrase_license_map cplm ON cp.cp_pk = cplm.cp_fk
            LEFT JOIN license_ref lr ON cplm.rf_fk = lr.rf_pk
            $whereClause
            ORDER BY cp.created_date DESC, cp.cp_pk, lr.rf_shortname";

    $rows = $this->dbManager->getRows($sql, $params);

    $phrases = array();
    foreach ($rows as $row) {
      $cpPk = $row['cp_pk'];
      if (!isset($phrases[$cpPk])) {
        $phrases[$cpPk] = array(
          'text' => $row['text'],
          'created_date' => $row['created_date'],
          // booleanFromDb(), not raw truthiness: Postgres returns 'f' for
          // false, which PHP treats as truthy.
          'is_active' => $this->dbManager->booleanFromDb($row['is_active']),
          'user_name' => $row['user_name'] ?: '',
          'group_name' => $row['group_name'] ?: '',
          'licenses' => array()
        );
      }
      if (!empty($row['rf_shortname'])) {
        $phrases[$cpPk]['licenses'][] = array(
          'shortname' => $row['rf_shortname'],
          'removing' => $this->dbManager->booleanFromDb($row['removing']),
          'comment' => $row['comment'] ?: '',
          'reportinfo' => $row['reportinfo'] ?: '',
          'acknowledgement' => $row['acknowledgement'] ?: ''
        );
      }
    }

    if ($generateJson) {
      return $this->createJson(array_values($phrases));
    } else {
      return $this->createCsvContent(array_values($phrases));
    }
  }

  /**
   * @brief Create CSV content from result array
   * @param array $result Database result array
   * @return string CSV content
   */
  private function createCsvContent($phrases)
  {
    $csv = '';

    $headers = array(
      'Text',
      'Created Date',
      'Is Active',
      'Created By',
      'Group',
      'License Shortname',
      'Removing',
      'Comment',
      'License Text',
      'Acknowledgement'
    );
    $csv .= $this->arrayToCsvLine($headers);

    foreach ($phrases as $phrase) {
      // Same newline-flattening scheme as BulkTextExport, so CustomTextImport
      // can reverse it regardless of which exporter produced the file.
      $text = CustomTextEscaping::escapeNewlines($phrase['text']);

      if (empty($phrase['licenses'])) {
        $csv .= $this->arrayToCsvLine(array(
          $text,
          $phrase['created_date'],
          $phrase['is_active'] ? 'true' : 'false',
          $phrase['user_name'],
          $phrase['group_name'],
          '', '', '', '', ''
        ));
      } else {
        foreach ($phrase['licenses'] as $lic) {
          $csv .= $this->arrayToCsvLine(array(
            $text,
            $phrase['created_date'],
            $phrase['is_active'] ? 'true' : 'false',
            $phrase['user_name'],
            $phrase['group_name'],
            $lic['shortname'],
            $lic['removing'] ? 'true' : 'false',
            CustomTextEscaping::escapeNewlines($lic['comment']),
            CustomTextEscaping::escapeNewlines($lic['reportinfo']),
            CustomTextEscaping::escapeNewlines($lic['acknowledgement'])
          ));
        }
      }
    }

    return $csv;
  }

  /**
   * @brief Create JSON content from result array
   * @param array $result Database result array
   * @return string JSON content
   */
  private function createJson($phrases)
  {
    $data = array();

    foreach ($phrases as $phrase) {
      $data[] = array(
        'text' => $phrase['text'],
        'created_date' => $phrase['created_date'],
        'is_active' => (bool) $phrase['is_active'],
        'created_by' => $phrase['user_name'],
        'group' => $phrase['group_name'],
        'licenses' => array_map(function ($lic) {
          return array(
            'shortname' => $lic['shortname'],
            'removing' => (bool) $lic['removing'],
            'comment' => $lic['comment'],
            'reportinfo' => $lic['reportinfo'],
            'acknowledgement' => $lic['acknowledgement']
          );
        }, $phrase['licenses'])
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
