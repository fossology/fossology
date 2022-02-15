<?php
/***************************************************************
Copyright (C) 2017 Siemens AG
Copyright (C) 2021 Orange by Piotr Pszczola <piotr.pszczola@orange.com>

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
 ***************************************************************/
/**
 * @file
 * @brief Scan options model
 */

namespace Fossology\UI\Api\Models;

use Fossology\Lib\Auth\Auth;
use Fossology\UI\Api\Models\Info;
use Fossology\UI\Api\Models\InfoType;
use Symfony\Component\HttpFoundation\Request;
use Fossology\Lib\Dao\UserDao;

if (!class_exists("AgentAdder", false)) {
  require_once dirname(dirname(__DIR__)) . "/agent-add.php";
}
require_once dirname(dirname(dirname(dirname(__DIR__)))) . "/lib/php/common-folders.php";

/**
 * @class ScanOptions
 * @brief Model to hold add settings for new scan
 */
class ScanOptions
{
  /**
   * @var Analysis $analysis
   * Analysis settings
   */
  private $analysis;
  /**
   * @var Reuser $reuse
   * Reuser settings
   */
  private $reuse;
  /**
   * @var Decider $decider
   * Decider settings
   */
  private $decider;

  /**
   * ScanOptions constructor.
   * @param Analysis $analysis
   * @param Reuser $reuse
   * @param Decider $decider
   */
  public function __construct($analysis, $reuse, $decider)
  {
    $this->analysis = $analysis;
    $this->reuse = $reuse;
    $this->decider = $decider;
  }

  /**
   * Get ScanOptions elements as associative array
   * @return array
   */
  public function getArray()
  {
    return [
      "analysis"  => $this->analysis,
      "reuse"     => $this->reuse,
      "decide"    => $this->decider
    ];
  }

  /**
   * Schedule the agents for the given upload in the given folder based on
   * current settings.
   * @param integer $folderId Folder with the upload
   * @param integer $uploadId Upload to be scanned
   * @return Fossology::UI::Api::Models::Info
   */
  public function scheduleAgents($folderId, $uploadId)
  {
    $uploadsAccessible = FolderListUploads_perm($folderId, Auth::PERM_WRITE);
    $found = false;
    foreach ($uploadsAccessible as $singleUpload) {
      if ($singleUpload['upload_pk'] == $uploadId) {
        $found = true;
        break;
      }
    }
    if ($found === false) {
      return new Info(404, "Folder id $folderId does not have upload id ".
        "$uploadId or you do not have write access to the folder.", InfoType::ERROR);
    }

    $paramAgentRequest = new Request();
    $agentsToAdd = $this->prepareAgents();
    $this->prepareReuser($paramAgentRequest);
    $this->prepareDecider($paramAgentRequest);
    $returnStatus = (new \AgentAdder())->scheduleAgents($uploadId, $agentsToAdd, $paramAgentRequest);
    if (is_numeric($returnStatus)) {
      return new Info(201, $returnStatus, InfoType::INFO);
    } else {
      return new Info(403, $returnStatus, InfoType::ERROR);
    }
  }

  /**
   * Prepare agentsToAdd string based on Analysis settings.
   * @return string[]
   */
  private function prepareAgents()
  {
    $agentsToAdd = [];
    foreach ($this->analysis->getArray() as $agent => $set) {
      if ($set === true) {
        if ($agent == "copyright_email_author") {
          $agentsToAdd[] = "agent_copyright";
        } else {
          $agentsToAdd[] = "agent_$agent";
        }
      }
    }
    return $agentsToAdd;
  }

  /**
   * Prepare Request object based on Reuser settings.
   * @param Request $request
   */
  private function prepareReuser(Request &$request)
  {
    if ($this->reuse->getReuseUpload() == 0) {
      // No upload to reuse
      return;
    }
    $reuserRules = [];
    if ($this->reuse->getReuseMain() === true) {
      $reuserRules[] = 'reuseMain';
    }
    if ($this->reuse->getReuseEnhanced() === true) {
      $reuserRules[] = 'reuseEnhanced';
    }
    if ($this->reuse->getReuseReport() === true) {
      $reuserRules[] = 'reuseConf';
    }
    if ($this->reuse->getReuseCopyright() === true) {
      $reuserRules[] = 'reuseCopyright';
    }
    $userDao = $GLOBALS['container']->get("dao.user");
    $reuserSelector = $this->reuse->getReuseUpload() . "," . $userDao->getGroupIdByName($this->reuse->getReuseGroup());
    $request->request->set('uploadToReuse', $reuserSelector);
    $request->request->set('reuseMode', $reuserRules);
  }

  /**
   * Prepare Request object based on Decider settings.
   * @param Request $request
   */
  private function prepareDecider(Request &$request)
  {
    $deciderRules = [];
    if ($this->decider->getNomosMonk() === true) {
      $deciderRules[] = 'nomosInMonk';
    }
    if ($this->decider->getBulkReused() === true) {
      $deciderRules[] = 'reuseBulk';
    }
    if ($this->decider->getNewScanner() === true) {
      $deciderRules[] = 'wipScannerUpdates';
    }
    if ($this->decider->getOjoDecider() === true) {
      $deciderRules[] = 'ojoNoContradiction';
    }
    $request->request->set('deciderRules', $deciderRules);
    if ($this->analysis->getNomos()) {
      $request->request->set('Check_agent_nomos', 1);
    }
  }
}
