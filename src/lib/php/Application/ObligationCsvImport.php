<?php
/*
 SPDX-FileCopyrightText: © 2014-2017 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Application;

use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Util\ArrayOperation;
use Fossology\Lib\BusinessRules\ObligationMap;

/**
 * @file
 * @brief Helper class for Obligation CSV Import
 */

/**
 * @class ObligationCsvImport
 * @brief Helper class for Obligation CSV Import
 */
class ObligationCsvImport
{
  /** @var DbManager $dbManager
   * DB manager to be used */
  protected $dbManager;
  /** @var string $delimiter
   * Delimiter used in the CSV */
  protected $delimiter = ',';
  /** @var string $enclosure
   * Ecnlosure used in the CSV */
  protected $enclosure = '"';
  /** @var null|array $headrow
   * Header of CSV */
  protected $headrow = null;
  /** @var array $alias
   * Alias for headers */
  protected $alias = array(
      'type'=>array('type','Type'),
      'topic'=>array('topic','Obligation or Risk topic'),
      'text'=>array('text','Full Text'),
      'classification'=>array('classification','Classification'),
      'modifications'=>array('modifications','Apply on modified source code'),
      'comment'=>array('comment','Comment'),
      'licnames'=>array('licnames','Associated Licenses'),
      'candidatenames'=>array('candidatenames','Associated candidate Licenses'),
      'external_id'=>array('id','LicenseDB Id'),
    );

  /** @var ObligationMap $obligationMap
   * obligation map to use */
  protected $obligationMap;

  /**
   * Constructor
   * @param DbManager $dbManager DB manager to use
   */
  public function __construct(DbManager $dbManager, $obligationMap = null)
  {
    $this->dbManager = $dbManager;
    $this->obligationMap = $obligationMap;
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
   * @brief Read the CSV line by line and import it.
   * @param string $filename Location of the CSV file.
   * @return string message Error message, if any. Otherwise
   *         `Read csv: <count> licenses` on success.
   */
  public function handleFile($filename, $fileExtension)
  {
    if (!is_file($filename) || ($handle = fopen($filename, 'r')) === false) {
      return _('Internal error');
    }
    $cnt = -1;
    $msg = '';
    try {
      if ($fileExtension == 'csv') {
        while (($row = fgetcsv($handle,0,$this->delimiter,$this->enclosure)) !== false) {
          $log = $this->handleCsv($row);
          if (!empty($log)) {
            $msg .= "$log\n";
          }
          $cnt++;
        }
        $msg .= _('Read csv').(": $cnt ")._('obligations');
      } else {
        $jsonContent = fread($handle, filesize($filename));
        $data = json_decode($jsonContent, true);
        if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
          $msg .= "Error decoding JSON: " . json_last_error_msg() . "\n";
        }
        $msg = $this->importJsonData($data, $msg);
        $msg .= _('Read json').(":". count($data) ." ")._('obligations');
      }
    } catch(\Exception $e) {
      fclose($handle);
      return $msg .= _('Error while parsing file').': '.$e->getMessage();
    }
    fclose($handle);
    return $msg;
  }

  /**
   * Handle a single row read from the JSON.
   * If the key matches values from alias array then replace it with key
   * @param array $row
   * @return array $newArray
   */
  function handleRowJson($row)
  {
    $newArray = array();
    foreach ($row as $key => $value) {
      $newKey = $key;
      foreach ($this->alias as $aliasKey => $aliasValues) {
        if (in_array($key, $aliasValues)) {
          $newKey = $aliasKey;
          break;
        }
      }
      $newArray[$newKey] = $value;
    }
    return $newArray;
  }

  /**
   * Handle a single row read from the CSV. If headrow is not set, then handle
   * current row as head row.
   * @param array $row   Single row from CSV
   * @return string $log Log messages
   */
  private function handleCsv($row)
  {
    if ($this->headrow===null) {
      $this->headrow = $this->handleHeadCsv($row);
      return 'head okay';
    }

    $mRow = array();
    foreach (array('type','topic','text','classification','modifications','comment','licnames','candidatenames') as $needle) {
      $mRow[$needle] = $row[$this->headrow[$needle]];
    }

    return $this->handleCsvObligation($mRow);
  }

  /**
   * @brief Handle a row as head row.
   * @param array $row  Head row to be handled.
   * @throws \Exception
   * @return boolean[]|mixed[] Parsed head row.
   */
  private function handleHeadCsv($row)
  {
    $headrow = array();
    foreach (array('type','topic','text','classification','modifications','comment','licnames','candidatenames') as $needle) {
      $col = ArrayOperation::multiSearch($this->alias[$needle], $row);
      if (false === $col) {
        throw new \Exception("Undetermined position of $needle");
      }
      $headrow[$needle] = $col;
    }
    return $headrow;
  }

  /**
   * @brief Get the Obligation key from obligation topic and obligation text
   * @param array $row CSV array with `topic` and `text` keys
   * @return boolean|int False if not found, key otherwise.
   */
  private function getKeyFromTopicAndText($row)
  {
    $req = array($row['topic'], $row['text']);
    $row = $this->dbManager->getSingleRow('SELECT ob_pk FROM obligation_ref WHERE ob_topic=$1 AND ob_md5=md5($2)',$req);
    return ($row === false) ? false : $row['ob_pk'];
  }

  /**
   * @brief Compare licenses from Database and CSV
   * @param bool $exists       Existing license id
   * @param array $listFromCsv List of obligations from CSV
   * @param bool $candidate    Is a candidate obligation?
   * @param array $row         Unused
   * @return int strcmp() diff
   */
  private function compareLicList($exists, $listFromCsv, $candidate, $row)
  {
    $getList = $this->obligationMap->getLicenseList($exists, $candidate);
    $listFromDb = $this->reArrangeString($getList);
    $listFromCsv = $this->reArrangeString($listFromCsv);
    return strcmp($listFromDb, $listFromCsv);
  }

  /**
   * The function takes a string delimited by `;`, explodes it, sort the result
   * and joins them back using `,` as new delimiter.
   * @param string $string String to be rearranged.
   * @return string Rearranged string.
   */
  private function reArrangeString($string)
  {
    $string = explode(";", $string);
    sort($string);
    return implode(",", $string);
  }

  /**
   * @brief Clear all license maps for given obligation
   * @param int $exists     Existing obligation key
   * @param bool $candidate Is a candidate obligation?
   * @return boolean Always true
   */
  private function clearListFromDb($exists, $candidate)
  {
    $licId = 0;
    $this->obligationMap->unassociateLicenseFromObligation($exists, $licId, $candidate);
    return true;
  }

  /**
   * @brief Handle a single row from CSV.
   *
   * The function checks if the obligation text hash is already in the DB, then
   * update the license associations. Otherwise make a new entry in the DB.
   * @param array $row CSV row to be inserted.
   * @return string Log messages.
   */
  private function handleCsvObligation($row)
  {
    /* @var $dbManager DbManager */
    $dbManager = $this->dbManager;
    $exists = $this->getKeyFromTopicAndText($row);
    $associatedLicenses = "";
    $candidateLicenses = "";
    $msg = "";
    if ($exists !== false) {
      $msg = "Obligation topic '$row[topic]' already exists in DB (id=".$exists."),";
      if ( $this->compareLicList($exists, $row['licnames'], false, $row) === 0 ) {
        $msg .=" No Changes in AssociateLicense";
      } else {
        $this->clearListFromDb($exists, false);
        if (!empty($row['licnames'])) {
          $associatedLicenses .= $this->AssociateWithLicenses($row['licnames'], $exists, false);
        }
        $msg .=" Updated AssociatedLicense license";
      }
      if ($this->compareLicList($exists, $row['candidatenames'], true, $row) === 0) {
        $msg .=" No Changes in CandidateLicense";
      } else {
        $this->clearListFromDb($exists, true);
        if (!empty($row['candidatenames'])) {
          $associatedLicenses .= $this->AssociateWithLicenses($row['candidatenames'], $exists, true);
        }
        $msg .=" Updated CandidateLicense";
      }
      $this->updateOtherFields($exists, $row);
      return $msg . "\n" . $associatedLicenses . "\n";
    }

    $stmtInsert = __METHOD__.'.insert';
    $dbManager->prepare($stmtInsert,'INSERT INTO obligation_ref (ob_type,ob_topic,ob_text,ob_classification,ob_modifications,ob_comment,ob_md5)'
            . ' VALUES ($1,$2,$3,$4,$5,$6,md5($3)) RETURNING ob_pk');
    $resi = $dbManager->execute($stmtInsert,array($row['type'],$row['topic'],$row['text'],$row['classification'],$row['modifications'],$row['comment']));
    $new = $dbManager->fetchArray($resi);
    $dbManager->freeResult($resi);

    if (!empty($row['licnames'])) {
      $associatedLicenses .= $this->AssociateWithLicenses($row['licnames'], $new['ob_pk']);
    }
    if (!empty($row['candidatenames'])) {
      $candidateLicenses = $this->AssociateWithLicenses($row['candidatenames'], $new['ob_pk'], true);
    }

    $message = "License association results for obligation '$row[topic]':\n";
    $message .= "$associatedLicenses";
    $message .= "$candidateLicenses";
    $message .= "Obligation with id=$new[ob_pk] was added successfully.\n";
    return $message;
  }

  private function getArrayDiffs(array $oldArray, array $newArray): array
  {
    $add = [];
    $remove = [];

    $oldSet = array_flip($oldArray);
    $newSet = array_flip($newArray);

    foreach ($newArray as $item) {
      if (!isset($oldSet[$item])) {
        $add[] = $item;
      }
    }

    foreach ($oldArray as $item) {
      if (!isset($newSet[$item])) {
        $remove[] = $item;
      }
    }

    return [
      'add' => $add,
      'remove' => $remove,
    ];
  }

  /**
   * @brief Handle a single row from csv import form LicenseDB
   *
   * The function checks for obligation with the row's licensedb id in
   * the obligation_ref table. If found, updates it with the row's content
   * and if not, creates a new one.
   *
   * It also creates the license obligation maps with the licenses
   * that are found in the license_ref table.
   */
  private function handleLicenseDBObligationImport($row)
  {
    if (empty($row["external_id"])) {
      return "Error: external_id cannot be empty";
    }
    $stmt = __METHOD__ . ".getExistingObligation";
    $obPk = $this->dbManager->getSingleRow("SELECT ob_pk FROM " .
      "obligation_ref WHERE ob_external_id=$1", array($row["external_id"]), $stmt);
    $log = '';
    if (!empty($obPk)) {
      // update obligation
      $this->updateOtherFields($obPk['ob_pk'], $row);

      $this->dbManager->begin();
      // fetch new license associations
      $stmt = __METHOD__ . "getNewLicenseAssociations";
      $newLicIds = array();
      foreach ($row['license_ids'] as $licExternalId) {
        $rfPk = $this->dbManager->getSingleRow("SELECT rf_pk FROM license_ref WHERE rf_external_id = $1;",
          array($licExternalId), $stmt);
        if (!empty($rfPk)) {
          $newLicIds[] = $rfPk['rf_pk'];
        } else {
          $log .= \sprintf('license with rf_external_id %s not found', $licExternalId);
        }
      }

      // fetch old license associations
      $stmt = __METHOD__ . "getOldLiceneAssociations";
      $oldLicIdsDb = $this->dbManager->getRows("SELECT rf_fk FROM obligation_map WHERE ob_fk = $1", array($obPk['ob_pk']), $stmt);
      $oldLicIds = array();
      foreach ($oldLicIdsDb as $licId) {
        $oldLicIds[] = $licId['rf_fk'];
      }

      // create diff of licenses between current associated licenses and licensedb
      // associated licenses
      $diff = $this->getArrayDiffs($oldLicIds, $newLicIds);

      // delete associations
      $this->obligationMap->unassociateLicenseFromLicenseList($obPk['ob_pk'], $diff['remove']);

      // insert associations
      $this->obligationMap->associateLicenseFromLicenseList($obPk['ob_pk'], $diff['add']);

      $this->dbManager->commit();
    } else {
      $stmtInsert = __METHOD__.'.insert';
      $this->dbManager->prepare($stmtInsert,'INSERT INTO obligation_ref (ob_type,ob_topic,ob_text,ob_classification,ob_modifications,ob_comment,ob_md5,ob_external_id)'
              . ' VALUES ($1,$2,$3,$4,$5,$6,md5($3),$7) RETURNING ob_pk');
      $resi = $this->dbManager->execute($stmtInsert,array($row['type'],$row['topic'],$row['text'],$row['classification'],'False',$row['comment'], $row['external_id']));
      $new = $this->dbManager->fetchArray($resi);
      $this->dbManager->freeResult($resi);

      $this->dbManager->begin();
      $newLicIds = array();
      $stmt = __METHOD__ . "getNewLicensesForInsert";
      foreach ($row['license_ids'] as $licExternalId) {
        $rfPk = $this->dbManager->getSingleRow("SELECT rf_pk FROM license_ref WHERE rf_external_id = $1;",
          array($licExternalId), $stmt);
        if (!empty($rfPk)) {
          $newLicIds[] = $rfPk['rf_pk'];
        } else {
          $message .= \sprintf('license with rf_external_id %s not found', $licExternalId);
        }
      }

      // insert associations
      $this->obligationMap->associateLicenseFromLicenseList($new['ob_pk'], $newLicIds);

      $this->dbManager->commit();
    }
  }

  /**
   * @brief Associate selected licenses to the obligation
   *
   * @param array   $licList List of licenses to be associated
   * @param int     $obPk The id of the newly created obligation
   * @param boolean $candidate Do we handle candidate licenses?
   * @return string The list of associated licences
   */
  function AssociateWithLicenses($licList, $obPk, $candidate=False)
  {
    $associatedLicenses = "";
    $message = "";

    $licenses = explode(";",$licList);
    foreach ($licenses as $license) {
      $licIds = $this->obligationMap->getIdFromShortname($license, $candidate);
      $updated = false;
      if (empty($licIds)) {
        $message .= "License $license could not be found in the DB.\n";
      } else {
        $updated = $this->obligationMap->associateLicenseFromLicenseList($obPk,
          $licIds, $candidate);
      }
      if ($updated) {
        if ($associatedLicenses == "") {
          $associatedLicenses = "$license";
        } else {
          $associatedLicenses .= ";$license";
        }
      }
    }

    if (!empty($associatedLicenses)) {
      $message .= "$associatedLicenses were associated.\n";
    } else {
      $message .= "No ";
      $message .= $candidate ? "candidate": "";
      $message .= "licenses were associated.\n";
    }
    return $message;
  }

  /**
   * @brief Update other fields of the obligation.
   *
   * Fields updated are:
   * - classification
   * - modifications
   * - comment
   * In case of LicenseDB import,
   * - topic
   * - text
   * - type
   * are also modified
   * @param int $exists Obligation key
   * @param array $row  Row from CSV.
   */
  function updateOtherFields($exists, $row)
  {
    if (!empty($row['external_id'])) {
      $this->dbManager->getSingleRow(
        'UPDATE obligation_ref SET ob_topic=$2, ob_text=$3, ob_classification=$4, ob_modifications=\'False\', ob_comment=$5, ob_type=$6, ob_md5=md5($7) where ob_pk=$1',
        array($exists, $row['topic'], $row['text'], $row['classification'], $row['comment'], $row['type'], $row['text']
        ), __METHOD__ . '.updateOtherOb');
    } else {
      $this->dbManager->getSingleRow('UPDATE obligation_ref SET ob_classification=$2, ob_modifications=$3, ob_comment=$4 where ob_pk=$1',
        array($exists, $row['classification'], $row['modifications'], $row['comment']),
        __METHOD__ . '.updateOtherOb');
    }
  }

  /**
   * @param $data
   * @param string $msg
   * @return string
   */
  public function importJsonData($data, string $msg): string
  {
    foreach ($data as $row) {
      $log = $this->handleLicenseDBObligationImport($this->handleRowJson($row));
      if (!empty($log)) {
        $msg .= "$log\n";
      }
    }
    return $msg;
  }
}
