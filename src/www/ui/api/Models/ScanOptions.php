<?php
/*
 SPDX-FileCopyrightText: © 2017 Siemens AG
 SPDX-FileCopyrightText: © 2021 Orange by Piotr Pszczola <piotr.pszczola@orange.com>
 SPDX-FileCopyrightText: © 2025 Tiyasa Kundu <tiyasakundu20@gmail.com>

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @file
 * @brief Scan options model
 */

namespace Fossology\UI\Api\Models;

use Fossology\Lib\Auth\Auth;
use Fossology\UI\Api\Exceptions\HttpForbiddenException;
use Fossology\UI\Api\Exceptions\HttpNotFoundException;
use Fossology\UI\Api\Models\ApiVersion;
use Symfony\Component\HttpFoundation\Request;

if (!class_exists("AgentAdder", false)) {
  require_once dirname(__DIR__, 2) . "/agent-add.php";
}
require_once dirname(__DIR__, 4) . "/lib/php/common-folders.php";

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
   * @var Scancode $scancode
   * Scancode settings
   */
  private $scancode;
  /**
   * ScanOptions constructor.
   * @param Analysis $analysis
   * @param Reuser $reuse
   * @param Decider $decider
   * @param Scancode $scancode
   */
  public function __construct($analysis, $reuse, $decider, $scancode)
  {
    $this->analysis = $analysis;
    $this->reuse = $reuse;
    $this->decider = $decider;
    $this->scancode = $scancode;
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
      "decide"    => $this->decider,
      "scancode"  => $this->scancode
    ];
  }

  /**
   * Schedule the agents for the given upload in the given folder based on
   * current settings.
   * @param integer $folderId Folder with the upload
   * @param integer $uploadId Upload to be scanned
   * @param boolean $newUpload If true, do not check if the folder contains the
   *                           upload. Should be false for existing uploads.
   * @return \Fossology\UI\Api\Models\Info
   * @throws HttpNotFoundException  If the folder does not contain the upload
   * @throws HttpForbiddenException If the user does not have write access to the upload
   */
  public function scheduleAgents($folderId, $uploadId , $newUpload = true)
  {
    $uploadsAccessible = FolderListUploads_perm($folderId, Auth::PERM_WRITE);

    if (! $newUpload) {
      $found = false;
      foreach ($uploadsAccessible as $singleUpload) {
        if ($singleUpload['upload_pk'] == $uploadId) {
          $found = true;
          break;
        }
      }
      if ($found === false) {
        throw new HttpNotFoundException(
          "Folder id $folderId does not have upload id " .
          "$uploadId or you do not have write access to the folder.");
      }
    }

    $paramAgentRequest = new Request();
    $agentsToAdd = $this->prepareAgents($paramAgentRequest);
    $this->prepareReuser($paramAgentRequest);
    $this->prepareDecider($paramAgentRequest);
    $this->prepareScancode($paramAgentRequest);
    $returnStatus = (new \AgentAdder())->scheduleAgents($uploadId, $agentsToAdd, $paramAgentRequest);
    if (is_numeric($returnStatus)) {
      return new Info(201, $returnStatus, InfoType::INFO);
    } else {
      throw new HttpForbiddenException($returnStatus);
    }
  }

  /**
   * Prepare agentsToAdd string based on Analysis settings.
   * @param Request $request Request object to manipulate
   * @return string[]
   */
  private function prepareAgents(Request &$request)
  {
    $agentsToAdd = [];

    $apiVersion = ApiVersion::getVersionFromUri();

    if ($apiVersion == ApiVersion::V2) {
      foreach ($this->analysis->getArray($apiVersion) as $agent => $set) {
        if ($set === true) {
          if ($agent == "copyrightEmailAuthor") {
            $agentsToAdd[] = "agent_copyright";
            $request->request->set("Check_agent_copyright", 1);
          } elseif ($agent == "ipra") {
            $agentsToAdd[] = "agent_ipra";
            $request->request->set("Check_agent_ipra", 1);
          } elseif ($agent == "pkgagent") {
            $agentsToAdd[] = "agent_pkgagent";
            $request->request->set("Check_agent_pkgagent", 1);
          } elseif ($agent == "softwareHeritage") {
            $agentsToAdd[] = "agent_shagent";
            $request->request->set("Check_agent_shagent", 1);
          } else {
            $agentsToAdd[] = "agent_$agent";
            $request->request->set("Check_agent_$agent", 1);
          }
        }
      }
    } else {
      foreach ($this->analysis->getArray($apiVersion) as $agent => $set) {
        if ($set === true) {
          if ($agent == "copyright_email_author") {
            $agentsToAdd[] = "agent_copyright";
            $request->request->set("Check_agent_copyright", 1);
          } elseif ($agent == "patent") {
            $agentsToAdd[] = "agent_ipra";
            $request->request->set("Check_agent_ipra", 1);
          } elseif ($agent == "package") {
            $agentsToAdd[] = "agent_pkgagent";
            $request->request->set("Check_agent_pkgagent", 1);
          } elseif ($agent == "heritage") {
            $agentsToAdd[] = "agent_shagent";
            $request->request->set("Check_agent_shagent", 1);
          } else {
            $agentsToAdd[] = "agent_$agent";
            $request->request->set("Check_agent_$agent", 1);
          }
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
    if ($this->decider->getCopyrightDeactivation() === true) {
      $deciderRules[] = 'copyrightDeactivation';
    }
    if ($this->decider->getCopyrightClutterRemoval() === true) {
      $deciderRules[] = 'copyrightDeactivationClutterRemoval';
    }
    if (! empty($this->decider->getConcludeLicenseType())) {
      $deciderRules[] = 'licenseTypeConc';
      $request->request->set('licenseTypeConc',
          $this->decider->getConcludeLicenseType());
    }
    $request->request->set('deciderRules', $deciderRules);
    if ($this->analysis->getNomos()) {
      $request->request->set('Check_agent_nomos', 1);
    }
  }

  /**
   * Prepare Request object based on Scancode settings.
   * @param Request $request
   */
  private function prepareScancode(Request &$request)
  {
    $scancodeRules = [];
    if ($this->scancode->getScanLicense() === true) {
      $scancodeRules[] = 'license';
    }
    if ($this->scancode->getScanCopyright() === true) {
      $scancodeRules[] = 'copyright';
    }
    if ($this->scancode->getScanEmail() === true) {
      $scancodeRules[] = 'email';
    }
    if ($this->scancode->getScanUrl() === true) {
      $scancodeRules[] = 'url';
    }
    $request->request->set('scancodeFlags', $scancodeRules);
  }
}
