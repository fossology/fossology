<?php
/*
 SPDX-FileCopyrightText: © 2014-2018 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * @dir
 * @brief UI element of reuser agent
 * @file
 */

namespace Fossology\Reuser;

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Dao\PackageDao;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Plugin\AgentPlugin;
use Fossology\Lib\Util\StringOperation;
use Fossology\Lib\Util\OsselotLookupHelper;
use Fossology\Scancode\Ui\ScancodesAgentPlugin;
use Symfony\Component\HttpFoundation\Request;

include_once(dirname(__DIR__) . "/agent/version.php");

/**
 * @class ReuserAgentPlugin
 * @brief UI element for reuser during Uploading new package
 */
class ReuserAgentPlugin extends AgentPlugin
{
  const UPLOAD_TO_REUSE_SELECTOR_NAME = 'uploadToReuse';  ///< Form element name for main license to reuse
  const REUSE_MODE = 'reuseMode';  ///< Form element name for main license to reuse

  /** @var UploadDao $uploadDao
   * Upload Dao object
   */
  private $uploadDao;

  public function __construct()
  {
    $this->Name = "agent_reuser";
    $this->Title =  _("Reuse of License Clearing");
    $this->AgentName = "reuser";

    parent::__construct();

    $this->uploadDao = $GLOBALS['container']->get('dao.upload');
  }

  /**
   * @brief Render twig templates for plugin_reuser
   * @param array $vars Variables for twig template
   * @return string Rendered HTML
   */
  public function renderContent(&$vars)
  {
    $reuserPlugin = plugin_find('plugin_reuser');
    return $reuserPlugin->renderContent($vars);
  }

  /**
   * @brief Render footer twig templates for plugin_reuser
   * @param array $vars Variables for twig template
   * @return string Rendered HTML
   */
  public function renderFoot(&$vars)
  {
    $reuserPlugin = plugin_find('plugin_reuser');
    return $reuserPlugin->renderFoot($vars);
  }

  /**
   * @brief Get script tags to include before rendering foot
   * @param array $vars Variables for twig template
   * @return string Rendered JS includes
   */
  public function getScriptIncludes(&$vars)
  {
    $reuserPlugin = plugin_find('plugin_reuser');
    return $reuserPlugin->getScriptIncludes($vars);
  }

  /**
   * @copydoc Fossology::Lib::Plugin::AgentPlugin::preInstall()
   * @see Fossology::Lib::Plugin::AgentPlugin::preInstall()
   */
  public function preInstall()
  {
    menu_insert("ParmAgents::" . $this->Title, 0, $this->Name);
  }

  /**
   * @brief Get parameters from request and add to job queue
   * @param int $jobId        Job id to add to
   * @param int $uploadId     Upload id to add to
   * @param[out] string $errorMsg  Error message to display
   * @param Request $request  HTML request
   * @return int Job queue id
   */
  public function scheduleAgent($jobId, $uploadId, &$errorMsg, $request)
  {
    if ($request->get('reuseSource') === 'osselot') {
        return $this->scheduleOsselotImportDirect($jobId, $uploadId, $errorMsg, $request);
    }

    $reuseUploadPair = explode(',', $request->get(self::UPLOAD_TO_REUSE_SELECTOR_NAME), 2);
    if (count($reuseUploadPair) == 2) {
      list($reuseUploadId, $reuseGroupId) = $reuseUploadPair;
    } else {
      $errorMsg .= 'no reuse upload id given';
      return -1;
    }
    $groupId = $request->get('groupId', Auth::getGroupId());
    $getReuseValue = $request->get(self::REUSE_MODE) ?: array();
    $reuserDependencies = array("agent_adj2nest");

    $reuseMode = UploadDao::REUSE_NONE;
    foreach ($getReuseValue as $currentReuseValue) {
      switch ($currentReuseValue) {
        case 'reuseMain':
          $reuseMode |= UploadDao::REUSE_MAIN;
          break;
        case 'reuseEnhanced':
          $reuseMode |= UploadDao::REUSE_ENHANCED;
          break;
        case 'reuseConf':
          $reuseMode |= UploadDao::REUSE_CONF;
          break;
        case 'reuseCopyright':
          $reuseMode |= UploadDao::REUSE_COPYRIGHT;
          break;
      }
    }

    list($agentDeps, $scancodeDeps) = $this->getReuserDependencies($request);
    $reuserDependencies = array_unique(array_merge($reuserDependencies, $agentDeps));
    if (!empty($scancodeDeps)) {
      $reuserDependencies[] = $scancodeDeps;
    }

    $this->createPackageLink($uploadId, $reuseUploadId, $groupId, $reuseGroupId,
      $reuseMode);

    return $this->doAgentAdd($jobId, $uploadId, $errorMsg,
      $reuserDependencies, $uploadId, null, $request);
  }

  private function scheduleOsselotImportDirect(int $jobId, int $uploadId, string &$errorMsg, Request $request): int
  {
    $pkg = trim((string) $request->get('osselotPackage'));
    $ver = trim((string) $request->get('osselotVersions'));
    if (empty($pkg) || empty($ver)) {
        $errorMsg .= 'Package name and version are required';
        return -1;
    }
    try {
        $helper = new OsselotLookupHelper();
        $cachedPath = $helper->fetchSpdxFile($pkg, $ver);

      if (!$cachedPath || !is_file($cachedPath) || !is_readable($cachedPath)) {
          throw new \RuntimeException("Could not fetch or read SPDX file for {$pkg}:{$ver}");
      }

        global $SysConf;
        $fileBase = $SysConf['FOSSOLOGY']['path'] . "/ReportImport/";

      if (!is_dir($fileBase) && !mkdir($fileBase, 0755, true)) {
          throw new \RuntimeException('Failed to create ReportImport directory');
      }

        $originalName = basename($cachedPath);
      if (!str_ends_with($originalName, '.rdf.xml')) {
          $baseName = pathinfo($originalName, PATHINFO_FILENAME);
          $originalName = str_ends_with($originalName, '.rdf') ?
              $baseName . '.rdf.xml' : $originalName . '.rdf.xml';
      }

        $targetFile = time() . '_' . random_int(0, getrandmax()) . '_' . $originalName;
        $targetPath = $fileBase . $targetFile;

      if (!copy($cachedPath, $targetPath)) {
          throw new \RuntimeException('Failed to copy SPDX file');
      }

        $reportImportAgent = plugin_find('agent_reportImport');
      if (!$reportImportAgent || !method_exists($reportImportAgent, 'addReport') ||
            !method_exists($reportImportAgent, 'setAdditionalJqCmdArgs') ||
            !method_exists($reportImportAgent, 'AgentAdd')) {
          throw new \RuntimeException('agent_reportImport plugin not available or missing methods');
      }

        $importReq = new Request();

        $addNewLicensesAs = $request->get('osselotAddNewLicensesAs', 'candidate');
      if (!in_array($addNewLicensesAs, ['candidate', 'approved', 'rejected'], true)) {
          $addNewLicensesAs = 'candidate';
      }
        $importReq->request->set('addNewLicensesAs', $addNewLicensesAs);

        $booleanOptions = [
            'addLicenseInfoFromInfoInFile', 'addLicenseInfoFromConcluded',
            'addConcludedAsDecisions', 'addConcludedAsDecisionsOverwrite',
            'addConcludedAsDecisionsTBD', 'addCopyrights'
        ];

        foreach ($booleanOptions as $key) {
            $fullKey = 'osselot' . ucfirst($key);
            $rawValue = $request->get($fullKey);
            $finalValue = $rawValue ? 'true' : 'false';
            $importReq->request->set($key, $finalValue);
        }

        $licenseMatch = $request->get('osselotLicenseMatch', 'spdxid');
        if (!in_array($licenseMatch, ['spdxid', 'name', 'text'], true)) {
            $licenseMatch = 'spdxid';
        }
        $importReq->request->set('licenseMatch', $licenseMatch);

        $jqCmdArgs = $reportImportAgent->addReport($targetFile);
        $additionalArgs = $reportImportAgent->setAdditionalJqCmdArgs($importReq);
        $jqCmdArgs .= $additionalArgs;

        $error = "";
        $dependencies = array();
        $jobQueueId = $reportImportAgent->AgentAdd($jobId, $uploadId, $error, $dependencies, $jqCmdArgs);

        if ($jobQueueId < 0) {
            throw new \RuntimeException("Cannot schedule job: " . $error);
        }

        return intval($jobQueueId);

    } catch (\Throwable $e) {
      if (isset($targetPath) && file_exists($targetPath)) {
          unlink($targetPath);
      }
        $errorMsg .= $e->getMessage();
        error_log("OSSelot import error: " . $e->getMessage());
        return -1;
    }
  }

  /**
   * @brief Create links between old and new upload
   * @param int $uploadId
   * @param int $reuseUploadId
   * @param int $groupId
   * @param int $reuseGroupId
   * @param int $reuseMode
   */
  protected function createPackageLink($uploadId, $reuseUploadId, $groupId, $reuseGroupId, $reuseMode=0)
  {
    /* @var $packageDao PackageDao */
    $packageDao = $GLOBALS['container']->get('dao.package');
    $newUpload = $this->uploadDao->getUpload($uploadId);
    $uploadForReuse = $this->uploadDao->getUpload($reuseUploadId);

    $package = $packageDao->findPackageForUpload($reuseUploadId);

    if ($package === null) {
      $packageName = StringOperation::getCommonHead($uploadForReuse->getFilename(), $newUpload->getFilename());
      $package = $packageDao->createPackage($packageName ?: $uploadForReuse->getFilename());
      $packageDao->addUploadToPackage($reuseUploadId, $package);
    }

    $packageDao->addUploadToPackage($uploadId, $package);

    $this->uploadDao->addReusedUpload($uploadId, $reuseUploadId, $groupId, $reuseGroupId, $reuseMode);
  }

  /**
   * Add scanners as reuser dependencies.
   * @param Request $request Symfony request
   * @return array List of agent dependencies
   */
  private function getReuserDependencies($request)
  {
    $dependencies = array();
    $scancodeDeps = [];
    if ($request->get("Check_agent_nomos", false)) {
      $dependencies[] = "agent_nomos";
    }
    if ($request->get("Check_agent_monk", false)) {
      $dependencies[] = "agent_monk";
    }
    if ($request->get("Check_agent_ojo", false)) {
      $dependencies[] = "agent_ojo";
    }
    if ($request->get("Check_agent_ninka", false)) {
      $dependencies[] = "agent_ninka";
    }
    if ($request->get("Check_agent_copyright", false)) {
      $dependencies[] = "agent_copyright";
    }
    if (!empty($request->get("scancodeFlags", []))) {
      /**
       * @var ScancodesAgentPlugin ScanCode agent
       */
      $agentScanCode = plugin_find('agent_scancode');
      $flags = $request->get('scancodeFlags');
      $unpackArgs = intval($request->get('scm', 0)) == 1 ? 'I' : '';
      $scancodeDeps = [
        "name" => "agent_scancode",
        "args" => $agentScanCode->getScanCodeArgs($flags, $unpackArgs)
      ];
    }
    return [$dependencies, $scancodeDeps];
  }
}

register_plugin(new ReuserAgentPlugin());
