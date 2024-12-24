<?php
/*
 SPDX-FileCopyrightText: © 2024 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Dao;

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Data\Tree\ItemTreeBounds;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Exceptions\InvalidAgentStageException;
use Fossology\Lib\Proxy\ScanJobProxy;
use Monolog\Logger;

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
  /** @var bool $defaultCompatibility */
  private $defaultCompatibility;
  /** @var string $agentName */
  private $agentName;

  public function __construct(DbManager $dbManager, Logger $logger,LicenseDao $licenseDao, AgentDao $agentDao)
  {
    $this->dbManager = $dbManager;
    $this->logger = $logger;
    $this->licenseDao = $licenseDao;
    $this->agentDao = $agentDao;
    $this->agentName = "compatibility";
    $sql = "SELECT compatibility FROM license_rules
                     WHERE first_rf_fk IS NULL AND second_rf_fk IS NULL
                     AND first_type IS NULL AND second_type IS NULL;";
    $result = $this->dbManager->getSingleRow($sql, [], __METHOD__ .
      "defaultComp");
    if (!empty($result) && array_key_exists("compatibility", $result)) {
      $this->defaultCompatibility = $result["compatibility"];
    } else {
      $this->defaultCompatibility = false;
    }
  }

  /**
   * @brief Get compatibility of the licenses present in the file
   * @param ItemTreeBounds $itemTreeBounds Bounds of a file
   * @param string $shortname Shortname of the license to check
   * @return boolean True if licenses identified as compatible, false otherwise
   * @throws InvalidAgentStageException In case the agent is not scheduled for
   *         the upload.
   */
  public function getCompatibilityForFile($itemTreeBounds, $shortname)
  {
    $uploadTreeTableName = $itemTreeBounds->getUploadTreeTableName();
    $uploadTreePk = $itemTreeBounds->getItemId();
    $stmt = __METHOD__.$uploadTreeTableName;
    $sql = "SELECT pfile_fk FROM $uploadTreeTableName UT      
            WHERE UT.uploadtree_pk = $1";
    $result = $this->dbManager->getSingleRow($sql, [$uploadTreePk], $stmt);
    $pfileId = $result["pfile_fk"];
    $uploadId = $itemTreeBounds->getUploadId();
    /** @var ScanJobProxy $scanJobProxy */
    $scanJobProxy = new ScanJobProxy($this->agentDao, $uploadId);
    $scanJobProxy->createAgentStatus([$this->agentName]);
    $selectedScanners = $scanJobProxy->getLatestSuccessfulAgentIds();
    if (!array_key_exists($this->agentName, $selectedScanners)) {
      throw new InvalidAgentStageException("Agent " . $this->agentName .
        " has not been scheduled/completed on the upload.");
    }
    $latestAgentId = $selectedScanners[$this->agentName];
    $license = $this->licenseDao->getLicenseByShortName($shortname, $groupId=null);
    if ($license === null) {
      return true;
    }
    $licenseId = $license->getId();
    $stmt = __METHOD__ . "getCompResult";
    $sql = "SELECT result FROM comp_result
            WHERE pfile_fk = $1 AND agent_fk= $2 AND result = FALSE AND
            (first_rf_fk = $3 OR second_rf_fk = $3);";
    $res = $this->dbManager->getSingleRow($sql,
      [$pfileId, $latestAgentId, $licenseId], $stmt);
    return empty($res); // If not empty, there is at least 1 false result.
  }

  /**
   * @brief Get all the existing license compatibility rules from the database
   * @param int $limit The maximum number of rules to retrieve (default is 10)
   * @param int $offset The number of rules to skip (default is 0)
   * @param string $searchTerm The search term to filter rules by (default is an empty string)
   * @return array An array of license compatibility rules
   */
  public function getAllRules($limit = 10, $offset = 0, $searchTerm = '')
  {
    $sql = "SELECT lr_pk, first_rf_fk, second_rf_fk, first_type, second_type,
      comment, compatibility
      FROM license_rules";
    if (!empty($searchTerm)) {
      $sql .= " WHERE comment ILIKE '$searchTerm'";
    }
    $sql .= " ORDER BY lr_pk LIMIT $limit OFFSET $offset;";
    return $this->dbManager->getRows($sql);
  }

  /**
   * @brief Get the total count of license compatibility rules
   * @param string $searchTerm The search term to filter rules by (default is an empty string)
   * @return int The total count of rules that match the search term
   */
  public function getTotalRulesCount($searchTerm = '')
  {
    $query = "SELECT COUNT(*) as count FROM license_rules";
    if (!empty($searchTerm)) {
      $query .= " WHERE comment ILIKE '$searchTerm'";
    }
    $count = $this->dbManager->getSingleRow($query);
    return $count ? reset($count) : 0;
  }

  /**
   * @brief Insert a new empty rule in the database
   * @return int
   */
  public function insertEmptyRule()
  {
    if (!Auth::isAdmin()) {
      return -1;
    }
    $params = [
      'first_rf_fk' => null,
      'second_rf_fk' => null,
      'first_type' => null,
      'second_type' => null,
      'comment' => '',
      'compatibility' => false
    ];

    $statement = __METHOD__ . ".insertEmptyLicCompatibilityRule";
    $returning = "lr_pk";
    $returnVal = -1;

    try {
      $returnVal = $this->dbManager->insertTableRow("license_rules", $params, $statement, $returning);
    } catch (\Exception $_) {
      $returnVal = -2;
    }
    return $returnVal;
  }

  /**
   * @brief Insert new rule in the database
   * @param string $firstName  First license
   * @param string $secondName Second license
   * @param string $firstType  First license type
   * @param string $secondType Second license type
   * @param string $comment    Comment on the rule
   * @param string $result     Compatibility result of the two licenses
   * @return int -1: rule not inserted, -2 error at insertion otherwise rule ID
   */
  function insertRule($firstName, $secondName, $firstType, $secondType, $comment,
                      $result)
  {
    if (! Auth::isAdmin()) {
      // Only admins can add rules.
      return -1;
    }
    $comment = trim($comment);
    if (empty($comment)) {
      // Cannot insert empty fields.
      return -1;
    }
    $params = [
      'first_rf_fk' => $firstName,
      'second_rf_fk' => $secondName,
      'first_type' => $firstType,
      'second_type' => $secondType,
      'comment' => $comment,
      'compatibility' => $result
    ];
    $statement = __METHOD__ . ".insertNewLicCompatibilityRule";
    $returning = "lr_pk";
    $returnVal = -1;
    try {
      $returnVal = $this->dbManager->insertTableRow("license_rules",
        $params, $statement, $returning);
    } catch (\Exception $_) {
      $returnVal = -2;
    }
    return $returnVal;
  }

  /**
   * @brief Update the existing rules
   * @param array $ruleArray Contains the id of the licenses
   * @throws \UnexpectedValueException if not a single rule is present
   * @return int if 0 rule not updated, -1 on error, otherwise it is updated
   */
  function updateRuleFromArray($ruleArray)
  {
    if (!Auth::isAdmin()) {
      // Only admins can update the rules.
      return -1;
    }
    $updated = 0;
    foreach ($ruleArray as $rulePk => $rule) {
      if (count($rule) < 1) {
        throw new \UnexpectedValueException("Rule has no values");
      }
      $this->isRuleIdValid($rulePk);
      $statement = __METHOD__;
      $params = [$rulePk];
      $updateStatement = [];
      if (array_key_exists("firstLic", $rule)) {
        $params[] = $rule["firstLic"];
        $updateStatement[] = "first_rf_fk = $" . count($params);
        $statement .= ".first_rf_fk";
      }
      if (array_key_exists("secondLic", $rule)) {
        $params[] = $rule["secondLic"];
        $updateStatement[] = "second_rf_fk = $" . count($params);
        $statement .= ".second_rf_fk";
      }
      if (array_key_exists("firstType", $rule)) {
        $params[] = $rule["firstType"];
        $updateStatement[] = "first_type = $" . count($params);
        $statement .= ".first_type";
      }
      if (array_key_exists("secondType", $rule)) {
        $params[] = $rule["secondType"];
        $updateStatement[] = "second_type = $" . count($params);
        $statement .= ".second_type";
      }
      if (array_key_exists("comment", $rule)) {
        $params[] = $rule["comment"];
        $updateStatement[] = "comment = $" . count($params);
        $statement .= ".comment";
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
      $updated += intval($retVal["updated"]);
    }
    return $updated;
  }

  /**
   * @brief Check if rule ID exists in DB.
   * @param int $rulePk Rule ID to check.
   * @throws \UnexpectedValueException If ID does not exists in DB.
   */
  private function isRuleIdValid($rulePk)
  {
    if (! is_int($rulePk)) {
      throw new \UnexpectedValueException("Invalid rule id $rulePk");
    }
    $sql = "SELECT count(*) AS cnt FROM license_rules " .
           "WHERE lr_pk = $1;";

    $ruleCount = $this->dbManager->getSingleRow($sql, [$rulePk]);
    if ($ruleCount['cnt'] < 1) {
      // Invalid rule id
      throw new \UnexpectedValueException("Invalid rule id $rulePk");
    }
  }

  /**
   * @brief Delete a license compatibility rule
   * @param int $rulePk ID of the rule to be deleted
   * @return boolean
   */
  function deleteRule($rulePk)
  {
    if (!Auth::isAdmin()) {
      // Only admins can delete the rules.
      return false;
    }

    $stmt = __METHOD__ . "DeletionOfRule";
    $sql = "DELETE FROM license_rules " .
           "WHERE lr_pk = $1 " .
           "RETURNING lr_pk;";

    $retVal = $this->dbManager->getSingleRow($sql, [$rulePk], $stmt);
    if ($retVal["lr_pk"] == $rulePk) {
      return true;
    } else {
      return false;
    }
  }

  /**
   * Get the default compatibility from the rules table.
   * @return bool
   */
  public function getDefaultCompatibility()
  {
    return $this->defaultCompatibility;
  }
}
