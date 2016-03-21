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
namespace Fossology\clixml;

use Fossology\Lib\Agent\Agent;
use Fossology\Lib\Dao\ClearingDao;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Data\ClearingDecision;
use Fossology\Lib\Data\Upload\Upload;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Report\XpClearedGetter;
use Fossology\Lib\Report\LicenseMainGetter;
use Fossology\Lib\Report\LicenseClearedGetter;


include_once(__DIR__ . "/version.php");
include_once(__DIR__ . "/services.php");

class CliXml extends Agent
{

  const OUTPUT_FORMAT_KEY = "outputFormat";
  const DEFAULT_OUTPUT_FORMAT = "clixml";
  const AVAILABLE_OUTPUT_FORMATS = "xml";
  const UPLOAD_ADDS = "uploadsAdd";

  /** @var UploadDao */
  private $uploadDao;
  /** @var ClearingDao */
  private $clearingDao;
  /** @var DbManager */
  protected $dbManager;
  /** @var Twig_Environment */
  protected $renderer;
  /** @var array */
  protected $agentNames = array('nomos' => 'N', 'monk' => 'M');
  /** @var array */
  protected $includedLicenseIds = array();
  /** @var string */
  protected $uri;
  /** @var string */
  protected $packageName;


  /** @var string */
  protected $outputFormat = self::DEFAULT_OUTPUT_FORMAT;

  function __construct()
  {
    parent::__construct('clixml', AGENT_VERSION, AGENT_REV);

    $this->uploadDao = $this->container->get('dao.upload');
    $this->clearingDao = $this->container->get('dao.clearing');
    $this->dbManager = $this->container->get('db.manager');
    $this->renderer = $this->container->get('twig.environment');
    $this->renderer->setCache(false);
    
    $this->cpClearedGetter = new XpClearedGetter("copyright", "statement", false, "(content ilike 'Copyright%' OR content ilike '(c)%')");
    $this->licenseClearedGetter = new LicenseClearedGetter();
    $this->licenseMainGetter = new LicenseMainGetter();

    $this->agentSpecifLongOptions[] = self::UPLOAD_ADDS.':';
    $this->agentSpecifLongOptions[] = self::OUTPUT_FORMAT_KEY.':';
  }

  /**
   * @param string[] $args
   * @param string $key1
   * @param string $key2
   *
   * @return string[] $args
   */
  protected function preWorkOnArgsFlp($args,$key1,$key2)
  {
    $needle = ' --'.$key2.'=';
    if (strpos($args[$key1],$needle) !== false) {
      $exploded = explode($needle,$args[$key1]);
      $args[$key1] = trim($exploded[0]);
      $args[$key2] = trim($exploded[1]);
    }

    return $args;
  }

  /**
   * @param string[] $args
   *
   * @return string[] $args
   */
  protected function preWorkOnArgs($args)
  {
    if ((!array_key_exists(self::OUTPUT_FORMAT_KEY,$args)
         || $args[self::OUTPUT_FORMAT_KEY] === "")
        && array_key_exists(self::UPLOAD_ADDS,$args))
    {
      $args = $this->preWorkOnArgsFlp($args,self::UPLOAD_ADDS,self::OUTPUT_FORMAT_KEY);
    }
    else
    {
      if (!array_key_exists(self::UPLOAD_ADDS,$args) || $args[self::UPLOAD_ADDS] === "")
      {
        $args = $this->preWorkOnArgsFlp($args,self::UPLOAD_ADDS,self::OUTPUT_FORMAT_KEY);
      }
    }
    return $args;
  }

  function processUploadId($uploadId)
  {
    $groupId = $this->groupId;

    $args = $this->preWorkOnArgs($this->args);

    if(array_key_exists(self::OUTPUT_FORMAT_KEY,$args))
    {
      $possibleOutputFormat = trim($args[self::OUTPUT_FORMAT_KEY]);
      if(in_array($possibleOutputFormat, explode(',',self::AVAILABLE_OUTPUT_FORMATS)))
      {
        $this->outputFormat = $possibleOutputFormat;
      }
    }
    $this->computeUri($uploadId);

    $contents = $this->renderPackage($uploadId, $groupId);

    $additionalUploadIds = array_key_exists(self::UPLOAD_ADDS,$args) ? explode(',',$args[self::UPLOAD_ADDS]) : array();
    $packageIds = array($uploadId);
    foreach($additionalUploadIds as $additionalId)
    {
      $contents .= $this->renderPackage($additionalId, $groupId, $userId);
      $packageIds[] = $additionalId;
    }

    $this->writeReport($contents, $packageIds, $uploadId);
    return true;
  }

  protected function getTemplateFile($partname)
  {
    $prefix = $this->outputFormat . "-";
    $postfix = ".twig";
    switch ($this->outputFormat)
    {
      case "clixml":
        $postfix = ".xml" . $postfix;
        break;
    }
    return $prefix . $partname . $postfix;
  }

  protected function getUri($fileBase,$packageName)
  {
    $fileName = $fileBase. strtoupper($this->outputFormat)."_".$packageName.'_'.time();
    switch ($this->outputFormat)
    {
      case "clixml":
        $fileName = $fileName .".xml" ;
        break;
    }
    return $fileName;
  }

  protected function renderPackage($uploadId, $groupId)
  {
    $this->heartbeat(0);
    $licenses = $this->licenseClearedGetter->getCleared($uploadId, $groupId);
    $this->heartbeat(count($licenses["statements"]));
    $licensesMain = $this->licenseMainGetter->getCleared($uploadId, $groupId);
    $this->heartbeat(count($licensesMain["statements"]));
    $copyrights = $this->cpClearedGetter->getCleared($uploadId, $groupId);
    $this->heartbeat(count($copyrights["statements"]));
    $contents = array("licenses" => $licenses["statements"],
                      "copyrights" => $copyrights["statements"],
                      "licensesMain" => $licensesMain["statements"]
                     );
    $contents = $this->typecheck($contents);        
    return $contents;
  }

  protected function typecheck($contents)
  {
    $lenTotalLics = count($contents["licenses"]);
    for($i=0; $i<$lenTotalLics; $i++){
      
      $testVariable = $contents["licenses"][$i]["risk"];

      if($testVariable == "0" || $testVariable == "1" || $testVariable == null) 
      {
        $testVaribale = "white";  
      }
      if($$testVariable == "2" || $$testVariable == "3"){
        $testVariable = "YELLOW";  
      }
      if($$testVariable == "4" ||$testVariable == "5"){
        $$testVariable = "red";  
      }
    }

    $lenCopyrights=count($contents["copyrights"]);    
    for($i=0; $i<$lenCopyrights; $i++){
      $contents["copyrights"][$i]["contentCopy"] = $contents["copyrights"][$i]["content"];
        unset($contents["copyrights"][$i]["content"]);
    }
    return $contents;
  }


  protected function computeUri($uploadId)
  {
    global $SysConf;
    $upload = $this->uploadDao->getUpload($uploadId);
    $this->packageName = $upload->getFilename();

    $fileBase = $SysConf['FOSSOLOGY']['path']."/report/";

    $this->uri = $this->getUri($fileBase,$packageName);
  }

  protected function writeReport($contents, $packageIds, $uploadId)
  {
    $fileBase = dirname($this->uri);

    if(!is_dir($fileBase)) {
      mkdir($fileBase, 0777, true);
    }
    umask(0133);
    $message = $this->renderString($this->getTemplateFile('document'),array(
        'documentName'=>$this->packageName,
        'uri'=>$this->uri,
        'userName'=>$this->container->get('dao.user')->getUserName($this->userId),
        'organisation'=>'',
        'contents'=>$contents,
        'packageIds'=>$packageIds)
            );

    // To ensure the file is valid, replace any non-printable characters with a question mark.
    // 'Non-printable' is ASCII < 0x20 (excluding \r, \n and tab) and 0x7F (delete).
    $message = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/','?',$message);

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
}
$agent = new CliXml();
$agent->scheduler_connect();
$agent->run_scheduler_event_loop();
$agent->scheduler_disconnect(0);
