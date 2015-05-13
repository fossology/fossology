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
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Data\ClearingDecision;
use Fossology\Lib\Data\DecisionTypes;

include_once(__DIR__ . "/version.php");

class SpdxTwoAgent extends Agent
{
  /** @var UploadDao */
  private $uploadDao;
  /** @var ClearingDao */
  private $clearingDao;
  /** @var Twig_Environment */
  protected $renderer;

  function __construct()
  {
    parent::__construct('spdx2', AGENT_VERSION, AGENT_REV);

    $this->uploadDao = $this->container->get('dao.upload');
    $this->clearingDao = $this->container->get('dao.clearing');
    $this->renderer = $this->container->get('twig.environment');
    $this->renderer->setCache(false);

    // $this->agentSpecifLongOptions[] = self::UPLOAD_ADDS.':';
  }


  function processUploadId($uploadId)
  {
    $uploadTreeTableName = $this->uploadDao->getUploadtreeTableName($uploadId);
    $itemTreeBounds = $this->uploadDao->getParentItemBounds($uploadId,$uploadTreeTableName);
    $clearingDecisions = $this->clearingDao->getFileClearingsFolder($itemTreeBounds, $this->groupId);
    $dbManager = $this->container->get('db.manager');
    $licenseMap = new LicenseMap($dbManager, $this->groupId, LicenseMap::REPORT);

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
        $filesWithLicenses[$clearingDecision->getUploadTreeId()][] = 
                $licenseMap->getProjectedShortname($clearingLicense->getLicenseId(), $clearingLicense->getShortName());
      }
    }

    $this->heartbeat(count($filesWithLicenses));
   
    $this->writeReport($filesWithLicenses,$uploadId);
    return true;    
  }

  private function writeReport($filesWithLicenses, $uploadId)
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
    $fileNodes = $this->generateFileNodes($filesWithLicenses, $upload->getTreeTableName());
    
    $message = $this->renderString('spdx-document.xml.twig',array(
        'documentName'=>$fileBase,
        'uri'=>$fileName,
        'userName'=>$this->container->get('dao.user')->getUserName($this->userId),
        'organisation'=>'',
        'fileNodes'=>$fileNodes)
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
          'concludedLicenses'=>$licenses)
              );
    }
    return $content;
  }

}

$agent = new SpdxTwoAgent();
$agent->scheduler_connect();
$agent->run_scheduler_event_loop();
$agent->scheduler_disconnect(0);
