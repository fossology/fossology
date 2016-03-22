<?php
/*
 * Copyright (C) 2015, Siemens AG
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
use Fossology\Lib\Dao\LicenseDao;
use Fossology\Lib\Dao\UploadPermissionDao;
use Fossology\Lib\Db\DbManager;

use EasyRdf_Graph;

include_once(__DIR__ . "/version.php");
include_once(__DIR__ . "/services.php");

class SpdxTwoImportAgent extends Agent
{
  const TERMS = 'http://spdx.org/rdf/terms#';
  const REPORT_KEY = "spdxReport";

  /** @var UploadDao */
  private $uploadDao;
  /** @var LicenseDao */
  private $licenseDao;
  /** @var UploadPermissionDao */
  private $permissionDao;
  /** @var DbManager */
  protected $dbManager;

  private $agent_pk;

  function __construct()
  {
    parent::__construct(AGENT_SPDX2IMPORT_NAME, AGENT_SPDX2IMPORT_VERSION, AGENT_SPDX2IMPORT_REV);
    $this->uploadDao = $this->container->get('dao.upload');
    $this->licenseDao = $this->container->get('dao.license');
    $this->permissionDao = $this->container->get('dao.upload.permission');
    $this->dbManager = $this->container->get('db.manager');
    $this->agentSpecifLongOptions[] = self::REPORT_KEY.':';

    $this->setAgent_PK();
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
      throw new Exception("agent_pk could not be set");
    }
    $this->agent_pk = intval($row['agent_pk']);
  }

  function processUploadId($uploadId)
  {
    echo "Start working on Upload: $uploadId\n";
    $this->heartbeat(0);

    $args = $this->args;

    $groupId = $this->groupId;
    if (!$this->permissionDao->isEditable($uploadId, $groupId)) {
      return false;
    }

    $spdxReportPre = array_key_exists(self::REPORT_KEY,$args) ? $args[self::REPORT_KEY] : ""; 
    if($spdxReportPre)
    {
      echo "The SPDX report filename is: ".htmlspecialchars($spdxReportPre)."\n";
    }
    global $SysConf;
    $fileBase = $SysConf['FOSSOLOGY']['path']."/SPDX2Import/";
    $spdxReport = $fileBase.$spdxReportPre;
    if(empty($spdxReportPre) || !is_readable($spdxReport))
    {
      echo "No SPDX report was uploaded\n";
      echo "Maybe the permissions on ".htmlspeciealchars($fileBase)." are not sufficient\n";
      return false;
    }

    $this->dbManager->insertTableRow('reportgen',
            array('upload_fk'=>$uploadId, 'job_fk'=>$this->jobId, 'filepath'=>$spdxReport),
            __METHOD__.'addToReportgen');

    $this->walkAllFiles($spdxReport,$uploadId);

    return true;
  }

  private static function stripPrefix($str)
  {
    $parts = explode('#', $str, 2);
    if (sizeof($parts) === 2)
    {
      return $parts[1];
    }
    return "";
  }

  private static function stripPrefixes($strs)
  {
    return array_map(array(__CLASS__, "stripPrefix"), $strs);
  }

  private static function getTypes($properties)
  {
    $key = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#type';
    if (isset($properties[$key]))
    {
      $func = function($value) { return $value['value']; };
      return array_map($func, $properties[$key]);
    }
    return null;
  }

  // or $kind='licenseInfoInFile'
  private static function getLicenseInfoForFile(&$properties, $kind='licenseConcluded')
  {
    $func = function($value) { return $value['value']; };
    $key = self::TERMS . '' . $kind;
    return array_map($func, $properties[$key]);
  }

  private static function getHashesMap(&$index, &$property)
  {
    $key = self::TERMS . 'checksum';
    $func = function($value) { return $value['value']; };
    $hashKeys = array_map($func, $property[$key]);

    $hashes = array();
    $keyAlgo = self::TERMS . 'algorithm';
    $keyAlgoVal = self::TERMS . 'checksumValue';
    $algoKeyPrefix = self::TERMS . 'checksumAlgorithm_';
    foreach ($hashKeys as $hashKey)
    {
      $hashItem = $index[$hashKey];
      $algorithm = $hashItem[$keyAlgo][0]['value'];
      if(substr($algorithm, 0, strlen($algoKeyPrefix)) === $algoKeyPrefix)
      {
        $algorithm = substr($algorithm, strlen($algoKeyPrefix));
      }
      $hashes[$algorithm] = $hashItem[$keyAlgoVal][0]['value'];
    }

    return $hashes;
  }

  private static function isPropertyAFile(&$property)
  {
    $key = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#type';
    $target = self::TERMS . 'File';

    return isset($property[$key]) &&
      $property[$key][0]['value'] === $target;
  }

  private static function getValue(&$property, $key)
  {
    $key = self::TERMS . $key;
    if (self::isPropertyAFile($property) &&
        isset($property[$key]))
    {
      return $property[$key][0]['value'];
    }
    return false;
  }

  private static function getFileName(&$property)
  {
    return self::getValue($property, 'fileName');
  }

  private static function getCopyrightText(&$property)
  {
    return self::getValue($property, 'copyrightText');
  }

  private static function doHashesMatch(&$index, &$property, $hashesInDB)
  {
    $actualHashes = self::getHashesMap($index, $property);
    foreach ($actualHashes as $hashType => $hashValue)
    {
      if (!isset($hashesInDB[$hashType]) ||
          strcasecmp($hashesInDB[$hashType], $hashValue) !== 0 )
      {
        return false;
      }
    }
    return true;
  }

  private function loadGraph($filename, $uri = null)
  {
    /** @var EasyRdf_Graph */
    $graph = new EasyRdf_Graph();
    $graph->parseFile($filename, 'rdfxml', $uri);
    return $graph;
  }

  private function graphToIndex(EasyRdf_Graph $graph)
  {
    return $graph->toRdfPhp();
  }

  private function getPFilePerFileName($upload_pk)
  {
    $uploadtreeTablename = GetUploadtreeTableName($upload_pk);

    $uploadtreeRec = $this->dbManager->getSingleRow(
      'SELECT uploadtree_pk FROM uploadtree WHERE parent IS NULL AND upload_fk=$1',
      array($upload_pk),
      __METHOD__.'.find.uploadtree.to.use.in.browse.link');
    $uploadtree_pk = $uploadtreeRec['uploadtree_pk'];
    /** @var ItemTreeBounds */
    $itemTreeBounds = $this->uploadDao->getItemTreeBounds($uploadtree_pk, $uploadtreeTablename);

    return $this->uploadDao->getPFileDataPerFileName($itemTreeBounds);
  }

  private function getShortnamesFromLicenseExpression($licenseExpr)
  {
    $licenseExpr = trim(ltrim(rtrim(trim($licenseExpr),')'),'('));
    $licensePrefix = "LicenseRef-";
    if(strpos(' ', $licenseExpr) !== false)
    {
      $exploded = explode(' ', $licenseExpr);
      $shortnamess = array_map(array(&$this, "getLicenseIdsFromLicenseExpression"), $exploded);
      $collectedShortnames = array();
      foreach ($shortnamess as $shortnames)
      {
        $collectedShortnames[] = $shortnames;
      }
      return $collectedShortnames;
    }
    else if($licenseExpr == "OR")
    {
      return array($this->getLicenseIdsFromLicenseExpression("Dual-license"));
    }
    else if(substr($licenseExpr, 0, strlen($licensePrefix)) === $licensePrefix)
    {
      return array(substr($licenseExpr, strlen($licensePrefix)));
    }
    return array($licenseExpr);
  }

  private function insertFoundItemsToDB($licenseExpressions, $pfile_fk, $asConclusion=false, $percentage=100)
  {
    foreach($licenseExpressions as $licenseExpr)
    {
      $shortnames = $this->getShortnamesFromLicenseExpression($licenseExpr);
      foreach($shortnames as $shortname)
      {
        $lic = $this->licenseDao->getLicenseByShortName($shortname);
        if($lic !== null)
        {
          $this->heartbeat(1);
          $this->saveAsFindingToDB($lic->getId(), $this->agent_pk, $pfile_fk, $percentage);
          // $this->clearingDao->insertClearingEvent($uploadTreeId, $userId, $groupId, $licenseId, $isRemoved, $type = ClearingEventTypes::USER, $reportInfo = '', $comment = '', $jobId=0);
        }
      }
    }
  }

  private function saveAsFindingToDB($licenseId, $agent_fk, $pfile_fk, $percent)
  {
    return $this->dbManager->getSingleRow(
      "insert into license_file(rf_fk, agent_fk, pfile_fk, rf_match_pct) values($1,$2,$3,$4) RETURNING fl_pk",
      array($licenseId, $agent_fk, $pfile_fk, $percent),
      __METHOD__."forSpdx2Import");
  }

  public function walkAllFiles($SPDXfilename, $upload_pk)
  {
    // Prepare data from SPDX import
    $index = $this->graphToIndex($this->loadGraph($SPDXfilename));

    // Prepare data from DB
    $pfilePerFileName = $this->getPFilePerFileName($upload_pk);

    foreach ($index as $subject => $properties)
    {
      if (!self::isPropertyAFile($properties))
      {
        continue;
      }

      $filename = self::getFileName($properties);
      if(!array_key_exists($filename, $pfilePerFileName))
      {
        continue;
      }

      $entry = $pfilePerFileName[$filename];
      if(!self::doHashesMatch($index, $properties, $entry))
      {
        echo $filename . " has different hashes\n";
        continue;
      }

      $licenseInfosInFile = self::stripPrefixes(self::getLicenseInfoForFile($properties, 'licenseInfoInFile'));
      $this->insertFoundItemsToDB($licenseInfosInFile, $entry['pfile_pk']);

      $licensesConcluded = self::stripPrefixes(self::getLicenseInfoForFile($properties, 'licenseConcluded'));
      $this->insertFoundItemsToDB($licensesConcluded, $entry['pfile_pk']);


    }
  }

  function dump($graph)
  {
    echo $graph->dump();
  }
}

$agent = new SpdxTwoImportAgent();
$agent->scheduler_connect();
$agent->run_scheduler_event_loop();
$agent->scheduler_disconnect(0);