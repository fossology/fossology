<?php
/*
 SPDX-FileCopyrightText: © 2023 Sushant Kumar(sushantmishra02102002@gmail.com)

 SPDX-License-Identifier: GPL-2.0-only
*/
namespace Fossology\Lib\Report;

use Fossology\Lib\Agent\Agent;
use Fossology\Lib\BusinessRules\LicenseMap;
use Fossology\Lib\Dao\ClearingDao;
use Fossology\Lib\Dao\CopyrightDao;
use Fossology\Lib\Dao\LicenseDao;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Data\AgentRef;
use Fossology\Lib\Data\DecisionTypes;
use Fossology\Lib\Data\License;
use Fossology\Lib\Data\LicenseRef;
use Fossology\Lib\Data\Report\FileNode;
use Fossology\Lib\Data\Report\SpdxLicenseInfo;
use Fossology\Lib\Data\Tree\ItemTreeBounds;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Proxy\ScanJobProxy;
use Fossology\Lib\Proxy\UploadTreeProxy;
use Fossology\Lib\Util\StringOperation;
use Symfony\Component\DependencyInjection\ContainerBuilder;


class ReportUtils
{
  /** @var array $agentNames
   * Agent names mapping
   */
  protected $agentNames = AgentRef::AGENT_LIST;
  /** @var ContainerBuilder $container
   * Symfony DI container
   */
  protected $container;
  /** @var LicenseMap $licenseMap
   * LicenseMap object
   */
  private $licenseMap;
  /** @var DbManager $dbManager
   * DbManager object
   */
  protected $dbManager;
  /** @var UploadDao $uploadDao
   * UploadDao object
   */
  private $uploadDao;
  /** @var LicenseDao $licenseDao
   * LicenseDao object
   */
  private $licenseDao;
  /**
   * @var ClearingDao $clearingDao
   * ClearingDao object
   */
  private $clearingDao;

  function __construct()
  {
    global $container;
    $this->container = $container;

    $this->dbManager = $this->container->get('db.manager');
    $this->uploadDao = $this->container->get('dao.upload');
    $this->licenseDao = $this->container->get('dao.license');
    $this->clearingDao = $this->container->get('dao.clearing');
    $this->licenseMap = null;
  }

  /**
   * @brief Add clearing status to the files
   * @param FileNode[] &$filesWithLicenses
   * @param ItemTreeBounds $itemTreeBounds
   * @param int $groupId
   */
  public function addClearingStatus(&$filesWithLicenses, ItemTreeBounds $itemTreeBounds, $groupId)
  {
    $alreadyClearedUploadTreeView = new UploadTreeProxy($itemTreeBounds->getUploadId(),
        array(UploadTreeProxy::OPT_SKIP_THESE => UploadTreeProxy::OPT_SKIP_ALREADY_CLEARED,
              UploadTreeProxy::OPT_ITEM_FILTER => "AND (lft BETWEEN ".$itemTreeBounds->getLeft()." AND ".$itemTreeBounds->getRight().")",
              UploadTreeProxy::OPT_GROUP_ID => $groupId),
        $itemTreeBounds->getUploadTreeTableName(),
        'already_cleared_uploadtree' . $itemTreeBounds->getUploadId());

    $alreadyClearedUploadTreeView->materialize();
    $filesThatShouldStillBeCleared = $alreadyClearedUploadTreeView->getNonArtifactDescendants($itemTreeBounds);
    $alreadyClearedUploadTreeView->unmaterialize();

    $uploadTreeIds = array_keys($filesWithLicenses);
    foreach ($uploadTreeIds as $uploadTreeId) {
      if (!array_key_exists($uploadTreeId, $filesWithLicenses)) {
        $filesWithLicenses[$uploadTreeId] = new FileNode();
      }
      $filesWithLicenses[$uploadTreeId]->setIsCleared(false === array_key_exists($uploadTreeId, $filesThatShouldStillBeCleared));
    }
  }

  /**
   * @brief Attach finding agents to the files and return names of scanners
   * @param FileNode[] &$filesWithLicenses
   * @param ItemTreeBounds $itemTreeBounds
   * @param int $groupId
   * @param SpdxLicenseInfo[] &$licensesInDocument
   * @return array Name(s) of scanners used
   */
  public function addScannerResults(&$filesWithLicenses, ItemTreeBounds $itemTreeBounds, $groupId, &$licensesInDocument)
  {
    if ($this->licenseMap === null) {
      $this->licenseMap = new LicenseMap($this->dbManager, $groupId, LicenseMap::REPORT, true);
    }
    $uploadId = $itemTreeBounds->getUploadId();
    $scannerAgents = array_keys($this->agentNames);
    $scanJobProxy = new ScanJobProxy($this->container->get('dao.agent'), $uploadId);
    $scanJobProxy->createAgentStatus($scannerAgents);
    $scannerIds = $scanJobProxy->getLatestSuccessfulAgentIds();
    if (empty($scannerIds)) {
      return [];
    }
    $tableName = $itemTreeBounds->getUploadTreeTableName();
    $stmt = __METHOD__ .'.scanner_findings';
    $sql = "SELECT DISTINCT uploadtree_pk,rf_fk FROM $tableName ut, license_file
      WHERE ut.pfile_fk=license_file.pfile_fk AND rf_fk IS NOT NULL AND agent_fk=any($1)";
    $param = array('{'.implode(',',$scannerIds).'}');
    if ($tableName == 'uploadtree_a') {
      $param[] = $uploadId;
      $sql .= " AND upload_fk=$".count($param);
      $stmt .= $tableName;
    }
    $sql .=  " GROUP BY uploadtree_pk,rf_fk";
    $rows = $this->dbManager->getRows($sql, $param, $stmt);
    foreach ($rows as $row) {
      $reportedLicenseId = $this->licenseMap->getProjectedId($row['rf_fk']);
      $foundLicense = $this->licenseDao->getLicenseById($reportedLicenseId);
      if ($foundLicense !== null && $foundLicense->getShortName() != 'Void') {
        $reportLicId =  "$reportedLicenseId-" . md5($foundLicense->getText());
        $listedLicense = !StringOperation::stringStartsWith(
          $foundLicense->getSpdxId(), LicenseRef::SPDXREF_PREFIX);

        if (!array_key_exists($row['uploadtree_pk'], $filesWithLicenses)) {
          $filesWithLicenses[$row['uploadtree_pk']] = new FileNode();
        }
        if ($foundLicense->getShortName() != 'No_license_found') {
          $filesWithLicenses[$row['uploadtree_pk']]->addScanner($reportLicId);
        } else {
          $filesWithLicenses[$row['uploadtree_pk']]->addScanner("");
        }
        if (!array_key_exists($reportLicId, $licensesInDocument)) {
          $licensesInDocument[$reportLicId] = (new SpdxLicenseInfo())
            ->setLicenseObj($foundLicense)
            ->setCustomText(false)
            ->setListedLicense($listedLicense);
        }
      }
    }
    return $scannerIds;
  }

  /**
   * @brief Add copyright results to the files
   * @param FileNode[] &$filesWithLicenses
   * @param int $uploadId
   */
  public function addCopyrightResults(&$filesWithLicenses, $uploadId)
  {
    $agentName = array('copyright', 'reso', 'scancode');
    /** @var CopyrightDao $copyrightDao */
    $copyrightDao = $this->container->get('dao.copyright');
    /** @var ScanJobProxy $scanJobProxy */
    $scanJobProxy = new ScanJobProxy($this->container->get('dao.agent'),
      $uploadId);

    $scanJobProxy->createAgentStatus($agentName);
    $selectedScanners = $scanJobProxy->getLatestSuccessfulAgentIds();
    if (!array_key_exists($agentName[0], $selectedScanners) &&
        !array_key_exists($agentName[2], $selectedScanners)) {
      return;
    }

    $latestAgentId = array();
    if (array_key_exists($agentName[0], $selectedScanners)) {
      $latestAgentId[] = $selectedScanners[$agentName[0]];
    }
    if (array_key_exists($agentName[1], $selectedScanners)) {
      $latestAgentId[] = $selectedScanners[$agentName[1]];
    }
    if (array_key_exists($agentName[2], $selectedScanners)) {
      $latestAgentId[] = $selectedScanners[$agentName[2]];
    }

    $ids = implode(',', $latestAgentId);
    $extrawhere = ' agent_fk IN ('.$ids.')';

    $uploadtreeTable = $this->uploadDao->getUploadtreeTableName($uploadId);

    // Get copyright agent findings
    $allScannerEntries = $copyrightDao->getScannerEntries('copyright', $uploadtreeTable, $uploadId, $type='statement', $extrawhere);
    $allEditedEntries = $copyrightDao->getEditedEntries('copyright_decision', $uploadtreeTable, $uploadId, $decisionType=null);

    // Get SCANCODE copyright findings
    if (array_key_exists($agentName[2], $selectedScanners)) {
      $scancodeAgentId = $selectedScanners[$agentName[2]];
      $scancodeExtrawhere = ' agent_fk = '.$scancodeAgentId;
      $scancodeCopyrights = $copyrightDao->getScannerEntries('scancode_copyright',
        $uploadtreeTable, $uploadId, $type='statement', $scancodeExtrawhere);
      $allScannerEntries = array_merge($allScannerEntries, $scancodeCopyrights);
    }

    foreach ($allScannerEntries as $finding) {
      if (!array_key_exists($finding['uploadtree_pk'], $filesWithLicenses)) {
        $filesWithLicenses[$finding['uploadtree_pk']] = new FileNode();
      }
      $filesWithLicenses[$finding['uploadtree_pk']]->addCopyright(\convertToUTF8($finding['content'],false));
    }
    foreach ($allEditedEntries as $finding) {
      if (!array_key_exists($finding['uploadtree_pk'], $filesWithLicenses)) {
        $filesWithLicenses[$finding['uploadtree_pk']] = new FileNode();
      }
      $filesWithLicenses[$finding['uploadtree_pk']]->addCopyright(\convertToUTF8($finding['textfinding'],false));
    }
  }

  /**
   * @brief Given an ItemTreeBounds, get the files with clearings
   * @param ItemTreeBounds $itemTreeBounds
   * @param int $groupId
   * @param Agent $agentObj
   * @param SpdxLicenseInfo[] &$licensesInDocument
   * @return FileNode[] Mapping item->FileNode
   */
  public function getFilesWithLicensesFromClearings(
    ItemTreeBounds $itemTreeBounds, $groupId, $agentObj, &$licensesInDocument)
  {
    if ($this->licenseMap === null) {
      $this->licenseMap = new LicenseMap($this->dbManager, $groupId, LicenseMap::REPORT, true);
    }

    $clearingDecisions = $this->clearingDao->getFileClearingsFolder($itemTreeBounds, $groupId);

    $filesWithLicenses = array();
    $clearingsProceeded = 0;
    foreach ($clearingDecisions as $clearingDecision) {
      $clearingsProceeded += 1;
      if (($clearingsProceeded&2047)==0) {
        $agentObj->heartbeat(0);
      }
      if ($clearingDecision->getType() == DecisionTypes::IRRELEVANT) {
        continue;
      }

      foreach ($clearingDecision->getClearingEvents() as $clearingEvent) {
        $clearingLicense = $clearingEvent->getClearingLicense();
        if ($clearingLicense->isRemoved()) {
          continue;
        }

        if (!array_key_exists($clearingDecision->getUploadTreeId(),
          $filesWithLicenses)) {
          $filesWithLicenses[$clearingDecision->getUploadTreeId()] = new FileNode();
        }

        /* ADD COMMENT */
        $filesWithLicenses[$clearingDecision->getUploadTreeId()]
          ->addComment($clearingLicense->getComment());
        /* ADD Acknowledgement */
        $filesWithLicenses[$clearingDecision->getUploadTreeId()]
          ->addAcknowledgement($clearingLicense->getAcknowledgement());
        $reportedLicenseId = $this->licenseMap->getProjectedId($clearingLicense->getLicenseId());
        $concludedLicense = $this->licenseDao->getLicenseById($reportedLicenseId, $groupId);
        if ($concludedLicense === null) {
          error_log(
              "ReportUtils: warning: clearing-license {$reportedLicenseId} not found; skipping event."
          );
          continue;
        }
        if ($clearingEvent->getReportinfo()) {
          $customLicenseText = $clearingEvent->getReportinfo();
          $reportedLicenseShortname = $concludedLicense->getShortName() . '-' .
            md5($customLicenseText);
          $reportedLicenseShortname = LicenseRef::convertToSpdxId($reportedLicenseShortname, "");

          $reportLicId = "$reportedLicenseId-" . md5($customLicenseText);
          $filesWithLicenses[$clearingDecision->getUploadTreeId()]
            ->addConcludedLicense($reportLicId);
          if (!array_key_exists($reportLicId, $licensesInDocument)) {
            $licenseObj = new License($concludedLicense->getId(),
              $reportedLicenseShortname, $concludedLicense->getFullName(),
              $concludedLicense->getRisk(), $customLicenseText,
              $concludedLicense->getUrl(), $concludedLicense->getDetectorType(),
              $concludedLicense->getSpdxId());
            $licensesInDocument[$reportLicId] = (new SpdxLicenseInfo())
              ->setLicenseObj($licenseObj)
              ->setCustomText(true)
              ->setListedLicense(false);
          }
        } else {
          $reportLicId = $concludedLicense->getId() . "-" .
            md5($concludedLicense->getText());
          $filesWithLicenses[$clearingDecision->getUploadTreeId()]
            ->addConcludedLicense($reportLicId);
          if (!array_key_exists($reportLicId, $licensesInDocument)) {
            $licenseObj = $this->licenseDao->getLicenseById($reportedLicenseId, $groupId);
            $listedLicense = !StringOperation::stringStartsWith(
              $licenseObj->getSpdxId(), LicenseRef::SPDXREF_PREFIX);
            $licensesInDocument[$reportLicId] = (new SpdxLicenseInfo())
              ->setLicenseObj($licenseObj)
              ->setCustomText(false)
              ->setListedLicense($listedLicense);
          }
        }
      }
    }
    return $filesWithLicenses;
  }

  /**
   * Update or insert an entry in the reportgen table.
   *
   * This function checks if an entry with the specified upload_fk and filepath
   * exists in the reportgen table. If it exists, it updates the job_fk for that row.
   * If it does not exist, a new row is inserted with the provided values.
   *
   * @param int $upload_fk  Foreign key for the upload.
   * @param int $job_fk     ID of the job to be updated or inserted.
   * @param string $filepath  Filepath associated with the entry.
   */
  public function updateOrInsertReportgenEntry($upload_fk, $job_fk, $filepath)
  {
    $sqlCheck = "SELECT 1 FROM reportgen WHERE upload_fk = $1 AND filepath = $2";
    $row = $this->dbManager->getSingleRow($sqlCheck, [$upload_fk, $filepath],
      __METHOD__.'.checkReportgenEntry');

    if (!empty($row)) {
      $sqlUpdate = "UPDATE reportgen SET job_fk = $1 WHERE upload_fk = $2 AND filepath = $3";
      $this->dbManager->getSingleRow($sqlUpdate, [$job_fk, $upload_fk, $filepath],
        __METHOD__.'.updateReportgen');
    } else {
      $this->dbManager->insertTableRow('reportgen',
        ['upload_fk' => $upload_fk, 'job_fk' => $job_fk, 'filepath' => $filepath],
        __METHOD__);
    }
  }
}
