<?php
/*
 Author: Daniele Fognini, Shaheem Azmal M MD
 SPDX-FileCopyrightText: © 2016-2018 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @dir readmeoss
 * @brief Readme_OSS agent
 * @dir readmeoss/agent
 * @brief Readme_OSS agent source
 * @file
 * @brief Readme_OSS agent
 * @page readmeoss Readme_OSS agent
 * @tableofcontents
 * @section readmeossabout About ReadmeOSS agent
 *
 * Readme_OSS agent generates a list of license short-names, license text,
 * license's acknowledgement and copyrights found in an upload. The output is
 * generated as a plain text plain.
 *
 * The agent creates the report and store on the server with other reports in
 * `/srv/fossology/repository/report/` folder with file name in
 * <b>`ReadMe_OSS_<uploadfilename>_<timestamp>.txt`</b> format.
 *
 * @section readmeossactions Supported actions
 * Currently, ReadMe_OSS does not support CLI commands and read only from
 * scheduler.
 *
 * @section readmeosssource Agent source
 *   - @link src/readmeoss/agent @endlink
 *   - @link src/readmeoss/ui @endlink
 *
 * @todo Write test cases for the agent
 */

use Fossology\Lib\Agent\Agent;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Report\LicenseClearedGetter;
use Fossology\Lib\Report\XpClearedGetter;
use Fossology\Lib\Report\LicenseMainGetter;

include_once(__DIR__ . "/version.php");

/**
 * @class ReadmeOssAgent
 * @brief Readme_OSS agent generates list of licenses and copyrights found
 * in an upload
 */
class ReadmeOssAgent extends Agent
{
  const UPLOAD_ADDS = "uploadsAdd";   ///< The HTTP GET parameter name

  /** @var LicenseClearedGetter $licenseClearedGetter
   * LicenseClearedGetter object
   */
  private $licenseClearedGetter;

  /** @var XpClearedGetter $cpClearedGetter
   * XpClearedGetter object
   */
  private $cpClearedGetter;

  /** @var LicenseMainGetter $licenseMainGetter
   * LicenseMainGetter object
   */
  private $licenseMainGetter;

  /** @var UploadDao $uploadDao
   * UploadDao object
   */
  private $uploadDao;

  /** @var int $additionalUploadIds
   * Additional Uploads to be included in report
   */
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
   * @copydoc Fossology::Lib::Agent::Agent::processUploadId()
   * @see Fossology::Lib::Agent::Agent::processUploadId()
   * @todo Without wrapper
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

    foreach ($uploadIds as $addUploadId) {
      if (!$this->uploadDao->isAccessible($addUploadId, $groupId)) {
        continue;
      }
      $moreLicenses = $this->licenseClearedGetter->getCleared($addUploadId, $this, $groupId, true, "license", false);
      $licenseStmts = array_merge($licenseStmts, $moreLicenses['statements']);
      $this->heartbeat(count($moreLicenses['statements']));
      $this->licenseClearedGetter->setOnlyAcknowledgements(true);
      $moreAcknowledgements = $this->licenseClearedGetter->getCleared($addUploadId, $this, $groupId, true, "license", false);
      $licenseAcknowledgements = array_merge($licenseAcknowledgements, $moreAcknowledgements['statements']);
      $this->heartbeat(count($moreAcknowledgements['statements']));
      $moreCopyrights = $this->cpClearedGetter->getCleared($addUploadId, $this, $groupId, true, "copyright", false);
      $copyrightStmts = array_merge($copyrightStmts, $moreCopyrights['statements']);
      $this->heartbeat(count($moreCopyrights['statements']));
      $moreMainLicenses = $this->licenseMainGetter->getCleared($addUploadId, $this, $groupId, true, null, false);
      $licenseStmtsMain = array_merge($licenseStmtsMain, $moreMainLicenses['statements']);
      $this->heartbeat(count($moreMainLicenses['statements']));
    }
    list($licenseStmtsMain, $licenseStmts) = $this->licenseClearedGetter->updateIdentifiedGlobalLicenses($licenseStmtsMain, $licenseStmts);
    $contents = array('licensesMain'=>$licenseStmtsMain, 'licenses'=>$licenseStmts, 'copyrights'=>$copyrightStmts, 'licenseAcknowledgements' => $licenseAcknowledgements);
    $this->writeReport($contents, $uploadId);

    return true;
  }

  /**
   * @brief Write data to text file
   * @param array $contents Contents of the report
   * @param int   $uploadId ID of the upload
   */
  private function writeReport($contents, $uploadId)
  {
    global $SysConf;

    $packageName = $this->uploadDao->getUpload($uploadId)->getFilename();

    $fileBase = $SysConf['FOSSOLOGY']['path']."/report/";
    $fileName = $fileBase. "ReadMe_OSS_".$packageName.'_'.time().".txt" ;

    foreach ($this->additionalUploadIds as $addUploadId) {
      $packageName .= ', ' . $this->uploadDao->getUpload($addUploadId)->getFilename();
    }

    if (!is_dir($fileBase)) {
      mkdir($fileBase, 0777, true);
    }
    umask(0133);
    $message = $this->generateReport($contents, $packageName);

    file_put_contents($fileName, $message);

    $this->updateReportTable($uploadId, $this->jobId, $fileName);
  }

  /**
   * @brief Update the report path
   * @param int    $uploadId Upload ID
   * @param int    $jobId    Job ID
   * @param string $filename Path of the file
   */
  private function updateReportTable($uploadId, $jobId, $filename)
  {
    $this->dbManager->insertTableRow('reportgen', array('upload_fk'=>$uploadId, 'job_fk'=>$jobId, 'filepath'=>$filename), __METHOD__);
  }

  /**
   * @brief This function lists elements of array
   * @param string $addSeparator  Separator to be used
   * @param string $dataForReadME Array of content
   * @param string $extract       Data to be extracted from $dataForReadME
   * @param string $break         Line break string
   * @return string Formated report
   */
  private function createReadMeOSSFormat($addSeparator, $dataForReadME, $extract, $break)
  {
    $outData = "";
    foreach ($dataForReadME as $statements) {
      if ($extract == 'text') {
        $outData .= $statements["content"] . $break;
      }
      $outData .= str_replace("\n", "\r\n", $statements[$extract]) . $break;
      if (!empty($addSeparator)) {
        $outData .= $addSeparator . $break;
      }
    }
    return htmlspecialchars_decode($outData, ENT_DISALLOWED);
  }

  /**
   * @brief Gather all the data
   * @param array  $contents    Array of contents with `licenseMain`, `licenses`
   * and `licenseAcknowledgements` keys.
   * @param string $packageName Package for which the report is generated
   * @return string ReadmeOSS report
   */
  private function generateReport($contents, $packageName)
  {
    $separator1 = str_repeat("=", 120);
    $separator2 = str_repeat("-", 120);
    $break = str_repeat("\r\n", 2);
    $output = $separator1 . $break . $packageName . $break . $separator2 . $break;
    if (!empty($contents['licensesMain'])) {
      $output .= $separator1 . $break . " MAIN LICENSES " . $break . $separator2 . $break;
      $output .= $this->createReadMeOSSFormat($separator2, $contents['licensesMain'], 'text', $break);
    }
    if (!empty($contents['licenses'])) {
      $output .= $separator1 . $break . " OTHER LICENSES " . $break . $separator2 . $break;
      $output .= $this->createReadMeOSSFormat($separator2, $contents['licenses'], 'text', $break);
    }
    if (!empty($contents['licenseAcknowledgements'])) {
      $output .= $separator1 . $break . " ACKNOWLEDGEMENTS " . $break . $separator2 . $break;
      $output .= $this->createReadMeOSSFormat($separator2, $contents['licenseAcknowledgements'], 'text', $break);
    }
    $copyrights = $this->createReadMeOSSFormat("", $contents['copyrights'], 'content', "\r\n");
    if (empty($copyrights)) {
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
