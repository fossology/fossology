<?php
/*
 SPDX-FileCopyrightText: © Fossology contributors

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Dao;

use Fossology\Lib\Db\DbManager;
use Monolog\Logger;

class PolicyDao
{
  /** @var DbManager */
  private $dbManager;
  /** @var Logger */
  private $logger;

  function __construct(DbManager $dbManager, Logger $logger)
  {
    $this->dbManager = $dbManager;
    $this->logger = $logger;
  }

  /**
   * Get all license policies
   * @return array
   */
  public function getAllPolicies()
  {
    $sql = "SELECT lp.lp_pk, lp.rf_fk, lp.policy_rank, lp.date_added, lp.date_modified, lr.rf_shortname, lr.rf_fullname 
            FROM license_policy lp 
            JOIN license_ref lr ON lp.rf_fk = lr.rf_pk";
    return $this->dbManager->getRows($sql, [], __METHOD__);
  }

  /**
   * Get policy for a specific license
   * @param int $licenseId
   * @return array|null
   */
  public function getPolicyByLicenseId($licenseId)
  {
    $sql = "SELECT lp_pk, rf_fk, policy_rank, date_added, date_modified FROM license_policy WHERE rf_fk = $1";
    $row = $this->dbManager->getSingleRow($sql, [$licenseId], __METHOD__);
    return $row !== false ? $row : null;
  }

  /**
   * Add or update policy for a license, and log it.
   * @param int $licenseId
   * @param int $rank
   * @param int $userId
   * @param string $source
   * @param string|null $requestIp
   * @return void
   */
  public function setLicensePolicy($licenseId, $rank, $userId, $source = 'API', $requestIp = null)
  {
    $this->dbManager->begin();
    try {
      // Concurrency lock using FOR UPDATE
      $sql = "SELECT lp_pk, rf_fk, policy_rank, date_added, date_modified FROM license_policy WHERE rf_fk = $1 FOR UPDATE";
      $existing = $this->dbManager->getSingleRow($sql, [$licenseId], __METHOD__ . '.lock');
      $oldRank = null;

      if ($existing) {
        $oldRank = $existing['policy_rank'];
        if ($oldRank == $rank) {
            $this->dbManager->commit();
            return;
        }
        $sql = "UPDATE license_policy SET policy_rank = $1, date_modified = now() WHERE rf_fk = $2";
        $this->dbManager->prepare(__METHOD__ . ".update", $sql);
        $this->dbManager->execute(__METHOD__ . ".update", [$rank, $licenseId]);
      } else {
        $sql = "INSERT INTO license_policy (rf_fk, policy_rank) VALUES ($1, $2)";
        $this->dbManager->prepare(__METHOD__ . ".insert", $sql);
        $this->dbManager->execute(__METHOD__ . ".insert", [$licenseId, $rank]);
      }

      // Audit log
      $logSql = "INSERT INTO license_policy_log (rf_fk, old_rank, new_rank, user_fk, source, request_ip) VALUES ($1, $2, $3, $4, $5, $6)";
      $logParams = [$licenseId, $oldRank, $rank, $userId, $source, $requestIp];
      $this->dbManager->prepare(__METHOD__ . ".log", $logSql);
      $this->dbManager->execute(__METHOD__ . ".log", $logParams);

      $this->dbManager->commit();
    } catch (\Exception $e) {
      $this->dbManager->rollback();
      throw $e;
    }
  }

  /**
   * Delete policy for a license
   * @param int $licenseId
   * @param int $userId
   * @param string $source
   * @param string|null $requestIp
   * @return bool Processed or not
   */
  public function deleteLicensePolicy($licenseId, $userId, $source = 'API', $requestIp = null)
  {
    $this->dbManager->begin();
    try {
      // Concurrency lock using FOR UPDATE
      $sql = "SELECT lp_pk, rf_fk, policy_rank, date_added, date_modified FROM license_policy WHERE rf_fk = $1 FOR UPDATE";
      $existing = $this->dbManager->getSingleRow($sql, [$licenseId], __METHOD__ . '.lock');
      if (!$existing) {
        $this->dbManager->commit();
        return false;
      }
      $oldRank = $existing['policy_rank'];

      $sql = "DELETE FROM license_policy WHERE rf_fk = $1";
      $this->dbManager->prepare(__METHOD__ . ".delete", $sql);
      $this->dbManager->execute(__METHOD__ . ".delete", [$licenseId]);

      // Audit log (-1 for deleted)
      $logSql = "INSERT INTO license_policy_log (rf_fk, old_rank, new_rank, user_fk, source, request_ip) VALUES ($1, $2, -1, $3, $4, $5)";
      $logParams = [$licenseId, $oldRank, $userId, $source, $requestIp];
      $this->dbManager->prepare(__METHOD__ . ".log", $logSql);
      $this->dbManager->execute(__METHOD__ . ".log", $logParams);

      $this->dbManager->commit();
      return true;
    } catch (\Exception $e) {
      $this->dbManager->rollback();
      throw $e;
    }
  }

  /**
   * Get user's active policy filter.
   * @param int $userId
   * @return array Array of integers (0, 1, 2)
   */
  public function getPolicyFilter(int $userId): array
  {
    $row = $this->dbManager->getSingleRow("SELECT policy_filter FROM users WHERE user_pk = $1", [$userId], __METHOD__);
    if ($row && !empty($row['policy_filter'])) {
      $decoded = json_decode($row['policy_filter'], true);
      return is_array($decoded) ? $decoded : [];
    }
    return [];
  }

  /**
   * Set user's active policy filter.
   * @param int $userId
   * @param array $filters Array of integers (0, 1, 2)
   */
  public function setPolicyFilter(int $userId, array $filters): void
  {
    $validFilters = array_filter($filters, function($v) {
      return in_array((int)$v, [0, 1, 2], true);
    });
    $validFilters = array_values(array_unique(array_map('intval', $validFilters)));
    
    $json = json_encode($validFilters) ?: '[]';
    $sql = "UPDATE users SET policy_filter = $1 WHERE user_pk = $2";
    $this->dbManager->prepare(__METHOD__, $sql);
    $this->dbManager->execute(__METHOD__, [$json, $userId]);
  }
}
