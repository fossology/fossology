<?php
/*
 * Copyright (C) 2019
 * Author: Vivek Kumar <vvksindia@gmail.com>
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * version 2 as published by the Free Software Foundation.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 */
namespace Fossology\Spasht;

use Fossology\Lib\Agent\Agent;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Dao\SpashtDao;
use Fossology\Lib\Dao\LicenseDao;
use Fossology\Lib\Dao\AgentDao;
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

     $getNewResult = $this->getInformation($uploadAvailable, $pfileSha1AndpfileId);

    $resultUploadIntoLicenseTable = $this->insertLicensesSpashtAgentRecord(
      $getNewResult, $agentId);
    $resultUploadIntoCopyrightTable = $this->insertCopyrightSpashtAgentRecord(
      $getNewResult, $agentId);
    return true;
  }

  /**
   * This function is responsible for available upload in the spasht db.
   * If the upload is available then only the spasht agent will run.
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
   * tool used is scancode
   */
  protected function getInformation($details, $pfileSha1AndpfileId)
  {
    global $SysConf;

    $namespace = $details['spasht_namespace'];
    $name = $details['spasht_name'];
    $revision = $details['spasht_revision'];
    $type = $details['spasht_type'];
    $provider = $details['spasht_provider'];

    $dir = "files";

    /**
     * Guzzle/http Guzzle Client that connect with ClearlyDefined API
     */
    $client = new Client([
      // Base URI is used with relative requests
      'base_uri' => $SysConf['SYSCONFIG']["ClearlyDefinedURL"]
    ]);

    // uri to definitions section in the api to get scancode details
    $uri = 'definitions/' . $type . "/" . $provider . "/" . $namespace . "/" .
      $name . "/" . $revision;
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

    $res = $client->request('GET', $uri, ["proxy" => $proxy]);

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
            $temp['license'] = $this->sperateLicenses($key->license);
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
        $this->heartbeat(1);
      }

      return $newResultBody;
    }
    return "UploadNotFound";
  }

  /**
   * Convert the license string into fossology format
   */
  protected function sperateLicenses($key)
  {
    $strLicense = array();
    $checkString = explode(" ", $key);

    foreach ($checkString as $license) {
      if ($license === "AND" || $license === "OR") {
        continue;
      } else {
        $strSubLicense = explode("-", $license);

        if ($strSubLicense[2] === "or" && $strSubLicense[3] === "later") {
          $license = $strSubLicense[0] . "-" . $strSubLicense[1] . "+";
        } elseif ($strSubLicense[2] === "only") {
          $license = $strSubLicense[0] . "-" . $strSubLicense[1];
        }

        $strLicense[] = $license;
      }
    }
    return $strLicense;
  }

  /**
   * @brief Insert the License Details in Spasht Agent table
   * @param $agentId Integer
   * @param $license Array
   *
   * @return boolean True if finished
   */
  protected function insertLicensesSpashtAgentRecord($body, $agentId)
  {
    foreach ($body as $key) {
      foreach ($key['license'] as $license) {
        $l = $this->licenseDao->getLicenseByShortName($license);

        if ($l != null) {
          if (! empty($l->getId())) {
            $this->dbManager->insertTableRow('license_file', [
              'agent_fk' => $agentId,
              'pfile_fk' => $key['pfileId'],
              'rf_fk' => $l->getId()
            ]);
          }
        }
      }
    }
    return true;
  }

  /**
   * @brief Insert the Copyright Details in Spasht Agent table
   * @param $agentId Integer
   * @param $license Array
   *
   * @return boolean True if finished
   */
  protected function insertCopyrightSpashtAgentRecord($body, $agentId)
  {
    foreach ($body as $key) {
      foreach ($key['attributions'] as $keyCopyright) {
        $hashForCopyright = hash("sha256", $keyCopyright);
        $this->dbManager->insertTableRow('copyright_spasht',
          [
            'agent_fk' => $agentId,
            'pfile_fk' => $key['pfileId'],
            'textfinding' => $keyCopyright,
            'hash' => $hashForCopyright,
            'clearing_decision_type_fk' => 0
          ]);
      }
    }
    return true;
  }
}
