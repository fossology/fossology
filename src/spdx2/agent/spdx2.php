<?php
/*
 SPDX-FileCopyrightText: © 2015-2016,2023 Siemens AG
 SPDX-FileCopyrightText: © 2017 TNG Technology Consulting GmbH

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @dir
 * @brief Source code of SPDX2 report agent
 * @file
 * @brief SPDX2 report generation
 *
 * Generates reports according to SPDX2 standards.
 * @page spdx2 SPDX2 report
 * @tableofcontents
 * @section spdx2about About SPDX2 agent
 * The agent generates report for an upload according to the spdx2 standard
 * format. It contains
 * -# File path
 * -# Copyright
 * -# License ID (short name)
 * -# License name
 * -# Actual license text in file
 * -# File checksum
 *
 * for every file.
 *
 * @section spdx2actions Supported actions
 * Currently, SPDX2 agent does not support CLI commands and read only from scheduler.
 *
 * @section spdx2source Agent source
 *   - @link src/spdx2/agent @endlink
 *   - @link src/spdx2/ui @endlink
 *   - Functional test cases @link src/spdx2/agent_tests/Functional @endlink
 *   - Unit test cases @link src/spdx2/agent_tests/Unit @endlink
 */

/**
 * @namespace Fossology::SpdxTwo
 * @brief Namespace used by SPDX2 agent
 */
namespace Fossology\SpdxTwo;

use Fossology\Lib\Agent\Agent;
use Fossology\Lib\BusinessRules\LicenseMap;
use Fossology\Lib\Dao\ClearingDao;
use Fossology\Lib\Dao\LicenseDao;
use Fossology\Lib\Dao\TreeDao;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Data\AgentRef;
use Fossology\Lib\Data\License;
use Fossology\Lib\Data\LicenseRef;
use Fossology\Lib\Data\Package\ComponentType;
use Fossology\Lib\Data\Report\FileNode;
use Fossology\Lib\Data\Report\SpdxLicenseInfo;
use Fossology\Lib\Data\Upload\Upload;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Report\LicenseClearedGetter;
use Fossology\Lib\Report\LicenseMainGetter;
use Fossology\Lib\Report\ObligationsGetter;
use Fossology\Lib\Report\ReportUtils;
use Fossology\Lib\Util\StringOperation;
use Twig\Environment;

include_once(__DIR__ . "/spdx2utils.php");

include_once(__DIR__ . "/version.php");
include_once(__DIR__ . "/services.php");

/**
 * @class SpdxTwoAgent
 * @brief SPDX2 agent
 */
class SpdxTwoAgent extends Agent
{

  const OUTPUT_FORMAT_KEY = "outputFormat";               ///< Argument key for output format
  const DEFAULT_OUTPUT_FORMAT = "spdx2";                  ///< Default output format
  const AVAILABLE_OUTPUT_FORMATS = "spdx2,spdx2tv,dep5,spdx2csv";  ///< Output formats available
  const UPLOAD_ADDS = "uploadsAdd";                       ///< Argument for additional uploads
  const DATA_LICENSE = "CC0-1.0";                         ///< Data license for SPDX reports

  /** @var UploadDao $uploadDao
   * UploadDao object
   */
  private $uploadDao;
  /** @var ClearingDao $clearingDao
   * ClearingDao object
   */
  private $clearingDao;
  /** @var LicenseDao $licenseDao
   * LicenseDao object
   */
  private $licenseDao;
  /** @var DbManager $dbManager
   * DbManager object
   */
  protected $dbManager;
  /** @var Environment $renderer
   * Twig_Environment object
   */
  protected $renderer;
  /** @var LicenseMap $licenseMap
   * LicenseMap object
   */
  private $licenseMap;
  /**
   * @var LicenseClearedGetter $licenseClearedGetter
   * License cleared getter to fetch licenses.
   */
  private $licenseClearedGetter;
  /**
   * @var LicenseMainGetter $licenseMainGetter
   * Getter for main licenses.
   */
  private $licenseMainGetter;
  /**
   * @var ObligationsGetter $obligationsGetter
   * Obligation getter.
   */
  private $obligationsGetter;
  /**
   * @var ReportUtils $reportutils
   * Report utils.
   */
  private $reportutils;
  /** @var array $agentNames
   * Agent names mapping
   */
  protected $agentNames = AgentRef::AGENT_LIST;
  /** @var string $filebasename
   * Basename of SPDX2 report
   */
  protected $filebasename = null;
  /** @var string $uri
   * URI of the file
   */
  protected $uri;
  /** @var string $filename
   * File name
   */
  protected $filename;
  /**
   * @var SpdxLicenseInfo[] $licensesInDocument
   * List of licenses found in the document.
   */
  private $licensesInDocument = [];
  /** @var string $outputFormat
   * Output format of the report
   */
  protected $outputFormat = self::DEFAULT_OUTPUT_FORMAT;

  function __construct()
  {
    // deduce the agent name from the command line arguments
    $args = getopt("", array(self::OUTPUT_FORMAT_KEY.'::'));
    $agentName = "";
    if (array_key_exists(self::OUTPUT_FORMAT_KEY, $args)) {
      $agentName = trim($args[self::OUTPUT_FORMAT_KEY]);
    }
    if (empty($agentName)) {
        $agentName = self::DEFAULT_OUTPUT_FORMAT;
    }

    parent::__construct($agentName, AGENT_VERSION, AGENT_REV);

    $this->uploadDao = $this->container->get('dao.upload');
    $this->clearingDao = $this->container->get('dao.clearing');
    $this->licenseDao = $this->container->get('dao.license');
    $this->dbManager = $this->container->get('db.manager');
    $this->renderer = $this->container->get('twig.environment');
    $this->renderer->setCache(false);

    $this->agentSpecifLongOptions[] = self::UPLOAD_ADDS.':';
    $this->agentSpecifLongOptions[] = self::OUTPUT_FORMAT_KEY.':';

    $this->licenseClearedGetter = new LicenseClearedGetter();
    $this->licenseMainGetter = new LicenseMainGetter();
    $this->obligationsGetter = new ObligationsGetter();
    $this->reportutils = new ReportUtils();
  }

  /**
   * @brief Parse arguments
   * @param string[] $args Array of arguments to be parsed
   * @return array $args Parsed arguments
   */
  protected function preWorkOnArgs($args)
  {
    if ((!array_key_exists(self::OUTPUT_FORMAT_KEY, $args)
         || $args[self::OUTPUT_FORMAT_KEY] === "")
        && array_key_exists(self::UPLOAD_ADDS,$args)) {
      $args = SpdxTwoUtils::preWorkOnArgsFlp($args,self::UPLOAD_ADDS,self::OUTPUT_FORMAT_KEY);
    } else {
      if (!array_key_exists(self::UPLOAD_ADDS,$args) || $args[self::UPLOAD_ADDS] === "") {
        $args = SpdxTwoUtils::preWorkOnArgsFlp($args,self::UPLOAD_ADDS,self::OUTPUT_FORMAT_KEY);
      }
    }
    return $args;
  }

  /**
   * @copydoc Fossology::Lib::Agent::Agent::processUploadId()
   * @see Fossology::Lib::Agent::Agent::processUploadId()
   */
  function processUploadId($uploadId)
  {
    $args = $this->preWorkOnArgs($this->args);

    if (array_key_exists(self::OUTPUT_FORMAT_KEY,$args)) {
      $possibleOutputFormat = trim($args[self::OUTPUT_FORMAT_KEY]);
      if (in_array($possibleOutputFormat, explode(',',self::AVAILABLE_OUTPUT_FORMATS))) {
        $this->outputFormat = $possibleOutputFormat;
      }
    }
    $this->licenseMap = new LicenseMap($this->dbManager, $this->groupId, LicenseMap::REPORT, true);
    $this->computeUri($uploadId);

    $docLicense = $this->licenseDao->getLicenseByShortName(self::DATA_LICENSE);
    $docLicenseId = $docLicense->getId() . "-" . md5($docLicense->getText());
    $this->licensesInDocument[$docLicenseId] = (new SpdxLicenseInfo())
      ->setLicenseObj($docLicense)
      ->setListedLicense(true)
      ->setCustomText(false)
      ->setTextPrinted(true);

    $packageNodes = $this->renderPackage($uploadId);
    $additionalUploadIds = array_key_exists(self::UPLOAD_ADDS,$args) ? explode(',',$args[self::UPLOAD_ADDS]) : array();
    $packageIds = array($uploadId);
    foreach ($additionalUploadIds as $additionalId) {
      $packageNodes .= $this->renderPackage($additionalId);
      $packageIds[] = $additionalId;
    }

    $this->writeReport($packageNodes, $packageIds, $uploadId);
    return true;
  }

  /**
   * @brief Get TWIG template file based on output format
   * @param string $partname copyright|document|file|package
   * @return string Template file path
   */
  protected function getTemplateFile($partname)
  {
    $prefix = $this->outputFormat . "-";
    $postfix = ".twig";
    switch ($this->outputFormat) {
      case "spdx2":
        $postfix = ".xml" . $postfix;
        break;
      case "spdx2csv":
      case "spdx2tv":
        break;
      case "dep5":
        $prefix .= "copyright-";
        break;
    }
    return $prefix . $partname . $postfix;
  }

  /**
   * @brief Generate report basename based on upload name
   *
   * The base name is in format <b>`<OutputFormat>_<packagename>_<timestamp><.spdx.rdf|.spdx|.txt|.csv>`</b>
   * @param string $packageName Name of the upload
   * @return string Report file's base name
   */
  protected function getFileBasename($packageName)
  {
    if ($this->filebasename == null) {
      $fileName = strtoupper($this->outputFormat)."_".$packageName.'_'.time();
      switch ($this->outputFormat) {
        case "spdx2":
          $fileName .= ".spdx.rdf";
          break;
        case "spdx2tv":
          $fileName .= ".spdx";
          break;
        case "spdx2csv":
          $fileName .= ".csv";
          break;
        case "dep5":
          $fileName .= ".txt";
          break;
      }
      $this->filebasename = $fileName;
    }
    return $this->filebasename;
  }

  /**
   * @brief Get absolute path for report
   * @param string $packageName Name of the upload
   * @return string Absolute file path for report
   */
  protected function getFileName($packageName)
  {
    global $SysConf;
    $fileBase = $SysConf['FOSSOLOGY']['path']."/report/";
    return $fileBase. $this->getFileBasename($packageName);
  }

  /**
   * @brief Get the URI for the given package
   * @param string $packageName Name of the upload
   * @return string URI for the upload
   */
  protected function getUri($packageName)
  {
    global $SysConf;
    $url=$SysConf['SYSCONFIG']['FOSSologyURL'];
    if (substr( $url, 0, 4 ) !== "http") {
      $url="http://".$url;
    }

    return rtrim($url, '/') . '/' . $this->getFileBasename($packageName);
  }

  /**
   * @brief Given an upload id, render the report string
   * @param int $uploadId
   * @return string Rendered report string
   */
  protected function renderPackage($uploadId)
  {
    $uploadTreeTableName = $this->uploadDao->getUploadtreeTableName($uploadId);
    $itemTreeBounds = $this->uploadDao->getParentItemBounds($uploadId,$uploadTreeTableName);
    $this->heartbeat(0);

    $filesWithLicenses = $this->reportutils
      ->getFilesWithLicensesFromClearings($itemTreeBounds, $this->groupId,
        $this, $this->licensesInDocument);
    $this->heartbeat(0);

    $this->reportutils->addClearingStatus($filesWithLicenses,$itemTreeBounds, $this->groupId);
    $this->heartbeat(0);

    $scannerIDs = $this->reportutils->addScannerResults($filesWithLicenses, $itemTreeBounds, $this->groupId, $this->licensesInDocument);
    $licenseComment = "";
    if (!empty($scannerIDs)) {
      $licenseComment = $this->getLicenseComment($scannerIDs);
    }
    $this->heartbeat(0);

    $this->reportutils->addCopyrightResults($filesWithLicenses, $uploadId);
    $this->heartbeat(0);

    $upload = $this->uploadDao->getUpload($uploadId);
    $fileNodes = $this->generateFileNodes($filesWithLicenses, $upload->getTreeTableName(), $uploadId);

    $mainLicenseIds = $this->clearingDao->getMainLicenseIds($uploadId, $this->groupId);
    $mainLicenses = array();
    foreach ($mainLicenseIds as $licId) {
      $reportedLicenseId = $this->licenseMap->getProjectedId($licId);
      $mainLicense = $this->licenseDao->getLicenseById($reportedLicenseId, $this->groupId);
      $reportLicId = $mainLicense->getId() . "-" . md5($mainLicense->getText());
      $mainLicenses[] = $reportLicId;
      if (!array_key_exists($reportLicId, $this->licensesInDocument)) {
        $listedLicense = stripos($mainLicense->getSpdxId(),
            LicenseRef::SPDXREF_PREFIX) !== 0;
        $this->licensesInDocument[$reportLicId] = (new SpdxLicenseInfo())
          ->setLicenseObj($mainLicense)
          ->setCustomText(false)
          ->setListedLicense($listedLicense);
      }
    }
    $mainLicenseString = [];
    if ($this->outputFormat == "spdx2tv" ||
        $this->outputFormat == "spdx2csv") {
      foreach ($mainLicenses as $mainLicense) {
        $shortName = $this->licensesInDocument[$mainLicense]
          ->getLicenseObj()->getShortName();
        if (StringOperation::stringStartsWith($shortName,
          LicenseRef::SPDXREF_PREFIX)) {
          $mainLicenseString[] = $shortName;
        } else {
          $mainLicenseString[] = $this->licensesInDocument[$mainLicense]
            ->getLicenseObj()->getSpdxId();
        }
      }
      $mainLicenseString = SpdxTwoUtils::implodeLicenses(
        SpdxTwoUtils::removeEmptyLicenses($mainLicenseString));
    }

    $hashes = $this->uploadDao->getUploadHashes($uploadId);

    $reportInfo = $this->uploadDao->getReportInfo($uploadId);
    $componentId = $reportInfo['ri_component_id'];
    $componentType = $reportInfo['ri_component_type'];
    $componentVersion = $reportInfo['ri_version'];
    $generalAssessment = $reportInfo['ri_general_assesment'];
    $releaseDate = $reportInfo['ri_release_date'];
    if ($componentId == "NA") {
      $componentId = "";
    }
    if ($componentVersion == "NA") {
      $componentVersion = "";
    }
    if ($generalAssessment == "NA") {
      $generalAssessment = "";
    }
    if ($releaseDate == "NA") {
      $releaseDate = "";
    } else {
      $timeStamp = strtotime($releaseDate);
      if ($timeStamp != -1) {
        $releaseDate = date("Y-m-d\\T00:00:00\\Z", $timeStamp);
      } else {
        $releaseDate = "";
      }
    }
    if ($componentType == ComponentType::MAVEN) {
      $componentType = "maven-central";
    } elseif ($componentType == ComponentType::PACKAGEURL) {
      $componentType = "purl";
    } else {
      if (!empty($componentType)) {
        $componentType = ComponentType::TYPE_MAP[$componentType];
      } else {
        $componentType = ComponentType::TYPE_MAP[ComponentType::PURL];
      }
    }
    $obligations = $this->getObligations($uploadId, $this->groupId);

    return $this->renderString($this->getTemplateFile('package'), [
      'packageId' => $uploadId,
      'uri' => $this->uri,
      'packageName' => $upload->getFilename(),
      'packageVersion' => $componentVersion,
      'releaseDate' => $releaseDate,
      'generalAssessment' => $generalAssessment,
      'uploadName' => $upload->getFilename(),
      'componentType' => $componentType,
      'componentId' => htmlspecialchars($componentId),
      'sha1' => $hashes['sha1'],
      'md5' => $hashes['md5'],
      'sha256' => $hashes['sha256'],
      'verificationCode' => $this->getVerificationCode($upload),
      'mainLicenses' => $mainLicenses,
      'mainLicenseString' => $mainLicenseString,
      'licenseComments' => $licenseComment,
      'fileNodes' => $fileNodes,
      'obligations' => $obligations,
      'licenseList' => $this->licensesInDocument
    ]);
  }

  /**
   * @param string[] $scannerIds
   * @return string License Comment
   */
  protected function getLicenseComment($scannerIds)
  {
    $agentDao = $this->agentDao;
    $func = function($scannerId) use ($agentDao)
    {
      return $agentDao->getAgentName($scannerId)." (".$agentDao->getAgentRev($scannerId).")";
    };
    $scannerNames = array_map($func, $scannerIds);
    return "licenseInfoInFile determined by Scanners:\n - ".implode("\n - ",$scannerNames);
  }

  /**
   * @brief Map licenses, copyrights, files and full path to filesWithLicenses array
   * @param[in,out] string $filesWithLicenses
   * @param string $licenses
   * @param string[] $copyrights
   * @param string $file
   * @param string $fullPath
   */
  protected function toLicensesWithFilesAdder(&$filesWithLicenses, $licenses, $copyrights, $file, $fullPath)
  {
    if (!array_key_exists($licenses, $filesWithLicenses)) {
      $filesWithLicenses[$licenses]['files']=array();
      $filesWithLicenses[$licenses]['copyrights']=array();
    }
    if (empty($copyrights)) {
      $copyrights = array();
    }
    $filesWithLicenses[$licenses]['files'][$file] = $fullPath;
    foreach ($copyrights as $copyright) {
      if (!in_array($copyright, $filesWithLicenses[$licenses]['copyrights'])) {
        $filesWithLicenses[$licenses]['copyrights'][] = $copyright;
      }
    }
  }

  /**
   * @brief Map findings to the files
   * @param FileNode[] &$filesWithLicenses
   * @param string $treeTableName
   * @return string[][][] Array of files with associated findings
   */
  protected function toLicensesWithFiles(&$filesWithLicenses, $treeTableName)
  {
    $licensesWithFiles = array();
    $treeDao = $this->container->get('dao.tree');
    $filesProceeded = 0;
    foreach ($filesWithLicenses as $fileId => $fileNode) {
      $filesProceeded += 1;
      if (($filesProceeded&2047)==0) {
        $this->heartbeat(0);
      }
      $fullPath = $treeDao->getFullPath($fileId, $treeTableName, 0);
      if (! empty($fileNode->getConcludedLicenses())) {
        $licenses = [];
        foreach ($fileNode->getConcludedLicenses() as $license) {
          $licenses[] = $this->licensesInDocument[$license]
            ->getLicenseObj()->getSpdxId();
        }
        $licenses = SpdxTwoUtils::implodeLicenses(
          SpdxTwoUtils::removeEmptyLicenses(array_unique($licenses)));
        $this->toLicensesWithFilesAdder($licensesWithFiles,
          $licenses, $fileNode->getCopyrights(), $fileId, $fullPath);
      } else {
        if (! empty($fileNode->getScanners())) {
          $implodedLicenses = [];
          foreach ($fileNode->getScanners() as $license) {
            $implodedLicenses[] = $this->licensesInDocument[$license]
              ->getLicenseObj()->getSpdxId();
          }
          $implodedLicenses = SpdxTwoUtils::implodeLicenses(
            SpdxTwoUtils::removeEmptyLicenses(array_unique($implodedLicenses)));
          if ($fileNode->isCleared()) {
            $msgLicense = "None (scanners found: " . $implodedLicenses . ")";
          } else {
            $msgLicense = "NoLicenseConcluded (scanners found: " . $implodedLicenses . ")";
          }
        } else {
          if ($fileNode->isCleared()) {
            $msgLicense = "None";
          } else {
            $msgLicense = "NoLicenseConcluded";
          }
        }
        $this->toLicensesWithFilesAdder($licensesWithFiles, $msgLicense,
          $fileNode->getCopyrights(), $fileId, $fullPath);
      }
    }
    return $licensesWithFiles;
  }

  /**
   * @brief For a given upload, compute the URI and filename for the report
   * @param int $uploadId
   */
  protected function computeUri($uploadId)
  {
    $upload = $this->uploadDao->getUpload($uploadId);
    $packageName = $upload->getFilename();

    $this->uri = $this->getUri($packageName);
    $this->filename = $this->getFileName($packageName);
  }

  /**
   * @brief Write the report the file and update report table
   * @param string $packageNodes
   * @param int[] $packageIds
   * @param int $uploadId
   */
  protected function writeReport(&$packageNodes, $packageIds, $uploadId)
  {
    global $SysConf;

    $fileBase = dirname($this->filename);

    if (!is_dir($fileBase)) {
      mkdir($fileBase, 0777, true);
    }
    umask(0133);

    $organizationName = $SysConf['SYSCONFIG']["ReportHeaderText"];
    $version = $SysConf['BUILD']['VERSION'];

    $message = $this->renderString($this->getTemplateFile('document'),array(
        'documentName' => $fileBase,
        'uri' => $this->uri,
        'userName' => $this->container->get('dao.user')->getUserName($this->userId) . " (" . $this->container->get('dao.user')->getUserEmail($this->userId) . ")",
        'organisation' => $organizationName,
        'toolVersion' => 'fossology-' . $version,
        'packageNodes' => $packageNodes,
        'packageIds' => $packageIds,
        'dataLicense' => $this->getSPDXDataLicense(),
        'licenseList' => $this->licensesInDocument
      )
    );

    // To ensure the file is valid, replace any non-printable characters with a question mark.
    // 'Non-printable' is ASCII < 0x20 (excluding \r, \n and tab) and 0x7F (delete).
    $message = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/','?',$message);

    file_put_contents($this->filename, $message);
    $this->updateReportTable($uploadId, $this->jobId, $this->filename);
  }

  /**
   * @brief Update the reportgen table with new report path
   * @param int $uploadId    Upload id
   * @param int $jobId       Job id
   * @param string $fileName File name of the report
   */
  protected function updateReportTable($uploadId, $jobId, $fileName)
  {
    $this->dbManager->insertTableRow('reportgen',
            array('upload_fk'=>$uploadId, 'job_fk'=>$jobId, 'filepath'=>$fileName),
            __METHOD__);
  }

  /**
   * @brief Render a twig template
   * @param string $templateName Name of the template to be rendered
   * @param array $vars Variables for the template
   * @return string The rendered output
   */
  protected function renderString($templateName, $vars)
  {
    return $this->renderer->load($templateName)->render($vars);
  }

  /**
   * @brief Generate report nodes for files
   * @param FileNode[] $filesWithLicenses
   * @param string $treeTableName
   * @param int $uploadId
   * @return string Node content
   */
  protected function generateFileNodes($filesWithLicenses, $treeTableName, $uploadId)
  {
    $this->deduplicateLicenseList();
    if (strcmp($this->outputFormat, "dep5") !== 0) {
      return $this->generateFileNodesByFiles($filesWithLicenses, $treeTableName, $uploadId);
    } else {
      return $this->generateFileNodesByLicenses($filesWithLicenses, $treeTableName);
    }
  }

  /**
   * @brief For each file, generate the nodes by files
   * @param FileNode[] $filesWithLicenses
   * @param string $treeTableName
   * @param int $uploadId
   * @return string Node string
   */
  protected function generateFileNodesByFiles($filesWithLicenses, $treeTableName, $uploadId)
  {
    /* @var $treeDao TreeDao */
    $treeDao = $this->container->get('dao.tree');

    $filesProceeded = 0;
    $lastValue = 0;
    $content = '';
    $textToBePrinted = [];
    foreach ($filesWithLicenses as $fileId => $fileData) {
      $filesProceeded += 1;
      if (($filesProceeded & 2047) == 0) {
        $this->heartbeat($filesProceeded - $lastValue);
        $lastValue = $filesProceeded;
      }
      $hashes = $treeDao->getItemHashes($fileId);
      $fileName = $treeDao->getFullPath($fileId, $treeTableName, 0);
      $stateComment = $this->getSPDXReportConf($uploadId, 0);
      $stateWoInfos = $this->getSPDXReportConf($uploadId, 1);
      foreach ($fileData->getConcludedLicenses() as $license) {
        if (! $this->licensesInDocument[$license]->isTextPrinted()) {
          $textToBePrinted[] = $license;
        }
      }
      foreach ($fileData->getScanners() as $license) {
        if (! $this->licensesInDocument[$license]->isTextPrinted()) {
          $textToBePrinted[] = $license;
        }
      }
      $concludedLicensesString = [];
      if ($this->outputFormat == "spdx2tv" ||
          $this->outputFormat == "spdx2csv") {
        foreach ($fileData->getConcludedLicenses() as $license) {
          $shortName = $this->licensesInDocument[$license]
            ->getLicenseObj()->getShortName();
          if (StringOperation::stringStartsWith($shortName,
            LicenseRef::SPDXREF_PREFIX)) {
            $concludedLicensesString[] = $shortName;
          } else {
            $concludedLicensesString[] = $this->licensesInDocument[$license]
              ->getLicenseObj()->getSpdxId();
          }
        }
        $concludedLicensesString = SpdxTwoUtils::implodeLicenses(
          SpdxTwoUtils::removeEmptyLicenses($concludedLicensesString));
      }
      if (!$stateWoInfos ||
          ($stateWoInfos && (!empty($fileData->getConcludedLicenses()) ||
              !empty($fileData->getScanners()) || !empty($fileData->getCopyrights())))) {
        $fileData->setAcknowledgements(
          SpdxTwoUtils::cleanTextArray($fileData->getAcknowledgements()));
        $fileData->setComments(
          SpdxTwoUtils::cleanTextArray($fileData->getComments()));
        $dataTemplate = array(
          'fileId' => $fileId,
          'sha1' => $hashes['sha1'],
          'md5' => $hashes['md5'],
          'sha256' => $hashes['sha256'],
          'uri' => $this->uri,
          'fileName' => $fileName,
          'fileDirName' => dirname($fileName),
          'fileBaseName' => basename($fileName),
          'fileData' => $fileData,
          'licenseList' => $this->licensesInDocument,
          'concludedLicensesString' => $concludedLicensesString,
          'licenseCommentState' => $stateComment
        );
        $content .= $this->renderString($this->getTemplateFile('file'),
          $dataTemplate);
      }
      foreach ($textToBePrinted as $license) {
        $this->licensesInDocument[$license]->setTextPrinted(true);
      }
    }
    $this->heartbeat($filesProceeded - $lastValue);
    return $content;
  }

  /**
   * @brief For each file, generate the nodes by licenses
   * @param FileNode[] $filesWithLicenses
   * @param string $treeTableName
   * @return string Node string
   */
  protected function generateFileNodesByLicenses($filesWithLicenses, $treeTableName)
  {
    $licensesWithFiles = $this->toLicensesWithFiles($filesWithLicenses, $treeTableName);

    $content = '';
    $filesProceeded = 0;
    $lastStep = 0;
    $lastValue = 0;
    foreach ($licensesWithFiles as $licenseId=>$entry) {
      $filesProceeded += count($entry['files']);
      if ($filesProceeded&(~2047) > $lastStep) {
        $this->heartbeat($filesProceeded - $lastValue);
        $lastStep = $filesProceeded&(~2047) + 2048;
        $lastValue = $filesProceeded;
      }

      $comment = "";
      if (strrpos($licenseId, "NoLicenseConcluded (scanners found: ", -strlen($licenseId)) !== false) {
        $comment = substr($licenseId,20,strlen($licenseId)-21);
        $licenseId = "NoLicenseConcluded";
      } elseif (strrpos($licenseId, "None (scanners found: ", -strlen($licenseId)) !== false) {
        $comment = substr($licenseId,6,strlen($licenseId)-7);
        $licenseId = "None";
      }

      $content .= $this->renderString($this->getTemplateFile('file'),array(
          'fileNames'=>$entry['files'],
          'license'=>$licenseId,
          'copyrights'=>$entry['copyrights'],
          'comment'=>$comment));
    }
    $this->heartbeat($filesProceeded - $lastValue);
    return $content;
  }

  /**
   * @brief Get a unique identifier for a given upload
   *
   * This is done using concatenating SHA1 of every pfile in upload and
   * calculating the SHA1 of the resulted string.
   * @param Upload $upload
   * @return string The unique identifier
   */
  protected function getVerificationCode(Upload $upload)
  {
    $stmt = __METHOD__;
    $param = array();
    if ($upload->getTreeTableName()=='uploadtree_a') {
      $sql = $upload->getTreeTableName().' WHERE upload_fk=$1 AND';
      $param[] = $upload->getId();
    } else {
      $sql = $upload->getTreeTableName().' WHERE';
      $stmt .= '.'.$upload->getTreeTableName();
    }

    $sql = "SELECT STRING_AGG(lower_sha1,'') concat_sha1 FROM
       (SELECT LOWER(pfile_sha1) lower_sha1 FROM pfile, $sql pfile_fk=pfile_pk AND parent IS NOT NULL ORDER BY pfile_sha1) templist";
    $filelistPack = $this->dbManager->getSingleRow($sql,$param,$stmt);

    return sha1($filelistPack['concat_sha1']);
  }

  /**
   * @brief Get spdx license comment state for a given upload
   *
   * @param int $uploadId
   * @return bool License comment state (TRUE : show license comment, FALSE : don't show it)
   */
  protected function getSPDXReportConf($uploadId, $key)
  {
    $sql = "SELECT ri_spdx_selection FROM report_info WHERE upload_fk = $1";
    $getCommentState = $this->dbManager->getSingleRow($sql, array($uploadId), __METHOD__.'.SPDX_license_comment');
    if (!empty($getCommentState['ri_spdx_selection'])) {
      $getCommentStateSingle = explode(',', $getCommentState['ri_spdx_selection']);
      if ($getCommentStateSingle[$key] === "checked") {
        return true;
      }
    }
    return false;
  }

  /**
   * Get obligations for the upload.
   * @param int $uploadId Current upload id
   * @param int $groupId  Current group id
   * @return array List of all obligations
   */
  private function getObligations(int $uploadId, int $groupId): array
  {
    $licenses = $this->licenseClearedGetter->getCleared($uploadId, $this,
      $groupId, true, "license", false);
    $this->heartbeat(0);
    $licensesMain = $this->licenseMainGetter->getCleared($uploadId, $this,
      $groupId, true, null, false);
    $this->heartbeat(0);
    list($obligations, $_) = $this->obligationsGetter->getObligations(
      $licenses['statements'], $licensesMain['statements'], $uploadId,
      $groupId);
    if (empty($obligations)) {
      return [];
    } else {
      return array_column($obligations, "text");
    }
  }

  /**
   * Get SPDX Data License `self::DATA_LICENSE`
   * @return string Associated array with 'text', 'name', 'id', 'url'
   */
  protected function getSPDXDataLicense()
  {
    $dataLic = $this->licenseDao->getLicenseByShortName(self::DATA_LICENSE);
    return $dataLic->getId() . "-" . md5($dataLic->getText());
  }

  /**
   * @brief De-duplicate license list by comparing licenses with the same SPDX
   * ID.
   *
   * First sort the license list by concatenating SPDX ID and shortname. Then
   * compare each license with the next one. If they have the same SPDX ID,
   * then the next one is a duplicate. Mark the duplicate by adding prefix of
   * SPDX LicenseRef and suffix of MD5 hash of the license text to the
   * shortname.
   * @return void
   */
  private function deduplicateLicenseList()
  {
    $localList = array_values($this->licensesInDocument);
    usort($localList,
      function(SpdxLicenseInfo $a, SpdxLicenseInfo $b) {
        return strcmp(
          $a->getLicenseObj()->getSpdxId() . $a->getLicenseObj()->getShortName(),
          $b->getLicenseObj()->getSpdxId() . $b->getLicenseObj()->getShortName());
      }
    );
    for ($i = 0; $i < count($localList) - 1; $i++) {
      if ((! $localList[$i]->isCustomText() && ! $localList[$i + 1]->isCustomText()) &&
          $localList[$i]->getLicenseObj()->getSpdxId() ===
          $localList[$i + 1]->getLicenseObj()->getSpdxId()) {
        $newShortName = $localList[$i + 1]->getLicenseObj()->getShortName();
        if (! StringOperation::stringStartsWith(
            $localList[$i + 1]->getLicenseObj()->getSpdxId(),
            LicenseRef::SPDXREF_PREFIX)) {
          $newShortName = LicenseRef::SPDXREF_PREFIX .
            $localList[$i + 1]->getLicenseObj()->getShortName();
          $newShortName = preg_replace('/\+$/', '-or-later', $newShortName);
        }
        $md5 = md5($localList[$i + 1]->getLicenseObj()->getText());
        $reportedLicenseShortname = "$newShortName-$md5";
        $licIndex = $localList[$i + 1]->getLicenseObj()->getId() . "-$md5";
        $oldLicObj = $this->licensesInDocument[$licIndex]->getLicenseObj();
        $this->licensesInDocument[$licIndex]->setLicenseObj(
          new License($oldLicObj->getId(), $reportedLicenseShortname,
            $oldLicObj->getFullName(), $oldLicObj->getRisk(),
            $oldLicObj->getText(), $oldLicObj->getUrl(),
            $oldLicObj->getDetectorType(), $oldLicObj->getSpdxId()));
      }
    }
  }
}

$agent = new SpdxTwoAgent();
$agent->scheduler_connect();
$agent->run_scheduler_event_loop();
$agent->scheduler_disconnect(0);
