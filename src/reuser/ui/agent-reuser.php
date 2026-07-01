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
use Fossology\Lib\Dao\ClearingDao;
use Fossology\Lib\Dao\PackageDao;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Dao\UserDao;
use Fossology\Lib\Data\Upload\UploadEvents;
use Fossology\Lib\Db\DbManager;
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

  /** @var UserDao $userDao */
  private $userDao;

  /** @var ClearingDao $clearingDao */
  private $clearingDao;

  public function __construct()
  {
    $this->Name = "agent_reuser";
    $this->Title =  _("Reuse of License Clearing");
    $this->AgentName = "reuser";

    parent::__construct();

    $this->uploadDao = $GLOBALS['container']->get('dao.upload');
    $this->userDao = $GLOBALS['container']->get('dao.user');
    $this->clearingDao = $GLOBALS['container']->get('dao.clearing');
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

    $autoSelect = $request->get('autoSelectReuse', false);
    $autoSelectEnabled = ($autoSelect === 'true' || $autoSelect === true || $autoSelect === 1 || $autoSelect === '1');

    $autoMatch = null;
    if ($autoSelectEnabled) {
      $autoMatch = $this->autoDetectReuseUpload($uploadId, $errorMsg);
      if ($autoMatch === null) {
        $logger = $GLOBALS['container']->get('logger');
        $logger->notice("Auto-select reuse: no matching closed upload found for upload $uploadId.");
      }
    }

    $reuseSelections = $request->get(self::UPLOAD_TO_REUSE_SELECTOR_NAME);
    if (!is_array($reuseSelections)) {
      $reuseSelections = [$reuseSelections];
    }

    $reuseSelections = array_filter($reuseSelections, fn($s) => is_string($s) && substr_count($s, ',') >= 1);

    if (empty($reuseSelections) && $autoMatch === null) {
      if ($autoSelectEnabled) {
        return $this->doAgentAdd($jobId, $uploadId, $errorMsg,
          ["agent_adj2nest"], $uploadId, null, $request);
      }
      $errorMsg .= 'No valid reuse upload selections found';
      return -1;
    }

    $autoMatchAlreadySelected = false;
    foreach ($reuseSelections as $reuseSelection) {
      [$reuseUploadId, $reuseGroupId] = explode(',', $reuseSelection, 2);
      if ($autoMatch !== null && intval($reuseUploadId) === intval($autoMatch['uploadId'])) {
        $autoMatchAlreadySelected = true;
      }
      $this->createPackageLink(
        $uploadId,
        intval($reuseUploadId),
        intval($groupId),
        intval($reuseGroupId),
        $reuseMode
      );
    }

    if ($autoMatch !== null && !$autoMatchAlreadySelected) {
      $this->createPackageLink(
        $uploadId,
        intval($autoMatch['uploadId']),
        intval($groupId),
        intval($autoMatch['groupId']),
        $reuseMode
      );
    }

    list($agentDeps, $scancodeDeps) = $this->getReuserDependencies($request);
    $reuserDependencies = array_unique(array_merge($reuserDependencies, $agentDeps));
    if (!empty($scancodeDeps)) {
      $reuserDependencies[] = $scancodeDeps;
    }

    return $this->doAgentAdd($jobId, $uploadId, $errorMsg,
      $reuserDependencies, $uploadId, null, $request);
  }

  /**
   * @brief Extract base package name from filename by removing version suffix
   * @param string $filename
   * @return string
   */
  private function extractBasePackageName($filename)
  {
    if (empty($filename)) {
      $logger = $GLOBALS['container']->get('logger');
      $logger->debug("extractBasePackageName: empty filename provided");
      return '';
    }
    $nameWithoutExt = preg_replace('/\.[^.]+$/', '', $filename);
    $nameWithoutExt = preg_replace('/\.(tar|zip|gz|bz2|xz|tgz|tbz2|txz|rar|7z)(\..*)?$/i', '', $nameWithoutExt);
    $nameWithoutExt = preg_replace('/\.(tar|zip|gz|bz2|xz|tgz|tbz2|txz|rar|7z)$/i', '', $nameWithoutExt);
    $baseName = preg_replace('/[-_](v?\d+[\.\d]*(?:[-_](?:alpha|beta|rc|pre|patch|p)\d*)?)$/i', '', $nameWithoutExt);
    $baseName = preg_replace('/[-_]\d{8}$/', '', $baseName);
    if ($baseName === $nameWithoutExt) {
      $parts = explode('-', $nameWithoutExt);
      $baseName = $parts[0];
    }
    return $baseName;
  }

  /**
   * @brief Auto-detect the best reuse upload for a given upload
   * @param int $uploadId Current upload ID
   * @param string &$errorMsg Error message
   * @return array|null ['uploadId'=>int, 'groupId'=>int] or null
   */
  private function autoDetectReuseUpload($uploadId, &$errorMsg)
  {
    $dbManager = $GLOBALS['container']->get('db.manager');
    $upload = $this->uploadDao->getUpload($uploadId);
    if ($upload === null) {
      $errorMsg .= 'Upload not found.';
      return null;
    }

    $filename = $upload->getFilename();
    $basePackageName = $this->extractBasePackageName($filename);
    if (empty($basePackageName)) {
      $errorMsg .= 'Could not extract package name from filename.';
      return null;
    }

    $stmtName = __METHOD__ . '.getUploader';
    $dbManager->prepare($stmtName, "SELECT user_fk FROM upload WHERE upload_pk = $1");
    $res = $dbManager->execute($stmtName, [$uploadId]);
    $uploaderRow = $dbManager->fetchArray($res);
    $dbManager->freeResult($res);
    $uploaderUserId = intval($uploaderRow['user_fk']);

    $match = $this->primarySearch($uploadId, $basePackageName, $uploaderUserId, $dbManager);
    if ($match !== null) {
      return $match;
    }

    $match = $this->secondarySearch($uploadId, $dbManager);
    if ($match !== null) {
      return $match;
    }

    return null;
  }

  /**
   * @brief Primary search: package name matching
   * @param int $uploadId
   * @param string $basePackageName
   * @param int $uploaderUserId
   * @param DbManager $dbManager
   * @return array|null
   */
  private function primarySearch($uploadId, $basePackageName, $uploaderUserId, $dbManager)
  {
    $currentUserId = Auth::getUserId();

    $uploaderGroups = $this->userDao->getUserGroupIds($uploaderUserId);
    $currentUserGroups = $this->userDao->getUserGroupIds($currentUserId);

    $matchTypes = [
      'exact' => function($filename) use ($basePackageName) {
        $candidateBase = $this->extractBasePackageName($filename);
        return strcasecmp($candidateBase, $basePackageName) === 0 && $candidateBase === $basePackageName;
      },
      'case_insensitive' => function($filename) use ($basePackageName) {
        $candidateBase = $this->extractBasePackageName($filename);
        return strcasecmp($candidateBase, $basePackageName) === 0;
      },
      'prefix' => function($filename) use ($basePackageName) {
        $nameWithoutExt = preg_replace('/\.[^.]+$/', '', $filename);
        return stripos($nameWithoutExt, $basePackageName) === 0;
      },
      'ilike' => function($filename) use ($basePackageName) {
        return stripos($filename, $basePackageName) !== false;
      }
    ];

    $priorityQueries = [];

    if ($uploaderUserId > 0) {
      $stmtName = __METHOD__ . '.sameUploader';
      $dbManager->prepare($stmtName,
         "SELECT DISTINCT u.upload_pk, u.upload_filename, uc.group_fk
          FROM upload u
          JOIN upload_clearing uc ON uc.upload_fk = u.upload_pk
          WHERE u.upload_pk != $1
            AND u.upload_mode IN (100, 104)
            AND u.pfile_fk IS NOT NULL
            AND u.user_fk = $2
            AND EXISTS (SELECT 1 FROM upload_events ue WHERE ue.upload_fk = u.upload_pk AND ue.event_type = " . UploadEvents::UPLOAD_CLOSED_EVENT . ")
          LIMIT 200");
      $priorityQueries[] = ['stmt' => $stmtName, 'params' => [$uploadId, $uploaderUserId]];
    }

    if (!empty($uploaderGroups)) {
      $stmtName = __METHOD__ . '.sameGroup';
      $placeholders = [];
      for ($i = 0; $i < count($uploaderGroups); $i++) {
        $placeholders[] = '$' . ($i + 2);
      }
      $groupIdPlaceholders = implode(',', $placeholders);
      $dbManager->prepare($stmtName,
        "SELECT DISTINCT u.upload_pk, u.upload_filename, uc.group_fk
          FROM upload u
          JOIN upload_clearing uc ON uc.upload_fk = u.upload_pk
          WHERE u.upload_pk != $1
            AND u.upload_mode IN (100, 104)
            AND u.pfile_fk IS NOT NULL
            AND uc.group_fk IN ($groupIdPlaceholders)
            AND EXISTS (SELECT 1 FROM upload_events ue WHERE ue.upload_fk = u.upload_pk AND ue.event_type = " . UploadEvents::UPLOAD_CLOSED_EVENT . ")
          LIMIT 200");
      $priorityQueries[] = ['stmt' => $stmtName, 'params' => array_merge([$uploadId], $uploaderGroups)];
    }

    if (!empty($currentUserGroups)) {
      $stmtName = __METHOD__ . '.anyAccessible';
      $placeholders = [];
      for ($i = 0; $i < count($currentUserGroups); $i++) {
        $placeholders[] = '$' . ($i + 2);
      }
      $groupIdPlaceholders = implode(',', $placeholders);
      $dbManager->prepare($stmtName,
        "SELECT DISTINCT u.upload_pk, u.upload_filename, uc.group_fk
          FROM upload u
          JOIN upload_clearing uc ON uc.upload_fk = u.upload_pk
          WHERE u.upload_pk != $1
            AND u.upload_mode IN (100, 104)
            AND u.pfile_fk IS NOT NULL
            AND uc.group_fk IN ($groupIdPlaceholders)
            AND EXISTS (SELECT 1 FROM upload_events ue WHERE ue.upload_fk = u.upload_pk AND ue.event_type = " . UploadEvents::UPLOAD_CLOSED_EVENT . ")
          LIMIT 200");
      $priorityQueries[] = ['stmt' => $stmtName, 'params' => array_merge([$uploadId], $currentUserGroups)];
    }

    foreach ($priorityQueries as $pq) {
      $res = $dbManager->execute($pq['stmt'], $pq['params']);
      $candidates = [];
      while ($row = $dbManager->fetchArray($res)) {
        $candidates[] = [
          'upload_pk' => intval($row['upload_pk']),
          'upload_filename' => $row['upload_filename'],
          'group_fk' => intval($row['group_fk'])
        ];
      }
      $dbManager->freeResult($res);

      if (empty($candidates)) {
        continue;
      }

      foreach ($matchTypes as $matchFn) {
        $matched = [];
        foreach ($candidates as $candidate) {
          if ($matchFn($candidate['upload_filename'])) {
            $matched[] = $candidate;
          }
        }
        if (!empty($matched)) {
          return $this->clearingDao->getMostRecentlyClearedUpload($matched);
        }
      }
    }

    return null;
  }

  /**
   * @brief Secondary search: PFile overlap
   * @param int $uploadId
   * @param int $uploaderUserId
   * @param DbManager $dbManager
   * @return array|null
   */
  private function secondarySearch($uploadId, $dbManager)
  {
    $currentUserId = Auth::getUserId();
    $currentUserGroups = $this->userDao->getUserGroupIds($currentUserId);
    if (empty($currentUserGroups)) {
      return null;
    }

    $placeholders = [];
    for ($i = 0; $i < count($currentUserGroups); $i++) {
      $placeholders[] = '$' . ($i + 2);
    }
    $groupIdPlaceholders = implode(',', $placeholders);
    $stmtName = __METHOD__ . '.candidates';
    $dbManager->prepare($stmtName,
       "SELECT DISTINCT u.upload_pk, uc.group_fk
        FROM upload u
        JOIN upload_clearing uc ON uc.upload_fk = u.upload_pk
        WHERE u.upload_pk != $1
          AND u.upload_mode IN (100, 104)
          AND u.pfile_fk IS NOT NULL
          AND uc.group_fk IN ($groupIdPlaceholders)
          AND EXISTS (SELECT 1 FROM upload_events ue WHERE ue.upload_fk = u.upload_pk AND ue.event_type = " . UploadEvents::UPLOAD_CLOSED_EVENT . ")
          LIMIT 50");
     $res = $dbManager->execute($stmtName, array_merge([$uploadId], $currentUserGroups));
    $candidates = [];
    while ($row = $dbManager->fetchArray($res)) {
      $candidates[] = [
        'upload_pk' => intval($row['upload_pk']),
        'group_fk' => intval($row['group_fk'])
      ];
    }
    $dbManager->freeResult($res);

    if (empty($candidates)) {
      return null;
    }

    $stmtName = __METHOD__ . '.clearingTimestamps';
    $placeholders = [];
    $params = [];
    foreach ($candidates as $i => $c) {
      $placeholders[] = '$' . ($i + 1);
      $params[] = $c['upload_pk'];
    }
    $inClause = implode(',', $placeholders);
    $dbManager->prepare($stmtName,
      "SELECT ut.upload_fk, MAX(cd.date_added) AS last_cleared
       FROM clearing_decision cd
       JOIN uploadtree ut ON ut.uploadtree_pk = cd.uploadtree_fk
       WHERE ut.upload_fk IN ($inClause)
       GROUP BY ut.upload_fk");
    $res = $dbManager->execute($stmtName, $params);
    $timestamps = [];
    while ($row = $dbManager->fetchArray($res)) {
      $timestamps[intval($row['upload_fk'])] = $row['last_cleared'];
    }
    $dbManager->freeResult($res);

    $bestCandidate = null;
    $bestOverlap = -1;
    $bestTimestamp = null;

    $overlapStmt = __METHOD__ . '.overlap';
    $dbManager->prepare($overlapStmt,
      "SELECT COUNT(*) AS overlap
       FROM uploadtree ut1
       JOIN uploadtree ut2 ON ut1.pfile_fk = ut2.pfile_fk
       WHERE ut1.upload_fk = $1
         AND ut2.upload_fk = $2
         AND ut1.ufile_mode != 0
         AND ut2.ufile_mode != 0");

    foreach ($candidates as $candidate) {
      $res = $dbManager->execute($overlapStmt, [$uploadId, $candidate['upload_pk']]);
      $row = $dbManager->fetchArray($res);
      $dbManager->freeResult($res);
      $overlap = intval($row['overlap']);

      if ($overlap === 0) {
        continue;
      }

      $uploadPk = $candidate['upload_pk'];
      $ts = $timestamps[$uploadPk] ?? null;

      if ($overlap > $bestOverlap ||
          ($overlap === $bestOverlap && $ts !== null &&
           ($bestTimestamp === null || $ts > $bestTimestamp))) {
        $bestOverlap = $overlap;
        $bestTimestamp = $ts;
        $bestCandidate = $candidate;
      }
    }

    if ($bestCandidate !== null) {
      return [
        'uploadId' => $bestCandidate['upload_pk'],
        'groupId' => $bestCandidate['group_fk']
      ];
    }

    return null;
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
