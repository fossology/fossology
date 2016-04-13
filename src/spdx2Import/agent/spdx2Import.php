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
use Fossology\Lib\Data\Clearing\ClearingEventTypes;

use EasyRdf_Graph;

include_once(__DIR__ . "/version.php");
include_once(__DIR__ . "/services.php");

class SpdxTwoImportAgent extends Agent
{
  const TERMS = 'http://spdx.org/rdf/terms#';
  const SYNTAX_NS = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#';
  const REPORT_KEY = "spdxReport";

  /** @var ClearingDao */
  private $clearingDao;
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
    $this->clearingDao = $this->container->get('dao.clearing');
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
    $this->heartbeat(0);

    $args = $this->args;

    $groupId = $this->groupId;
    if (!$this->permissionDao->isEditable($uploadId, $groupId)) {
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
  private static function getLicenseInfoForFile(&$properties, $kind='licenseConcluded', &$index=null)
  {
    $func = function($value) { return $value['value']; };
    $key = self::TERMS . $kind;

    if($properties[$key][0]['type'] === 'uri')
    {
      return array_map($func, $properties[$key]);
    }
    else if($properties[$key][0]['type'] === 'bnode' &&
            array_key_exists($properties[$key][0]['value'],$index))
    {
      $conclusion = ($index[$properties[$key][0]['value']]);
      if ($conclusion[self::SYNTAX_NS . 'type'][0]['value'] == self::TERMS . 'DisjunctiveLicenseSet' &&
        array_key_exists(self::TERMS . 'member',$conclusion))
      {
        return array_map($func, $conclusion[self::TERMS . 'member']);
      }
    }
    echo "the license info type ".$properties[$key][0]['type']." is not supported";
    return array();
  }

  private static function getCopyrightInfoForFile(&$properties)
  {
    $func = function($value) { return $value['value']; };
    $key = self::TERMS . "copyrightText";
    return array_map($func, $properties[$key])[0];
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
      $shortnamess = array_map(array(&$this, "getShortnamesFromLicenseExpression"), $exploded);
      $collectedShortnames = array();
      foreach ($shortnamess as $shortnames)
      {
        $collectedShortnames[] = $shortnames;
      }
      return $collectedShortnames;
    }
    else if($licenseExpr == "OR")
    {
      return array("Dual-license");
    }
    else if(substr($licenseExpr, 0, strlen($licensePrefix)) === $licensePrefix)
    {
      return array(urldecode(substr($licenseExpr, strlen($licensePrefix))));
    }
    return array(urldecode($licenseExpr));
  }

  private function insertFoundLicenseInfoToDB(&$licenseExpressions, &$entry, $asConclusion=false)
  {
    foreach($licenseExpressions as $licenseExpr)
    {
      $shortnames = $this->getShortnamesFromLicenseExpression($licenseExpr);
      foreach($shortnames as $shortname)
      {
        if($shortname == "noassertion")
        {
          continue;
        }
        $lic = $this->licenseDao->getLicenseByShortName($shortname);
        if($lic !== null)
        {
          $this->heartbeat(1);
          if($asConclusion)
          {
            $this->clearingDao->insertClearingEvent($entry['uploadtree_pk'],
                                                    $this->userId,
                                                    $this->groupId,
                                                    $lic->getId(),
                                                    false,
                                                    ClearingEventTypes::IMPORT,
                                                    '', // reportInfo
                                                    'Imported from SPDX2 report', // comment
                                                    $this->jobId);
          }
          else
          {
            $this->saveAsLicenseFindingToDB($lic->getId(), $entry['pfile_pk']);
          }
        }
        else
        {
          echo "No license with shortname=\"$shortname\" found\n";
          // TODO: create license candidate from information in SPDX
        }
      }
    }
  }

  private function saveAsLicenseFindingToDB($licenseId, $pfile_fk)
  {
    return $this->dbManager->getSingleRow(
      "insert into license_file(rf_fk, agent_fk, pfile_fk) values($1,$2,$3) RETURNING fl_pk",
      array($licenseId, $this->agent_pk, $pfile_fk),
      __METHOD__."forSpdx2Import");
  }

  private function insertFoundCopyrightInfoToDB($copyrightText, $pfile_fk)
  {
    $copyrightLines = array_map("trim", explode("\n",$copyrightText));
    foreach ($copyrightLines as $copyrightLine)
    {
      if(empty($copyrightLine))
      {
        continue;
      }

      $this->saveAsCopyrightFindingToDB($copyrightLine, $pfile_fk);
    }
  }

  private function saveAsCopyrightFindingToDB($content, $pfile_fk)
  {
    return $this->dbManager->getSingleRow(
      "insert into copyright(agent_fk, pfile_fk, content, hash, type) values($1,$2,$3,md5($3),$4) RETURNING ct_pk",
      array($this->agent_pk, $pfile_fk, $content, "statement"),
      __METHOD__."forSpdx2Import");
  }

  public function walkAllFiles($SPDXfilename, $upload_pk, $addConcludedLicsAsConclusion=falseq)
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

      $licenseInfosInFile = self::stripPrefixes(self::getLicenseInfoForFile($properties, 'licenseInfoInFile', $index));
      $this->insertFoundLicenseInfoToDB($licenseInfosInFile, $entry);

      $licensesConcluded = self::stripPrefixes(self::getLicenseInfoForFile($properties, 'licenseConcluded', $index));
      $this->insertFoundLicenseInfoToDB($licensesConcluded, $entry, $addConcludedLicsAsConclusion);

      $copyrightText = self::getCopyrightInfoForFile($properties);
      $this->insertFoundCopyrightInfoToDB($copyrightText, $entry['pfile_pk']);
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