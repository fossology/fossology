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

use Fossology\Lib\Agent\Agent;
use Fossology\Lib\BusinessRules\LicenseMap;
use Fossology\Lib\Dao\ClearingDao;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Data\ClearingDecision;
use Fossology\Lib\Data\DecisionTypes;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Proxy\LicenseViewProxy;
use Fossology\Lib\Proxy\ScanJobProxy;

include_once(__DIR__ . "/version.php");

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
    
    $packageNodes = $this->renderPackage($uploadId);
    
    $additionalUploadIds = array_key_exists(self::UPLOAD_ADDS,$this->args) ? explode(',',$this->args[self::UPLOAD_ADDS]) : array();
    foreach($additionalUploadIds as $additionalId)
    {
      $packageNodes .= $this->renderPackage($additionalId);
    }
   
    $this->writeReport($packageNodes, $uploadId);
    return true;    
  }
  
  private function renderPackage($uploadId)
  {
    $uploadTreeTableName = $this->uploadDao->getUploadtreeTableName($uploadId);
    $itemTreeBounds = $this->uploadDao->getParentItemBounds($uploadId,$uploadTreeTableName);
    $clearingDecisions = $this->clearingDao->getFileClearingsFolder($itemTreeBounds, $this->groupId);
    
    $filesWithLicenses = array();
    foreach ($clearingDecisions as $clearingDecision) {
      if($clearingDecision->getType() == DecisionTypes::IRRELEVANT)
      {
        continue;
      }
      /** @var ClearingDecision $clearingDecision */
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

    $licenseComment = $this->addScannerResults($filesWithLicenses, $uploadId);
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
        'packageName'=>$upload->getFilename(),
        'uploadName'=>$upload->getFilename(),
        'sha1'=>$hashes['sha1'],
        'md5'=>$hashes['md5'],
        'mainLicenses'=>$mainLicenses,
        'licenseComments'=>$licenseComment,
        'fileNodes'=>$fileNodes)
            );
  }

  private function addScannerResults(&$filesWithLicenses, $uploadId)
  {
    $scannerAgents = array_keys($this->agentNames);
    $scanJobProxy = new ScanJobProxy($this->container->get('dao.agent'), $uploadId);
    $scanJobProxy->createAgentStatus($scannerAgents);
    $scannerIds = $scanJobProxy->getLatestSuccessfulAgentIds();
    if(empty($scannerIds))
    {
      return;
    }
    $selectedScanners = '{'.implode(',',$scannerIds).'}';
    $sql= "SELECT uploadtree_pk,rf_fk FROM uploadtree_a ut, license_file
      WHERE ut.pfile_fk=license_file.pfile_fk AND rf_fk IS NOT NULL AND agent_fk=any($1) GROUP BY uploadtree_pk,rf_fk";
    $stmt = __METHOD__ .'.scanner_findings';
    $this->dbManager->prepare($stmt, $sql);
    $res = $this->dbManager->execute($stmt,array($selectedScanners));
    while($row=$this->dbManager->fetchArray($res))
    {
      $reportedLicenseId = $this->licenseMap->getProjectedId($row['uploadtree_pk']);
      $shortName = $this->licenseMap->getProjectedShortname($reportedLicenseId);
      if ($shortName != 'No_license_found' && $shortName != 'Void') {
        $filesWithLicenses[$reportedLicenseId]['scanner'][] = $shortName;
        $this->includedLicenseIds[$reportedLicenseId] = $reportedLicenseId;
      }
    }
    $this->dbManager->freeResult($res);
    return "licenseInfoInFile determined by Scanners $selectedScanners";
  }
  
  private function writeReport($packageNodes, $uploadId)
  {
    global $SysConf;
    $upload = $this->uploadDao->getUpload($uploadId);
    $packageName = $upload->getFilename();

    $fileBase = $SysConf['FOSSOLOGY']['path']."/report/";
    $fileName = $fileBase. "SPDX2_".$packageName.'_'.time().".txt" ;

    if(!is_dir($fileBase)) {
      mkdir($fileBase, 0777, true);
    }
    umask(0133);
    
    $message = $this->renderString('spdx-document.xml.twig',array(
        'documentName'=>$fileBase,
        'uri'=>$fileName,
        'userName'=>$this->container->get('dao.user')->getUserName($this->userId),
        'organisation'=>'',
        'packageNodes'=>$packageNodes,
        'licenseTexts'=>$this->getLicenseTexts())
            );
    file_put_contents($fileName, $message);
    $this->updateReportTable($uploadId, $this->jobId, $fileName);
  }

  private function updateReportTable($uploadId, $jobId, $fileName){
    $this->dbManager->insertTableRow('reportgen',
            array('upload_fk'=>$uploadId, 'job_fk'=>$jobId, 'filepath'=>$fileName),
            __METHOD__);
  }

  /**
   * @param string $templateName
   * @param array $vars
   * @return string
   */
  public function renderString($templateName, $vars)
  {
    return $this->renderer->loadTemplate($templateName)->render($vars);
  }  
  
  private function generateFileNodes($filesWithLicenses, $treeTableName)
  {
    $content = '';
    foreach($filesWithLicenses as $fileId=>$licenses) {
      $content .= $this->renderString('spdx-file.xml.twig',array(
          'fileId'=>$fileId,
          'fileName'=>$this->container->get('dao.tree')->getFullPath($fileId,$treeTableName),
          'concludedLicenses'=>$licenses['concluded'],
          'scannerLicenses'=>$licenses['scanner'])
              );
    }
    return $content;
  }

  /**
   * @return string[] with keys being shortname
   */
  private function getLicenseTexts() {
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
}

$agent = new SpdxTwoAgent();
$agent->scheduler_connect();
$agent->run_scheduler_event_loop();
$agent->scheduler_disconnect(0);
