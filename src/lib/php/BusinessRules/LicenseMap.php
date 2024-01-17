<?php
/*
 SPDX-FileCopyrightText: © 2014-2015 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\BusinessRules;

use Fossology\Lib\Data\LicenseRef;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Proxy\LicenseViewProxy;

/**
 * @class LicenseMap
 * @brief Wrapper class for license map
 */
class LicenseMap
{
  const CONCLUSION = 1;
  const TRIVIAL = 2;
  const FAMILY = 3;
  const REPORT = 4;
  const MAX_CHAR_LIMIT = "32767";
  const TEXT_MAX_CHAR_LIMIT = "The maximum number of characters per cell in a CSV file(32,767) exceeded, Please edit this license text in UI";

  /** @var DbManager */
  private $dbManager;
  /** @var int */
  private $usageId;
  /** @var int */
  private $groupId;
  /** @var array */
  private $map = array();

  /**
   * Constructor
   * @param DbManager $dbManager
   * @param int $groupId
   * @param int $usageId
   * @param bool $full
   */
  public function __construct(DbManager $dbManager, $groupId, $usageId=null, $full=false)
  {
    $this->usageId = $usageId?:self::CONCLUSION;
    $this->groupId = $groupId;
    $this->dbManager = $dbManager;
    if ($this->usageId == self::TRIVIAL && !$full) {
      return;
    }
    $licenseView = new LicenseViewProxy($groupId);
    if ($full) {
      $query = $licenseView->asCTE()
            .' SELECT distinct on(rf_pk) rf_pk rf_fk, rf_shortname parent_shortname,
                   rf_spdx_id AS parent_spdx_id, rf_parent, rf_fullname AS parent_fullname
               FROM (
                SELECT r1.rf_pk, r2.rf_shortname, r2.rf_spdx_id, usage,
                   rf_parent, r2.rf_fullname FROM '.$licenseView->getDbViewName()
            .' r1 inner join license_map on usage=$1 and rf_fk=r1.rf_pk
             left join license_ref r2 on rf_parent=r2.rf_pk
            UNION
            SELECT rf_pk, rf_shortname, rf_spdx_id, -1 usage, rf_pk rf_parent,
                  rf_fullname FROM '.$licenseView->getDbViewName()
            .') full_map ORDER BY rf_pk,usage DESC';

      $stmt = __METHOD__.".$this->usageId,$groupId,full";
    } else {
      $query = $licenseView->asCTE()
            .' SELECT rf_fk, rf_shortname AS parent_shortname, rf_spdx_id AS parent_spdx_id, '
            .'rf_parent, rf_fullname AS parent_fullname FROM license_map, '.$licenseView->getDbViewName()
            .' WHERE rf_pk=rf_parent AND rf_fk!=rf_parent AND usage=$1';
      $stmt = __METHOD__.".$this->usageId,$groupId";
    }
    $dbManager->prepare($stmt,$query);
    $res = $dbManager->execute($stmt,array($this->usageId));
    while ($row = $dbManager->fetchArray($res)) {
      $this->map[$row['rf_fk']] = $row;
    }
    $dbManager->freeResult($res);
  }

  /**
   * @brief For a given license id, get the projected id
   * @param int $licenseId  License id to be queried
   * @return int Projected license id
   */
  public function getProjectedId($licenseId)
  {
    if (array_key_exists($licenseId, $this->map)) {
      return $this->map[$licenseId]['rf_parent'];
    }
    return $licenseId;
  }

  /**
   * @brief For a given license id, get the projected shortname.
   *
   * If the license id is not found in the map, then default name is returned.
   * @param int $licenseId  License id to be queried
   * @param string $defaultName Default name to return if license not found in map
   * @return string|null Projected shortname or default name
   */
  public function getProjectedShortname($licenseId, $defaultName=null)
  {
    if (array_key_exists($licenseId, $this->map)) {
      return $this->map[$licenseId]['parent_shortname'];
    }
    return $defaultName;
  }

  /**
   * @brief For a given license id, get the projected SPDX ID (or shortname if
   * ID does not exist).
   * @param int $licenseId  License id to be queried
   * @param string $defaultID Default ID to return if license not found in map
   * @return string|null Projected SPDX ID or default name
   */
  public function getProjectedSpdxId($licenseId, $defaultID=null)
  {
    if (array_key_exists($licenseId, $this->map)) {
      return LicenseRef::convertToSpdxId($this->map[$licenseId]['parent_shortname'],
        $this->map[$licenseId]['parent_spdx_id']);
    }
    return $defaultID;
  }

  /**
   * @brief For a given license id, get the projected fullname. If empty, get
   * the shortname instead.
   * @param int $licenseId  License ID
   * @param string $defaultName Default name if license not in map
   * @return mixed|string|null Projected fullname or default name
   */
  public function getProjectedName($licenseId, $defaultName=null)
  {
    if (array_key_exists($licenseId, $this->map)) {
      $licenseName = $this->map[$licenseId]['parent_fullname'];
      if (empty($licenseName)) {
        $licenseName = $this->getProjectedShortname($licenseId, $defaultName);
      }
      return $licenseName;
    }
    return $defaultName;
  }

  /**
   * Get the Usage of the map.
   * @return number
   */
  public function getUsage()
  {
    return $this->usageId;
  }

  /**
   * Get the group id of the map.
   * @return number
   */
  public function getGroupId()
  {
    return $this->groupId;
  }

  /**
   * Get the top level license refs from the license map.
   * @return Fossology::Lib::Data::LicenseRef[]
   */
  public function getTopLevelLicenseRefs()
  {
    $licenseView = new LicenseViewProxy($this->groupId,
      array('columns'=>array('rf_pk','rf_shortname', 'rf_spdx_id', 'rf_fullname')),
      'license_visible');
    $query = $licenseView->asCTE()
          .' SELECT rf_pk, rf_shortname, rf_spdx_id, rf_fullname FROM '.$licenseView->getDbViewName()
          .' LEFT JOIN license_map ON rf_pk=rf_fk AND rf_fk!=rf_parent AND usage=$1'
          .' WHERE license_map_pk IS NULL';
    $stmt = __METHOD__.".$this->usageId,$this->groupId";
    $this->dbManager->prepare($stmt,$query);
    $res = $this->dbManager->execute($stmt,array($this->usageId));
    $topLevel = array();
    while ($row = $this->dbManager->fetchArray($res)) {
      $topLevel[$row['rf_pk']] = new LicenseRef($row['rf_pk'],$row['rf_shortname'],$row['rf_fullname'],$row['rf_spdx_id']);
    }
    return $topLevel;
  }

  /**
   * @brief Query to get license map view along with license ref
   * @param string $usageExpr Position of usage id in parameter array
   */
  public static function getMappedLicenseRefView($usageExpr='$1')
  {
    return "SELECT bot.rf_pk rf_origin, top.rf_pk, top.rf_shortname, top.rf_fullname, top.rf_spdx_id FROM license_ref bot "
          ."LEFT JOIN license_map ON bot.rf_pk=rf_fk AND usage=$usageExpr "
          ."INNER JOIN license_ref top ON rf_parent=top.rf_pk OR rf_parent IS NULL AND bot.rf_pk=top.rf_pk";
  }

  /**
   * @brief Get all Obligations attached with given license ref
   * @param int  $license_ref ID of license / candidate license
   * @param bool $candidate   Is the license candidate?
   * @return int[] Array of obligation ids
   */
  public function getObligationsForLicenseRef($license_ref, $candidate = false)
  {
    $tableName = $candidate ? "obligation_candidate_map" : "obligation_map";
    $sql = "SELECT distinct(ob_fk) FROM $tableName WHERE rf_fk = $1;";
    $ob_fks = $this->dbManager->getRows($sql, [$license_ref],
      __METHOD__ . $tableName);
    $returnVal = [];
    foreach ($ob_fks as $row) {
      $returnVal[] = $row['ob_fk'];
    }
    return $returnVal;
  }
}
