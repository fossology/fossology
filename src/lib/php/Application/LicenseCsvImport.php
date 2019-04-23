<?php
/*
Copyright (C) 2014-2015, Siemens AG

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

use Fossology\Lib\BusinessRules\LicenseMap;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Util\ArrayOperation;

/**
 * @file
 * @brief Import licenses from CSV
 */

/**
 * @class LicenseCsvImport
 * @brief Import licenses from CSV
 */
class LicenseCsvImport
{
  /** @var DbManager $dbManager
   * DB manager to use */
  protected $dbManager;
  /** @var string $delimiter
   * Delimiter used in CSV */
  protected $delimiter = ',';
  /** @var string $enclosure
   * Enclosure used in CSV */
  protected $enclosure = '"';
  /** @var null|array $headrow
   * Header of CSV */
  protected $headrow = null;
  /** @var array $nkMap
   * Map based on license shortname */
  protected $nkMap = array();
  /** @var array $alias
   * Alias for headers */
  protected $alias = array(
      'shortname'=>array('shortname','Short Name'),
      'fullname'=>array('fullname','Long Name'),
      'text'=>array('text','Full Text'),
      'parent_shortname'=>array('parent_shortname','Decider Short Name'),
      'report_shortname'=>array('report_shortname','Regular License Text Short Name'),
      'url'=>array('url','URL'),
      'notes'=>array('notes'),
      'source'=>array('source','Foreign ID'),
      'risk'=>array('risk','risk_level')
      );

  /**
   * Constructor
   * @param DbManager $dbManager DB manager to use
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
   * @brief Read the CSV line by line and import it.
   * @param string $filename Location of the CSV file.
   * @return string message Error message, if any. Otherwise
   *         `Read csv: <count> licenses` on success.
   */
  public function handleFile($filename)
  {
    if (!is_file($filename) || ($handle = fopen($filename, 'r')) === false) {
      return _('Internal error');
    }
    $cnt = -1;
    $msg = '';
    try {
      while (($row = fgetcsv($handle,0,$this->delimiter,$this->enclosure)) !== false) {
        $log = $this->handleCsv($row);
        if (!empty($log)) {
          $msg .= "$log\n";
        }
        $cnt++;
      }
      $msg .= _('Read csv').(": $cnt ")._('licenses');
    } catch(\Exception $e) {
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
    if ($this->headrow === null) {
      $this->headrow = $this->handleHeadCsv($row);
      return 'head okay';
    }

    $mRow = array();
    foreach (array('shortname','fullname','text') as $needle) {
      $mRow[$needle] = $row[$this->headrow[$needle]];
    }
    foreach (array('parent_shortname'=>null,'report_shortname'=>null,'url'=>'','notes'=>'','source'=>'','risk'=>0) as $optNeedle=>$defaultValue) {
      $mRow[$optNeedle] = $defaultValue;
      if ($this->headrow[$optNeedle]!==false && array_key_exists($this->headrow[$optNeedle], $row)) {
        $mRow[$optNeedle] = $row[$this->headrow[$optNeedle]];
      }
    }

    return $this->handleCsvLicense($mRow);
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
    foreach (array('shortname','fullname','text') as $needle) {
      $col = ArrayOperation::multiSearch($this->alias[$needle], $row);
      if (false === $col) {
        throw new \Exception("Undetermined position of $needle");
      }
      $headrow[$needle] = $col;
    }
    foreach (array('parent_shortname','report_shortname','url','notes','source','risk') as $optNeedle) {
      $headrow[$optNeedle] = ArrayOperation::multiSearch($this->alias[$optNeedle], $row);
    }
    return $headrow;
  }

  /**
   * @brief Update the license info in the DB.
   * @param array $row      Row with new values.
   * @param array $sameText Matched row with old values.
   * @return string Log messages.
   */
  private function updateLicense($row,$sameText)
  {
    $log = "Text of '$row[shortname]' already used for '$sameText[rf_shortname]'";
    $stmt = __METHOD__;
    $sql = "UPDATE license_ref SET ";
    $param = array($sameText['rf_pk']);
    if (!empty($row['source']) && empty($sameText['rf_source'])) {
      $param[] = $row['source'];
      $stmt .= '.updSource';
      $sql .= " rf_source=$".count($param);
      $log .= ', updated the source';
    }
    if (!empty($row['risk']) && $row['risk']!==$sameText['rf_risk']) {
      $param[] = $row['risk'];
      $stmt .= '.updRisk';
      $sql .= count($param)==3 ? ", rf_risk=$".count($param) : "rf_risk=$".count($param);
      $log .= ', updated the risk level';
    }
    if (count($param)>1) {
      $sql .=" WHERE rf_pk=$1";
      $this->dbManager->prepare($stmt, $sql);
      $res = $this->dbManager->execute($stmt,$param);
      $this->dbManager->freeResult($res);
    }
    return $log;
  }

  /**
   * @brief Handle a single row from CSV.
   *
   * The function checks if the license text hash is already in the DB, then
   * updates it. Otherwise inserts new row in the DB.
   * @param array $row CSV row to be inserted.
   * @return string Log messages.
   */
  private function handleCsvLicense($row)
  {
    /* @var $dbManager DbManager */
    $dbManager = $this->dbManager;
    if (empty($row['risk'])) {
      $row['risk'] = 0;
    }
    if ($this->getKeyFromShortname($row['shortname']) !== false) {
      return "Shortname '$row[shortname]' already in DB (id=".$this->getKeyFromShortname($row['shortname']).")";
    }
    $sameText = $dbManager->getSingleRow('SELECT rf_shortname,rf_source,rf_pk,rf_risk FROM license_ref WHERE rf_md5=md5($1)',array($row['text']));
    if ($sameText !== false) {
      return $this->updateLicense($row,$sameText);
    }
    $stmtInsert = __METHOD__.'.insert';
    $dbManager->prepare($stmtInsert,'INSERT INTO license_ref (rf_shortname,rf_fullname,rf_text,rf_md5,rf_detector_type,rf_url,rf_notes,rf_source,rf_risk)'
            . ' VALUES ($1,$2,$3,md5($3),$4,$5,$6,$7,$8) RETURNING rf_pk');
    $resi = $dbManager->execute($stmtInsert,
            array($row['shortname'],$row['fullname'],$row['text'],$userDetected=1,$row['url'],$row['notes'],$row['source'],$row['risk']));
    $new = $dbManager->fetchArray($resi);
    $dbManager->freeResult($resi);
    $this->nkMap[$row['shortname']] = $new['rf_pk'];
    $return = "Inserted '$row[shortname]' in DB";

    if ($this->insertMapIfNontrivial($row['parent_shortname'],$row['shortname'],LicenseMap::CONCLUSION)) {
      $return .= " with conclusion '$row[parent_shortname]'";
    }
    if ($this->insertMapIfNontrivial($row['report_shortname'],$row['shortname'],LicenseMap::REPORT)) {
      $return .= " reporting '$row[report_shortname]'";
    }
    return $return;
  }

  /**
   * @brief Insert in `license_map` table if the license conclusion is
   * non-trivial.
   *
   * If the from and to are not same and from exists in database, then the
   * conclusion is non-trivial.
   * @param string $fromName  Parent license name
   * @param string $toName    License name
   * @param string $usage     Usage of the license
   * @return boolean True if license is non-trivial, false otherwise.
   */
  private function insertMapIfNontrivial($fromName,$toName,$usage)
  {
    $isNontrivial = ($fromName!==null && $fromName!=$toName && $this->getKeyFromShortname($fromName)!==false);
    if ($isNontrivial) {
      $this->dbManager->insertTableRow('license_map',
        array('rf_fk'=>$this->getKeyFromShortname($toName),
            'rf_parent'=>$this->getKeyFromShortname($fromName),
            'usage'=> $usage));
    }
    return $isNontrivial;
  }

  /**
   * @brief Get the license id using license shortname from DB or nkMap.
   * @param string $shortname Shortname of the license.
   * @return int License id
   */
  private function getKeyFromShortname($shortname)
  {
    if (array_key_exists($shortname, $this->nkMap)) {
      return $this->nkMap[$shortname];
    }
    $row = $this->dbManager->getSingleRow('SELECT rf_pk FROM license_ref WHERE rf_shortname=$1',array($shortname));
    $this->nkMap[$shortname] = ($row===false) ? false : $row['rf_pk'];
    return $this->nkMap[$shortname];
  }
}
