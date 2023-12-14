<?php
/*
 SPDX-FileCopyrightText: © 2022 Siemens AG
 Author: Shaheem Azmal M MD <shaheem.azmal@siemens.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Dao;

use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Proxy\UploadTreeProxy;
use Monolog\Logger;

/**
 * Class AllDecisionsDao
 * @package Fossology\Lib\Dao
 */
class AllDecisionsDao
{
  /** @var DbManager */
  private $dbManager;
  /** @var AgentDao */
  private $agentDao;
  /** @var UploadDao */
  private $uploadDao;

  /**
   * @param DbManager $dbManager
   * @param Logger $logger
   */
  function __construct(DbManager $dbManager, Logger $logger)
  {
    $this->dbManager = $dbManager;
    $this->logger = $logger;
    global $container;
    $this->agentDao = $container->get('dao.agent');
    $this->uploadDao = $container->get('dao.upload');
  }

  /**
   * @param int $uploadId
   * @return array
   */
  public function getAllJobTypeForUpload($uploadId)
  {
    $extendedQuery = " AND jq_type LIKE 'nomos' OR jq_type LIKE 'monk'".
                     " OR jq_type LIKE 'ojo' OR jq_type LIKE 'copyright'".
                     " OR jq_type LIKE 'ecc' OR jq_type LIKE 'ipra'";
    $sql = "SELECT DISTINCT(jq_type) FROM jobqueue INNER JOIN job ON jq_job_fk=job_pk " .
      "WHERE jq_end_bits ='1'".$extendedQuery." AND job_upload_fk=$1;";
    $statementName = __METHOD__ . ".getAllFinishedJobsForUploadId";
    $rows = $this->dbManager->getRows($sql, array($uploadId), $statementName);

    return array_column($rows, 'jq_type');
  }

  /**
   * @param int $uploadId
   * @param int $groupId
   * @param int $userId
   * @param string $skip
   * @return string
   */
  public function getAllAgentEntriesForPfile($uploadId, $groupId, $userId, $skip)
  {
    $uploadTreeTableName = $this->uploadDao->getUploadtreeTableName($uploadId);
    return new UploadTreeProxy($uploadId,
    array(UploadTreeProxy::OPT_SKIP_THESE => $skip,
      UploadTreeProxy::OPT_GROUP_ID => $groupId),
    $uploadTreeTableName,
    'no_license_uploadtree' . $uploadId);
  }

  /**
   * @param int $uploadId
   * @param int $groupId
   * @param int $userId
   * @param string $skip
   * @return array
   */
  public function getSqlQueryDataPfile($uploadId, $groupId, $userId, $skip='noLicense')
  {
    $allLicEntries = $this->getAllAgentEntriesForPfile($uploadId, $groupId, $userId, $skip);
    $sql = "WITH latestResultsPfile AS (".$allLicEntries->getDbViewQuery().")".
         "SELECT pfile_fk, pfile_sha1 || '.' || pfile_md5 || '.' || pfile_size AS pfilehash FROM latestResultsPfile LSP".
         " INNER JOIN pfile p ON LSP.pfile_fk = p.pfile_pk " ;
    $statementName = __METHOD__ . ".allLicEntrieslatestResultsPfile." . $skip;
    $rows = $this->dbManager->getRows($sql, array(), $statementName);
    return array_column($rows,'pfilehash','pfile_fk');
  }

  /**
   * @param int $uploadId
   * @param string $tableName
   * @return array
   */
  public function getAllAgentUploadTreeDataForUpload($uploadId, $tableName)
  {
    $sql = "SELECT DISTINCT ON(uploadtree_pk) " .
         "uploadtree_pk, ut.pfile_fk, ut.lft, ut.rgt FROM uploadtree ut " .
         "INNER JOIN ".$tableName." ON ".$tableName.".pfile_fk=ut.pfile_fk WHERE upload_fk=$1";
    $statementName = __METHOD__ . ".allLicEntriesuploadtreeForUpload";
    $rows = $this->dbManager->getRows($sql, array($uploadId), $statementName);
    foreach ($rows as $index => $row) {
      $rows[$index]["path"] = implode("/",
        array_column(Dir2Path($row["uploadtree_pk"]), "ufile_name"));
    }
    return $rows;
  }

  /**
   * @param int $uploadId
   * @param string $tableName
   * @return array
   */
  public function getAllClearingDecisionDataForUpload($uploadId, $tableName)
  {
    $columns = "clearing_decision_pk, cd.uploadtree_fk, cd.pfile_fk, decision_type, scope, date_added";
    $sql = "SELECT ".$columns." FROM clearing_decision cd ".
         " INNER JOIN ".$tableName." ON ".$tableName.".pfile_fk=cd.pfile_fk ".
         " INNER JOIN uploadtree ut ON cd.uploadtree_fk=ut.uploadtree_pk WHERE ut.upload_fk=$1";
    $statementName = __METHOD__ . ".allLicEntriesClearingDecisionForUpload";
    return $this->dbManager->getRows($sql, array($uploadId), $statementName);
  }

  /**
   * @param int $uploadId
   * @param string $tableName
   * @return array
   */
  public function getAllClearingEventDataForUpload($uploadId, $tableName)
  {
    $columns = "DISTINCT(clearing_event_pk), ce.uploadtree_fk, rf_fk, removed, ".
             " lrb_pk, type_fk, comment, reportinfo, acknowledgement, date_added";
    $sql = "SELECT ".$columns." FROM clearing_event ce ".
         " INNER JOIN uploadtree ut ON ce.uploadtree_fk=ut.uploadtree_pk ".
         " INNER JOIN ".$tableName." ON ".$tableName.".pfile_fk=ut.pfile_fk " .
         " LEFT JOIN jobqueue jq ON jq.jq_job_fk=ce.job_fk AND jq.jq_type='monkbulk'" .
         " LEFT JOIN license_ref_bulk lrb ON jq.jq_args::int=lrb_pk " .
         "WHERE ut.upload_fk=$1";
    $statementName = __METHOD__ . ".allLicEntriesClearingEventForUpload";
    return $this->dbManager->getRows($sql, array($uploadId), $statementName);
  }

  /**
   * @param int $uploadId
   * @param string $tableName
   * @return array
   */
  public function getAllClearingDecisionEventDataForUpload($uploadId, $tableName)
  {
    $columns = "clearing_decision_fk, clearing_event_fk";
    $sql = "SELECT ".$columns." FROM clearing_decision_event cde ".
         " INNER JOIN clearing_decision cd ON cde.clearing_decision_fk=cd.clearing_decision_pk ".
         " INNER JOIN ".$tableName." ON ".$tableName.".pfile_fk=cd.pfile_fk".
         " INNER JOIN uploadtree ut ON cd.uploadtree_fk=ut.uploadtree_pk  WHERE ut.upload_fk=$1";
    $statementName = __METHOD__ . ".allLicEntriesClearingDecisionEventForUpload";
    return $this->dbManager->getRows($sql, array($uploadId), $statementName);
  }

  /**
   * @param int $uploadId
   * @return array
   */
  public function getAllLicenseRefBulkDataForUpload($uploadId)
  {
    $columns = "lrb_pk, rf_text, uploadtree_fk, ignore_irrelevant, bulk_delimiters, scan_findings";
    $sql = "SELECT ".$columns." FROM license_ref_bulk lrb ".
         " INNER JOIN uploadtree ut ON lrb.uploadtree_fk=ut.uploadtree_pk WHERE ut.upload_fk=$1";
    $statementName = __METHOD__ . ".allLicEntriesLicenseRefBulkForUpload";
    return $this->dbManager->getRows($sql, array($uploadId), $statementName);
  }

  /**
   * @param int $uploadId
   * @return array
   */
  public function getAllLicenseSetBulkDataForUpload($uploadId)
  {
    $columns = "rf_fk, removing, lrb_fk, comment, reportinfo, acknowledgement";
    $sql = "SELECT ".$columns." FROM license_set_bulk lsb ".
         " INNER JOIN license_ref_bulk lrb ON lsb.lrb_fk=lrb.lrb_pk ".
         " INNER JOIN uploadtree ut ON lrb.uploadtree_fk=ut.uploadtree_pk WHERE ut.upload_fk=$1";
    $statementName = __METHOD__ . ".allLicEntriesLicenseSetBulkForUpload";
    return $this->dbManager->getRows($sql, array($uploadId), $statementName);
  }

  /**
   * @param int $uploadId
   * @return array
   */
  public function getAllBulkHighlightDataForUpload($uploadId)
  {
    $columns = "clearing_event_fk, lrb_fk, start, len";
    $sql = "SELECT $columns FROM highlight_bulk hb INNER JOIN license_ref_bulk lrb ON hb.lrb_fk=lrb.lrb_pk " .
      "INNER JOIN uploadtree ut ON lrb.uploadtree_fk=ut.uploadtree_pk WHERE ut.upload_fk=$1";
    $statementName = __METHOD__ . ".allBulkHighlightDataForUpload";
    return $this->dbManager->getRows($sql, [$uploadId], $statementName);
  }

  /**
   * @param string $pfileTableName
   * @param string $givenTableName
   * @return array
   */
  public function getAllDataForGivenTableUpload($pfileTableName, $givenTableName)
  {
    $columns = "DISTINCT(".$givenTableName."_pk), ".$givenTableName.".pfile_fk, content, hash, copy_startbyte, copy_endbyte";
    $sql = "SELECT ".$columns." FROM ".$givenTableName." ".
         " INNER JOIN ".$pfileTableName." ON ".$pfileTableName.".pfile_fk=".$givenTableName.".pfile_fk";
    $statementName = __METHOD__ . ".allLicEntries".$givenTableName."ForUpload";
    return $this->dbManager->getRows($sql, array(), $statementName);
  }

  /**
   * @param string $pfileTableName
   * @param string $givenTableName
   * @return array
   */
  public function getAllDataForGivenDecisionTableUpload($pfileTableName, $givenTableName)
  {
    $columns = "DISTINCT(".$givenTableName."_pk), ".$givenTableName.".pfile_fk, ".
             " clearing_decision_type_fk, description, textfinding, hash, comment";
    $sql = "SELECT ".$columns." FROM ".$givenTableName." ".
         " INNER JOIN ".$pfileTableName." ON ".$pfileTableName.".pfile_fk=".$givenTableName.".pfile_fk";
    $statementName = __METHOD__ . ".allLicEntries".$givenTableName."ForUpload";
    return $this->dbManager->getRows($sql, array(), $statementName);
  }

  /**
   * @param int $uploadId
   * @param string $givenTableName
   * @param string $tableType
   * @return array
   */
  public function getAllDataForGivenEventTableUpload($uploadId, $givenTableName, $tableType)
  {
    $columns = "DISTINCT(".$givenTableName."_pk), ".$tableType."_fk, ".
             "uploadtree_fk, content, hash, is_enabled, scope";
    $sql = "SELECT ".$columns." FROM ".$givenTableName." ".
         " INNER JOIN uploadtree ut ON ".$givenTableName.".uploadtree_fk=ut.uploadtree_pk".
         " WHERE ".$givenTableName.".upload_fk=$1";
    $statementName = __METHOD__ . ".allLicEntries".$givenTableName."ForUpload";
    return $this->dbManager->getRows($sql, array($uploadId), $statementName);
  }

  /**
   * @param int $uploadId
   * @return array
   */
  public function getAllLicenseDataForUpload($uploadId)
  {
    $columns = "rf_pk, rf_shortname, rf_fullname, rf_text, rf_url, rf_notes, rf_md5, rf_risk";
    $sql = "WITH alllicense AS (" .
      "SELECT $columns, false AS is_candidate FROM ONLY license_ref UNION " .
      "SELECT $columns, true AS is_candidate FROM ONLY license_candidate) " .
      "SELECT lf.* FROM alllicense AS lf " .
      " INNER JOIN clearing_event ce ON ce.rf_fk=lf.rf_pk " .
      " INNER JOIN uploadtree ut ON ce.uploadtree_fk=ut.uploadtree_pk WHERE ut.upload_fk=$1" .
      " UNION DISTINCT " .
      "SELECT lf.* FROM alllicense AS lf " .
      " INNER JOIN upload_clearing_license ucl ON ucl.rf_fk = lf.rf_pk AND ucl.upload_fk=$1;";
    $statementName = __METHOD__ . ".allLicEntriesLicenseForUpload";
    return $this->dbManager->getRows($sql, array($uploadId), $statementName);
  }

  /**
   * @param int $uploadId
   * @param int $groupId
   * @param int $userId
   * @return array
   */
  public function getAllAgentPfileIdsForUpload($uploadId, $groupId, $userId)
  {
    $licensePfile = array();
    $copyrightPfile = array();
    $eccPfile = array();
    $ipPfile = array();
    $licenseAgentNames = array('nomos','monk','ojo');
    $executedAgents = $this->getAllJobTypeForUpload($uploadId);
    foreach ($executedAgents as $agent) {
      if (in_array($agent,$licenseAgentNames)) {
        $executedAgents = array_diff($executedAgents,$licenseAgentNames);
        $licensePfile = $this->getSqlQueryDataPfile($uploadId, $groupId, $userId);
      } else if ($agent == 'copyright') {
        $executedAgents = array_diff($executedAgents,array('copyright'));
        $copyrightPfile = $this->getSqlQueryDataPfile($uploadId, $groupId, $userId, 'noCopyright');
      } else if ($agent == 'ecc') {
        $executedAgents = array_diff($executedAgents,array('ecc'));
        $eccPfile = $this->getSqlQueryDataPfile($uploadId, $groupId, $userId, 'noEcc');
      } else if ($agent == 'ipra') {
        $executedAgents = array_diff($executedAgents,array('ipra'));
        $ipPfile = $this->getSqlQueryDataPfile($uploadId, $groupId, $userId, 'noIpra');
      }
    }
    return $licensePfile + $copyrightPfile + $eccPfile + $ipPfile;
  }
}
