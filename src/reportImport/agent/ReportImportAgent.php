<?php
/*
 SPDX-FileCopyrightText: © 2015-2017,2024 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/
namespace Fossology\ReportImport;

use Fossology\Lib\Agent\Agent;
use Fossology\Lib\Dao\ClearingDao;
use Fossology\Lib\Dao\CopyrightDao;
use Fossology\Lib\Dao\LicenseDao;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Dao\UserDao;
use Fossology\Lib\Data\Tree\ItemTreeBounds;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Exception;
use Fossology\Lib\Util\StringOperation;

require_once 'SpdxTwoImportSource.php';
require_once 'SpdxThreeImportSource.php';
require_once 'XmlImportSource.php';
require_once 'ReportImportSink.php';
require_once 'ReportImportHelper.php';
require_once 'ReportImportConfiguration.php';

require_once 'version.php';
require_once 'services.php';

class ReportImportAgent extends Agent
{
  const REPORT_KEY = "report";
  const ACLA_KEY = "addConcludedAsDecisions";
  const ACLAO_KEY = "addConcludedAsDecisionsOverwrite";
  const ACLATBD_KEY = "addConcludedAsDecisionsTBD";
  const ALIFI_KEY = "addLicenseInfoFromInfoInFile";
  const ALFC_KEY = "addLicenseInfoFromConcluded";
  const ANLA_KEY = "addNewLicensesAs";
  const LMATCH_KEY = "licenseMatch";
  const COPYRIGHTS_KEY = "addCopyrights";

  /** @var UploadDao */
  private $uploadDao;
  /** @var UserDao */
  private $userDao;
  /** @var DbManager */
  protected $dbManager;
  /** @var LicenseDao */
  protected $licenseDao;
  /** @var ClearingDao */
  protected $clearingDao;
  /** @var CopyrightDao */
  private $copyrightDao;

  protected $agent_pk;

  function __construct()
  {
    parent::__construct(AGENT_REPORTIMPORT_NAME, AGENT_REPORTIMPORT_VERSION, AGENT_REPORTIMPORT_REV);
    $this->uploadDao = $this->container->get('dao.upload');
    $this->dbManager = $this->container->get('db.manager');
    $this->userDao = $this->container->get('dao.user');
    $this->licenseDao = $this->container->get('dao.license');
    $this->clearingDao = $this->container->get('dao.clearing');
    $this->copyrightDao = $this->container->get('dao.copyright');
    
    $this->agentSpecifLongOptions[] = self::REPORT_KEY.':';
    $this->agentSpecifLongOptions[] = self::ACLA_KEY.':';
    $this->agentSpecifLongOptions[] = self::ACLAO_KEY.':';
    $this->agentSpecifLongOptions[] = self::ACLATBD_KEY.':';
    $this->agentSpecifLongOptions[] = self::ALIFI_KEY.':';
    $this->agentSpecifLongOptions[] = self::ALFC_KEY.':';
    $this->agentSpecifLongOptions[] = self::ANLA_KEY.':';
    $this->agentSpecifLongOptions[] = self::LMATCH_KEY.':';
    $this->agentSpecifLongOptions[] = self::COPYRIGHTS_KEY.':';

    $this->setAgent_PK();
  }

  /**
   * @throws \Exception In case agent_pk could not be identified
   */
  private function setAgent_PK()
  {
    // should be already set in $this->agentId?
    $row = $this->dbManager->getSingleRow(
      "SELECT agent_pk FROM agent WHERE agent_name = $1 order by agent_ts desc limit 1",
      array(AGENT_REPORTIMPORT_NAME), __METHOD__."select"
    );

    if ($row === false) {
      throw new \Exception("agent_pk could not be determined");
    }
    $this->agent_pk = intval($row['agent_pk']);
  }

  function processUploadId($uploadId)
  {
    $this->heartbeat(0);

    $reportPre = array_key_exists(self::REPORT_KEY,$this->args) ? $this->args[self::REPORT_KEY] : "";
    $reportPre = trim($reportPre, "\"'");
    global $SysConf;
    $fileBase = $SysConf['FOSSOLOGY']['path'] . "/ReportImport/";
    $report = $fileBase . $reportPre;
    if(empty($reportPre) || !is_readable($report)) {
      echo "No report was uploaded\n";
      echo "Maybe the permissions on " . htmlspecialchars($fileBase) . " are not sufficient\n";
      return false;
    }

    $this->dbManager->insertTableRow('reportgen',
            array('upload_fk'=>$uploadId, 'job_fk'=>$this->jobId, 'filepath'=>$report),
            __METHOD__.'addToReportgen');

    $configuration = new ReportImportConfiguration($this->args);

    $this->walkAllFiles($report, $uploadId, $configuration);

    return true;
  }

  private function getItemTreeBounds($upload_pk)
  {
    $uploadtreeTablename = GetUploadtreeTableName($upload_pk);

    $uploadtreeRec = $this->dbManager->getSingleRow(
      'SELECT uploadtree_pk FROM uploadtree WHERE parent IS NULL AND upload_fk=$1',
      array($upload_pk),
      __METHOD__.'.find.uploadtree.to.use.in.browse.link');
    $uploadtree_pk = $uploadtreeRec['uploadtree_pk'];
    /** @var ItemTreeBounds */
    return $this->uploadDao->getItemTreeBounds($uploadtree_pk, $uploadtreeTablename);
  }

  static private function getEntries($fileId, $fileName, &$pfilePerFileName, &$hashMap=NULL, &$pfilesPerHash=NULL, $hashAlgo="sha1")
  {
    $pfilesByFilename = self::getEntriesForFilename($fileName, $pfilePerFileName);

    if (($pfilesByFilename !== null && sizeof($pfilesByFilename) > 0))
    {
      if ( $hashMap !== null && sizeof($hashMap) > 0 )
      {
        $pfiles = array();
        foreach ($pfilesByFilename as $pfile)
        {
          if (strtolower($pfile[$hashAlgo]) !== strtolower($hashMap[$hashAlgo]))
          {
            print "INFO: the file with fileName=[$fileName] does not match the hash of pfile_pk=[" . $pfile['pfile_pk'] . "] and uploadtree_pk=[" . $pfile['uploadtree_pk'] . "]\n";
          }
          else
          {
            $pfiles[] = $pfile;
          }
        }
        return $pfiles;
      }
      else
      {
        return $pfilesByFilename;
      }
    }

    if ($pfilesPerHash !== null && sizeof($pfilesPerHash) > 0 &&
      $hashMap !== null && sizeof($hashMap) > 0 )
    {
      return self::getEntriesForHash($hashMap, $pfilesPerHash, 'sha1');
    }

    return array();
  }

  static private function getEntriesForFilename($filename, &$pfilesPerFileName)
  {
    if(array_key_exists($filename, $pfilesPerFileName))
    {
      return array($pfilesPerFileName[$filename]);
    }
    # Allow matching "./README.MD" with "pack.tar.gz/pack.tar/README.MD" by
    # matching "/README.MD" with "/README.MD".
    $length = strlen($filename) - 1;
    $fileWithoutDot = substr($filename, -$length);
    if($length > 3)
    {
      foreach(array_keys($pfilesPerFileName) as $key)
      {
        if(substr($key, -$length) === $fileWithoutDot)
        {
          return array($pfilesPerFileName[$key]);
        }
      }
    }
    return array();
  }

  static private function getEntriesForHash(&$hashMap, &$pfilesPerHash, $hashAlgo)
  {
    if(!array_key_exists($hashAlgo, $hashMap))
    {
      return array();
    }

    $hash = strtolower($hashMap[$hashAlgo]);
    if(!array_key_exists($hash, $pfilesPerHash))
    {
      return array();
    }
    return $pfilesPerHash[$hash];
  }

  /**
   * @param string $reportFilename
   * @return SpdxTwoImportSource|SpdxThreeImportSource|XmlImportSource
   * @throws \Exception
   */
  private function getImportSource($reportFilename)
  {

    if(StringOperation::stringEndsWith($reportFilename, ".rdf") ||
      StringOperation::stringEndsWith($reportFilename, ".rdf.xml") ||
      StringOperation::stringEndsWith($reportFilename, ".ttl")){
    /**
     * @param string $version
     * @return specVersion for RDF report parsing
     */
      $parse = new SpdxTwoImportSource($reportFilename);
      $version = $parse->getVersion();
      if($version == "2.2" || $version == "2.3"){
        $importSource = new SpdxTwoImportSource($reportFilename);
        if($importSource->parse()) {
          return $importSource;
        }
      }
      else{
        $importSource = new SpdxThreeImportSource($reportFilename);
        if($importSource->parse()) {
          return $importSource;
        }
      }
    }

    if (StringOperation::stringEndsWith($reportFilename, ".xml")) {
      $importSource = new XmlImportSource($reportFilename);
      if($importSource->parse()) {
        return $importSource;
      }
    }

    error_log("ERROR: can not handle report");
    throw new \Exception("unsupported report type with filename: $reportFilename");
  }

  /**
   * @throws Exception If parent item bounds could not be determined.
   */
  public function walkAllFiles($reportFilename, $upload_pk, $configuration)
  {
    /** @var ImportSource $source */
    $source = $this->getImportSource($reportFilename);
    if ($source === NULL) {
      return;
    }

    /** @var ReportImportSink $sink */
    $sink = new ReportImportSink($this->agent_pk, $this->userDao,
      $this->licenseDao, $this->clearingDao, $this->copyrightDao,
      $this->dbManager, $this->groupId, $this->userId, $this->jobId,
      $configuration);

    // Prepare data from DB
    $itemTreeBounds = $this->uploadDao->getParentItemBounds($upload_pk);
    $pfilePerFileName = $this->uploadDao->getPFileDataPerFileName($itemTreeBounds);
    $pfilesPerHash = $this->uploadDao->getPFilesDataPerHashAlgo($itemTreeBounds, 'sha1');

    foreach ($source->getAllFiles() as $fileId => $fileName)
    {
      $hashMap = NULL;
      if ($pfilesPerHash !== NULL && sizeof($pfilesPerHash) > 0)
      {
        $hashMap = $source->getHashesMap($fileId);
      }

      $pfiles = self::getEntries($fileId,
        $fileName, $pfilePerFileName,
        $hashMap, $pfilesPerHash, 'sha1');

      if ($pfiles === null || sizeof($pfiles) === 0) {
        print "WARN: no match for fileId=[".$fileId."] with filename=[".$fileName."]\n";
        continue;
      }

      $this->heartbeat(sizeof($pfiles));

      $data = $source->getDataForFile($fileId)
            ->setPfiles($pfiles);
      $sink->handleData($data);
    }
  }
}
