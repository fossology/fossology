<?php
/*
 SPDX-FileCopyrightText: © 2014-2015 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Application;

use Fossology\Lib\BusinessRules\LicenseMap;
use Fossology\Lib\Dao\UserDao;
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
  /** @var array $nkMap
   * Map based on license shortname */
  protected $nkMap = array();
  /** @var array $mdkMap
   * Map based on license text MD5 */
  protected $mdkMap = array();
  /** @var array $alias
   * Alias for headers */
  protected $alias = array(
      'shortname'=>array('shortname','Short Name'),
      'fullname'=>array('fullname','Long Name'),
      'spdx_id'=>array('spdx_id', 'SPDX ID'),
      'text'=>array('text','Full Text'),
      'parent_shortname'=>array('parent_shortname','Decider Short Name'),
      'report_shortname'=>array('report_shortname','Regular License Text Short Name'),
      'url'=>array('url','URL'),
      'notes'=>array('notes'),
      'source'=>array('source','Foreign ID'),
      'risk'=>array('risk','risk_level'),
      'group'=>array('group','License group'),
      'obligations'=>array('obligations','License obligations')
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
    foreach (array('parent_shortname' => null, 'report_shortname' => null,
      'url' => '', 'notes' => '', 'source' => '', 'risk' => 0,
      'group' => null, 'spdx_id' => null) as $optNeedle=>$defaultValue) {
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
    $row[0] = trim($row[0], "\xEF\xBB\xBF");  // Remove BOM
    foreach (array('shortname','fullname','text') as $needle) {
      $col = ArrayOperation::multiSearch($this->alias[$needle], $row);
      if (false === $col) {
        throw new \Exception("Undetermined position of $needle");
      }
      $headrow[$needle] = $col;
    }
    foreach (array('parent_shortname', 'report_shortname', 'url', 'notes',
      'source', 'risk', 'group', 'spdx_id') as $optNeedle) {
      $headrow[$optNeedle] = ArrayOperation::multiSearch($this->alias[$optNeedle], $row);
    }
    return $headrow;
  }

  /**
   * @brief Update the license info in the DB.
   * @param array $row  Row with new values.
   * @param array $rfPk Matched license ID.
   * @return string Log messages.
   */
  private function updateLicense($row, $rfPk)
  {
    $stmt = __METHOD__ . '.getOldLicense';
    $oldLicense = $this->dbManager->getSingleRow('SELECT ' .
      'rf_shortname, rf_fullname, rf_spdx_id, rf_text, rf_url, rf_notes, rf_source, rf_risk ' .
      'FROM license_ref WHERE rf_pk = $1', array($rfPk), $stmt);

    $stmt = __METHOD__ . '.getOldMapping';
    $sql = 'SELECT rf_parent FROM license_map WHERE rf_fk = $1 AND usage = $2;';
    $oldParent = null;
    $oldParentRow = $this->dbManager->getSingleRow($sql, array($rfPk,
      LicenseMap::CONCLUSION), $stmt);
    if (!empty($oldParentRow)) {
      $oldParent = $oldParentRow['rf_parent'];
    }
    $oldReport = null;
    $oldReportRow = $this->dbManager->getSingleRow($sql, array($rfPk,
      LicenseMap::REPORT), $stmt);
    if (!empty($oldReportRow)) {
      $oldReport = $oldReportRow['rf_parent'];
    }

    $newParent = null;
    $newParent = ($row['parent_shortname'] == null) ? null :
      $this->getKeyFromShortname($row['parent_shortname']);

    $newReport = null;
    $newReport = ($row['report_shortname'] == null) ? null :
      $this->getKeyFromShortname($row['report_shortname']);

    $log = "License '$row[shortname]' already exists in DB (id = $rfPk)";
    $stmt = __METHOD__ . '.updateLicense';
    $sql = "UPDATE license_ref SET ";
    if (! empty($row['group'])) {
      $sql = "UPDATE license_candidate SET ";
    }
    $extraParams = array();
    $param = array($rfPk);
    if (!empty($row['fullname']) && $row['fullname'] != $oldLicense['rf_fullname']) {
      $param[] = $row['fullname'];
      $stmt .= '.fullN';
      $extraParams[] = "rf_fullname=$" . count($param);
      $log .= ", updated fullname";
    }
    if (!empty($row['spdx_id']) && $row['spdx_id'] != $oldLicense['rf_spdx_id']) {
      $param[] = $row['spdx_id'];
      $stmt .= '.spId';
      $extraParams[] = "rf_spdx_id=$" . count($param);
      $log .= ", updated SPDX ID";
    }
    if (!empty($row['text']) && $row['text'] != $oldLicense['rf_text'] && $row['text'] != LicenseMap::TEXT_MAX_CHAR_LIMIT) {
      $param[] = $row['text'];
      $stmt .= '.text';
      $extraParams[] = "rf_text=$" . count($param) . ",rf_md5=md5($" .
        count($param) . ")";
      $log .= ", updated text";
    }
    if (!empty($row['url']) && $row['url'] != $oldLicense['rf_url']) {
      $param[] = $row['url'];
      $stmt .= '.url';
      $extraParams[] = "rf_url=$" . count($param);
      $log .= ", updated URL";
    }
    if (!empty($row['notes']) && $row['notes'] != $oldLicense['rf_notes']) {
      $param[] = $row['notes'];
      $stmt .= '.notes';
      $extraParams[] = "rf_notes=$" . count($param);
      $log .= ", updated notes";
    }
    if (!empty($row['source']) && $row['source'] != $oldLicense['rf_source']) {
      $param[] = $row['source'];
      $stmt .= '.updSource';
      $extraParams[] = "rf_source=$".count($param);
      $log .= ', updated the source';
    }
    if (!empty($row['risk']) && $row['risk'] != $oldLicense['rf_risk']) {
      $param[] = $row['risk'];
      $stmt .= '.updRisk';
      $extraParams[] = "rf_risk=$".count($param);
      $log .= ', updated the risk level';
    }
    if (count($param) > 1) {
      $sql .= join(",", $extraParams);
      $sql .= " WHERE rf_pk=$1;";
      $this->dbManager->getSingleRow($sql, $param, $stmt);
      $this->mdkMap[md5($row['text'])] = $rfPk;
    }

    if (($oldParent != $newParent) && $this->setMap($newParent, $rfPk, LicenseMap::CONCLUSION)) {
      $log .= " with conclusion '$row[parent_shortname]'";
    }
    if (($oldReport != $newReport) && $this->setMap($newReport, $rfPk, LicenseMap::REPORT)) {
      $log .= " reporting '$row[report_shortname]'";
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
    if (empty($row['risk'])) {
      $row['risk'] = 0;
    }
    $rfPk = $this->getKeyFromShortname($row['shortname'], $row['group']);
    $md5Match = $this->getKeyFromMd5($row['text']);

    // If shortname exists, does not collide with other texts and is not
    // candidate
    if ($rfPk !== false) {
      if (! empty($row['group']) || ($md5Match == $rfPk || $md5Match === false)) {
        return $this->updateLicense($row, $rfPk);
      } else {
        return "Error: MD5 checksum of '" . $row['shortname'] .
          "' collides with license id=$md5Match";
      }
    }
    if ($md5Match !== false && empty($row['group'])) {
      return "Error: MD5 checksum of '" . $row['shortname'] .
        "' collides with license id=$md5Match";
    }

    $return = "";
    if (!empty($row['group'])) {
      $return = $this->insertNewLicense($row, "license_candidate");
    } else {
      $return = $this->insertNewLicense($row, "license_ref");
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
  private function getKeyFromShortname($shortname, $groupFk = null)
  {
    $keyName = $shortname;
    $tableName = "license_ref";
    $addCondition = "";
    $statement = __METHOD__ . ".getId";
    $params = array($shortname);

    if ($groupFk != null) {
      $keyName .= $groupFk;
      $tableName = "license_candidate";
      $addCondition = "AND group_fk = $2";
      $statement .= ".candidate";
      $params[] = $this->userDao->getGroupIdByName($groupFk);
    }
    $sql = "SELECT rf_pk FROM ONLY $tableName WHERE rf_shortname = $1 $addCondition;";
    if (array_key_exists($keyName, $this->nkMap)) {
      return $this->nkMap[$keyName];
    }
    $row = $this->dbManager->getSingleRow($sql, $params, $statement);
    $this->nkMap[$keyName] = ($row===false) ? false : $row['rf_pk'];
    return $this->nkMap[$keyName];
  }

  /**
   * Get the license id using license text's checksum from DB or mdkMap.
   * @param string $licenseText License text
   * @return integer License id
   */
  private function getKeyFromMd5($licenseText)
  {
    $md5 = md5($licenseText);
    if (array_key_exists($md5, $this->mdkMap)) {
      return $this->mdkMap[$md5];
    }
    $row = $this->dbManager->getSingleRow("SELECT rf_pk " .
      "FROM ONLY license_ref WHERE rf_md5=md5($1)",
      array($licenseText));
    $this->mdkMap[$md5] = (empty($row)) ? false : $row['rf_pk'];
    return $this->mdkMap[$md5];
  }

  /**
   * @brief Update license mappings
   *
   * First check if the mapping already exists for the license, then update it.
   * If the mapping does not exists, then insert it.
   * @param integer $from  The new mapping license
   * @param integer $to    The license to be updated
   * @param integer $usage The usage
   * @return boolean False if mapping could not be updated or $from is empty.
   */
  private function setMap($from, $to, $usage)
  {
    $return = false;
    if (!empty($from)) {
      $sql = "SELECT license_map_pk, rf_parent FROM license_map WHERE rf_fk = $1 AND usage = $2;";
      $statement = __METHOD__ . ".getCurrentMapping";
      $row = $this->dbManager->getSingleRow($sql, array($to, $usage), $statement);
      if (!empty($row) && $row['rf_parent'] != $from) {
        $this->dbManager->updateTableRow("license_map", array(
          'rf_fk' => $to,
          'rf_parent' => $from,
          'usage' => $usage
        ), 'license_map_pk', $row['license_map_pk']);
        $return = true;
      } elseif (empty($row)) {
        $this->dbManager->insertTableRow('license_map', array(
          'rf_fk' => $to,
          'rf_parent' => $from,
          'usage' => $usage
        ));
        $return = true;
      }
    }
    return $return;
  }

  /**
   * @brief Insert a new license in DB
   *
   * Creates a new main license/candidate license based on table name sent
   * and if the required group exists in DB.
   * @param array $row        Rows comming from CSV
   * @param string $tableName Table where this new license should go to
   * @return string Log messages
   */
  private function insertNewLicense($row, $tableName = "license_ref")
  {
    $stmtInsert = __METHOD__ . '.insert.' . $tableName;
    $columns = array(
      "rf_shortname" => $row['shortname'],
      "rf_fullname"  => $row['fullname'],
      "rf_spdx_id"   => $row['spdx_id'],
      "rf_text"      => $row['text'],
      "rf_md5"       => md5($row['text']),
      "rf_detector_type" => 1,
      "rf_url"       => $row['url'],
      "rf_notes"     => $row['notes'],
      "rf_source"    => $row['source'],
      "rf_risk"      => $row['risk']
    );

    $as = "";
    if ($tableName == "license_candidate") {
      $groupId = $this->userDao->getGroupIdByName($row['group']);
      if (empty($groupId)) {
        return "Error: Unable to insert candidate license " . $row['shortname'] .
          " as group " . $row['group'] . " does not exist";
      }
      $columns["group_fk"] = $groupId;
      $columns["marydone"] = $this->dbManager->booleanToDb(true);
      $as = " as candidate license under group " . $row["group"];
    }

    $newPk = $this->dbManager->insertTableRow($tableName, $columns, $stmtInsert, 'rf_pk');

    if ($tableName == "license_candidate") {
      $this->nkMap[$row['shortname'].$row['group']] = $newPk;
    } else {
      $this->nkMap[$row['shortname']] = $newPk;
    }
    $this->mdkMap[md5($row['text'])] = $newPk;
    $return = "Inserted '$row[shortname]' in DB" . $as;

    if ($this->insertMapIfNontrivial($row['parent_shortname'], $row['shortname'], LicenseMap::CONCLUSION)) {
      $return .= " with conclusion '$row[parent_shortname]'";
    }
    if ($this->insertMapIfNontrivial($row['report_shortname'], $row['shortname'], LicenseMap::REPORT)) {
      $return .= " reporting '$row[report_shortname]'";
    }
    return $return;
  }
}
