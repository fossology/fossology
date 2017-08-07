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
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Dao\UploadPermissionDao;
use Fossology\Lib\Db\DbManager;
require_once 'SpdxTwoImportSource.php';
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
  const KEYS = [ self::ACLA_KEY, 'addLicenseInfoFromInfoInFile', 'addLicenseInfoFromConcluded', 'addConcludedAsDecisionsOverwrite', 'addConcludedAsDecisionsTBD' ];

  /** @var UploadDao */
  private $uploadDao;
  /** @var UploadPermissionDao */
  private $permissionDao;
  /** @var DbManager */
  protected $dbManager;
  /** @var LicenseDao */
  protected $licenseDao;
  /** @var ClearingDao */
  protected $clearingDao;

  protected $agent_pk;

  function __construct()
  {
    parent::__construct(AGENT_REPORTIMPORT_NAME, AGENT_REPORTIMPORT_VERSION, AGENT_REPORTIMPORT_REV);
    $this->uploadDao = $this->container->get('dao.upload');
    $this->permissionDao = $this->container->get('dao.upload.permission');
    $this->dbManager = $this->container->get('db.manager');
    $this->agentSpecifLongOptions[] = self::REPORT_KEY.':';
    $this->agentSpecifLongOptions[] = self::ACLA_KEY.':';

    $this->setAgent_PK();

    /** @var LicenseDao */
    $this->licenseDao = $this->container->get('dao.license');
    /** @var ClearingDao */
    $this->clearingDao = $this->container->get('dao.clearing');
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
   * @return string[] $args
   *
   * Duplicate of function in file ../../spdx2/agent/spdx2utils.php
   */
  static private function preWorkOnArgsFlp(&$args,$longArgsKey)
  {
    if (is_array($args) &&
      array_key_exists($longArgsKey, $args)){
      echo "DEBUG: unrefined \$longArgs are: ".$args[$longArgsKey]."\n";
      $chunks = explode(" --", $args[$longArgsKey]);
      if(sizeof(chunks > 1))
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

  static private function getEntriesForHash(&$hashMap, &$pfilesPerHash, $hashAlgo)
  {
    if(!array_key_exists($hashAlgo, $hashMap))
    {
      return null;
    }

    $hash = strtolower($hashMap[$hashAlgo]);
    if(!array_key_exists($hash, $pfilesPerHash))
    {
      return null;
    }
    return $pfilesPerHash[$hash];
  }

  /**
   * @param string $reportFilename
   * @param ReportImportConfiguration $configuration
   * @return SpdxTwoImportSource
   */
  private function getImportSource($reportFilename, $configuration)
  {
    if($configuration->getReportType() === "spdx-rdf")
    {
      return new SpdxTwoImportSource($reportFilename);
    }
    else
    {
      echo "ERROR: can not handle report of type=[" . $configuration->getReportType() . "]\n";
    }
  }

  public function walkAllFiles($reportFilename, $upload_pk, $configuration)
  {
    $source = $this->getImportSource($reportFilename, $configuration);

    /** @var ReportImportSink */
    $sink = new ReportImportSink($this->agent_pk, $this->licenseDao, $this->clearingDao, $this->dbManager,
                                  $this->groupId, $this->userId, $this->jobId, $configuration);

    // Prepare data from DB
    $itemTreeBounds = $this->getItemTreeBounds($upload_pk);
    $pfilesPerHash = $this->uploadDao->getPFilesDataPerHashAlgo($itemTreeBounds, 'sha1');

    foreach ($source->getAllFileIds() as $fileId)
    {
      $hashMap = $source->getHashesMap($fileId);
      $pfiles = self::getEntriesForHash($hashMap, $pfilesPerHash, 'sha1');
      $this->heartbeat(sizeof($pfiles));
      if ($pfiles === null || sizeof($pfiles) === 0)
      {
        print "no match for file: " . $fileId . "\n";
        continue;
      }

      $data = $source->getDataForFile($fileId)
            ->setPfiles($pfiles);
      $sink->handleData($data);
    }
  }
}
