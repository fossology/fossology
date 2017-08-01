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
namespace Fossology\SpdxTwoImport;

use Fossology\Lib\Agent\Agent;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Dao\UploadPermissionDao;
use Fossology\Lib\Db\DbManager;
require_once 'SpdxTwoImportSource.php';
require_once 'SpdxTwoImportSink.php';
require_once 'SpdxTwoImportHelper.php';
require_once 'SpdxTwoImportConfiguration.php';

use EasyRdf_Graph;

require_once 'version.php';
require_once 'services.php';

class SpdxTwoImportAgent extends Agent
{
  const REPORT_KEY = "spdxReport";
  const ACLA_KEY = "addConcludedLicensesAs";

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
    parent::__construct(AGENT_SPDX2IMPORT_NAME, AGENT_SPDX2IMPORT_VERSION, AGENT_SPDX2IMPORT_REV);
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
      array(AGENT_SPDX2IMPORT_NAME), __METHOD__."select"
    );

    if ($row === false)
    {
      throw new Exception("agent_pk could not be determined");
    }
    $this->agent_pk = intval($row['agent_pk']);
  }

  /**
   * @param string[] $args
   * @param string $key1
   * @param string $key2
   *
   * @return string[] $args
   *
   * Duplicate of function in file ../../spdx2/agent/spdx2utils.php
   */
  static private function preWorkOnArgsFlp($args,$key1,$key2)
  {
    $needle = ' --'.$key2.'=';
    if (is_array($args) &&
        array_key_exists($key1, $args) &&
        strpos($args[$key1],$needle) !== false) {
      $exploded = explode($needle,$args[$key1]);
      $args[$key1] = trim($exploded[0]);
      $args[$key2] = trim($exploded[1]);
    }
    return $args;
  }

  function processUploadId($uploadId)
  {
    $this->heartbeat(0);

    $args = self::preWorkOnArgsFlp($this->args, self::REPORT_KEY, self::ACLA_KEY);

    if (!$this->permissionDao->isEditable($uploadId, $this->groupId)) {
      return false;
    }

    $spdxReportPre = array_key_exists(self::REPORT_KEY,$args) ? $args[self::REPORT_KEY] : "";
    global $SysConf;
    $fileBase = $SysConf['FOSSOLOGY']['path']."/SPDX2Import/";
    $spdxReport = $fileBase.$spdxReportPre;
    if(empty($spdxReportPre) || !is_readable($spdxReport))
    {
      echo "No SPDX report was uploaded\n";
      echo "Maybe the permissions on ".htmlspecialchars($fileBase)." are not sufficient\n";
      return false;
    }

    $this->dbManager->insertTableRow('reportgen',
            array('upload_fk'=>$uploadId, 'job_fk'=>$this->jobId, 'filepath'=>$spdxReport),
            __METHOD__.'addToReportgen');

    $addConcludedLicsAsConclusion = array_key_exists(self::ACLA_KEY,$args) ? $args[self::ACLA_KEY] === "true" : false;

    $this->walkAllFiles($spdxReport, $uploadId, $addConcludedLicsAsConclusion);

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

  public function walkAllFiles($SPDXfilename, $upload_pk, $addConcludedLicsAsConclusion=false)
  {
    $configuration = new SpdxTwoImportConfiguration();
    /** @var SpdxTwoImportSink */
    $sink = new SpdxTwoImportSink($this->agent_pk, $this->licenseDao, $this->clearingDao, $this->dbManager,
                                  $this->groupId, $this->userId, $this->jobId, $configuration);

    // Prepare data from SPDX import
    /** @var SpdxTwoImportSource */
    $source = new SpdxTwoImportSource($SPDXfilename);

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
