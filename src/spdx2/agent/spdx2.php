<?php
/*
 * Copyright (C) 2015, Siemens AG
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * version 2 as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 */
namespace Fossology\SpdxTwo;

use Fossology\Lib\Agent\Agent;
use Fossology\Lib\BusinessRules\LicenseMap;
use Fossology\Lib\Dao\ClearingDao;
use Fossology\Lib\Dao\CopyrightDao;
use Fossology\Lib\Dao\TreeDao;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Data\ClearingDecision;
use Fossology\Lib\Data\DecisionTypes;
use Fossology\Lib\Data\Tree\ItemTreeBounds;
use Fossology\Lib\Data\Upload\Upload;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Proxy\LicenseViewProxy;
use Fossology\Lib\Proxy\ScanJobProxy;

include_once(__DIR__ . "/version.php");
include_once(__DIR__ . "/services.php");

class SpdxTwoAgent extends Agent
{
  const UPLOAD_ADDS = "uploadsAdd";
  /** @var UploadDao */
  private $uploadDao;
  /** @var ClearingDao */
  private $clearingDao;
  /** @var DbManager */
  protected $dbManager;
  /** @var Twig_Environment */
  protected $renderer;
  /** @var LicenseMap */
  private $licenseMap;
  /** @var array */
  protected $agentNames = array('nomos' => 'N', 'monk' => 'M');
  /** @var array */
  protected $includedLicenseIds = array();
  /** @var string */
  protected $uri;

  function __construct()
  {
    parent::__construct('spdx2', AGENT_VERSION, AGENT_REV);

    $this->uploadDao = $this->container->get('dao.upload');
    $this->clearingDao = $this->container->get('dao.clearing');
    $this->dbManager = $this->container->get('db.manager');
    $this->renderer = $this->container->get('twig.environment');
    $this->renderer->setCache(false);

    $this->agentSpecifLongOptions[] = self::UPLOAD_ADDS.':';
  }


  function processUploadId($uploadId)
  {
    $this->licenseMap = new LicenseMap($this->dbManager, $this->groupId, LicenseMap::REPORT, true);
    $this->computeUri($uploadId);
    
    $packageNodes = $this->renderPackage($uploadId);
    $additionalUploadIds = array_key_exists(self::UPLOAD_ADDS,$this->args) ? explode(',',$this->args[self::UPLOAD_ADDS]) : array();
    foreach($additionalUploadIds as $additionalId)
    {
      $packageNodes .= $this->renderPackage($additionalId);
    }
   
    $this->writeReport($packageNodes, $uploadId);
    return true;    
  }
  
  protected function renderPackage($uploadId)
  {
    $uploadTreeTableName = $this->uploadDao->getUploadtreeTableName($uploadId);
    $itemTreeBounds = $this->uploadDao->getParentItemBounds($uploadId,$uploadTreeTableName);
    $clearingDecisions = $this->clearingDao->getFileClearingsFolder($itemTreeBounds, $this->groupId);
    $this->heartbeat(0);
    $filesWithLicenses = $this->getFilesWithLicensesFromClearings($clearingDecisions);
    
    $licenseComment = $this->addScannerResults($filesWithLicenses, $itemTreeBounds);
    $this->addCopyrightResults($filesWithLicenses, $uploadId);
    $this->heartbeat(count($filesWithLicenses));
    
    $upload = $this->uploadDao->getUpload($uploadId);
    $fileNodes = $this->generateFileNodes($filesWithLicenses, $upload->getTreeTableName());
    
    $mainLicenseIds = $this->clearingDao->getMainLicenseIds($uploadId, $this->groupId);
    $mainLicenses = array();
    foreach($mainLicenseIds as $licId)
    {
      $reportedLicenseId = $this->licenseMap->getProjectedId($licId);
      $this->includedLicenseIds[$reportedLicenseId] = $reportedLicenseId;
      $mainLicenses[] = $this->licenseMap->getProjectedShortname($reportedLicenseId);
    }
    
    $hashes = $this->uploadDao->getUploadHashes($uploadId);
    return $this->renderString('spdx-package.xml.twig',array(
        'uploadId'=>$uploadId,
        'uri'=>$this->uri,
        'packageName'=>$upload->getFilename(),
        'uploadName'=>$upload->getFilename(),
        'sha1'=>$hashes['sha1'],
        'md5'=>$hashes['md5'],
        'verificationCode'=>$this->getVerificationCode($upload),
        'mainLicenses'=>$mainLicenses,
        'licenseComments'=>$licenseComment,
        'fileNodes'=>$fileNodes)
            );
  }

  /**
   * @param ClearingDecision[] $clearingDecisions
   * @return string[][][] $filesWithLicenses mapping item->'concluded'->(array of shortnames)
   */
  protected function getFilesWithLicensesFromClearings(&$clearingDecisions)
  {
    $filesWithLicenses = array();
    $clearingsProceeded = 0;
    foreach ($clearingDecisions as $clearingDecision) {
      $clearingsProceeded += 1;
      if(($clearingsProceeded&2047)==0)
      {
        $this->heartbeat(count($filesWithLicenses));
      }
      if($clearingDecision->getType() == DecisionTypes::IRRELEVANT)
      {
        continue;
      }
      
      foreach ($clearingDecision->getClearingLicenses() as $clearingLicense) {
        if ($clearingLicense->isRemoved())
        {
          continue;
        }
        $reportedLicenseId = $this->licenseMap->getProjectedId($clearingLicense->getLicenseId());
        $this->includedLicenseIds[$reportedLicenseId] = $reportedLicenseId;
        $filesWithLicenses[$clearingDecision->getUploadTreeId()]['concluded'][] = 
                                                 $this->licenseMap->getProjectedShortname($reportedLicenseId);
      }
    }
    return $filesWithLicenses;
  }

  /**
   * @param string[][][] $filesWithLicenses
   * @param ItemTreeBounds $itemTreeBounds
   */
  protected function addScannerResults(&$filesWithLicenses, $itemTreeBounds)
  {
    $uploadId = $itemTreeBounds->getUploadId();
    $scannerAgents = array_keys($this->agentNames);
    $scanJobProxy = new ScanJobProxy($this->container->get('dao.agent'), $uploadId);
    $scanJobProxy->createAgentStatus($scannerAgents);
    $scannerIds = $scanJobProxy->getLatestSuccessfulAgentIds();
    if(empty($scannerIds))
    {
      return;
    }
    $selectedScanners = '{'.implode(',',$scannerIds).'}';
    $tableName = $itemTreeBounds->getUploadTreeTableName();
    $stmt = __METHOD__ .'.scanner_findings';
    $sql = "SELECT uploadtree_pk,rf_fk FROM $tableName ut, license_file
      WHERE ut.pfile_fk=license_file.pfile_fk AND rf_fk IS NOT NULL AND agent_fk=any($1)";
    $param = array($selectedScanners);
    if ($tableName == 'uploadtree_a') {
      $param[] = $uploadId;
      $sql .= " AND upload_fk=$".count($param);
      $stmt .= $tableName;
    }
    $sql .=  " GROUP BY uploadtree_pk,rf_fk";
    $this->dbManager->prepare($stmt, $sql);
    $res = $this->dbManager->execute($stmt,$param);
    while($row=$this->dbManager->fetchArray($res))
    {
      $reportedLicenseId = $this->licenseMap->getProjectedId($row['rf_fk']);
      $shortName = $this->licenseMap->getProjectedShortname($reportedLicenseId);
      if ($shortName != 'No_license_found' && $shortName != 'Void') {
        $filesWithLicenses[$row['uploadtree_pk']]['scanner'][] = $shortName;
        $this->includedLicenseIds[$reportedLicenseId] = $reportedLicenseId;
      }
    }
    $this->dbManager->freeResult($res);
    return "licenseInfoInFile determined by Scanners $selectedScanners";
  }
  
  protected function addCopyrightResults(&$filesWithLicenses, $uploadId)
  {
    /* @var $copyrightDao CopyrightDao */
    $copyrightDao = $this->container->get('dao.copyright');
    $uploadtreeTable = $this->uploadDao->getUploadtreeTableName($uploadId);
    $allEntries = $copyrightDao->getAllEntries('copyright', $uploadId, $uploadtreeTable, $type='skipcontent'); //, $onlyCleared=true, DecisionTypes::IDENTIFIED, 'textfinding!=\'\'');
    foreach ($allEntries as $finding) {
      $filesWithLicenses[$finding['uploadtree_pk']]['copyrights'][] = \convertToUTF8($finding['content']);
    }
  }       

  protected function computeUri($uploadId)
  {
    global $SysConf;
    $upload = $this->uploadDao->getUpload($uploadId);
    $packageName = $upload->getFilename();

    $fileBase = $SysConf['FOSSOLOGY']['path']."/report/";
    $fileName = $fileBase. "SPDX2_".$packageName.'_'.time().".rdf" ;
    
    $this->uri = $fileName;
  }
  
  protected function writeReport($packageNodes, $uploadId)
  {
    $fileBase = dirname($this->uri);
            
    if(!is_dir($fileBase)) {
      mkdir($fileBase, 0777, true);
    }
    umask(0133);
    
    $message = $this->renderString('spdx-document.xml.twig',array(
        'documentName'=>$fileBase,
        'uri'=>$this->uri,
        'userName'=>$this->container->get('dao.user')->getUserName($this->userId),
        'organisation'=>'',
        'packageNodes'=>$packageNodes,
        'licenseTexts'=>$this->getLicenseTexts())
            );
    file_put_contents($this->uri, $message);
    $this->updateReportTable($uploadId, $this->jobId, $this->uri);
  }

  protected function updateReportTable($uploadId, $jobId, $fileName){
    $this->dbManager->insertTableRow('reportgen',
            array('upload_fk'=>$uploadId, 'job_fk'=>$jobId, 'filepath'=>$fileName),
            __METHOD__);
  }

  /**
   * @param string $templateName
   * @param array $vars
   * @return string
   */
  protected function renderString($templateName, $vars)
  {
    return $this->renderer->loadTemplate($templateName)->render($vars);
  }  
  
  protected function generateFileNodes($filesWithLicenses, $treeTableName)
  {
    $filesProceeded = 0;
    /* @var $treeDao TreeDao */
    $treeDao = $this->container->get('dao.tree');
    $content = '';
    foreach($filesWithLicenses as $fileId=>$licenses) {
      $filesProceeded += 1;
      if(($filesProceeded&2047)==0){
        $this->heartbeat($filesProceeded);
      }
      $hashes = $treeDao->getItemHashes($fileId);
      $content .= $this->renderString('spdx-file.xml.twig',array(
          'fileId'=>$fileId,
          'sha1'=>$hashes['sha1'],
          'md5'=>$hashes['md5'],
          'uri'=>$this->uri,
          'fileName'=>$treeDao->getFullPath($fileId,$treeTableName),
          'concludedLicenses'=>$licenses['concluded'],
          'scannerLicenses'=>$licenses['scanner'],
          'copyrights'=>$licenses['copyrights'])
              );
    }
    return $content;
  }

  /**
   * @return string[] with keys being shortname
   */
  protected function getLicenseTexts() {
    $licenseTexts = array();
    $licenseViewProxy = new LicenseViewProxy($this->groupId,array(LicenseViewProxy::OPT_COLUMNS=>array('rf_pk','rf_shortname','rf_text')));
    $this->dbManager->prepare($stmt=__METHOD__, $licenseViewProxy->getDbViewQuery());
    $res = $this->dbManager->execute($stmt);
    while($row=$this->dbManager->fetchArray($res))
    {
      if (array_key_exists($row['rf_pk'], $this->includedLicenseIds)) {
        $licenseTexts[$row['rf_shortname']] = $row['rf_text'];
      }
    }
    $this->dbManager->freeResult($res);
    return $licenseTexts;
  }

  /**
   * @param UploadTree $upload
   * @return string
   */
  protected function getVerificationCode(Upload $upload)
  {
    $stmt = __METHOD__;
    $param = array();
    if ($upload->getTreeTableName()=='uploadtree_a')
    {
      $sql = $upload->getTreeTableName().' WHERE upload_fk=$1 AND';
      $param[] = $upload->getId();
    }
    else
    {
      $sql = $upload->getTreeTableName().' AND';
      $stmt .= '.'.$upload->getTreeTableName();
    }
      
    $sql = "SELECT STRING_AGG(lower_sha1,'') concat_sha1 FROM 
       (SELECT LOWER(pfile_sha1) lower_sha1 FROM pfile, $sql pfile_fk=pfile_pk ORDER BY pfile_sha1) templist";
    $filelistPack = $this->dbManager->getSingleRow($sql,$param,$stmt);
    
    return sha1($filelistPack['concat_sha1']);
  }

}

$agent = new SpdxTwoAgent();
$agent->scheduler_connect();
$agent->run_scheduler_event_loop();
$agent->scheduler_disconnect(0);
