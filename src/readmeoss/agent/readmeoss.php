<?php
/*
 * Author: Daniele Fognini, Shaheem Azmal M MD
 * Copyright (C) 2016-2018, Siemens AG
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
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Report\LicenseClearedGetter;
use Fossology\Lib\Report\XpClearedGetter;
use Fossology\Lib\Report\LicenseMainGetter;

include_once(__DIR__ . "/version.php");

class ReadmeOssAgent extends Agent
{
  const UPLOAD_ADDS = "uploadsAdd";

  /** @var LicenseClearedGetter  */
  private $licenseClearedGetter;

  /** @var XpClearedGetter */
  private $cpClearedGetter;

  /** @var LicenseMainGetter  */
  private $licenseMainGetter;

  /** @var UploadDao */
  private $uploadDao;

  /** @var int[] */
  protected $additionalUploadIds = array();

  function __construct()
  {
    $this->cpClearedGetter = new XpClearedGetter("copyright", "statement");
    $this->licenseClearedGetter = new LicenseClearedGetter();
    $this->licenseMainGetter = new LicenseMainGetter();

    parent::__construct(README_AGENT_NAME, AGENT_VERSION, AGENT_REV);

    $this->uploadDao = $this->container->get('dao.upload');

    $this->agentSpecifLongOptions[] = self::UPLOAD_ADDS.':';
  }

  /**
   * @todo without wrapper
   */
  function processUploadId($uploadId)
  {
    $groupId = $this->groupId;

    $args = $this->args;
    $this->additionalUploadIds = array_key_exists(self::UPLOAD_ADDS,$args) ? explode(',',$args[self::UPLOAD_ADDS]) : array();
    $uploadIds = $this->additionalUploadIds;
    array_unshift($uploadIds, $uploadId);

    $this->heartbeat(0);
    $licenseStmts = array();
    $copyrightStmts = array();
    $licenseStmtsMain = array();
    $licenseAcknowledgements = array();

    foreach($uploadIds as $addUploadId)
    {
      if (!$this->uploadDao->isAccessible($addUploadId, $groupId)) {
        continue;
      }
      $moreLicenses = $this->licenseClearedGetter->getCleared($addUploadId, $groupId);
      $licenseStmts = array_merge($licenseStmts, $moreLicenses['statements']);
      $this->heartbeat(count($moreLicenses['statements']));
      $this->licenseClearedGetter->setOnlyAcknowledgements(true);
      $moreAcknowledgements = $this->licenseClearedGetter->getCleared($addUploadId, $groupId);
      $licenseAcknowledgements = array_merge($licenseAcknowledgements, $moreAcknowledgements['statements']);
      $this->heartbeat(count($moreAcknowledgements['statements']));
      $moreCopyrights = $this->cpClearedGetter->getCleared($addUploadId, $groupId, true, "copyright");
      $copyrightStmts = array_merge($copyrightStmts, $moreCopyrights['statements']);
      $this->heartbeat(count($moreCopyrights['statements']));
      $moreMainLicenses = $this->licenseMainGetter->getCleared($addUploadId, $groupId);
      $licenseStmtsMain = array_merge($licenseStmtsMain, $moreMainLicenses['statements']);
      $this->heartbeat(count($moreMainLicenses['statements']));
    }

    $contents = array('licensesMain'=>$licenseStmtsMain, 'licenses'=>$licenseStmts, 'copyrights'=>$copyrightStmts, 'licenseAcknowledgements' => $licenseAcknowledgements);
    $this->writeReport($contents, $uploadId);

    return true;
  }

  /**
   * @brief write data to text file
   * @param array $contents
   * @param int $uploadId
   */
  private function writeReport($contents, $uploadId)
  {
    global $SysConf;

    $packageName = $this->uploadDao->getUpload($uploadId)->getFilename();

    $fileBase = $SysConf['FOSSOLOGY']['path']."/report/";
    $fileName = $fileBase. "ReadMe_OSS_".$packageName.'_'.time().".txt" ;

    foreach($this->additionalUploadIds as $addUploadId)
    {
      $packageName .= ', ' . $this->uploadDao->getUpload($addUploadId)->getFilename();
    }

    if(!is_dir($fileBase)) {
      mkdir($fileBase, 0777, true);
    }
    umask(0133);
    $message = $this->generateReport($contents, $packageName);

    file_put_contents($fileName, $message);

    $this->updateReportTable($uploadId, $this->jobId, $fileName);
  }

  /**
   * @brief update the report path
   * @param int $uploadId
   * @param int $jobId
   * @param char $filename
   */
  private function updateReportTable($uploadId, $jobId, $filename)
  {
    $this->dbManager->insertTableRow('reportgen', array('upload_fk'=>$uploadId, 'job_fk'=>$jobId, 'filepath'=>$filename), __METHOD__);
  }

  /**
   * @brief This function lists elements of array
   * @param addSeparator
   * @param $dataForReadME
   * @param $extract
   * @param $break
   */ 
  private function createReadMeOSSFormat($addSeparator, $dataForReadME, $extract='text', $break)
  {
    $outData = "";
    foreach($dataForReadME as $statements) {
      if($extract == 'text') {
        $outData .= $statements["content"] . $break;
      }
      $outData .= str_replace("\n", "\r\n", $statements[$extract]) . $break;
      if(!empty($addSeparator)) {
        $outData .= $addSeparator . $break;
      }
     }
    return $outData;
  }

  /**
   * @brief gather all the data
   * @param array $contents
   * @param char $packageName
   */
  private function generateReport($contents, $packageName)
  {
    $separator1 = str_repeat("=", 120);
    $separator2 = str_repeat("-", 120);
    $break = str_repeat("\r\n", 2);

    $output = $separator1 . $break . $packageName . $break . $separator2 . $break;
    if(!empty($contents['licensesMain'])) {
      $output .= $separator1 . $break . " MAIN LICENSES " . $break . $separator2 . $break;
      $output .= $this->createReadMeOSSFormat($separator2, $contents['licensesMain'], 'text', $break);
    }
    if(!empty($contents['licenses'])) {
      $output .= $separator1 . $break . " OTHER LICENSES " . $break . $separator2 . $break;
      $output .= $this->createReadMeOSSFormat($separator2, $contents['licenses'], 'text', $break);
    }
    if(!empty($contents['licenseAcknowledgements'])) {
      $output .= $separator1 . $break . " ACKNOWLEDGEMENTS " . $break . $separator2 . $break;
      $output .= $this->createReadMeOSSFormat($separator2, $contents['licenseAcknowledgements'], 'text', $break);
    }
    $copyrights = $this->createReadMeOSSFormat("", $contents['copyrights'], 'content', "\r\n");
    if(empty($copyrights)) {
      $output .= "<Copyright notices>";
      $output .= $break;
      $output .= "<notices>";
    } else {
      $output .= "Copyright notices";
      $output .= $break;
      $output .= $copyrights;
    }
    return $output;
  }
}

$agent = new ReadmeOssAgent();
$agent->scheduler_connect();
$agent->run_scheduler_event_loop();
$agent->scheduler_disconnect(0);
