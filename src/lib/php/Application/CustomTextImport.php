<?php
/*
 SPDX-FileCopyrightText: © 2025 Harshit Gandhi <gandhiharshit716@gmail.com>
 SPDX-FileCopyrightText: © Fossology contributors

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Application;
use Fossology\Lib\Auth\Auth;

use Fossology\Lib\Dao\LicenseDao;
use Fossology\Lib\Dao\UserDao;
use Fossology\Lib\Db\DbManager;
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
  /** @var LicenseDao $licenseDao */
  protected $licenseDao;
  /** @var string $delimiter
   * Delimiter used in CSV */
  protected $delimiter = ',';
  /** @var string $enclosure
   * Enclosure used in CSV */
  protected $enclosure = '"';
  /** @var null|array $headrow
   * Header of CSV */
  protected $headrow = null;
  /**
   * @var bool $unescapeNewlines
   * True for a CSV row, where real newlines were flattened to a literal
   * '\n' on export. JSON is never unescaped: it carries real newlines as-is.
   */
  protected $unescapeNewlines = false;
  /** @var array $alias
   * Alias for CSV headers */
  protected $alias = array(
      'text' => array('text', 'Text'),
      'is_active' => array('is_active', 'Is Active', 'active'),
      'created_by' => array('created_by', 'Created By', 'user_name'),
      'group' => array('group', 'Group', 'group_name'),
      'license_shortname' => array('license_shortname', 'License Shortname'),
      'removing' => array('removing', 'Removing'),
      'comment' => array('comment', 'Comment'),
      'reportinfo' => array('reportinfo', 'License Text'),
      'acknowledgement' => array('acknowledgement', 'Acknowledgement'),
      // legacy flat-format aliases kept for backward compatibility
      'licenses_to_add' => array('licenses_to_add', 'Licenses To Add', 'add_licenses'),
      'licenses_to_remove' => array('licenses_to_remove', 'Licenses To Remove', 'remove_licenses')
      );

  /**
   * @param DbManager $dbManager
   * @param UserDao $userDao
   * @param LicenseDao $licenseDao Falls back to DI container when null.
   */
  public function __construct(DbManager $dbManager, UserDao $userDao, LicenseDao $licenseDao = null)
  {
    $this->dbManager = $dbManager;
    $this->userDao = $userDao;
    $this->licenseDao = $licenseDao ?: $GLOBALS['container']->get('dao.license');
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
    $this->unescapeNewlines = false;

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
    $this->unescapeNewlines = true;

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
    $created = 0;
    $updated = 0;
    $unchanged = 0;
    $errors = array();

    foreach ($data as $index => $phraseData) {
      try {
        $result = $this->importSinglePhrase($phraseData);
        if ($result['success']) {
          if (!empty($result['unchanged'])) {
            $unchanged++;
          } elseif (!empty($result['existing'])) {
            $updated++;
          } else {
            $created++;
          }
        } else {
          $errors[] = sprintf(_("Row %d: %s"), $index + 1, $result['message']);
        }
      } catch (\Throwable $e) {
        $errors[] = sprintf(_("Row %d: %s"), $index + 1, $e->getMessage());
      }
    }

    $parts = array();
    if ($created > 0) {
      $parts[] = sprintf(_("%d phrase(s) created"), $created);
    }
    if ($updated > 0) {
      $parts[] = sprintf(_("%d license(s) added to existing phrase(s)"), $updated);
    }
    if ($unchanged > 0) {
      $parts[] = sprintf(_("%d row(s) already up to date"), $unchanged);
    }
    $message = _("Import complete") . ": " . (empty($parts) ? _("nothing new to import") : implode(', ', $parts));
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
    $textMd5 = md5($mappedData['text']);

    $this->dbManager->begin();
    try {
      // ON CONFLICT avoids a check-then-insert race on concurrent imports.
      // ack/comments left NULL; metadata lives in the license map.
      $insertSql = "INSERT INTO custom_phrase (text, text_md5, user_fk, group_fk, is_active)
                    VALUES ($1, $2, $3, $4, $5)
                    ON CONFLICT (text_md5) DO NOTHING
                    RETURNING cp_pk";
      $params = array(
        $mappedData['text'],
        $textMd5,
        $userId,
        $groupId,
        $this->parseBoolean($mappedData['is_active'] ?? false) ? 'true' : 'false'
      );
      $row = $this->dbManager->getSingleRow($insertSql, $params, __METHOD__ . '.insertPhrase');

      $isNewPhrase = ($row !== false);
      if ($isNewPhrase) {
        $cpPk = intval($row['cp_pk']);
      } else {
        $existing = $this->dbManager->getSingleRow(
          "SELECT cp_pk FROM custom_phrase WHERE text_md5 = $1", array($textMd5),
          __METHOD__ . '.findExisting');
        if ($existing === false) {
          throw new Exception("custom_phrase insert conflicted but no row found for text_md5");
        }
        $cpPk = intval($existing['cp_pk']);
      }

      $totalInserted = 0;
      $allFailed = array();

      if (!empty($mappedData['licenses'])) {
        $result = $this->associateLicensesWithMetadata($cpPk, $mappedData['licenses'], $groupId);
        $totalInserted += $result['inserted'];
        $allFailed = array_merge($allFailed, $result['failed']);
      } elseif ($isNewPhrase) {
        // Backward compat for the old flat licenses_to_add/licenses_to_remove format.
        if (!empty($mappedData['licenses_to_add'])) {
          $r = $this->associateLicenseNames($cpPk, $mappedData['licenses_to_add'], false, $groupId);
          $totalInserted += $r['inserted'];
          $allFailed = array_merge($allFailed, $r['failed']);
        }
        if (!empty($mappedData['licenses_to_remove'])) {
          $r = $this->associateLicenseNames($cpPk, $mappedData['licenses_to_remove'], true, $groupId);
          $totalInserted += $r['inserted'];
          $allFailed = array_merge($allFailed, $r['failed']);
        }
      }

      $this->dbManager->commit();

      if (!$isNewPhrase) {
        // Nothing new is "unchanged", not an error: re-importing the same
        // export must be a safe no-op.
        if ($totalInserted > 0) {
          return array('success' => true, 'existing' => true,
            'message' => sprintf(_("Added %d license(s) to existing phrase"), $totalInserted));
        }
        if (!empty($allFailed)) {
          return array('success' => false,
            'message' => sprintf(_("Duplicate text; could not associate license(s): %s"), implode(', ', $allFailed)));
        }
        return array('success' => true, 'unchanged' => true,
          'message' => _("Phrase already exists, nothing new to add"));
      }

      $message = _("Phrase imported successfully");
      if (!empty($allFailed)) {
        $message .= ". " . sprintf(_("Warning: Could not find license(s): %s"), implode(', ', $allFailed));
      }
      if ($totalInserted > 0) {
        $message .= ". " . sprintf(_("Associated %d license(s)"), $totalInserted);
      }
      return array('success' => true, 'message' => $message);
    } catch (\Throwable $e) {
      $this->dbManager->rollback();
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

    if (isset($data['licenses']) && is_array($data['licenses'])) {
      $mapped['licenses'] = $data['licenses'];
    }

    foreach ($this->alias as $standardName => $aliases) {
      foreach ($aliases as $alias) {
        if (isset($data[$alias])) {
          $mapped[$standardName] = $data[$alias];
          break;
        }
      }
    }

    if (empty($mapped['licenses']) && !empty($mapped['license_shortname'])) {
      $mapped['licenses'] = array(array(
        'shortname' => $mapped['license_shortname'],
        'removing' => $this->parseBoolean($mapped['removing'] ?? false),
        'comment' => $mapped['comment'] ?? '',
        'reportinfo' => $mapped['reportinfo'] ?? '',
        'acknowledgement' => $mapped['acknowledgement'] ?? ''
      ));
    }

    // JSON allows any value type per field; drop what downstream code can't
    // safely trim()/md5() as a string instead of crashing on it.
    if (isset($mapped['text']) && !is_string($mapped['text'])) {
      unset($mapped['text']);
    }
    if (!empty($mapped['licenses']) && is_array($mapped['licenses'])) {
      $mapped['licenses'] = array_values(array_filter($mapped['licenses'], function ($entry) {
        return is_array($entry) && is_string($entry['shortname'] ?? null);
      }));
      foreach ($mapped['licenses'] as &$licenseEntry) {
        foreach (array('comment', 'reportinfo', 'acknowledgement') as $field) {
          if (isset($licenseEntry[$field]) && !is_string($licenseEntry[$field])) {
            $licenseEntry[$field] = '';
          }
        }
      }
      unset($licenseEntry);
    }

    if ($this->unescapeNewlines) {
      if (isset($mapped['text']) && is_string($mapped['text'])) {
        $mapped['text'] = CustomTextEscaping::unescapeNewlines($mapped['text']);
      }
      if (!empty($mapped['licenses']) && is_array($mapped['licenses'])) {
        foreach ($mapped['licenses'] as &$licenseEntry) {
          foreach (array('comment', 'reportinfo', 'acknowledgement') as $field) {
            if (isset($licenseEntry[$field]) && is_string($licenseEntry[$field])) {
              $licenseEntry[$field] = CustomTextEscaping::unescapeNewlines($licenseEntry[$field]);
            }
          }
        }
        unset($licenseEntry);
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
    if (!is_scalar($value)) {
      return false;
    }

    $value = strtolower(trim($value));
    return in_array($value, array('true', '1', 'yes', 'on', 'active'));
  }

  /**
   * @brief Associate licenses with a phrase, writing per-mapping metadata.
   *
   * Looks licenses up by shortname within the importing group, so
   * group-scoped candidate licenses resolve too (see
   * LicenseDao::getLicenseByCondition()). An already-mapped license is
   * counted as 'skipped', never 'inserted', so a caller can tell a real
   * import apart from a no-op re-import of the same file.
   *
   * @param int $cpPk custom phrase PK
   * @param array $licenses entries with shortname, removing, comment, reportinfo, acknowledgement
   * @param int|null $groupId Group to resolve candidate licenses against
   * @return array{inserted:int,skipped:int,failed:string[]}
   */
  private function associateLicensesWithMetadata($cpPk, $licenses, $groupId = null)
  {
    $inserted = 0;
    $skipped = 0;
    $failed = array();

    foreach ($licenses as $entry) {
      $shortname = trim($entry['shortname'] ?? '');
      if ($shortname === '') {
        continue;
      }

      $license = $this->licenseDao->getLicenseByShortName($shortname, $groupId);
      if (!$license) {
        $failed[] = $shortname . " (unknown)";
        continue;
      }

      $licenseId = $license->getId();
      $removing = $this->parseBoolean($entry['removing'] ?? false);

      // Constant statement name: the SQL never varies, so a large import
      // does not create one prepared statement per (cpPk, licenseId) pair.
      $existing = $this->dbManager->getSingleRow(
        "SELECT 1 FROM custom_phrase_license_map WHERE cp_fk = $1 AND rf_fk = $2 LIMIT 1",
        array($cpPk, $licenseId), __METHOD__ . '.checkMapping');
      if ($existing) {
        $skipped++;
        continue;
      }

      $insertData = array(
        'cp_fk' => $cpPk,
        'rf_fk' => $licenseId,
        'removing' => $removing ? 'true' : 'false',
        'comment' => ($entry['comment'] ?? '') ?: null,
        'reportinfo' => ($entry['reportinfo'] ?? '') ?: null,
        'acknowledgement' => ($entry['acknowledgement'] ?? '') ?: null
      );

      try {
        $this->dbManager->insertTableRow('custom_phrase_license_map', $insertData);
        $inserted++;
      } catch (\Throwable $e) {
        $failed[] = $shortname . " (insert failed)";
      }
    }

    return array('inserted' => $inserted, 'skipped' => $skipped, 'failed' => $failed);
  }

  /**
   * @brief Legacy flat-format helper: split a license-name string/array and
   * delegate to associateLicensesWithMetadata() so the two formats share one
   * lookup/insert path.
   * @param int $cpPk custom phrase PK
   * @param string|array $licenseNames Comma/semicolon/pipe separated names, or an array of names
   * @param bool $removing
   * @param int|null $groupId
   * @return array{inserted:int,failed:string[]}
   */
  private function associateLicenseNames($cpPk, $licenseNames, $removing, $groupId = null)
  {
    $names = is_array($licenseNames) ? $licenseNames : $this->splitLicenseNames($licenseNames);

    $entries = array();
    foreach ($names as $name) {
      $name = trim($name);
      if ($name === '') {
        continue;
      }
      $entries[] = array(
        'shortname' => $name,
        'removing' => $removing,
        'comment' => '',
        'reportinfo' => '',
        'acknowledgement' => ''
      );
    }

    $result = $this->associateLicensesWithMetadata($cpPk, $entries, $groupId);
    return array('inserted' => $result['inserted'], 'failed' => $result['failed']);
  }

  /**
   * @brief Split a delimited license-name string on the first separator found.
   * @param string $licenseNames
   * @return string[]
   */
  private function splitLicenseNames($licenseNames)
  {
    foreach (array(', ', ',', ';', '|') as $separator) {
      if (strpos($licenseNames, $separator) !== false) {
        return array_map('trim', explode($separator, $licenseNames));
      }
    }
    return array($licenseNames);
  }

  /**
   * @brief Import JSON data directly
   * @param array $data Decoded JSON array
   * @param string $msg Populated with the result message
   * @return string Result message
   */
  public function importJsonData($data, string &$msg): string
  {
    $this->unescapeNewlines = false;
    $msg = $this->importPhrases($data);
    return $msg;
  }
}
