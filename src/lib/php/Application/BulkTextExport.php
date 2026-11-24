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
  public function exportBulkText($user_pk=0, $group_pk=0, $generateJson=false, $includeLicenseText=false)
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

    $licenseTextColumn = $includeLicenseText ? ",\n              lr.rf_text AS license_text" : "";

    // Subquery picks the latest lrb_pk per unique rf_text so duplicates keep only the newest entry.
    $sql = "SELECT
              lrb.rf_text,
              lr.rf_shortname,
              lsb.removing,
              lsb.comment,
              lsb.reportinfo,
              lsb.acknowledgement,
              lr.rf_active$licenseTextColumn
            FROM (
              SELECT rf_text, MAX(lrb_pk) AS lrb_pk
              FROM license_ref_bulk
              $whereClause
              GROUP BY rf_text
            ) latest
            JOIN license_ref_bulk lrb ON lrb.lrb_pk = latest.lrb_pk
            LEFT JOIN license_set_bulk lsb ON lsb.lrb_fk = lrb.lrb_pk
            LEFT JOIN license_ref lr ON lr.rf_pk = lsb.rf_fk
            ORDER BY lrb.rf_text, lr.rf_shortname";

    $result = $this->dbManager->getRows($sql, $params);

    if ($generateJson) {
      return $this->createJson($result, $includeLicenseText);
    } else {
      return $this->createCsvContent($result, $includeLicenseText);
    }
  }

  /**
   * @brief Group flat DB rows by rf_text into the nested licenses format.
   * @param array $result Database result array
   * @param bool $includeLicenseText Whether to include rf_text from license_ref
   * @return array keyed by rf_text
   */
  private function groupResultsByText($result, $includeLicenseText=false)
  {
    $grouped = array();
    foreach ($result as $row) {
      $text = $row['rf_text'] ?: '';
      if (!isset($grouped[$text])) {
        $grouped[$text] = array(
          'licenses' => array(),
          'is_active_values' => array()
        );
      }

      if (!empty($row['rf_shortname'])) {
        $removing = ($row['removing'] === 't' || $row['removing'] === true);
        $license = array(
          'shortname' => $row['rf_shortname'],
          'removing' => $removing,
          'comment' => $row['comment'] ?: '',
          'reportinfo' => $row['reportinfo'] ?: '',
          'acknowledgement' => $row['acknowledgement'] ?: ''
        );
        if ($includeLicenseText && !empty($row['license_text'])) {
          $license['license_text'] = $row['license_text'];
        }
        $grouped[$text]['licenses'][] = $license;
      }

      if ($row['rf_active'] !== null) {
        $isActive = ($row['rf_active'] === 't' || $row['rf_active'] === true);
        $grouped[$text]['is_active_values'][] = $isActive;
      }
    }

    foreach ($grouped as $text => $values) {
      $grouped[$text]['is_active'] = empty($values['is_active_values'])
        ? null
        : !in_array(false, $values['is_active_values'], true);
      unset($grouped[$text]['is_active_values']);
    }

    return $grouped;
  }

  /**
   * @brief Create CSV content, one row per text+license, compatible with CustomTextImport.
   * @param array $result Database result array
   * @param bool $includeLicenseText Whether to include rf_text from license_ref
   * @return string CSV content
   */
  private function createCsvContent($result, $includeLicenseText=false)
  {
    $headers = array(
      'Text', 'Is Active', 'License Shortname', 'Removing',
      'Comment', 'License Text', 'Acknowledgement'
    );
    if ($includeLicenseText) {
      $headers[] = 'license_ref_text';
    }

    $out = fopen('php://output', 'w');
    ob_start();
    fputs($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
    fputcsv($out, $headers, $this->delimiter, $this->enclosure);

    foreach ($this->groupResultsByText($result, $includeLicenseText) as $text => $entry) {
      $isActive = $entry['is_active'] === null ? '' : ($entry['is_active'] ? 'true' : 'false');

      if (empty($entry['licenses'])) {
        $row = array(
          $this->normalizeNewlinesForCsv($text),
          $isActive, '', '', '', '', ''
        );
        if ($includeLicenseText) {
          $row[] = '';
        }
        fputcsv($out, $row, $this->delimiter, $this->enclosure);
      } else {
        foreach ($entry['licenses'] as $lic) {
          $row = array(
            $this->normalizeNewlinesForCsv($text),
            $isActive,
            $lic['shortname'],
            $lic['removing'] ? 'true' : 'false',
            $this->normalizeNewlinesForCsv($lic['comment']),
            $this->normalizeNewlinesForCsv($lic['reportinfo']),
            $this->normalizeNewlinesForCsv($lic['acknowledgement'])
          );
          if ($includeLicenseText) {
            $row[] = $this->normalizeNewlinesForCsv($lic['license_text'] ?? '');
          }
          fputcsv($out, $row, $this->delimiter, $this->enclosure);
        }
      }
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
    return CustomTextEscaping::escapeNewlines($value);
  }

  /**
   * @brief Create JSON content, nested licenses format, compatible with CustomTextImport.
   * @param array $result Database result array
   * @param bool $includeLicenseText Whether to include rf_text from license_ref
   * @return string JSON content
   */
  private function createJson($result, $includeLicenseText=false)
  {
    $data = array();

    foreach ($this->groupResultsByText($result, $includeLicenseText) as $text => $entry) {
      $licenses = array_map(function($lic) use ($includeLicenseText) {
        $out = array(
          'shortname' => $lic['shortname'],
          'removing' => $lic['removing'],
          'comment' => $lic['comment'],
          'reportinfo' => $lic['reportinfo'],
          'acknowledgement' => $lic['acknowledgement']
        );
        if ($includeLicenseText) {
          $out['license_ref_text'] = $lic['license_text'] ?? '';
        }
        return $out;
      }, $entry['licenses']);

      $data[] = array(
        'text' => $text,
        'is_active' => $entry['is_active'],
        'licenses' => $licenses
      );
    }

    return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
  }
}