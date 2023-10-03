<?php
/*
 SPDX-FileCopyrightText: © 2017 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\BusinessRules;

use Fossology\Lib\Db\DbManager;

/**
 * @class ObligationMap
 * @brief Wrapper class for obligation map
 */
class ObligationMap
{

  /** @var DbManager $dbManager
   * DB manager object */
  private $dbManager;

  /**
   * Constructor
   * @param DbManager $dbManager DB manager to use
   */
  public function __construct(DbManager $dbManager)
  {
    $this->dbManager = $dbManager;
  }

  /**
   * @brief Get the list of license shortnames
   *
   * If candidate license, return list of all licenses.
   * If not-candidate license, return list of licenses which have conclusion on
   * self.
   * @param bool $candidate Is a candidate license
   * @return string[] Array of license shortnames
   */
  public function getAvailableShortnames($candidate=false)
  {
    $params = [];
    if ($candidate) {
      $sql = "SELECT rf_shortname FROM license_candidate;";
      $stmt = __METHOD__.".rf_candidate_shortnames";
    } else {
      $sql = LicenseMap::getMappedLicenseRefView();
      $stmt = __METHOD__.".rf_shortnames";
      $params[] = LicenseMap::CONCLUSION;
    }
    $this->dbManager->prepare($stmt,$sql);
    $res = $this->dbManager->execute($stmt, $params);
    $vars = $this->dbManager->fetchAll($res);
    $this->dbManager->freeResult($res);

    $licshortnames = array();
    foreach ($vars as $rf_entry) {
      $shortname = $rf_entry['rf_shortname'];
      $licshortnames[$shortname] = $shortname;
    }

    return $licshortnames;
  }

  /**
   * @brief Get the license ids from the shortname
   * @param string $shortname Short name of the license
   * @param bool   $candidate Is a candidate license?
   * @return int[] License ids
   */
  public function getIdFromShortname($shortname, $candidate=false)
  {
    $tableName = "";
    if ($candidate) {
      $tableName = "license_candidate";
    } else {
      $tableName = "license_ref";
    }
    $sql = "SELECT * FROM ONLY $tableName WHERE rf_shortname = $1;";
    $statement = __METHOD__ . ".getLicId.$tableName";
    $results = $this->dbManager->getRows($sql, array($shortname), $statement);
    $licenseIds = array();
    foreach ($results as $row) {
      $licenseIds[] = $row['rf_pk'];
    }
    return $licenseIds;
  }

  /**
   * @brief Get the shortname of the license by Id
   * @param int $rfId ID of the license
   * @param bool $candidate Is a candidate license?
   * @return string License shortname
   */
  public function getShortnameFromId($rfId,$candidate=false)
  {
    if ($candidate) {
      $sql = "SELECT * FROM ONLY license_candidate WHERE rf_pk = $1;";
    } else {
      $sql = "SELECT * FROM ONLY license_ref WHERE rf_pk = $1;";
    }
    $statement = __METHOD__ . "." . ($candidate ? "candidate" : "license");
    $result = $this->dbManager->getSingleRow($sql,array($rfId), $statement);
    return $result['rf_shortname'];
  }

  /**
   * @brief Get the list of licenses associated with the obligation
   * @param int $obId Obligation id
   * @param bool $candidate Is a candidate obligation?
   * @return string List of license shortname delimited by `';'`
   */
  public function getLicenseList($obId,$candidate=false)
  {
    $liclist = array();
    if ($candidate) {
      $sql = "SELECT rf_fk FROM obligation_candidate_map WHERE ob_fk=$1;";
      $stmt = __METHOD__.".om_candidate";
    } else {
      $sql = "SELECT rf_fk FROM obligation_map WHERE ob_fk=$1;";
      $stmt = __METHOD__.".om_license";
    }
    $this->dbManager->prepare($stmt,$sql);
    $res = $this->dbManager->execute($stmt, array($obId));
    $vars = $this->dbManager->fetchAll($res);
    $this->dbManager->freeResult($res);
    foreach ($vars as $map_entry) {
      $liclist[] = $this->getShortnameFromId($map_entry['rf_fk'], $candidate);
    }

    return join(";", array_unique($liclist));
  }

  /**
   * @brief Check if the obligation is already associated with the license
   * @param int $obId   Obligation id
   * @param int $licId  License id
   * @param bool $candidate Is a candidate obligation?
   * @return bool True if license is already mapped, false otherwise
   */
  public function isLicenseAssociated($obId,$licId,$candidate=false)
  {
    $tableName = "";
    if ($candidate) {
      $stmt = __METHOD__.".om_testcandidate";
      $tableName .= "obligation_candidate_map";
    } else {
      $stmt = __METHOD__.".om_testlicense";
      $tableName .= "obligation_map";
    }
    $sql = "SELECT * FROM $tableName WHERE ob_fk = $1 AND rf_fk = $2;";
    $this->dbManager->prepare($stmt,$sql);
    $res = $this->dbManager->execute($stmt,array($obId,$licId));
    $vars = $this->dbManager->fetchAll($res);
    $this->dbManager->freeResult($res);

    if (!empty($vars)) {
      return true;
    }

    return false;
  }

  /**
   * @brief Associate a license with an obligation
   * @param int $obId  Obligation id
   * @param int $licId License id
   * @param bool $candidate Is a candidate obligation?
   */
  public function associateLicenseWithObligation($obId,$licId,$candidate=false)
  {
    if (! $this->isLicenseAssociated($obId, $licId, $candidate)) {
      if ($candidate) {
        $sql = "INSERT INTO obligation_candidate_map (ob_fk, rf_fk) VALUES ($1, $2)";
        $stmt = __METHOD__ . ".om_addcandidate";
      } else {
        $sql = "INSERT INTO obligation_map (ob_fk, rf_fk) VALUES ($1, $2)";
        $stmt = __METHOD__ . ".om_addlicense";
      }
      $this->dbManager->prepare($stmt, $sql);
      $res = $this->dbManager->execute($stmt, array($obId,$licId));
      $this->dbManager->fetchArray($res);
      $this->dbManager->freeResult($res);
    }
  }

  /**
   * @brief Unassociate a license from an obligation
   * @param int $obId Obligation id
   * @param int $licId License id
   * @param bool $candidate Is a candidate obligation?
   */
  public function unassociateLicenseFromObligation($obId,$licId=0,$candidate=false)
  {
    if ($licId == 0) {
      $stmt = __METHOD__.".omdel_all";
      if ($candidate) {
        $sql = "DELETE FROM obligation_candidate_map WHERE ob_fk=$1";
        $stmt .= ".candidate";
      } else {
        $sql = "DELETE FROM obligation_map WHERE ob_fk=$1";
      }
      $this->dbManager->prepare($stmt,$sql);
      $res = $this->dbManager->execute($stmt,array($obId));
    } else {
      $stmt = __METHOD__.".omdel_lic";
      if ($candidate) {
        $sql = "DELETE FROM obligation_candidate_map WHERE ob_fk=$1 AND rf_fk=$2";
        $stmt .= ".candidate";
      } else {
        $sql = "DELETE FROM obligation_map WHERE ob_fk=$1 AND rf_fk=$2";
      }
      $this->dbManager->prepare($stmt,$sql);
      $res = $this->dbManager->execute($stmt,array($obId,$licId));
    }
    $this->dbManager->fetchArray($res);
    $this->dbManager->freeResult($res);
  }

  /**
   * @brief Get all obligations from DB
   * @return array
   */
  public function getObligations()
  {
    $sql = "SELECT * FROM obligation_ref;";
    return $this->dbManager->getRows($sql);
  }

  /**
   * @brief Get the obligation topic from the obligation id
   * @param int     $ob_pk     Obligation id
   * @return string Obligation topic
   */
  public function getTopicNameFromId($ob_pk)
  {
    $sql = "SELECT ob_topic FROM obligation_ref WHERE ob_pk = $1;";
    $result = $this->dbManager->getSingleRow($sql,array($ob_pk));
    return $result['ob_topic'];
  }

  /**
   * Associate a list of license IDs with given obligation.
   * @param integer $obligationId Obligation to be associated
   * @param array   $licenses     Array of licenses to be associated
   * @param boolean $candidate    Is a candidate association?
   * @return boolean True if new association is made, false otherwise.
   */
  public function associateLicenseFromLicenseList($obligationId, $licenses, $candidate = false)
  {
    $updated = false;
    foreach ($licenses as $license) {
      if (! $this->isLicenseAssociated($obligationId, $license, $candidate)) {
        $this->associateLicenseWithObligation($obligationId, $license, $candidate);
        $updated = true;
      }
    }
    return $updated;
  }

  /**
   * Unassociate a list of license IDs with given obligation.
   * @param integer $obligationId Obligation to be unassociated
   * @param array   $licenses     Array of licenses to be unassociated
   * @param boolean $candidate    Is a candidate association?
   */
  public function unassociateLicenseFromLicenseList($obligationId, $licenses, $candidate = false)
  {
    foreach ($licenses as $license) {
        $this->unassociateLicenseFromObligation($obligationId, $license, $candidate);
    }
  }

  /**
   * Get obligation by id
   * @param int $ob_pk Obligation ID
   * @return array Obligation from DB
   */
  public function getObligationById($ob_pk)
  {
    $sql = "SELECT * FROM obligation_ref WHERE ob_pk = $1;";
    return $this->dbManager->getSingleRow($sql, [$ob_pk]);
  }
}
