<?php
/*
 SPDX-FileCopyrightText: © 2024 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Dao;

use Fossology\Lib\Db\DbManager;
use Monolog\Logger;
use Fossology\Lib\Proxy\ScanJobProxy;
use Fossology\Lib\Auth\Auth;

/**
 * Class SoftwareHeritageDao
 * @package Fossology\Lib\Dao
 */
class CompatibilityDao
{
  /** @var DbManager */
  private $dbManager;
  /** @var Logger */
  private $logger;
  /** @var LicenseDao */
  private $licenseDao;
  /** @var AgentDao */
  private $agentDao;

  public function __construct(DbManager $dbManager, Logger $logger,LicenseDao $licenseDao, AgentDao $agentDao)
  {
    $this->dbManager = $dbManager;
    $this->logger = $logger;
    $this->licenseDao = $licenseDao;
    $this->agentDao = $agentDao;
  }

  /**
   * @brief get compatibility of the licenses present in the file
   * @param ItemTreeBounds $itemTreeBounds pointing to a file
   * @param string $shortname shortname of the license
   * @return boolean
   */
  public function getCompatibilityForFile($itemTreeBounds, $shortname)
  {
    $uploadTreeTableName = $itemTreeBounds->getUploadTreeTableName();
    $uploadTreePk = $itemTreeBounds->getItemId();
    $stmt = __METHOD__.$uploadTreeTableName;
    $sql = "SELECT pfile_fk FROM $uploadTreeTableName UT      
            WHERE UT.uploadtree_pk = $1";
    $result = $this->dbManager->getSingleRow($sql, array($uploadTreePk), $stmt);
    $pfileId = $result["pfile_fk"];
    $agentName = 'compatibility';
    $uploadIdd = $itemTreeBounds->getUploadId();
    /** @var ScanJobProxy $scanJobProxy */
    $scanJobProxy = new ScanJobProxy($this->agentDao,
      $uploadIdd);
    $scanJobProxy->createAgentStatus(array($agentName));
    $selectedScanners = $scanJobProxy->getLatestSuccessfulAgentIds();
    if (!array_key_exists($agentName, $selectedScanners)) {
      return true;
    }
    $latestAgentId = $selectedScanners[$agentName];
    $licId= $this->licenseDao->getLicenseByShortName($shortname, $groupId=null);
    $licId2= $licId->getId();
    $stmt = __METHOD__."GetCompResult";
    $sql = "SELECT 1 AS found FROM comp_result WHERE (result= false) AND (pfile_fk= $1) AND (agent_fk= $2) AND (first_rf_fk = $3 OR second_rf_fk = $3)";
    $res= $this->dbManager->getSingleRow($sql, array($pfileId, $latestAgentId, $licId2), $stmt);
    if (!empty($res)) {
      return $res["found"]!=1;
    }
    return true;
  }

  /**
   * @brief get all the existing rules present in the database
   * @return array
   */
  public function getAllRules()
  {
      $sql = "SELECT lr_pk, main_rf_fk, sub_rf_fk, main_type, sub_type, text, compatibility " .
          "FROM license_rules " .
          "ORDER BY lr_pk;"
          ;
      return $this->dbManager->getRows($sql);
  }

  /**
   * @brief insert new rule in the database
   * @param string $firstName first license
   * @param string $secondName second license
   * @param string $firstType first license type
   * @param string $secondType second license type
   * @param string $text description of the rule
   * @param string $result compatibility result of the two licenses
   * @return number if -1: rule not inserted, -2 error at insertion otherwise rule inserted
   */
  function insertRule($firstName, $secondName, $firstType, $secondType, $text, $result)
  {
    if (! Auth::isAdmin()) {
        // Only admins can add rules.
        return -1;
    }
    $text = trim($text);
    if (empty($text)) {
        // Cannot insert empty fields.
        return -1;
    }
    $params = [
        'main_rf_fk' => $firstName,
        'sub_rf_fk' => $secondName,
        'main_type' => $firstType,
        'sub_type' => $secondType,
        'text' => $text,
        'compatibility' => $result
    ];
    $statement = __METHOD__ . ".insertNewLicCompatibilityRule";
    $returning = "lr_pk";
    $returnVal = -1;
    try {
        $returnVal = $this->dbManager->insertTableRow("license_rules",
            $params, $statement, $returning);
    } catch (\Exception $e) {
        $returnVal = -2;
    }
    return $returnVal;
  }

  /**
   * @brief update the existing rules
   * @param int $ruleArray contains the id of the licenses
   * @throws \UnexpectedValueException if not a single rule is present
   * @return number if 0 rule not updated otherwise it is updated
   */
  function updateRuleFromArray($ruleArray)
  {
    if (!Auth::isAdmin()) {
        // Only admins can update the rules.
        return false;
    }
    $updated = 0;
    foreach ($ruleArray as $rulePk => $rule) {
      if (count($rule) < 1) {
        throw new \UnexpectedValueException("At least a rule is required");
      }
      $this->isRuleIdValid($rulePk);
      $statement = __METHOD__;
      $params = [$rulePk];
      $updateStatement = [];
      if (array_key_exists("firstLic", $rule)) {
        $params[] = $rule["firstLic"];
        $updateStatement[] = "main_rf_fk = $" . count($params);
        $statement .= ".main_rf_fk";
      }
      if (array_key_exists("secondLic", $rule)) {
        $params[] = $rule["secondLic"];
        $updateStatement[] = "sub_rf_fk = $" . count($params);
        $statement .= ".sub_rf_fk";
      }
      if (array_key_exists("firstType", $rule)) {
        $params[] = $rule["firstType"];
        $updateStatement[] = "main_type = $" . count($params);
        $statement .= ".main_type";
      }
      if (array_key_exists("secondType", $rule)) {
        $params[] = $rule["secondType"];
        $updateStatement[] = "sub_type = $" . count($params);
        $statement .= ".sub_type";
      }
      if (array_key_exists("text", $rule)) {
        $params[] = $rule["text"];
        $updateStatement[] = "text = $" . count($params);
        $statement .= ".text";
      }
      if (array_key_exists("result", $rule)) {
        $params[] = $rule["result"];
        $updateStatement[] = "compatibility = $" . count($params);
        $statement .= ".compatibility";
      }
      $sql = "UPDATE license_rules " .
             "SET " . join(",", $updateStatement) .
             " WHERE lr_pk = $1 " .
             "RETURNING 1 AS updated;";
      $retVal = $this->dbManager->getSingleRow($sql, $params, $statement);
      $updated += intval($retVal);
    }
    return $updated;
  }

  /**
   * @brief to check validity of the license
   * @param int $rulePk
   * @throws \UnexpectedValueException
   */
  private function isRuleIdValid($rulePk)
  {
    if (! is_int($rulePk)) {
      throw new \UnexpectedValueException("Inavlid rule id");
    }
    $sql = "SELECT count(*) AS cnt FROM license_rules " .
           "WHERE lr_pk = $1;";

    $ruleCount = $this->dbManager->getSingleRow($sql, [$rulePk]);
    if ($ruleCount['cnt'] < 1) {
      // Invalid rule id
      throw new \UnexpectedValueException("Inavlid rule id");
    }
  }

  /**
   * @brief to delete a rule
   * @param int $rulePk id of the license to be deleted
   * @return boolean
   */
  function deleteRule($rulePk)
  {
    if (!Auth::isAdmin()) {
      // Only admins can delete the rules.
      return false;
    }

    $stmt = __METHOD__."DeletionOfRule";
    $sql = "DELETE from license_rules " .
           "WHERE lr_pk = $1 " .
           "RETURNING lr_pk;";

    $retVal = $this->dbManager->getSingleRow($sql, array($rulePk), $stmt);
    if ($retVal["lr_pk"] == $rulePk) {
      return true;
    } else {
        return false;
    }
  }
}
