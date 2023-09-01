<?php
/*
 SPDX-FileCopyrightText: © 2019 Vivek Kumar <vvksindia@gmail.com>
 Author: Vivek Kumar <vvksindia@gmail.com>

 SPDX-License-Identifier: GPL-2.0-only
*/
namespace Fossology\Spasht;

use Fossology\Lib\Agent\Agent;
use Fossology\Lib\Dao\AgentDao;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Dao\SpashtDao;
use Fossology\Lib\Dao\LicenseDao;
use Fossology\Lib\Data\Spasht\Coordinate;
use GuzzleHttp\Client;

include_once (__DIR__ . "/version.php");

/**
 * @file
 * @brief Spasht agent source
 * @class SpashtAgent
 * @brief The Spasht agent
 */
class SpashtAgent extends Agent
{

  /**
   * @var UploadDao $uploadDao
   * UploadDao object
   */
  private $uploadDao;

  /**
   * @var SpashtDao $spashtDao
   * SpashtDao object
   */
  private $spashtDao;

  /**
   * @var LicenseDao $licenseDao
   * LicenseDao object
   */
  private $licenseDao;

  function __construct()
  {
    parent::__construct(SPASHT_AGENT_NAME, AGENT_VERSION, AGENT_REV);
    $this->uploadDao = $this->container->get('dao.upload');
    $this->spashtDao = $this->container->get('dao.spasht');
    $this->licenseDao = $this->container->get('dao.license');
  }

  /**
   * @brief Run Spasht Agent for a package
   * @param $uploadId Integer
   * @see Fossology::Lib::Agent::Agent::processUploadId()
   */
  function processUploadId($uploadId)
  {
    $itemTreeBounds = $this->uploadDao->getParentItemBounds($uploadId);
    $pfileFileDetails = $this->uploadDao->getPFileDataPerFileName(
      $itemTreeBounds);

    $agentId = $this->agentDao->getCurrentAgentId("spasht");
    $pfileSha1AndpfileId = array();

    foreach ($pfileFileDetails as $pfileDetail) {
      $pfileSha1AndpfileId[$pfileDetail['pfile_pk']] = strtolower(
        $pfileDetail['sha1']);
    }

    $uploadAvailable = $this->searchUploadIdInSpasht($uploadId);
    if ($uploadAvailable === false) {
      // Nothing to perform
      return true;
    }

    $getNewResult = $this->getInformation($uploadAvailable,
      $pfileSha1AndpfileId);
    if (is_string($getNewResult)) {
      echo "Error: $getNewResult";
      return false;
    }

    $this->insertLicensesSpashtAgentRecord($getNewResult, $agentId);
    $this->insertCopyrightSpashtAgentRecord($getNewResult, $agentId);
    return true;
  }

  /**
   * This function is responsible for available upload in the spasht db.
   * If the upload is available then only the spasht agent will run.
   *
   * @param integer $uploadId
   * @return Coordinate|boolean
   */
  protected function searchUploadIdInSpasht($uploadId)
  {
    $result = $this->spashtDao->getComponent($uploadId);

    if (! empty($result)) {
      return $result;
    }

    return false;
  }

  /**
   * Search pfile for uploads into clearlydefined
   *
   * @param Coordinate $details
   * @param array $pfileSha1AndpfileId Associated pfile and sha1 array
   * @return array Array containing license, copyright and pfile info. String
   *         containing error message if and error occurred
   */
  protected function getInformation($details, $pfileSha1AndpfileId)
  {
    global $SysConf;

    $dir = "files";

    /**
     * Guzzle/http Guzzle Client that connect with ClearlyDefined API
     */
    $client = new Client([
      // Base URI is used with relative requests
      'base_uri' => $SysConf['SYSCONFIG']["ClearlyDefinedURL"]
    ]);

    // uri to definitions section in the api to get scancode details
    $uri = 'definitions/' . $details->generateUrlString();
    // Prepare proxy
    $proxy = [];
    if (array_key_exists('http_proxy', $SysConf['FOSSOLOGY']) &&
      ! empty($SysConf['FOSSOLOGY']['http_proxy'])) {
      $proxy['http'] = $SysConf['FOSSOLOGY']['http_proxy'];
    }
    if (array_key_exists('https_proxy', $SysConf['FOSSOLOGY']) &&
      ! empty($SysConf['FOSSOLOGY']['https_proxy'])) {
      $proxy['https'] = $SysConf['FOSSOLOGY']['https_proxy'];
    }
    if (array_key_exists('no_proxy', $SysConf['FOSSOLOGY']) &&
      ! empty($SysConf['FOSSOLOGY']['no_proxy'])) {
      $proxy['no'] = explode(',', $SysConf['FOSSOLOGY']['no_proxy']);
    }

    try {
      $res = $client->request('GET', $uri, ["proxy" => $proxy]);
    } catch (\Exception $e) {
      return "Unable to fetch info from $uri. " . $e->getMessage();
    }

    if ($res->getStatusCode() == 200) {
      $body = json_decode($res->getBody()->getContents());

      if (empty($body)) {
        return "BodyNotFound";
      }

      $newResultBody = array();

      foreach ($body->$dir as $key) {
        $searchInUpload = array_search($key->hashes->sha1, $pfileSha1AndpfileId);

        if (! empty($searchInUpload)) {
          $temp = array();
          $temp['pfileId'] = $searchInUpload;
          $temp['sha1'] = $key->hashes->sha1;

          if (! empty($key->license)) {
            $temp['license'] = $this->separateLicenses($key->license);
          } else {
            $temp['license'] = [
              "No_License_Found"
            ];
          }

          if (! empty($key->attributions)) {
            $temp['attributions'] = $key->attributions;
          } else {
            $temp['attributions'] = [
              "No_Copyright_Found"
            ];
          }
          $newResultBody[] = $temp;
        }
      }

      return $newResultBody;
    }
    return "UploadNotFound";
  }

  /**
   * Convert the license string into fossology format
   *
   * @param string $rawLicenses License name
   * @return string Fossology license shortname
   */
  private function separateLicenses($rawLicenses)
  {
    $strLicense = array();
    $checkString = explode(" ", $rawLicenses);

    foreach ($checkString as $license) {
      if (strcasecmp($license, "and") === 0 || strcasecmp($license, "or") === 0) {
        continue;
      }
      $strSubLicense = explode("-", $license);
      $partCount = count($strSubLicense);
      if ($partCount < 2) {
        $strLicense[] = $license;
        continue;
      }

      $fossLicense = $license;
      if ($partCount >= 3 &&
        strcasecmp($strSubLicense[$partCount - 2], "or") === 0 &&
        strcasecmp($strSubLicense[$partCount - 1], "later") === 0) {
        // <license>-or-later
        $fossLicense = implode("-", array_slice($strSubLicense, 0, -2)) . "+";
      } elseif (strcasecmp($strSubLicense[$partCount - 1], "only") === 0) {
        // <license>-only
        $fossLicense = implode("-", array_slice($strSubLicense, 0, -1));
      }

      $strLicense[] = $fossLicense;
    }
    return $strLicense;
  }

  /**
   * @brief Insert the License Details in Spasht Agent table
   * @param $body
   * @param $agentId
   */
  protected function insertLicensesSpashtAgentRecord($body, $agentId)
  {
    foreach ($body as $key) {
      foreach ($key['license'] as $license) {
        $l = $this->licenseDao->getLicenseByShortName($license);

        if ($l != null && ! empty($l->getId())) {
          $sql = "SELECT fl_pk FROM license_file " .
            "WHERE agent_fk = $1 AND pfile_fk = $2 AND rf_fk = $3;";
          $statement = __METHOD__ . ".checkExists";
          $row = $this->dbManager->getSingleRow($sql, [$agentId,
            $key['pfileId'], $l->getId()], $statement);
          if (! empty($row) && ! empty($row['fl_pk'])) {
            continue;
          }
          $this->dbManager->insertTableRow('license_file', [
            'agent_fk' => $agentId,
            'pfile_fk' => $key['pfileId'],
            'rf_fk' => $l->getId()
          ], __METHOD__ . ".insertLicense");
          $this->heartbeat(1);
        }
      }
    }
  }

  /**
   * @brief Insert the Copyright Details in Spasht Agent table
   * @param $agentId Integer
   * @param $license Array
   */
  protected function insertCopyrightSpashtAgentRecord($body, $agentId)
  {
    foreach ($body as $key) {
      foreach ($key['attributions'] as $keyCopyright) {
        if ($keyCopyright == "No_Copyright_Found") {
          continue;
        }

        $hashForCopyright = hash("sha256", $keyCopyright);
        $sql = "SELECT copyright_spasht_pk FROM copyright_spasht " .
          "WHERE agent_fk = $1 AND pfile_fk = $2 AND hash = $3 " .
          "AND clearing_decision_type_fk = 0;";
        $statement = __METHOD__ . ".checkExists";
        $row = $this->dbManager->getSingleRow($sql, [$agentId, $key['pfileId'],
          $hashForCopyright
        ], $statement);
        if (! empty($row) && ! empty($row['copyright_spasht_pk'])) {
          continue;
        }
        $this->dbManager->insertTableRow('copyright_spasht', [
          'agent_fk' => $agentId,
          'pfile_fk' => $key['pfileId'],
          'textfinding' => $keyCopyright,
          'hash' => $hashForCopyright,
          'clearing_decision_type_fk' => 0
        ], __METHOD__ . ".insertCopyright");
        $this->heartbeat(1);
      }
    }
  }
}
