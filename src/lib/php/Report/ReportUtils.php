<?php
/*
 SPDX-FileCopyrightText: © 2023 Sushant Kumar(sushantmishra02102002@gmail.com)

 SPDX-License-Identifier: GPL-2.0-only
*/
namespace Fossology\Lib\Report;

use Fossology\Lib\BusinessRules\LicenseMap;
use Fossology\Lib\Dao\CopyrightDao;
use Fossology\Lib\Dao\LicenseDao;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Data\AgentRef;
use Fossology\Lib\Data\LicenseRef;
use Fossology\Lib\Data\Report\FileNode;
use Fossology\Lib\Data\Report\SpdxLicenseInfo;
use Fossology\Lib\Data\Tree\ItemTreeBounds;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Proxy\LicenseViewProxy;
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

  function __construct()
  {
    global $container;
    $this->container = $container;

    $this->dbManager = $this->container->get('db.manager');
    $this->uploadDao = $this->container->get('dao.upload');
    $this->licenseDao = $this->container->get('dao.license');
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
      if ($foundLicense !== null && $foundLicense->getShortName() != 'Void' &&
          $foundLicense->getShortName() != 'No_license_found') {
        $reportLicId =  "$reportedLicenseId-" . md5($foundLicense->getText());
        $listedLicense = !StringOperation::stringStartsWith(
          $foundLicense->getSpdxId(), LicenseRef::SPDXREF_PREFIX);

        if (!array_key_exists($row['uploadtree_pk'], $filesWithLicenses)) {
          $filesWithLicenses[$row['uploadtree_pk']] = new FileNode();
        }
        $filesWithLicenses[$row['uploadtree_pk']]->addScanner($reportLicId);
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
    $agentName = array('copyright', 'reso');
    /** @var CopyrightDao $copyrightDao */
    $copyrightDao = $this->container->get('dao.copyright');
    /** @var ScanJobProxy $scanJobProxy */
    $scanJobProxy = new ScanJobProxy($this->container->get('dao.agent'),
      $uploadId);

    $scanJobProxy->createAgentStatus($agentName);
    $selectedScanners = $scanJobProxy->getLatestSuccessfulAgentIds();
    if (!array_key_exists($agentName[0], $selectedScanners)) {
      return;
    }
    $latestAgentId[] = $selectedScanners[$agentName[0]];
    if (array_key_exists($agentName[1], $selectedScanners)) {
      $latestAgentId[] = $selectedScanners[$agentName[1]];
    }
    $ids = implode(',', $latestAgentId);
    $extrawhere = ' agent_fk IN ('.$ids.')';

    $uploadtreeTable = $this->uploadDao->getUploadtreeTableName($uploadId);
    $allScannerEntries = $copyrightDao->getScannerEntries('copyright', $uploadtreeTable, $uploadId, $type='statement', $extrawhere);
    $allEditedEntries = $copyrightDao->getEditedEntries('copyright_decision', $uploadtreeTable, $uploadId, $decisionType=null);
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
   * @brief Get the license texts from fossology
   * @param int $groupId
   * @param[in,out] bool &$includedLicenseIds
   * @return string[] with keys being shortname
   */
  public function getLicenseTexts($groupId, &$includedLicenseIds)
  {
    $licenseTexts = array();
    $licenseViewProxy = new LicenseViewProxy($groupId,
      [LicenseViewProxy::OPT_COLUMNS => [
        'rf_pk','rf_shortname','rf_spdx_id','rf_fullname','rf_text','rf_url'
      ]]);
    $this->dbManager->prepare($stmt=__METHOD__, $licenseViewProxy->getDbViewQuery());
    $res = $this->dbManager->execute($stmt);

    while ($row = $this->dbManager->fetchArray($res)) {
      if (array_key_exists($row['rf_pk'], $includedLicenseIds)) {
        $shortname = $row['rf_shortname'];
        $spdxId = LicenseRef::convertToSpdxId($shortname, $row['rf_spdx_id']);
        $licenseTexts[$spdxId] = [
          'text' => $row['rf_text'],
          'name' => $row['rf_fullname'] ?: $shortname,
          'id'   => $spdxId,
          'url'  => $row['rf_url']
        ];
      }
    }
    foreach ($includedLicenseIds as $license => $customText) {
      if (true !== $customText) {
        $licenseTexts[$license] = [
          'text' => $customText,
          'name' => $license,
          'id'   => $license,
          'url'  => ''
        ];
      }
    }
    $this->dbManager->freeResult($res);
    return $licenseTexts;
  }
}
