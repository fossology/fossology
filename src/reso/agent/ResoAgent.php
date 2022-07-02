<?php
/*
 SPDX-FileCopyrightText: © 2021 Orange
 Author: Bartłomiej Dróżdż <bartlomiej.drozdz@orange.com>
 Author: Piotr Pszczoła <piotr.pszczola@orange.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Reso;

use Fossology\Lib\Agent\Agent;
use Fossology\Lib\Dao\AgentDao;
use Fossology\Lib\Dao\UploadDao;


include_once(__DIR__ . "/version.php");

/**
 * @file
 * @brief Reso  agent source
 * @class Reso
 * @brief The reso  agent
 */
class ResoAgent extends Agent
{

  /** @var reuse software
   * license file suffix (extention)
   */
  const REUSE_FILE_SUFFIX = ".license";

  /** @var UploadDao $uploadDao
   * UploadDao object
   */
  private $uploadDao;

  /**
   * @var AgentDao $agentDao
   * AgentDao object
   */
  protected $agentDao;

  /**
   * ResoAgent constructor.
   * @throws \Exception
   */
  function __construct()
  {
    parent::__construct(RESO_AGENT_NAME, AGENT_VERSION, AGENT_REV);
    $this->uploadDao = $this->container->get('dao.upload');
    $this->agentDao = $this->container->get('dao.agent');
  }

  /**
   * @brief Run reso for a package
   * @param int $uploadId
   * @return bool
   * @throws \Fossology\Lib\Exception
   * @see Fossology::Lib::Agent::Agent::processUploadId()
   */
  function processUploadId($uploadId)
  {
    $uploadTreeTableName = $this->uploadDao->getUploadtreeTableName($uploadId);

    $reSoUploadFiles = $this->findReSoUploadFiles($uploadId);
    if (empty($reSoUploadFiles)) {
      return true;
    }
    $linkedFiles = $this->associateBaseFile($reSoUploadFiles, $uploadTreeTableName);
    $this->copyOjoFindings($linkedFiles,$uploadId);

    return true;
  }

  /**
   * @brief Find all files with specific suffix.
   * @param int $uploadId
   * @return array[] Array of uploadtree objects
   */
  protected function findReSoUploadFiles($uploadId)
  {
    $uploadTreeTableName = $this->uploadDao->getUploadtreeTableName($uploadId);
    $param = array();
    $stmt = __METHOD__ .'reSo_files'.self::REUSE_FILE_SUFFIX;
    $sql = "SELECT * FROM $uploadTreeTableName
      WHERE upload_fk=$1 AND pfile_fk != 0 AND ufile_name like '%" . self::REUSE_FILE_SUFFIX . "' ";
    $param[] = $uploadId;
    $this->dbManager->prepare($stmt, $sql);
    $res = $this->dbManager->execute($stmt,$param);

    return $this->dbManager->fetchAll($res);
  }

  /**
   * @brief Find and associate base file to files containting license info
   * @param array[] $reSoUploadFiles list of uploadtree objects to process
   * @param string $uploadTreeTableName - uploadtree table for upload
   * @return array[][] multi-dimensional array with license holding files with associated base files
   */
  protected function associateBaseFile($reSoUploadFiles, $uploadTreeTableName)
  {
    $mergedArray = array();
    foreach ($reSoUploadFiles as $row) {
      $baseFileRow = $this->findAssociatedFile($row, $uploadTreeTableName);
      if (!empty($baseFileRow)) {
        $row['assoc_file'] = $baseFileRow;
        $mergedArray[] = $row;
      }
    }
    return $mergedArray;
  }

  /**
   * @brief Find associated base file.
   * @param array[] $row - uploadtree entry to process
   * @param string $uploadTreeTableName - uploadtree table for upload
   * @return array[] uploadtree entry containing base file for input param
   */
  protected function findAssociatedFile($row, $uploadTreeTableName)
  {
    $stmt = __METHOD__ .'find_reso_base_file';
    $sql = "SELECT * FROM $uploadTreeTableName
      WHERE upload_fk=$1 AND ufile_name =$2 AND pfile_fk != 0 AND realparent=$3";
    $param[] = $row['upload_fk'];
    $param[] = substr($row['ufile_name'],0,-1 * abs(strlen(self::REUSE_FILE_SUFFIX)));
    $param[] = $row['realparent'];
    $this->dbManager->prepare($stmt, $sql);
    $res = $this->dbManager->execute($stmt,$param);

    return $this->dbManager->fetchAll($res);
  }

  /**
   * @brief Copy license from licene holder to base file
   * @param array[][] $linkedFiles - multi-dimensional array with license holding files with associated base files
   * @param int $uploadId
   * @return true on success, Error on failure
   */
  protected function copyOjoFindings($linkedFiles,$uploadId)
  {
    //find agentId used for specific upload
    $latestOjoAgent=$this->agentDao->agentARSList("ojo_ars",$uploadId);
    $resoAgentId=$this->agentDao->getCurrentAgentId("reso");

    foreach ($linkedFiles as $file) {
      $this->heartbeat(1);
      $param = array();
      $insertParam = array();
      $stmt = __METHOD__ .'readLicenseFindingsOjo';
      $sql = "SELECT * FROM license_file
        WHERE pfile_fk =$1 AND agent_fk=$2 AND rf_fk IS NOT NULL";
      $param[] = $file['pfile_fk'];
      $param[] = $latestOjoAgent[0]['agent_fk'];

      $this->dbManager->prepare($stmt, $sql);
      $res = $this->dbManager->execute($stmt,$param);
      while ($row=$this->dbManager->fetchArray($res)) {
        $insertParam = array();
        $Istmt = __METHOD__ .'insertLicenseFindingsReso';
        $Isql = "INSERT INTO license_file(rf_fk, agent_fk, pfile_fk)
                 (SELECT $1, $2, $3 WHERE NOT EXISTS (SELECT fl_pk FROM license_file where rf_fk=$1 AND agent_fk=$2 AND pfile_fk=$3))
                 RETURNING fl_pk";
        $insertParam[] = $row['rf_fk'];
        $insertParam[] = $resoAgentId;
        $insertParam[] = $file['assoc_file'][0]['pfile_fk'];

        $this->dbManager->prepare($Istmt, $Isql);
        $Ires = $this->dbManager->execute($Istmt,$insertParam);
      }
    }
    return true;
  }
}
