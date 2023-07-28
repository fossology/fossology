<?php
/*
 SPDX-FileCopyrightText: © 2023 Sushant Kumar(sushantmishra02102002@gmail.com)

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @dir
 * @brief Source code of CycloneDX report agent
 * @file
 * @brief CycloneDX report generation
 *
 * Generates reports according to CycloneDX standards.
 */

/**
 * @namespace Fossology::CycloneDX
 * @brief Namespace used by CycloneDX agent
 */
namespace Fossology\CycloneDX;

use Fossology\Lib\Agent\Agent;
use Fossology\Lib\BusinessRules\LicenseMap;
use Fossology\Lib\Dao\ClearingDao;
use Fossology\Lib\Dao\TreeDao;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Data\AgentRef;
use Fossology\Lib\Data\DecisionTypes;
use Fossology\Lib\Data\LicenseRef;
use Fossology\Lib\Data\Tree\ItemTreeBounds;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Report\ReportUtils;

include_once(__DIR__ . "/version.php");
include_once(__DIR__ . "/reportgenerator.php");

/**
 * @class cyclonedxAgent
 * @brief cyclonedxAgent agent generates SBOM in cyclonedx format
 */
class CycloneDXAgent extends Agent
{
  const OUTPUT_FORMAT_KEY = "outputFormat";               ///< Argument key for output format
  const DEFAULT_OUTPUT_FORMAT = "cyclonedx_json";                  ///< Default output format

  /** @var BomReportGenerator $reportGenerator
   * UploadDao object
   */
  private $reportGenerator;
  /**
   * @var ReportUtils $reportutils
   * ReportUtils object
   */
  private $reportutils;
  /** @var UploadDao $uploadDao
   * UploadDao object
   */
  private $uploadDao;
  /** @var ClearingDao $clearingDao
   * ClearingDao object
   */
  private $clearingDao;
  /** @var DbManager $dbManager
   * DbManager object
   */
  protected $dbManager;
  /** @var LicenseMap $licenseMap
   * LicenseMap object
   */
  private $licenseMap;
  /** @var array $agentNames
   * Agent names mapping
   */
  protected $agentNames = AgentRef::AGENT_LIST;
  /** @var array $includedLicenseIds
   * License ids included
   */
  protected $includedLicenseIds = array();
  /** @var string $uri
   * URI of the file
   */
  protected $uri;
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
    $this->dbManager = $this->container->get('db.manager');

    $this->reportutils = new ReportUtils();
    $this->reportGenerator = new BomReportGenerator();
  }

  /**
   * @copydoc Fossology::Lib::Agent::Agent::processUploadId()
   * @see Fossology::Lib::Agent::Agent::processUploadId()
   */
  function processUploadId($uploadId)
  {
    $this->licenseMap = new LicenseMap($this->dbManager, $this->groupId, LicenseMap::REPORT, true);

    $packageNodes = $this->renderPackage($uploadId);

    $this->computeUri($uploadId);

    $this->writeReport($packageNodes, $uploadId);
    return true;
  }

  /**
   * @brief Get the URI for the given package
   * @param string $fileBase Name of the upload
   * @return string URI for the upload
   */
  protected function getUri($fileBase)
  {
    $fileName = $fileBase. strtoupper($this->outputFormat)."_".$this->packageName.'_'.date("Y-m-d_H:i:s");
    return $fileName .".json" ;
  }

  /**
   * @brief For a given upload, compute the URI
   * @param int $uploadId
   */
  protected function computeUri($uploadId)
  {
    global $SysConf;
    $upload = $this->uploadDao->getUpload($uploadId);
    $this->packageName = $upload->getFilename();

    $fileBase = $SysConf['FOSSOLOGY']['path']."/report/";

    $this->uri = $this->getUri($fileBase);
  }

  /**
   * @brief Given an upload id, render the report string
   * @param int $uploadId
   * @return string Rendered report string
   */
  protected function renderPackage($uploadId)
  {
    global $SysConf;
    $uploadTreeTableName = $this->uploadDao->getUploadtreeTableName($uploadId);
    $itemTreeBounds = $this->uploadDao->getParentItemBounds($uploadId, $uploadTreeTableName);
    $this->heartbeat(0);

    $filesWithLicenses = $this->getFilesWithLicensesFromClearings($itemTreeBounds);
    $this->heartbeat(0);

    $this->reportutils->addClearingStatus($filesWithLicenses, $itemTreeBounds, $this->groupId);
    $this->heartbeat(0);

    $this->reportutils->addScannerResults($filesWithLicenses, $itemTreeBounds, $this->groupId, $this->includedLicenseIds);
    $this->heartbeat(0);

    $this->reportutils->addCopyrightResults($filesWithLicenses, $uploadId);
    $this->heartbeat(0);

    $upload = $this->uploadDao->getUpload($uploadId);
    $components = $this->generateFileComponents($filesWithLicenses, $upload->getTreeTableName(), $uploadId, $itemTreeBounds);

    $mainLicenseIds = $this->clearingDao->getMainLicenseIds($uploadId, $this->groupId);
    $mainLicenses = array();
    $licenseTexts = $this->reportutils->getLicenseTexts($this->groupId, $this->includedLicenseIds);
    foreach ($mainLicenseIds as $licId) {
      $reportedLicenseId = $this->licenseMap->getProjectedId($licId);
      $this->includedLicenseIds[$reportedLicenseId] = true;
      $spdxId = $this->licenseMap->getProjectedSpdxId($reportedLicenseId);
      $licensedata['id'] = $spdxId;
      $licensedata['url'] = $licenseTexts[$spdxId]['url'];
      $mainLicenses[] = $this->reportGenerator->createLicense($licensedata);
    }

    $hashes = $this->uploadDao->getUploadHashes($uploadId);
    $serializedhash = array();
    $serializedhash[] = $this->reportGenerator->createHash('SHA-1', $hashes['sha1']);
    $serializedhash[] = $this->reportGenerator->createHash('MD5', $hashes['md5']);
    // Check if sha256 is not empty
    if (array_key_exists('sha256', $hashes) && !empty($hashes['sha256'])) {
      $serializedhash[] = $this->reportGenerator->createHash('SHA-256', $hashes['sha256']);
    }

    $maincomponentData = array (
      'bomref' => strval($uploadId),
      'type' => 'library',
      'name' => $upload->getFilename(),
      'hashes' => $serializedhash,
      'scope' => 'required',
      'mimeType' => $this->getMimeType($uploadId),
      'licenses' => $mainLicenses
    );
    $maincomponent = $this->reportGenerator->createComponent($maincomponentData);

    $bomdata = array (
      'tool-version' => $SysConf['BUILD']['VERSION'],
      'maincomponent' => $maincomponent,
      'components' => $components
    );

    return $this->reportGenerator->generateReport($bomdata);
  }

  /**
   * @brief Given an ItemTreeBounds, get the files with clearings
   * @param ItemTreeBounds $itemTreeBounds
   * @return string[][][] Mapping item->'concluded'->(array of shortnames)
   */
  protected function getFilesWithLicensesFromClearings(ItemTreeBounds $itemTreeBounds)
  {
    $clearingDecisions = $this->clearingDao->getFileClearingsFolder($itemTreeBounds, $this->groupId);

    $filesWithLicenses = array();
    $clearingsProceeded = 0;
    foreach ($clearingDecisions as $clearingDecision) {
      $clearingsProceeded += 1;
      if (($clearingsProceeded&2047)==0) {
        $this->heartbeat(0);
      }
      if ($clearingDecision->getType() == DecisionTypes::IRRELEVANT) {
        continue;
      }

      foreach ($clearingDecision->getClearingEvents() as $clearingEvent) {
        $clearingLicense = $clearingEvent->getClearingLicense();
        if ($clearingLicense->isRemoved()) {
          continue;
        }

        if ($clearingEvent->getReportinfo()) {
          $customLicenseText = $clearingEvent->getReportinfo();
          $reportedLicenseShortname = $this->licenseMap->getProjectedSpdxId($this->licenseMap->getProjectedId($clearingLicense->getLicenseId())) .
                                    '-' . md5($customLicenseText);
          $reportedLicenseShortname = LicenseRef::convertToSpdxId($reportedLicenseShortname, "");
          $this->includedLicenseIds[$reportedLicenseShortname] = $customLicenseText;
          $filesWithLicenses[$clearingDecision->getUploadTreeId()]['concluded'][] = $reportedLicenseShortname;
        } else {
          $reportedLicenseId = $this->licenseMap->getProjectedId($clearingLicense->getLicenseId());
          $this->includedLicenseIds[$reportedLicenseId] = true;
          $filesWithLicenses[$clearingDecision->getUploadTreeId()]['concluded'][] = $this->licenseMap->getProjectedSpdxId($reportedLicenseId);
        }
      }
    }
    return $filesWithLicenses;
  }

  /**
   * @brief Generate the components by files
   * @param string[][][] $filesWithLicenses
   * @param string $treeTableName
   * @param int $uploadId
   * @return array Components list
   */
  protected function generateFileComponents($filesWithLicenses, $treeTableName, $uploadId, $itemTreeBounds)
  {
    /* @var $treeDao TreeDao */
    $treeDao = $this->container->get('dao.tree');

    $filesProceeded = 0;
    $lastValue = 0;
    $components = array();
    $licenseTexts = $this->reportutils->getLicenseTexts($this->groupId, $this->includedLicenseIds);
    foreach ($filesWithLicenses as $fileId=>$licenses) {
      $filesProceeded += 1;
      if (($filesProceeded & 2047) == 0) {
        $this->heartbeat($filesProceeded - $lastValue);
        $lastValue = $filesProceeded;
      }

      $hashes = $treeDao->getItemHashes($fileId);
      $serializedhash = array();
      $serializedhash[] = $this->reportGenerator->createHash('SHA-1', $hashes['sha1']);
      $serializedhash[] = $this->reportGenerator->createHash('MD5', $hashes['md5']);
      // Check if sha256 is not empty
      if (array_key_exists('sha256', $hashes) && !empty($hashes['sha256'])) {
        $serializedhash[] = $this->reportGenerator->createHash('SHA-256', $hashes['sha256']);
      }

      $fileName = $treeDao->getFullPath($fileId, $treeTableName, 0);
      if (!array_key_exists('concluded', $licenses) || !is_array($licenses['concluded'])) {
        $licenses['concluded'] = [];
      }
      if (!array_key_exists('scanner', $licenses) || !is_array($licenses['scanner'])) {
        $licenses['scanner'] = [];
      }
      if (!array_key_exists('copyrights', $licenses)) {
        $licenses['copyrights'] = [];
      }
      $licensesfound = [];

      if (!empty($licenses['concluded'])) {
        foreach ($licenses['concluded'] as $license) {
          if (array_key_exists($license, $licenseTexts)) {
            $licensedata = array(
              "id"   => $licenseTexts[$license]["id"],
              "name" => $licenseTexts[$license]["name"],
              "url"  => $licenseTexts[$license]["url"]
            );
            $licensesfound[] = $this->reportGenerator->createLicense($licensedata);
          }
        }
      } else {
        foreach ($licenses['scanner'] as $license) {
          if (array_key_exists($license, $licenseTexts)) {
            $licensedata = array(
              "id"   => $licenseTexts[$license]["id"],
              "name" => $licenseTexts[$license]["name"],
              "url"  => $licenseTexts[$license]["url"]
            );
            $licensesfound[] = $this->reportGenerator->createLicense($licensedata);
          }
        }
      }
      if (!empty($fileName)) {
        $componentdata = array(
          'bomref' => $uploadId .'-'. $fileId,
          'type' => 'file',
          'name' => $fileName,
          'hashes' => $serializedhash,
          'mimeType' => 'text/plain',
          'copyright' => implode("\n", $licenses['copyrights']),
          'licenses' => $licensesfound
        );
        $components[] = $this->reportGenerator->createComponent($componentdata);
      }
    }
    $this->heartbeat($filesProceeded - $lastValue);
    return $components;
  }

  /**
   * @brief Write the report the file and update report table
   * @param string $packageNodes
   * @param int[] $packageIds
   * @param int $uploadId
   */
  protected function writeReport($packageNodes, $uploadId)
  {
    $fileBase = dirname($this->uri);

    if (!is_dir($fileBase)) {
      mkdir($fileBase, 0777, true);
    }
    umask(0133);

    $contents = json_encode($packageNodes, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    // To ensure the file is valid, replace any non-printable characters with a question mark.
    // 'Non-printable' is ASCII < 0x20 (excluding \r, \n and tab) and 0x7F - 0x9F.
    $contents = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F-\x9F]/u','?',$contents);
    file_put_contents($this->uri, $contents);
    $this->updateReportTable($uploadId, $this->jobId, $this->uri);
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
   * @brief Get the mime type of the upload
   * @return string Mime type of the upload
   */
  protected function getMimeType($uploadId)
  {
    $sql = "SELECT *
      FROM upload t1
      JOIN pfile t2 ON t1.pfile_fk = t2.pfile_pk
      JOIN mimetype t3 ON t2.pfile_mimetypefk = t3.mimetype_pk
      WHERE t1.upload_pk = $uploadId";

    $this->dbManager->prepare($stmt=__METHOD__, $sql);
    $res = $this->dbManager->execute($stmt);
    $row = $this->dbManager->fetchArray($res);
    $this->dbManager->freeResult($res);
    return $row['mimetype_name'];
  }
}

$agent = new CycloneDXAgent();
$agent->scheduler_connect();
$agent->run_scheduler_event_loop();
$agent->scheduler_disconnect(0);
