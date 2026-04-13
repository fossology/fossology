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
    if (!is_string($delimiter) || strlen($delimiter) !== 1) {
      throw new \InvalidArgumentException("CSV delimiter must be a non-empty single-byte character.");
    }
    if ($delimiter === $this->enclosure) {
      throw new \InvalidArgumentException("CSV delimiter and enclosure must be different characters.");
    }
    $this->delimiter = $delimiter;
  }

  /**
   * @brief Update the enclosure
   * @param string $enclosure New enclosure to use.
   */
  public function setEnclosure($enclosure='"')
  {
    if (!is_string($enclosure) || strlen($enclosure) !== 1) {
      throw new \InvalidArgumentException("CSV enclosure must be a non-empty single-byte character.");
    }
    if ($enclosure === $this->delimiter) {
      throw new \InvalidArgumentException("CSV delimiter and enclosure must be different characters.");
    }
    $this->enclosure = $enclosure;
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

    $sql = "SELECT DISTINCT
              lrb.rf_text,
              lr.rf_shortname,
              lsb.removing,
              lsb.comment,
              lsb.acknowledgement,
              lr.rf_active
            FROM license_ref_bulk lrb
            LEFT JOIN license_set_bulk lsb ON lsb.lrb_fk = lrb.lrb_pk
            LEFT JOIN license_ref lr ON lr.rf_pk = lsb.rf_fk
            $whereClause
            ORDER BY lrb.rf_text, lr.rf_shortname";

    $result = $this->dbManager->getRows($sql, $params);

    if ($generateJson) {
      return $this->createJson($result);
    } else {
      return $this->createCsvContent($result);
    }
  }

  /**
   * @brief Group flat DB rows by bulk text, splitting licenses into add/remove buckets
   * @param array $result Database result array
   * @return array Associative array keyed by rf_text with grouped export fields
   */
  private function groupResultsByText($result)
  {
    $grouped = array();
    foreach ($result as $row) {
      $text = $row['rf_text'] ?: '';
      if (!isset($grouped[$text])) {
        $grouped[$text] = array(
          'licenses_to_add'    => array(),
          'licenses_to_remove' => array(),
          'comments'           => array(),
          'acknowledgements'   => array(),
          'is_active_values'   => array()
        );
      }
      if (!empty($row['rf_shortname'])) {
        if ($row['removing'] === 't' || $row['removing'] === true) {
          $grouped[$text]['licenses_to_remove'][] = $row['rf_shortname'];
        } else {
          $grouped[$text]['licenses_to_add'][] = $row['rf_shortname'];
        }
      }

      if (!empty($row['comment'])) {
        $grouped[$text]['comments'][] = $row['comment'];
      }

      if (!empty($row['acknowledgement'])) {
        $grouped[$text]['acknowledgements'][] = $row['acknowledgement'];
      }

      if ($row['rf_active'] !== null) {
        $isActive = ($row['rf_active'] === 't' || $row['rf_active'] === true ||
          $row['rf_active'] === 1 || $row['rf_active'] === '1');
        $grouped[$text]['is_active_values'][] = $isActive;
      }
    }

    foreach ($grouped as $text => $values) {
      $grouped[$text]['licenses_to_add'] = array_values(array_unique($values['licenses_to_add']));
      $grouped[$text]['licenses_to_remove'] = array_values(array_unique($values['licenses_to_remove']));
      $grouped[$text]['comments'] = array_values(array_unique($values['comments']));
      $grouped[$text]['acknowledgements'] = array_values(array_unique($values['acknowledgements']));

      if (empty($values['is_active_values'])) {
        $grouped[$text]['is_active'] = null;
      } else {
        $grouped[$text]['is_active'] = !in_array(false, $values['is_active_values'], true);
      }

      unset($grouped[$text]['is_active_values']);
    }

    return $grouped;
  }

  /**
   * @brief Create CSV content from result array
   * @param array $result Database result array
   * @return string CSV content
   */
  private function createCsvContent($result)
  {
    $headers = array('text', 'licenses_to_add', 'licenses_to_remove', 'comments', 'acknowledgements', 'is_active');
    $out = fopen('php://output', 'w');
    ob_start();
    fputs($out, $bom =( chr(0xEF) . chr(0xBB) . chr(0xBF) ));
    fputcsv($out, $headers, $this->delimiter, $this->enclosure);

    foreach ($this->groupResultsByText($result) as $text => $licenses) {
      $csvRow = array(
        $this->normalizeNewlinesForCsv($text),
        implode('|', array_map(array($this, 'normalizeNewlinesForCsv'), $licenses['licenses_to_add'])),
        implode('|', array_map(array($this, 'normalizeNewlinesForCsv'), $licenses['licenses_to_remove'])),
        implode('|', array_map(array($this, 'normalizeNewlinesForCsv'), $licenses['comments'])),
        implode('|', array_map(array($this, 'normalizeNewlinesForCsv'), $licenses['acknowledgements'])),
        $licenses['is_active'] === null ? '' : ($licenses['is_active'] ? 'true' : 'false')
      );
      fputcsv($out, $csvRow, $this->delimiter, $this->enclosure);
    }

    $content = ob_get_contents();
    ob_end_clean();
    return $content;
  }

  /**
   * @brief Convert CR/LF variants to literal \n for line-safe CSV rows.
   * @param string|null $value Value to normalize.
   * @return string
   */
  private function normalizeNewlinesForCsv($value)
  {
    if ($value === null) {
      return '';
    }
    return str_replace(array("\r\n", "\r", "\n"), '\\n', (string)$value);
  }

  /**
   * @brief Create JSON content from result array
   * @param array $result Database result array
   * @return string JSON content
   */
  private function createJson($result)
  {
    $data = array();

    foreach ($this->groupResultsByText($result) as $text => $licenses) {
      $data[] = array(
        'text'               => $text,
        'licenses_to_add'    => $licenses['licenses_to_add'],
        'licenses_to_remove' => $licenses['licenses_to_remove'],
        'comments'           => $licenses['comments'],
        'acknowledgements'   => $licenses['acknowledgements'],
        'is_active'          => $licenses['is_active']
      );
    }

    return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
  }
}