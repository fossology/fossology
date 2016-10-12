<?php
/*
 * Author: Daniele Fognini
 * Copyright (C) 2014-2015, Siemens AG
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
    $this->cpClearedGetter = new XpClearedGetter("copyright", "statement", false);
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

    foreach($uploadIds as $addUploadId)
    {
      if (!$this->uploadDao->isAccessible($addUploadId, $groupId)) {
        continue;
      }
      $moreLicenses = $this->licenseClearedGetter->getCleared($addUploadId, $groupId);
      $licenseStmts = array_merge($licenseStmts, $moreLicenses['statements']);
      $this->heartbeat(count($moreLicenses['statements']));
      $moreCopyrights = $this->cpClearedGetter->getCleared($addUploadId, $groupId);
      $copyrightStmts = array_merge($copyrightStmts, $moreCopyrights['statements']);
      $this->heartbeat(count($moreCopyrights['statements']));
      $moreMainLicenses = $this->licenseMainGetter->getCleared($addUploadId, $groupId);
      $licenseStmts = array_merge($licenseStmts, $moreMainLicenses['statements']);
      $this->heartbeat(count($moreMainLicenses['statements']));
    }

    $contents = array('licenses'=>$licenseStmts, 'copyrights'=>$copyrightStmts );
    $this->writeReport($contents, $uploadId);

    return true;
  }

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

  private function updateReportTable($uploadId, $jobId, $filename){
    $this->dbManager->insertTableRow('reportgen',
            array('upload_fk'=>$uploadId, 'job_fk'=>$jobId, 'filepath'=>$filename),
            __METHOD__);
  }

  private function generateReport($contents, $packageName)
  {
    $separator1 = str_repeat("=", 120);
    $separator2 = str_repeat("-", 120);
    $break = str_repeat("\r\n", 2);

    $output = $separator1 . $break . $packageName . $break . $separator2 . $break;
    foreach($contents['licenses'] as $licenseStatement){
      $output .= $licenseStatement['text'] . $break;
      $output .= $separator2 . $break;
    }
    $copyrights = "";
    foreach($contents['copyrights'] as $copyrightStatement){
      $copyrights .= $copyrightStatement['content']."\r\n";
    }
    if(empty($copyrights)){
      $output .= "<Copyright notices>";
      $output .= $break;
      $output .= "<notices>";
    }else{
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
