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

/**
 * @file
 * @brief Import custom text phrases from CSV/JSON
 */

/**
 * @class CustomTextCsvImport
 * @brief Import custom text phrases from CSV/JSON
 */
class CustomTextCsvImport
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
      'acknowledgement'=>array('acknowledgement','Acknowledgement'),
      'comments'=>array('comments','Comments'),
      'is_active'=>array('is_active','Is Active','active'),
      'created_by'=>array('created_by','Created By','user_name'),
      'group'=>array('group','Group','group_name'),
      'associated_licenses'=>array('associated_licenses','Associated Licenses','license_names','licenses')
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
    $existing = $this->dbManager->getSingleRow($existingSql, array($textMd5));
    
    if ($existing) {
      return array('success' => false, 'message' => _("Duplicate text already exists"));
    }

    // Insert the phrase
    $insertSql = "INSERT INTO custom_phrase (text, text_md5, acknowledgement, comments, user_fk, group_fk, is_active) 
                  VALUES ($1, $2, $3, $4, $5, $6, $7) RETURNING cp_pk";
    
    $params = array(
      $mappedData['text'],
      $textMd5,
      $mappedData['acknowledgement'] ?: null,
      $mappedData['comments'] ?: null,
      $userId,
      $groupId,
      $this->parseBoolean($mappedData['is_active'])
    );

    $result = $this->dbManager->getSingleRow($insertSql, $params);
    $cpPk = $result['cp_pk'];

    // Handle associated licenses
    if (!empty($mappedData['associated_licenses'])) {
      $this->associateLicenses($cpPk, $mappedData['associated_licenses']);
    }

    return array('success' => true, 'message' => _("Phrase imported successfully"));
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
   * @brief Associate licenses with a phrase
   * @param int $cpPk Custom phrase ID
   * @param string $licenseNames Comma-separated license names
   */
  private function associateLicenses($cpPk, $licenseNames)
  {
    if (is_array($licenseNames)) {
      $licenseArray = $licenseNames;
    } else {
      $licenseArray = array_map('trim', explode(',', $licenseNames));
    }

    foreach ($licenseArray as $licenseName) {
      if (empty($licenseName)) continue;
      
      // Find license by shortname
      $licenseSql = "SELECT rf_pk FROM license_ref WHERE rf_shortname = $1";
      $license = $this->dbManager->getSingleRow($licenseSql, array($licenseName));
      
      if ($license) {
        // Check if association already exists
        $checkSql = "SELECT cp_pk FROM custom_phrase_license_map WHERE cp_pk = $1 AND rf_pk = $2";
        $existing = $this->dbManager->getSingleRow($checkSql, array($cpPk, $license['rf_pk']));
        
        if (!$existing) {
          $insertSql = "INSERT INTO custom_phrase_license_map (cp_pk, rf_pk) VALUES ($1, $2)";
          $this->dbManager->getSingleRow($insertSql, array($cpPk, $license['rf_pk']));
        }
      }
    }
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
