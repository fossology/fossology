<?php
/*
 SPDX-FileCopyrightText: © 2019 Sandip Kumar Bhuyan <sandipbhuyan@gmail.com>
 SPDX-FileCopyrightText: © 2020 Siemens AG
 Author: Sandip Kumar Bhuyan<sandipbhuyan@gmail.com>,
         Shaheem Azmal M MD<shaheem.azmal@siemens.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\SoftwareHeritage;

use Fossology\Lib\Agent\Agent;
use Fossology\Lib\Dao\AgentDao;
use Fossology\Lib\Dao\LicenseDao;
use Fossology\Lib\Dao\SoftwareHeritageDao;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Util\HttpUtils;
use GuzzleHttp\Psr7;
use GuzzleHttp\Exception\RequestException;

include_once(__DIR__ . "/version.php");

/**
 * @file
 * @brief Software Heritage agent source
 * @class SoftwareHeritage
 * @brief The software heritage agent
 */
class softwareHeritageAgent extends Agent
{
  /** @var UploadDao $uploadDao
   * UploadDao object
   */
  private $uploadDao;

  /** @var LicenseDao $licenseDao
   * LicenseDao object
   */
  private $licenseDao;

  /**
   * configuraiton for software heritage api
   * @var array $configuration
   */
  private $configuration;

  /**
   * @var DbManager $dbManeger
   * DbManeger object
   */
  private $dbManeger;

  /**
   * @var AgentDao $agentDao
   * AgentDao object
   */
  protected $agentDao;

  /**
   * @var SoftwareHeritageDao $shDao
   * SoftwareHeritageDao object
   */
  private $softwareHeritageDao;

  /**
   * softwareHeritageAgent constructor.
   * @throws \Exception
   */
  function __construct()
  {
    global $SysConf;
    parent::__construct(SOFTWARE_HERITAGE_AGENT_NAME, AGENT_VERSION, AGENT_REV);
    $this->uploadDao = $this->container->get('dao.upload');
    $this->licenseDao = $this->container->get('dao.license');
    $this->dbManeger = $this->container->get('db.manager');
    $this->agentDao = $this->container->get('dao.agent');
    $this->softwareHeritageDao = $this->container->get('dao.softwareHeritage');
    $sysconfig = $SysConf['SYSCONFIG'];
    $this->configuration = [
      'url' => trim($sysconfig['SwhURL']),
      'uri' => trim($sysconfig['SwhBaseURL']),
      'content' => trim($sysconfig['SwhContent']),
      'maxtime' => intval($sysconfig['SwhSleep']),
      'token' => trim($sysconfig['SwhToken'])
    ];

    $this->guzzleClient = HttpUtils::getGuzzleClient($SysConf, $this->configuration['url'], $this->configuration['token']);
  }

  /**
   * @brief Run software heritage for a package
   * @param int $uploadId
   * @return bool
   * @throws \Fossology\Lib\Exception
   * @see Fossology::Lib::Agent::Agent::processUploadId()
   */
  function processUploadId($uploadId)
  {
    $itemTreeBounds = $this->uploadDao->getParentItemBounds($uploadId);
    $pfileFileDetails = $this->uploadDao->getPFileDataPerFileName($itemTreeBounds);
    $pfileFks = $this->softwareHeritageDao->getSoftwareHeritagePfileFk($uploadId);
    $agentId = $this->agentDao->getCurrentAgentId("softwareHeritage");
    $maxTime = $this->configuration['maxtime'];
    $maxTime = ($maxTime < 2) ? 2 : $maxTime;
    foreach ($pfileFileDetails as $pfileDetail) {
      // Skip files without valid pfile_pk to avoid NULL constraint violation
      if (empty($pfileDetail['pfile_pk']) || $pfileDetail['pfile_pk'] === null) {
        continue;
      }
      if (!in_array($pfileDetail['pfile_pk'], array_column($pfileFks, 'pfile_fk'))) {
        $this->processEachPfileForSWH($pfileDetail, $agentId, $maxTime);
      }
      $this->heartbeat(1);
    }
    return true;
  }

  /**
   * @brief process each pfile for software heritage
   * and wait till the reset time
   * @param int $pfileDetail
   * @param int $maxTime
   * @return bool
   */
  function processEachPfileForSWH($pfileDetail, $agentId, $maxTime)
  {
    list ($currentStatus, $currentResult) = $this->getSoftwareHeritageLicense($pfileDetail['sha256']);
    if (SoftwareHeritageDao::SWH_RATELIMIT_EXCEED == $currentStatus) {
      $this->heartbeat(0); //Fake heartbeat to keep the agent alive.
      $timeToReset = $currentResult - time();
      print "INFO :Software Heritage X-RateLimit-Limit reached. Next slot unlocks in ".gmdate("H:i:s", $timeToReset)."\n";
      if ($timeToReset > $maxTime) {
        sleep($maxTime);
      } else {
        sleep($timeToReset);
      }
      $this->processEachPfileForSWH($pfileDetail, $agentId, $maxTime);
    } else {
      $this->insertSoftwareHeritageRecord($pfileDetail['pfile_pk'], $currentResult, $agentId, $currentStatus);
    }

    return true;
  }

  /**
   * @brief Get the license details from software heritage
   * @param String $sha256
   *
   * @return array
   */
  protected function getSoftwareHeritageLicense($sha256)
  {
    $sha256 = strtolower($sha256);
    $URIToGetContent = $this->configuration['uri'] . $sha256;
    $URIToGetLicenses = $URIToGetContent . $this->configuration['content'];

    try {
      $response = $this->guzzleClient->get($URIToGetLicenses);
      $statusCode = $response->getStatusCode();
      $cookedResult = array();
      if ($statusCode == SoftwareHeritageDao::SWH_STATUS_OK) {
        $responseContent = json_decode($response->getBody()->getContents(),true);
        $cookedResult = $responseContent["facts"][0]["licenses"];
      } else if ($statusCode == SoftwareHeritageDao::SWH_RATELIMIT_EXCEED) {
        $responseContent = $response->getHeaders();
        $cookedResult = $responseContent["X-RateLimit-Reset"][0];
      } else if ($statusCode == SoftwareHeritageDao::SWH_NOT_FOUND) {
        $response = $this->guzzleClient->get($URIToGetContent);
        $responseContent = json_decode($response->getBody(),true);
        if (isset($responseContent["status"])) {
          $statusCode = SoftwareHeritageDao::SWH_STATUS_OK;
        }
      }
      return array($statusCode, $cookedResult);
    } catch (RequestException $e) {
      echo "Sorry, something went wrong. check if the host is accessible!\n";
      echo Psr7\str($e->getRequest());
      if ($e->hasResponse()) {
        echo Psr7\str($e->getResponse());
      }
      $this->scheduler_disconnect(1);
      exit;
    }
  }

  /**
   * @brief Insert the License Details in softwareHeritage table
   * @param int $pfileId
   * @param array $licenses
   * @param int $agentId
   * @return boolean True if finished
   */
  protected function insertSoftwareHeritageRecord($pfileId, $licenses, $agentId, $status)
  {
    // Safety check: ensure pfileId is valid before inserting
    if (empty($pfileId) || $pfileId === null) {
      echo "WARNING: Skipping Software Heritage record insertion for NULL pfile_pk\n";
      return false;
    }

    $licenseString = !empty($licenses) ? implode(", ", $licenses) : '';
    $this->softwareHeritageDao->setSoftwareHeritageDetails($pfileId,
                                  $licenseString, $status);
    if (!empty($licenses)) {
      foreach ($licenses as $license) {
        $l = $this->licenseDao->getLicenseByShortName($license);
        if ($l != NULL) {
          $this->dbManeger->insertTableRow('license_file',['agent_fk' => $agentId,
                                             'pfile_fk' => $pfileId,'rf_fk'=> $l->getId()]);
        }
      }
      return true;
    }
  }
}
