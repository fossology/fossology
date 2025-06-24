<?php
/*
 SPDX-FileCopyrightText: © 2025 Harshit Gandhi <gandhiharshit716@gmail.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Application;
use Fossology\Lib\Auth\Auth;

use Fossology\Lib\Dao\UserDao;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Util\ArrayOperation;
use Exception;

/**
 * @file
 * @brief Import custom text phrases from CSV/JSON
 */

/**
 * @class CustomTextImport
 * @brief Import custom text phrases from CSV/JSON
 */
class CustomTextImport
{
  /** @var DbManager $dbManager
   * DB manager to use */
  protected $dbManager;
  /** @var UserDao $userDao
   * User DAO to use */
  protected $userDao;
  /** @var string $delimiter
   * Delimiter used in CSV */
  protected $delimiter = ',';
  /** @var string $enclosure
   * Enclosure used in CSV */
  protected $enclosure = '"';
  /** @var null|array $headrow
   * Header of CSV */
  protected $headrow = null;
  /** @var array $alias
   * Alias for headers */
  protected $alias = array(
      'text'=>array('text','Text'),
      'acknowledgement'=>array('acknowledgement','Acknowledgement','acknowledgements'),
      'comments'=>array('comments','Comments'),
      'is_active'=>array('is_active','Is Active','active'),
      'created_by'=>array('created_by','Created By','user_name'),
      'group'=>array('group','Group','group_name'),
      'licenses_to_add'=>array('licenses_to_add','Licenses To Add','add_licenses'),
      'licenses_to_remove'=>array('licenses_to_remove','Licenses To Remove','remove_licenses')
      );

  /**
   * Constructor
   * @param DbManager $dbManager DB manager to use
   * @param UserDao $userDao     User Dao to use
   */
  public function __construct(DbManager $dbManager, UserDao $userDao)
  {
    $this->dbManager = $dbManager;
    $this->userDao = $userDao;
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
   * @brief Read the CSV/JSON file and import it.
   * @param string $filename Location of the file.
   * @param string $fileExtension File extension (csv or json)
   * @return string message Error message, if any. Otherwise
   *         `Read file: <count> phrases` on success.
   */
  public function handleFile($filename, $fileExtension)
  {
    if ($fileExtension === 'json') {
      return $this->handleJsonFile($filename);
    } else {
      return $this->handleCsvFile($filename);
    }
  }

  /**
   * @brief Handle JSON file import
   * @param string $filename Location of the JSON file.
   * @return string message
   */
  private function handleJsonFile($filename)
  {
    $content = file_get_contents($filename);
    if ($content === false) {
      return _("Could not read JSON file");
    }

    $data = json_decode($content, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
      return _("Invalid JSON format: ") . json_last_error_msg();
    }

    if (!is_array($data)) {
      return _("JSON file must contain an array of phrases");
    }

    return $this->importPhrases($data);
  }

  /**
   * @brief Handle CSV file import
   * @param string $filename Location of the CSV file.
   * @return string message
   */
  private function handleCsvFile($filename)
  {
    $handle = fopen($filename, 'r');
    if ($handle === false) {
      return _("Could not open CSV file");
    }

    $this->headrow = fgetcsv($handle, 0, $this->delimiter, $this->enclosure);
    if ($this->headrow === false) {
      fclose($handle);
      return _("Could not read CSV header");
    }

    // Strip BOM from the first header column if present
    $bom = chr(0xEF) . chr(0xBB) . chr(0xBF);
    if (isset($this->headrow[0]) && strpos($this->headrow[0], $bom) === 0) {
      $this->headrow[0] = substr($this->headrow[0], 3);
    }

    $data = array();
    $lineNumber = 1;
    while (($row = fgetcsv($handle, 0, $this->delimiter, $this->enclosure)) !== false) {
      $lineNumber++;
      if (count($row) !== count($this->headrow)) {
        fclose($handle);
        return sprintf(_("CSV line %d has %d columns, expected %d"),
                      $lineNumber, count($row), count($this->headrow));
      }

      $data[] = array_combine($this->headrow, $row);
    }
    fclose($handle);

    return $this->importPhrases($data);
  }

  /**
   * @brief Import phrases from data array
   * @param array $data Array of phrase data
   * @return string message
   */
  private function importPhrases($data)
  {
    $imported = 0;
    $errors = array();

    foreach ($data as $index => $phraseData) {
      try {
        $result = $this->importSinglePhrase($phraseData);
        if ($result['success']) {
          $imported++;
        } else {
          $errors[] = sprintf(_("Row %d: %s"), $index + 1, $result['message']);
        }
      } catch (Exception $e) {
        $errors[] = sprintf(_("Row %d: %s"), $index + 1, $e->getMessage());
      }
    }

    $message = sprintf(_("Read file: %d phrases"), $imported);
    if (!empty($errors)) {
      $message .= "\n" . _("Errors:") . "\n" . implode("\n", $errors);
    }

    return $message;
  }

  /**
   * @brief Import a single phrase
   * @param array $phraseData Phrase data
   * @return array Result with success flag and message
   */
  private function importSinglePhrase($phraseData)
  {
    // Map headers to standard names
    $mappedData = $this->mapHeaders($phraseData);

    // Validate required fields
    if (empty($mappedData['text'])) {
      return array('success' => false, 'message' => _("Text is required"));
    }

    // Get current user info
    $userId = Auth::getUserId();
    $groupId = Auth::getGroupId();

    // Check for duplicate text
    $textMd5 = md5($mappedData['text']);
    $existingSql = "SELECT cp_pk FROM custom_phrase WHERE text_md5 = $1";
    $existing = $this->dbManager->getSingleRow($existingSql, array($textMd5), __METHOD__ . '.duplicateCheck');

    if ($existing) {
      return array('success' => false, 'message' => _("Duplicate text already exists"));
    }

    // Insert the phrase
    $insertSql = "INSERT INTO custom_phrase (text, text_md5, acknowledgement, comments, user_fk, group_fk, is_active) 
                  VALUES ($1, $2, $3, $4, $5, $6, $7)";

    $params = array(
      $mappedData['text'],
      $textMd5,
      $mappedData['acknowledgement'] ?? '',
      $mappedData['comments'] ?? '',
      $userId,
      $groupId,
      $this->parseBoolean($mappedData['is_active'] ?? false) ? 'true' : 'false'
    );

    try {
      $cpPk = $this->dbManager->insertPreparedAndReturn(__METHOD__ . '.insertPhrase', $insertSql, $params, 'cp_pk');
      $message = _("Phrase imported successfully");

      $totalAssociated = 0;
      $allFailed = array();
      $allCreated = array();

      // Handle licenses to add
      if (!empty($mappedData['licenses_to_add'])) {
        $licenseResult = $this->associateLicenses($cpPk, $mappedData['licenses_to_add'], false);
        $totalAssociated += $licenseResult['associated'];
        $allFailed = array_merge($allFailed, $licenseResult['failed']);
        $allCreated = array_merge($allCreated, $licenseResult['created']);
      }

      // Handle licenses to remove
      if (!empty($mappedData['licenses_to_remove'])) {
        $licenseResult = $this->associateLicenses($cpPk, $mappedData['licenses_to_remove'], true);
        $totalAssociated += $licenseResult['associated'];
        $allFailed = array_merge($allFailed, $licenseResult['failed']);
        $allCreated = array_merge($allCreated, $licenseResult['created']);
      }

      if (!empty($allCreated)) {
        $message .= ". " . sprintf(_("Created new licenses: %s"), implode(', ', $allCreated));
      }
      if (!empty($allFailed)) {
        $message .= ". " . sprintf(_("Warning: Could not create/find licenses: %s"), implode(', ', $allFailed));
      }
      if ($totalAssociated > 0) {
        $message .= ". " . sprintf(_("Associated %d licenses"), $totalAssociated);
      }

      return array('success' => true, 'message' => $message);
    } catch (Exception $e) {
      error_log("Failed to import phrase: " . $e->getMessage());
      return array('success' => false, 'message' => _("Failed to import phrase: ") . $e->getMessage());
    }
  }

  /**
   * @brief Map CSV headers to standard field names
   * @param array $data Row data
   * @return array Mapped data
   */
  private function mapHeaders($data)
  {
    $mapped = array();

    foreach ($this->alias as $standardName => $aliases) {
      foreach ($aliases as $alias) {
        if (isset($data[$alias])) {
          $mapped[$standardName] = $data[$alias];
          break;
        }
      }
    }

    // Normalize array/pipe-separated values from bulk text export format
    $mapped = $this->normalizeBulkExportValues($mapped);

    return $mapped;
  }

  /**
   * @brief Normalize values from bulk text export format
   *
   * The bulk text export produces arrays (JSON) or pipe-separated strings (CSV)
   * for acknowledgements and comments. This method joins them into single
   * strings suitable for the custom_phrase table.
   * It also restores literal '\\n' escape sequences (produced by the bulk CSV
   * exporter) back to real newlines in the text field.
   *
   * @param array $mapped Mapped data
   * @return array Normalized data
   */
  private function normalizeBulkExportValues($mapped)
  {
    // Join array values to single strings for acknowledgement and comments
    foreach (array('acknowledgement', 'comments') as $field) {
      if (isset($mapped[$field])) {
        if (is_array($mapped[$field])) {
          $mapped[$field] = implode('; ', array_filter($mapped[$field]));
        } elseif (is_string($mapped[$field]) && strpos($mapped[$field], '|') !== false) {
          // Handle pipe-separated values from bulk CSV export
          $parts = array_map('trim', explode('|', $mapped[$field]));
          $mapped[$field] = implode('; ', array_filter($parts));
        }
      }
    }

    // Restore literal '\n' escape sequences back to real newlines in text
    if (isset($mapped['text']) && is_string($mapped['text'])) {
      $mapped['text'] = str_replace('\\n', "\n", $mapped['text']);
    }

    return $mapped;
  }

  /**
   * @brief Parse boolean value from string
   * @param string $value String value
   * @return bool Boolean value
   */
  private function parseBoolean($value)
  {
    if (is_bool($value)) {
      return $value;
    }

    $value = strtolower(trim($value));
    return in_array($value, array('true', '1', 'yes', 'on', 'active'));
  }

  /**
   * @brief Normalize license name for lookup
   * @param string $licenseName License name to normalize
   * @return string Normalized license name
   */
  private function normalizeLicenseName($licenseName)
  {
    // Trim whitespace
    $licenseName = trim($licenseName);

    // Handle common variations
    $variations = array(
      'GPL-2.0' => array('GPL-2.0-only', 'GPL-2.0+', 'GPL-2.0-or-later'),
      'GPL-3.0' => array('GPL-3.0-only', 'GPL-3.0+', 'GPL-3.0-or-later'),
      'LGPL-2.1' => array('LGPL-2.1-only', 'LGPL-2.1+', 'LGPL-2.1-or-later'),
      'LGPL-3.0' => array('LGPL-3.0-only', 'LGPL-3.0+', 'LGPL-3.0-or-later'),
      'MIT' => array('MIT License', 'MIT-License'),
      'Apache-2.0' => array('Apache License 2.0', 'Apache-2.0-only'),
      'BSD-3-Clause' => array('BSD-3-Clause License', 'BSD-3-Clause-only'),
      'MPL-2.0' => array('Mozilla Public License 2.0', 'MPL-2.0-only'),
      'EPL-1.0' => array('Eclipse Public License 1.0', 'EPL-1.0-only'),
      'AGPL-3.0' => array('AGPL-3.0-only', 'AGPL-3.0+', 'AGPL-3.0-or-later')
    );

    // Check if the license name matches any variations
    foreach ($variations as $standard => $variants) {
      if (in_array($licenseName, $variants)) {
        return $standard;
      }
    }

    return $licenseName;
  }


  private function associateLicenses($cpPk, $licenseNames, $removing = false)
  {
    if (is_array($licenseNames)) {
      $licenseArray = $licenseNames;
    } else {
      // Handle multiple possible separators: comma-space, comma, semicolon, pipe
      $separators = array(', ', ',', ';', '|');
      $licenseArray = array($licenseNames); // Default to single license

      foreach ($separators as $separator) {
        if (strpos($licenseNames, $separator) !== false) {
          $licenseArray = array_map('trim', explode($separator, $licenseNames));
          break;
        }
      }
    }

    $associatedCount = 0;
    $failedLicenses = array();
    $createdLicenses = array();

    // Get LicenseDao for proper license lookups
    $licenseDao = $GLOBALS['container']->get('dao.license');

    foreach ($licenseArray as $licenseName) {
      if (empty($licenseName)) {
        continue;
      }

      // Normalize license name
      $normalizedLicenseName = $this->normalizeLicenseName($licenseName);

      // Find license using LicenseDao to avoid prepared statement conflicts
      $license = $licenseDao->getLicenseByShortName($normalizedLicenseName);

      if (!$license) {
        // License not found in DB - validate and auto-create it
        if (!$this->isValidLicenseShortname($normalizedLicenseName)) {
          $failedLicenses[] = $licenseName . " (invalid shortname)";
          continue;
        }

        try {
          $newLicenseId = $licenseDao->insertLicense(
            $normalizedLicenseName,  // rf_shortname
            '',                      // rf_text (empty, only shortname available)
            null                     // rf_spdx_id
          );
          $license = $licenseDao->getLicenseById($newLicenseId);
          $createdLicenses[] = $normalizedLicenseName;
          error_log("Auto-created license '$normalizedLicenseName' (ID: $newLicenseId) during custom text import");
        } catch (Exception $e) {
          error_log("Failed to create license '$licenseName': " . $e->getMessage());
          $failedLicenses[] = $licenseName . " (creation failed)";
          continue;
        }
      }

      if ($license) {
        $licenseId = $license->getId();

        // Check if association already exists
        $checkSql = "SELECT 1 FROM custom_phrase_license_map WHERE cp_fk = $1 AND rf_fk = $2 LIMIT 1";
        $existing = $this->dbManager->getSingleRow($checkSql, array($cpPk, $licenseId),
                                                  __METHOD__ . '.check.' . $cpPk . '.' . $licenseId);

        if (!$existing) {
          // Insert the license association with removing flag
          $insertData = array(
            'cp_fk' => $cpPk,
            'rf_fk' => $licenseId,
            'removing' => $removing ? 'true' : 'false'
          );

          try {
            $this->dbManager->insertTableRow('custom_phrase_license_map', $insertData);
            $associatedCount++;
          } catch (Exception $e) {
            error_log("Failed to insert license association: " . $e->getMessage());
            $failedLicenses[] = $licenseName . " (insert failed)";
          }
        } else {
          $associatedCount++; // Already exists, count as successful
        }
      }
    }

    // Log results for debugging
    if (!empty($createdLicenses)) {
      error_log("Auto-created licenses during import for phrase ID $cpPk: " . implode(', ', $createdLicenses));
    }
    if (!empty($failedLicenses)) {
      error_log("Failed licenses during import for phrase ID $cpPk: " . implode(', ', $failedLicenses));
    }
    if ($associatedCount > 0) {
      error_log("Successfully associated $associatedCount licenses for phrase ID $cpPk");
    }

    return array('associated' => $associatedCount, 'failed' => $failedLicenses, 'created' => $createdLicenses);
  }

  /**
   * @brief Validate a license shortname before auto-creating it
   * @param string $shortname License shortname to validate
   * @return bool True if valid, false otherwise
   */
  private function isValidLicenseShortname($shortname)
  {
    // Must not be empty
    if (empty(trim($shortname))) {
      return false;
    }

    // Must not exceed 256 characters
    if (strlen($shortname) > 256) {
      return false;
    }

    // Must not contain control characters (except spaces)
    if (preg_match('/[\x00-\x1F\x7F]/', $shortname)) {
      return false;
    }

    return true;
  }

  /**
   * @brief Import JSON data directly
   * @param array $data JSON data array
   * @param string $msg Reference to message string
   * @return string Result message
   */
  public function importJsonData($data, &$msg)
  {
    return $this->importPhrases($data);
  }
}
