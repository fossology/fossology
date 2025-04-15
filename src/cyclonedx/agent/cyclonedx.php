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
use Fossology\Lib\Dao\LicenseDao;
use Fossology\Lib\Dao\TreeDao;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Data\AgentRef;
use Fossology\Lib\Data\Report\FileNode;
use Fossology\Lib\Data\Report\SpdxLicenseInfo;
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
  const UPLOADS_ADD_KEY = "uploadsAdd";

  /** @var array $addtionalUploads
   * Array of addtional uploads
   */
  private $additionalUploads = [];

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
  /**
   * @var LicenseDao $licenseDao
   * LicenseDao object
   */
  private $licenseDao;
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
  /** @var string $uri
   * URI of the file
   */
  protected $uri;
  /**
   * @var SpdxLicenseInfo[] $licensesInDocument
   * List of licenses found in the document.
   */
  private $licensesInDocument = [];
  /** @var string $outputFormat
   * Output format of the report
   */
  protected $outputFormat = self::DEFAULT_OUTPUT_FORMAT;
  /**
   * @var string $packageName
   */
  private $packageName;

  function __construct()
  {
    // deduce the agent name from the command line arguments
    $args = getopt("", array(
      self::OUTPUT_FORMAT_KEY.'::',
      self::UPLOADS_ADD_KEY.'::'
    ));
    $agentName = "";
    if (array_key_exists(self::OUTPUT_FORMAT_KEY, $args)) {
      $agentName = trim($args[self::OUTPUT_FORMAT_KEY]);
    }
    if (empty($agentName)) {
        $agentName = self::DEFAULT_OUTPUT_FORMAT;
    }
    if (array_key_exists(self::UPLOADS_ADD_KEY, $args)) {
      $uploadsString = $args[self::UPLOADS_ADD_KEY];
      if (!empty($uploadsString)) {
          $this->additionalUploads = explode(',', $uploadsString);
      }
    }

    parent::__construct($agentName, AGENT_VERSION, AGENT_REV);

    $this->uploadDao = $this->container->get('dao.upload');
    $this->clearingDao = $this->container->get('dao.clearing');
    $this->licenseDao = $this->container->get('dao.license');
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
    if (count($this->additionalUploads) > 0) {
      $fileName = $fileBase . "multifile" . "_" . strtoupper($this->outputFormat);
    } else {
      $fileName = $fileBase. strtoupper($this->outputFormat)."_".$this->packageName;
    }

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
   * @return array Rendered report string
   */
  protected function renderPackage($uploadId)
  {
    global $SysConf;
    $uploadTreeTableName = $this->uploadDao->getUploadtreeTableName($uploadId);
    $itemTreeBounds = $this->uploadDao->getParentItemBounds($uploadId, $uploadTreeTableName);
    $this->heartbeat(0);

    $filesWithLicenses = $this->reportutils
      ->getFilesWithLicensesFromClearings($itemTreeBounds, $this->groupId,
        $this, $this->licensesInDocument);
    $this->heartbeat(0);

    $this->reportutils->addClearingStatus($filesWithLicenses, $itemTreeBounds, $this->groupId);
    $this->heartbeat(0);

    $this->reportutils->addScannerResults($filesWithLicenses, $itemTreeBounds, $this->groupId, $this->licensesInDocument);
    $this->heartbeat(0);

    $this->reportutils->addCopyrightResults($filesWithLicenses, $uploadId);
    $this->heartbeat(0);

    $upload = $this->uploadDao->getUpload($uploadId);
    $components = $this->generateFileComponents($filesWithLicenses, $upload->getTreeTableName(), $uploadId, $itemTreeBounds);

    $mainLicenseIds = $this->clearingDao->getMainLicenseIds($uploadId, $this->groupId);
    $mainLicenses = array();
    foreach ($mainLicenseIds as $licId) {
      $reportedLicenseId = $this->licenseMap->getProjectedId($licId);
      $mainLicObj = $this->licenseDao->getLicenseById($reportedLicenseId, $this->groupId);
      $licId = $mainLicObj->getId() . "-" . md5($mainLicObj->getText());
      if (!array_key_exists($licId, $this->licensesInDocument)) {
        $this->licensesInDocument = (new SpdxLicenseInfo())
          ->setLicenseObj($mainLicObj)
          ->setCustomText(false)
          ->setTextPrinted(true)
          ->setListedLicense(true);
      }
      $licensedata['id'] = $mainLicObj->getSpdxId();
      $licensedata['url'] = $mainLicObj->getUrl();
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
   * @brief Generate the components by files
   * @param FileNode[] $filesWithLicenses
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
    foreach ($filesWithLicenses as $fileId => $licenses) {
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
      $licensesfound = [];

      if (!empty($licenses->getConcludedLicenses())) {
        foreach ($licenses->getConcludedLicenses() as $licenseId) {
          if (array_key_exists($licenseId, $this->licensesInDocument)) {
            $licensedata = array(
              "id"   => $this->licensesInDocument[$licenseId]->getLicenseObj()->getSpdxId(),
              "name" => $this->licensesInDocument[$licenseId]->getLicenseObj()->getFullName(),
              "url"  => $this->licensesInDocument[$licenseId]->getLicenseObj()->getUrl()
            );
            $licensesfound[] = $this->reportGenerator->createLicense($licensedata);
          }
        }
      } else {
        foreach ($licenses->getScanners() as $licenseId) {
          if (array_key_exists($licenseId, $this->licensesInDocument)) {
            $licensedata = array(
              "id"   => $this->licensesInDocument[$licenseId]->getLicenseObj()->getSpdxId(),
              "name" => $this->licensesInDocument[$licenseId]->getLicenseObj()->getFullName(),
              "url"  => $this->licensesInDocument[$licenseId]->getLicenseObj()->getUrl()
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
          'copyright' => implode("\n", $licenses->getCopyrights()),
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
   * @param array $packageNodes
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
    $this->reportutils->updateOrInsertReportgenEntry($uploadId, $jobId, $fileName);
  }

  /**
   * @brief Get the mime type of the upload
   * @return string Mime type of the upload
   */
  protected function getMimeType($uploadId)
  {
    $sql = "SELECT mimetype_name
      FROM upload u
      JOIN pfile pf ON u.pfile_fk = pf.pfile_pk
      JOIN mimetype m ON pf.pfile_mimetypefk = m.mimetype_pk
      WHERE u.upload_pk = $1";

    $row = $this->dbManager->getSingleRow($sql, [$uploadId], __METHOD__);
    return $row['mimetype_name'];
  }
}

$agent = new CycloneDXAgent();
$agent->scheduler_connect();
$agent->run_scheduler_event_loop();
$agent->scheduler_disconnect(0);
