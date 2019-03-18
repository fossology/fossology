<?php
/*
 * Copyright (C) 2015-2017, Siemens AG
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
namespace Fossology\ReportImport;

use Fossology\Lib\Agent\Agent;
use Fossology\Lib\Dao\ClearingDao;
use Fossology\Lib\Dao\LicenseDao;
use Fossology\Lib\Dao\UserDao;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Dao\UploadPermissionDao;
use Fossology\Lib\Data\Tree\ItemTreeBounds;
use Fossology\Lib\Db\DbManager;
require_once 'SpdxTwoImportSource.php';
require_once 'XmlImportSource.php';
require_once 'ReportImportSink.php';
require_once 'ReportImportHelper.php';
require_once 'ReportImportConfiguration.php';

use EasyRdf_Graph;

require_once 'version.php';
require_once 'services.php';

class ReportImportAgent extends Agent
{
  const REPORT_KEY = "report";
  const ACLA_KEY = "addConcludedAsDecisions";

  /** @var UploadDao */
  private $uploadDao;
  /** @var UserDao */
  private $userDao;
  /** @var UploadPermissionDao */
  private $permissionDao;
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
    $this->permissionDao = $this->container->get('dao.upload.permission');
    $this->dbManager = $this->container->get('db.manager');
    $this->userDao = $this->container->get('dao.user');
    $this->licenseDao = $this->container->get('dao.license');
    $this->clearingDao = $this->container->get('dao.clearing');
    $this->copyrightDao = $this->container->get('dao.copyright');
    $this->agentSpecifLongOptions[] = self::REPORT_KEY.':';
    $this->agentSpecifLongOptions[] = self::ACLA_KEY.':';

    $this->setAgent_PK();
  }

  private function setAgent_PK()
  {
    // should be already set in $this->agentId?
    $row = $this->dbManager->getSingleRow(
      "SELECT agent_pk FROM agent WHERE agent_name = $1 order by agent_ts desc limit 1",
      array(AGENT_REPORTIMPORT_NAME), __METHOD__."select"
    );

    if ($row === false)
    {
      throw new \Exception("agent_pk could not be determined");
    }
    $this->agent_pk = intval($row['agent_pk']);
  }

  /**
   * @param string[] $args
   * @param string $longArgsKey
   *
   * Duplicate of function in file ../../spdx2/agent/spdx2utils.php
   */
  static private function preWorkOnArgsFlp(&$args,$longArgsKey)
  {
    if (is_array($args) &&
      array_key_exists($longArgsKey, $args)){
      echo "DEBUG: unrefined \$longArgs are: ".$args[$longArgsKey]."\n";
      $chunks = explode(" --", $args[$longArgsKey]);
      if(sizeof($chunks > 1))
      {
        $args[$longArgsKey] = $chunks[0];
        foreach(array_slice($chunks, 1) as $chunk)
        {
          if (strpos($chunk, '=') !== false)
          {
            list($key, $value) = explode('=', $chunk, 2);
            $args[$key] = $value;
          }
          else
          {
            $args[$chunk] = true;
          }
        }
      }
    }
  }

  function processUploadId($uploadId)
  {
    $this->heartbeat(0);

    self::preWorkOnArgsFlp($this->args, self::REPORT_KEY);
    if (!$this->permissionDao->isEditable($uploadId, $this->groupId)) {
      return false;
    }

    $reportPre = array_key_exists(self::REPORT_KEY,$this->args) ? $this->args[self::REPORT_KEY] : "";
    global $SysConf;
    $fileBase = $SysConf['FOSSOLOGY']['path']."/ReportImport/";
    $report = $fileBase.$reportPre;
    if(empty($reportPre) || !is_readable($report))
    {
      echo "No report was uploaded\n";
      echo "Maybe the permissions on ".htmlspecialchars($fileBase)." are not sufficient\n";
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

    if (($pfilesByFilename !== null || sizeof($pfilesByFilename) === 0))
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
    $length = strlen($filename);
    if($length > 3)
    {
      foreach(array_keys($pfilesPerFileName) as $key)
      {
        if(substr($key, -$length) === $filename)
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
   * @return SpdxTwoImportSource
   * @throws \Exception
   */
  private function getImportSource($reportFilename)
  {

    if(substr($reportFilename, -4) === ".xml")
    {
      $importSource = new XmlImportSource($reportFilename);
      if($importSource->parse())
      {
        return $importSource;
      }
    }

    if(substr($reportFilename, -4) === ".rdf")
    {
      $importSource = new SpdxTwoImportSource($reportFilename);
      if($importSource->parse())
      {
        return $importSource;
      }
    }

    error_log("ERROR: can not handle report");
    throw new \Exception("unsupported report type with filename: $reportFilename");
  }

  public function walkAllFiles($reportFilename, $upload_pk, $configuration)
  {
    /** @var ReportImportSource */
    $source = $this->getImportSource($reportFilename);
    if($source === NULL)
    {
      return;
    }

    /** @var ReportImportSink */
    $sink = new ReportImportSink($this->agent_pk, $this->userDao, $this->licenseDao, $this->clearingDao, $this->copyrightDao,
                                 $this->dbManager, $this->groupId, $this->userId, $this->jobId, $configuration);

    // Prepare data from DB
    $itemTreeBounds = $this->getItemTreeBounds($upload_pk);
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

      if ($pfiles === null || sizeof($pfiles) === 0)
      {
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
