<?php
/*
 Copyright (C) 2019
 Author: Vivek Kumar <vvksindia@gmail.com>

 This program is free software; you can redistribute it and/or
 modify it under the terms of the GNU General Public License
 version 2 as published by the Free Software Foundation.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License along
 with this program; if not, write to the Free Software Foundation, Inc.,
 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 */

namespace Fossology\Spasht;

use Fossology\Lib\Agent\Agent;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Dao\SpashtDao;
use Fossology\Lib\Dao\LicenseDao;
use Fossology\Lib\Dao\AgentDao;
use GuzzleHttp\Client;

include_once(__DIR__ . "/version.php");

/**
 * @file
 * @brief Spasht agent source
 * @class SpashtAgent
 * @brief The Spasht agent
 */
class SpashtAgent extends Agent
{
  /** @var UploadDao $uploadDao
   * UploadDao object
   */
  private $uploadDao;

  /** @var SpashtDao $spashtDao
   * SpashtDao object
   */
  private $spashtDao;

  /** @var LicenseDao $licenseDao
   * LicenseDao object
   */
  private $licenseDao;

  // /**
  //  * @var DbManager $dbManager
  //  * DbManager object
  //  */
  // private $dbManager;

  // /**
  //  * @var AgentDao $agentDao
  //  * AgentDao object
  //  */
  // protected $agentDao;

  function __construct()
  {
    parent::__construct(SPASHT_AGENT_NAME, AGENT_VERSION, AGENT_REV);
    $this->uploadDao = $this->container->get('dao.upload');
    $this->spashtDao = $this->container->get('dao.spasht');
    $this->licenseDao = $this->container->get('dao.license');
    // $this->dbManager = $this->container->get('db.manager');
    // $this->agentDao = $this->container->get('dao.agent');
  }

  /*
   * @brief Run Spasht Agent for a package
   * @param $uploadId Integer
   * @see Fossology::Lib::Agent::Agent::processUploadId()
   */
  function processUploadId($uploadId)
  {
    $itemTreeBounds = $this->uploadDao->getParentItemBounds($uploadId);
    $pfileFileDetails = $this->uploadDao->getPFileDataPerFileName($itemTreeBounds);

    $agentId = $this->agentDao->getCurrentAgentId("spasht");
    $pfileSha1AndpfileId = array();

    foreach ($pfileFileDetails as $pfileDetail) {
      $pfileSha1AndpfileId[$pfileDetail['pfile_pk']] = strtolower($pfileDetail['sha1']);
    }

    $uploadAvailable = $this->searchUploadIdInSpasht($uploadId);
    $scancodeVersion = $this->getScanCodeVersion($uploadAvailable);

    $getNewResult = $this->getInformation($scancodeVersion, $uploadAvailable, $pfileSha1AndpfileId);

    $resultUploadIntoLicenseTable = $this->insertLicensesSpashtAgentRecord($getNewResult, $agentId);

    $resultUploadIntoCopyrightTable = $this->insertCopyrightSpashtAgentRecord($getNewResult, $agentId);
    return true;
  }

  /**
   * This function is responsible for available upload in the spasht db.
   * If the upload is available then only the spasht agent will run.
   */

  protected function searchUploadIdInSpasht($uploadId)
  {
    $result = $this->spashtDao->getComponent($uploadId);

    if (!empty($result)) {
      return $result;
    }

    return false;
  }

  /**
   * Get ScanCode Versions and Uri from harvest end point.
   * This collection will be used for filtering of harvest data.
   */

  protected function getScanCodeVersion($details)
  {
    $namespace = $details['spasht_namespace'];
    $name = $details['spasht_name'];
    $revision = $details['spasht_revision'];
    $type = $details['spasht_type'];
    $provider = $details['spasht_provider'];

    $tool = "scancode";

    /** Guzzle/http Guzzle Client that connect with ClearlyDefined API */
    $client = new Client([
     // Base URI is used with relative requests
     'base_uri' => 'https://api.clearlydefined.io/',
     ]);

    // uri to harvest section in the api
    $uri = 'harvest/'.$type."/".$provider."/".$namespace."/".$name."/".$revision."/".$tool;

    $res = $client->request('GET',$uri,[]);

    if ($res->getStatusCode()==200) {
      $body = json_decode($res->getBody()->getContents());

      if (sizeof($body) == 0) {
        return "Scancode not found!";
      }

      $latestToolVersion = 0;

      for ($x = 0; $x < sizeof($body) ; $x++) {
        $str = explode ("/", $body[$x]);
        $toolVersion = $str[6];
        $newToolVersion = "";

        for ($y = 0; $y < strlen($toolVersion); $y++) {
          if ($toolVersion[$y] != ".") {
            $newToolVersion .= $toolVersion[$y];
          }
        }

        if ($latestToolVersion < $newToolVersion) {
          $latestToolVersion = $newToolVersion;
          $result = $toolVersion;
        }
      }
      return $result;
    }
    return "scancode not found!";
  }

  /**
   * Search pfile for uploads into clearlydefined
   * tool used is scancode
   */
  protected function getInformation($scancodeVersion, $details, $pfileSha1AndpfileId)
  {
    $namespace = $details['spasht_namespace'];
    $name = $details['spasht_name'];
    $revision = $details['spasht_revision'];
    $type = $details['spasht_type'];
    $provider = $details['spasht_provider'];

    $tool = "scancode";
    $dir = "files";

    /** Guzzle/http Guzzle Client that connect with ClearlyDefined API */
    $client = new Client([
     // Base URI is used with relative requests
     'base_uri' => 'https://api.clearlydefined.io/',
     ]);

    // uri to harvest section in the api to get scancode details
    $uri = 'harvest/'.$type."/".$provider."/".$namespace."/".$name."/".$revision;

    $res = $client->request('GET',$uri,[]);

    if ($res->getStatusCode()==200) {
      $body = json_decode($res->getBody()->getContents());

      if (empty($body)) {
        return "BodyNotFound";
      }

      $newResultBody = array();

      foreach ($body->$tool->$scancodeVersion->$dir as $key) {
        $searchInUpload = array_search($key->hashes->sha1, $pfileSha1AndpfileId);

        if (!empty($searchInUpload)) {
          $temp = array();
          $temp['pfileId'] = $searchInUpload;
          $temp['sha1'] = $key->hashes->sha1;

          if (!empty($key->license)) {
            $temp['license'] = $this->sperateLicenses($key->license);
          } else {
            $temp['license'] = ["No_License_Found"];
          }

          if (!empty($key->attributions)) {
            $temp['attributions'] = $key->attributions;
          } else {
            $temp['attributions'] = ["No_Copyright_Found"];
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
    $checkString = explode (" ", $key);

    foreach ($checkString as $license) {
      if ($license === "AND" || $license === "OR") {
        continue;
      } else {
        $strSubLicense = explode("-",$license);

        if ($strSubLicense[2] === "or" && $strSubLicense[3] === "later") {
          $license = $strSubLicense[0]."-".$strSubLicense[1]."+";
        } elseif ($strSubLicense[2] === "only") {
          $license = $strSubLicense[0]."-".$strSubLicense[1];
        }

        $strLicense []= $license;
      }
    }
    return $strLicense;
  }

  /*
   * @brief Insert the License Details in Spasht Agent table
   * @param $agentId  Integer
   * @param $license  Array
   *
   * @return boolean True if finished
   */
  protected function insertLicensesSpashtAgentRecord($body, $agentId)
  {
    foreach ($body as $key) {
      foreach ($key['license'] as $license) {
        $l = $this->licenseDao->getLicenseByShortName($license);

        if ($l != null) {
          if (!empty($l->getId())) {
            $this->dbManager->insertTableRow('license_file',['agent_fk' => $agentId,'pfile_fk' => $key['pfileId'],'rf_fk'=> $l->getId()]);
          }
        }
      }
    }
    return true;
  }

  /*
   * @brief Insert the Copyright Details in Spasht Agent table
   * @param $agentId  Integer
   * @param $license  Array
   *
   * @return boolean True if finished
   */
  protected function insertCopyrightSpashtAgentRecord($body, $agentId)
  {
    $file = fopen('/home/fossy/abc.json','w');
    fwrite($file, $agentId."->agentID\n");
    foreach ($body as $key) {
      foreach ($key['attributions'] as $keyCopyright) {
        fwrite($file, $keyCopyright." -> ");
        fwrite($file, $key['pfileId']."\n");

        $hashForCopyright = hash ("sha256" , $keyCopyright);

        fwrite($file, $hashForCopyright);
        $this->dbManager->insertTableRow('copyright_spasht',['agent_fk' => $agentId,'pfile_fk' => $key['pfileId'],'textfinding' => $keyCopyright,'hash' => $hashForCopyright,'clearing_decision_type_fk' => 0]);
      }
    }
    fclose($file);
    return true;
  }
}
