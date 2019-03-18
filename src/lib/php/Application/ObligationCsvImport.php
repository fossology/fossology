<?php
/*
Copyright (C) 2014-2017, Siemens AG

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
version 2 as published by the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License along
with this program; if not, write to the Free Software Foundation, Inc.,
51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/

namespace Fossology\Lib\Application;

use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Util\ArrayOperation;

/**
 * @file
 * @brief Helper class for Obligation CSV Import
 */

/**
 * @class ObligationCsvImport
 * @brief Helper class for Obligation CSV Import
 */
class ObligationCsvImport {
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
      'candidatenames'=>array('candidatenames','Associated candidate Licenses')
    );

  /**
   * Constructor
   * @param DbManager $dbManager DB manager to use
   */
  public function __construct(DbManager $dbManager)
  {
    $this->dbManager = $dbManager;
    $this->obligationMap = $GLOBALS['container']->get('businessrules.obligationmap');
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
  public function handleFile($filename)
  {
    if (!is_file($filename) || ($handle = fopen($filename, 'r')) === FALSE) {
      return _('Internal error');
    }
    $cnt = -1;
    $msg = '';
    try
    {
      while(($row = fgetcsv($handle,0,$this->delimiter,$this->enclosure)) !== FALSE) {
        $log = $this->handleCsv($row);
        if (!empty($log))
        {
          $msg .= "$log\n";
        }
        $cnt++;
      }
      $msg .= _('Read csv').(": $cnt ")._('obligations');
    }
    catch(\Exception $e)
    {
      fclose($handle);
      return $msg .= _('Error while parsing file').': '.$e->getMessage();
    }
    fclose($handle);
    return $msg;
  }

  /**
   * Handle a single row read from the CSV. If headrow is not set, then handle
   * current row as head row.
   * @param array $row   Single row from CSV
   * @return string $log Log messages
   */
  private function handleCsv($row)
  {
    if($this->headrow===null)
    {
      $this->headrow = $this->handleHeadCsv($row);
      return 'head okay';
    }

    $mRow = array();
    foreach( array('type','topic','text','classification','modifications','comment','licnames','candidatenames') as $needle){
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
    foreach( array('type','topic','text','classification','modifications','comment','licnames','candidatenames') as $needle){
      $col = ArrayOperation::multiSearch($this->alias[$needle], $row);
      if (false === $col)
      {
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
   * @return number strcmp() diff
   */
  private function compareLicList($exists, $listFromCsv, $candidate, $row)
  {
    $getList = $this->obligationMap->getLicenseList($exists, $candidate);
    $listFromDb = $this->reArrangeString($getList);
    $listFromCsv = $this->reArrangeString($listFromCsv);
    $diff = strcmp($listFromDb, $listFromCsv);
    return $diff;
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
    $string = implode(",", $string);
    return $string;
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
    $listFromCsv = "";
    $msg = "";
    if ($exists !== false)
    {
      $msg = "Obligation topic '$row[topic]' already exists in DB (id=".$exists."),";
      if ( $this->compareLicList($exists, $row['licnames'], false, $row) === 0 ) {
        $msg .=" No Changes in AssociateLicense";
      }
      else {
        $this->clearListFromDb($exists, false);
        if (!empty ($row['licnames'] ) ) {
          $associatedLicenses = $this->AssociateWithLicenses($row['licnames'], $exists, false);
        }
        $msg .=" Updated AssociatedLicense license";
      }
      if($this->compareLicList($exists, $row['candidatenames'], True, $row) === 0) {
        $msg .=" No Changes in CandidateLicense";
      }
      else {
        $this->clearListFromDb($exists, $listFromCsv);
        if(!empty($row['candidatenames'])) {
          $associatedLicenses = $this->AssociateWithLicenses($row['candidatenames'], $exists, True);
        }
        $msg .=" Updated CandidateLicense";
      }
      $this->updateOtherFields($exists, $row);
      return $msg."\n";
    }

    $stmtInsert = __METHOD__.'.insert';
    $dbManager->prepare($stmtInsert,'INSERT INTO obligation_ref (ob_type,ob_topic,ob_text,ob_classification,ob_modifications,ob_comment,ob_md5)'
            . ' VALUES ($1,$2,$3,$4,$5,$6,md5($3)) RETURNING ob_pk');
    $resi = $dbManager->execute($stmtInsert,array($row['type'],$row['topic'],$row['text'],$row['classification'],$row['modifications'],$row['comment']));
    $new = $dbManager->fetchArray($resi);
    $dbManager->freeResult($resi);

    if (!empty($row['licnames'])) {
      $associatedLicenses = $this->AssociateWithLicenses($row['licnames'], $new['ob_pk']);
    }
    if (!empty($row['candidatenames'])) {
      $candidateLicenses = $this->AssociateWithLicenses($row['candidatenames'], $new['ob_pk'], True);
    }

    $message = "License association results for obligation '$row[topic]':\n";
    $message .= "$associatedLicenses";
    $message .= "$candidateLicenses";
    $message .= "Obligation with id=$new[ob_pk] was added successfully.\n";
    return $message;
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
    foreach ($licenses as $license)
    {
      $licId = $this->obligationMap->getIdFromShortname($license, $candidate);
      if ($this->obligationMap->isLicenseAssociated($obPk, $licId, $candidate))
      {
        continue;
      }

      if (!empty($licId))
      {
        $this->obligationMap->associateLicenseWithObligation($obPk, $licId, $candidate);
        if ($associatedLicenses == "")
        {
          $associatedLicenses = "$license";
        } else {
          $associatedLicenses .= ";$license";
        }
      } else {
        $message .= "License $license could not be found in the DB.\n";
      }
    }

    if (!empty($associatedLicenses))
    {
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
   * @param int $exists Obligation key
   * @param array $row  Row from CSV.
   */
  function updateOtherFields($exists, $row)
  {
    $this->dbManager->getSingleRow('UPDATE obligation_ref SET ob_classification=$2, ob_modifications=$3, ob_comment=$4 where ob_pk=$1',
      array($exists, $row['classification'], $row['modifications'], $row['comment']),
      __METHOD__ . '.updateOtherOb');
  }
}
