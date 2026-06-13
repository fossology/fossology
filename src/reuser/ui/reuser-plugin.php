<?php
/*
 SPDX-FileCopyrightText: © 2014-2018 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @file
 */
namespace Fossology\Reuser;

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Dao\FolderDao;
use Fossology\Lib\Plugin\DefaultPlugin;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Fossology\Lib\Util\OsselotLookupHelper;
use Fossology\Reuse\ReuseAutoDetect;
use Monolog\Handler\BrowserConsoleHandler;
use Monolog\Handler\NullHandler;
use Monolog\Logger;

include_once(__DIR__ . "/../agent/version.php");
require_once(__DIR__ . "/ReuseAutoDetect.php");

/**
 * @class ReuserPlugin
 * @brief UI plugin for reuser
 */
class ReuserPlugin extends DefaultPlugin
{
  const NAME = "plugin_reuser";             ///< UI mod name

  const REUSE_FOLDER_SELECTOR_NAME = 'reuseFolderSelectorName'; ///< Reuse upload folder element name
  const UPLOAD_TO_REUSE_SELECTOR_NAME = 'uploadToReuse';  ///< Upload to reuse HTML element name
  const FOLDER_PARAMETER_NAME = 'folder';   ///< Folder parameter HTML element name
  const AUTO_DETECT_LIMIT = 4;              ///< Max uploads suggested by auto-detection

  /**
   * Extract package name from filename
   * @param string $filename
   * @return string
   */
  private function extractPackageNameFromFilename($filename)
  {
    if (empty($filename)) {
      return '';
    }

    $nameWithoutExt = preg_replace('/\.[^.]+$/', '', $filename);
    $parts = explode('-', $nameWithoutExt);
    $packageName = strtolower($parts[0]);

    return $packageName;
  }

  /** @var string $AgentName
   * Agent name from DB
   */
  public $AgentName = 'agent_reuser';
  /** @var FolderDao $folderDao
   * Folder Dao object
   */
  private $folderDao;

  public function __construct()
  {
    parent::__construct(self::NAME, array(
        self::TITLE => _("Automatic Clearing Decision Reuser"),
        self::PERMISSION => Auth::PERM_WRITE
    ));

    $this->folderDao = $this->getObject('dao.folder');
  }

  /**
   * @brief Get all uploads accessible to current user
   * @return array[] List of structured upload entries
   */
  function getAllUploads()
  {
    $trustGroupId = Auth::getGroupId();
    $folderUploads = $this->folderDao->getAllUploadsForGroup($trustGroupId);
    return $this->buildStructuredUploads($folderUploads, $trustGroupId);
  }

  /**
   * @copydoc Fossology::Lib::Plugin::DefaultPlugin::handle()
   * @see Fossology::Lib::Plugin::DefaultPlugin::handle()
   */
  protected function handle(Request $request)
  {
    $this->folderDao->ensureTopLevelFolder();
    $ajax = $request->get('do');

    // Every branch below returns JSON (or a plain-text error), never HTML.
    // The DI bootstrap unconditionally attaches a BrowserConsoleHandler that
    // appends a <script> debug dump to the response body on shutdown, which
    // would corrupt the JSON payload; suppress it the same way ui-download.php
    // does for its binary responses.
    $logger = $GLOBALS['container']->get('logger');
    $logger->pushHandler(new NullHandler(Logger::DEBUG));
    BrowserConsoleHandler::resetStatic();

    if ($ajax === 'getUploads') {
        list($fid, $tgid) = $this->getFolderIdAndTrustGroup($request->get(self::FOLDER_PARAMETER_NAME, ''));
        $uploads = (empty($fid) || empty($tgid))
            ? $this->getAllUploads()
            : $this->prepareFolderUploads($fid, $tgid);
        return new JsonResponse($uploads, JsonResponse::HTTP_OK);
    }

    if ($ajax === 'getOsselotVersions') {
        $pkg = trim($request->get('pkg', $request->get('osselotPackage', '')));
      if ($pkg === '') {
          return new JsonResponse([], JsonResponse::HTTP_OK);
      }
        $helper = new OsselotLookupHelper();
      try {
          $versions = $helper->getVersions($pkg);
      } catch (\Exception $e) {
          $versions = [];
      }
        return new JsonResponse($versions, JsonResponse::HTTP_OK);
    }

    if ($ajax === 'extractPackageName') {
        $filename = trim($request->get('filename', ''));
      if ($filename === '') {
          return new JsonResponse(['packageName' => ''], JsonResponse::HTTP_OK);
      }

        $packageName = $this->extractPackageNameFromFilename($filename);
        return new JsonResponse(['packageName' => $packageName], JsonResponse::HTTP_OK);
    }

    if ($ajax === 'autoDetectReuse') {
        $candidatesParam = trim($request->get('candidates', ''));
      if (!empty($candidatesParam)) {
        try {
            return new JsonResponse($this->narrowCandidates($candidatesParam), JsonResponse::HTTP_OK);
        } catch (\Exception $e) {
            return new JsonResponse([], JsonResponse::HTTP_OK);
        }
      }
        $filename = trim($request->get('filename', ''));
      if ($filename === '') {
          return new JsonResponse([], JsonResponse::HTTP_OK);
      }
      $folderGroupPair = trim($request->get(self::FOLDER_PARAMETER_NAME, ''));
      try {
          $result = $this->autoDetectReuseFromFilename($filename, $folderGroupPair);
          return new JsonResponse($result, JsonResponse::HTTP_OK);
      } catch (\Exception $e) {
          return new JsonResponse([], JsonResponse::HTTP_OK);
      }
    }

    return new Response('called without valid method', Response::HTTP_METHOD_NOT_ALLOWED);
  }

  /**
   * @brief For a given folder group, extract forder id and trust group id
   * @param array $folderGroup
   * @return int[]
   */
  public function getFolderIdAndTrustGroup($folderGroup)
  {
    $folderGroupPair = explode(',', $folderGroup,2);
    if (count($folderGroupPair) == 2) {
      list($folder, $trustGroup) = $folderGroupPair;
      $folderId = intval($folder);
      $trustGroupId = intval($trustGroup);
    } else {
      $trustGroupId = Auth::getGroupId();
      $folderId = 0;
    }
    return array($folderId, $trustGroupId);
  }

  /**
   * @brief Load the data in array and render twig template
   * @param[in,out] array $vars
   * @return string
   */
  public function renderContent(&$vars)
  {
    global $SysConf;
    $osselotAvailable = (array_key_exists('EnableOsselotReuse', $SysConf['SYSCONFIG']) && $SysConf['SYSCONFIG']["EnableOsselotReuse"] === 'true');
    if (!array_key_exists('folderStructure', $vars)) {
      $rootFolderId = $this->folderDao->getRootFolder(Auth::getUserId())->getId();
      $vars['folderStructure'] = $this->folderDao->getFolderStructure($rootFolderId);
    }
    if ($this->folderDao->isWithoutReusableFolders($vars['folderStructure'])) {
      return '';
    }
    $pair = array_key_exists(self::FOLDER_PARAMETER_NAME, $vars) ? $vars[self::FOLDER_PARAMETER_NAME] : '';

    list($folderId, $trustGroupId) = $this->getFolderIdAndTrustGroup($pair);
    if (empty($folderId) && !empty($vars['folderStructure'])) {
      $folderId = $vars['folderStructure'][0][FolderDao::FOLDER_KEY]->getId();
    }

    $vars['reuseFolderSelectorName'] = self::REUSE_FOLDER_SELECTOR_NAME;
    $vars['folderParameterName'] = self::FOLDER_PARAMETER_NAME;
    $vars['uploadToReuseSelectorName'] = self::UPLOAD_TO_REUSE_SELECTOR_NAME;
    $vars['folderUploads'] = $this->prepareFolderUploads($folderId, $trustGroupId);
    $vars['osselotAvailable'] = $osselotAvailable;
    $vars['defaultPkgName']  = '';
    $vars['userIsAdmin']     = Auth::isAdmin();

    $renderer = $this->getObject('twig.environment');
    return $renderer->load('agent_reuser.html.twig')->render($vars);
  }

  /**
   * @brief Render footer template
   * @param array $vars
   * @return string
   */
  public function renderFoot(&$vars)
  {
    global $SysConf;
    $osselotAvailable = (array_key_exists('EnableOsselotReuse', $SysConf['SYSCONFIG']) && $SysConf['SYSCONFIG']["EnableOsselotReuse"] === 'true');
    $vars['reuseFolderSelectorName'] = self::REUSE_FOLDER_SELECTOR_NAME;
    $vars['folderParameterName'] = self::FOLDER_PARAMETER_NAME;
    $vars['uploadToReuseSelectorName'] = self::UPLOAD_TO_REUSE_SELECTOR_NAME;
    $vars['osselotAvailable']           = $osselotAvailable;
    $vars['autoDetectEnabled'] = filter_var(
      $SysConf['SYSCONFIG']['ReuserAutoDetect'] ?? 'true',
      FILTER_VALIDATE_BOOLEAN
    );
    $statusFilter = $SysConf['SYSCONFIG']['ReuserSearchStatus'] ?? '3';
    $statusIds = array_map('intval', array_filter(explode(',', $statusFilter)));
    $vars['reuserSearchStatusesJson'] = json_encode($statusIds);
    $vars['reuserCurrentUserId'] = Auth::getUserId();

    $renderer = $this->getObject('twig.environment');
    return $renderer->load('agent_reuser.js.twig')->render($vars);
  }

  /**
   * @brief Render JS inclues
   * @param array $vars
   * @return string
   */
  public function getScriptIncludes(&$vars)
  {
    return '<script src="scripts/tools.js" type="text/javascript"></script>';
  }

  /**
   * @brief For a given folder id, collect all uploads
   *
   * Creates a structured list of uploads with upload id, group id, filename,
   * timestamp, status id, most recent clearing time and display string.
   * @param int $folderId
   * @param int $trustGroupId
   * @return array[]
   */
  protected function prepareFolderUploads($folderId, $trustGroupId=null)
  {
    if (empty($trustGroupId)) {
      $trustGroupId = Auth::getGroupId();
    }
    $folderUploads = $this->folderDao->getFolderUploads($folderId, $trustGroupId);

    return $this->buildStructuredUploads($folderUploads, $trustGroupId);
  }

  /**
   * @brief Build a structured upload list with most recent clearing time
   * @param UploadProgress[] $uploadProgresses
   * @param int $groupId Group to scope clearing decisions to
   * @return array[] List of ['uploadId','groupId','filename','timestamp',
   *                          'statusId','clearedAt','display']
   */
  private function buildStructuredUploads($uploadProgresses, $groupId)
  {
    $uploads = array();
    $uploadIds = array();
    foreach ($uploadProgresses as $uploadProgress) {
      $uploadIds[] = $uploadProgress->getId();
      $uploads[] = array(
        'uploadId' => $uploadProgress->getId(),
        'groupId' => $uploadProgress->getGroupId(),
        'userId' => 0,
        'filename' => $uploadProgress->getFilename(),
        'timestamp' => date("Y-m-d H:i:s", $uploadProgress->getTimestamp()),
        'statusId' => $uploadProgress->getStatusId(),
        'clearedAt' => null,
        'display' => $uploadProgress->getFilename() . _(" from ")
          . Convert2BrowserTime(date("Y-m-d H:i:s", $uploadProgress->getTimestamp()))
          . ' (' . $uploadProgress->getStatusString() . ')'
      );
    }
    if (empty($uploadIds)) {
      return array();
    }

    $placeholders = array();
    $params = array();
    for ($i = 0; $i < count($uploadIds); $i++) {
      $placeholders[] = '$' . ($i + 1);
      $params[] = $uploadIds[$i];
    }
    $groupIdPlaceholder = '$' . (count($uploadIds) + 1);
    $params[] = $groupId;

    $dbManager = $GLOBALS['container']->get('db.manager');
    $stmtUser = __METHOD__ . '.uploader';
    $dbManager->prepare($stmtUser,
      "SELECT upload_pk, user_fk
       FROM upload
       WHERE upload_pk IN (" . implode(',', $placeholders) . ")");
    $res = $dbManager->execute($stmtUser, array_slice($params, 0, count($uploadIds)));
    $userIdByUpload = array();
    while ($row = $dbManager->fetchArray($res)) {
      $userIdByUpload[intval($row['upload_pk'])] = intval($row['user_fk']);
    }
    $dbManager->freeResult($res);

    $stmtName = __METHOD__ . '.lastCleared';
    $dbManager->prepare($stmtName,
      "SELECT ut.upload_fk, MAX(cd.date_added) AS last_cleared
       FROM clearing_decision cd
       JOIN uploadtree ut ON ut.uploadtree_pk = cd.uploadtree_fk
       WHERE ut.upload_fk IN (" . implode(',', $placeholders) . ")
         AND cd.group_fk = $groupIdPlaceholder
       GROUP BY ut.upload_fk");
    $res = $dbManager->execute($stmtName, $params);
    $clearedById = array();
    while ($row = $dbManager->fetchArray($res)) {
      $clearedById[intval($row['upload_fk'])] =
        date("Y-m-d H:i:s", strtotime($row['last_cleared']));
    }
    $dbManager->freeResult($res);

    foreach ($uploads as &$entry) {
      if (array_key_exists($entry['uploadId'], $clearedById)) {
        $entry['clearedAt'] = $clearedById[$entry['uploadId']];
      }
      if (array_key_exists($entry['uploadId'], $userIdByUpload)) {
        $entry['userId'] = $userIdByUpload[$entry['uploadId']];
      }
    }
    unset($entry);
    return $uploads;
  }
  /**
   * @brief Narrow a client-provided candidate list to a single upload
   *
   * The candidate pairs come from the client's local ranking and are not
   * trusted as-is: only pairs whose upload is owned by the current user
   * and whose group is one the current user can access (same eligibility
   * rule as primarySearchFromPlugin()) are considered.
   * @param string $candidatesParam "uploadId,groupId;uploadId,groupId;..."
   * @return array[] Single result ['uploadId'=>int,'groupId'=>int] or []
   */
  private function narrowCandidates($candidatesParam)
  {
    $pairs = [];
    foreach (explode(';', $candidatesParam) as $pairStr) {
      $parts = explode(',', trim($pairStr));
      if (count($parts) === 2 && is_numeric($parts[0]) && is_numeric($parts[1])) {
        $pairs[] = ['uploadId' => intval($parts[0]), 'groupId' => intval($parts[1])];
      }
    }
    if (empty($pairs)) {
      return [];
    }
    $dbManager = $GLOBALS['container']->get('db.manager');
    $eligiblePairs = ReuseAutoDetect::filterEligibleCandidates($pairs, Auth::getUserId(), $dbManager);
    if (empty($eligiblePairs)) {
      return [];
    }
    $clearingDao = $GLOBALS['container']->get('dao.clearing');
    $winner = ReuseAutoDetect::selectWinnerByClearing($eligiblePairs, $clearingDao);
    if ($winner === null) {
      return [];
    }
    return [['uploadId' => $winner['uploadId'], 'groupId' => $winner['groupId']]];
  }

  /**
   * @brief Convert a candidate into an auto-detect result with display
   * @param array $candidate Candidate with uploadId, groupId and either
   *        display or statusId
   * @return array|null ['uploadId'=>int,'groupId'=>int,'display'=>string] or null
   */
  private function candidateToResult($candidate)
  {
    if (!empty($candidate['display'])) {
      return ['uploadId' => $candidate['uploadId'], 'groupId' => $candidate['groupId'], 'display' => $candidate['display']];
    }
    $uploadDao = $GLOBALS['container']->get('dao.upload');
    $statusType = new \Fossology\Lib\Data\UploadStatus();
    $upload = $uploadDao->getUpload($candidate['uploadId']);
    if ($upload === null) {
      return null;
    }
    $statusId = isset($candidate['statusId']) ? $candidate['statusId'] : 3;
    $display = $upload->getFilename() . _(" from ")
      . Convert2BrowserTime(date("Y-m-d H:i:s", $upload->getTimestamp()))
      . ' (' . $statusType->getTypeName($statusId) . ')';
    return ['uploadId' => $candidate['uploadId'], 'groupId' => $candidate['groupId'], 'display' => $display];
  }

  /**
   * @brief Auto-detect best reuse upload for a given filename
   *
   * Eligibility: uploads uploaded by the current user in groups the user
   * can access. When a specific upload status filter is configured, the
   * single best match (nearest version, then date) is selected. Without a
   * status filter the top candidates are evaluated by clearing decisions:
   * first fully cleared, then highest cleared percentage, finally the best
   * by version/date.
   * @param string $filename
   * @param string $folderGroupPair "folderId,trustGroupId" pair; when given,
   *        the search is restricted to that folder's uploads
   * @return array[] Single result ['uploadId'=>int,'groupId'=>int,'display'=>string]
   *                 or []
   */
  private function autoDetectReuseFromFilename($filename, $folderGroupPair = '')
  {
    global $SysConf;

    $autoDetectEnabled = filter_var(
      $SysConf['SYSCONFIG']['ReuserAutoDetect'] ?? 'true',
      FILTER_VALIDATE_BOOLEAN
    );
    if (!$autoDetectEnabled) {
      return [];
    }

    $statusFilter = $SysConf['SYSCONFIG']['ReuserSearchStatus'] ?? '3';
    $statusIds = array_map('intval', array_filter(explode(',', $statusFilter)));
    $statusSpecific = ReuseAutoDetect::isStatusSpecific($statusIds);
    $limit = $statusSpecific ? 1 : self::AUTO_DETECT_LIMIT;

    $parsed = ReuseAutoDetect::parsePackageName($filename);
    $basePackageName = $parsed['baseName'];
    if (empty($basePackageName)) {
      return [];
    }

    list($folderId, $trustGroupId) = $this->getFolderIdAndTrustGroup($folderGroupPair);
    if (!empty($folderId) && !empty($trustGroupId)) {
      return $this->autoDetectInFolder($folderId, $trustGroupId, $basePackageName,
        $parsed['versionParts'], $statusIds, $limit);
    }

    $dbManager = $GLOBALS['container']->get('db.manager');
    $uploaderUserId = Auth::getUserId();

    $matches = $this->primarySearchFromPlugin($basePackageName, $parsed['versionParts'],
      $uploaderUserId, $dbManager, $statusIds, $limit);
    if (empty($matches)) {
      return [];
    }
    if (!$statusSpecific) {
      $clearingDao = $GLOBALS['container']->get('dao.clearing');
      $winner = ReuseAutoDetect::selectWinnerByClearing($matches, $clearingDao);
      if ($winner === null) {
        return [];
      }
      $matches = [$winner];
    } else {
      $matches = [$matches[0]];
    }

    $results = [];
    foreach ($matches as $match) {
      $result = $this->candidateToResult($match);
      if ($result !== null) {
        $results[] = $result;
      }
    }
    return $results;
  }

  /**
   * @brief Auto-detect reuse uploads restricted to a single folder
   * @param int $folderId
   * @param int $trustGroupId
   * @param string $basePackageName
   * @param int[]|null $requestedVersionParts
   * @param int[] $statusIds Upload clearing status IDs to filter; empty for all
   * @param int $limit Number of candidates (1 when status filter set)
   * @return array[] Single result ['uploadId'=>int,'groupId'=>int,'display'=>string]
   *                 or []
   */
  private function autoDetectInFolder($folderId, $trustGroupId, $basePackageName, $requestedVersionParts, $statusIds, $limit)
  {
    $uploaderUserId = Auth::getUserId();
    $folderUploads = $this->prepareFolderUploads($folderId, $trustGroupId);
    if (empty($folderUploads)) {
      return [];
    }

    $candidates = [];
    foreach ($folderUploads as $item) {
      if ($uploaderUserId > 0 && intval($item['userId']) !== $uploaderUserId) {
        continue;
      }
      $parsed = ReuseAutoDetect::parsePackageName($item['filename']);
      if (strcasecmp($parsed['baseName'], $basePackageName) !== 0) {
        continue;
      }
      if (!empty($statusIds) && !in_array($item['statusId'], $statusIds)) {
        continue;
      }
      $candidates[] = [
        'uploadId' => $item['uploadId'],
        'groupId' => $item['groupId'],
        'statusId' => $item['statusId'],
        'versionParts' => $parsed['versionParts'],
        'prerelease' => $parsed['prerelease'],
        'clearedAt' => $item['clearedAt'],
        'timestamp' => strtotime($item['timestamp']),
        'display' => $item['display']
      ];
    }
    if (empty($candidates)) {
      return [];
    }

    ReuseAutoDetect::rankCandidates($candidates, $requestedVersionParts);
    $candidates = array_slice($candidates, 0, $limit);
    if ($limit > 1) {
      $clearingDao = $GLOBALS['container']->get('dao.clearing');
      $winner = ReuseAutoDetect::selectWinnerByClearing($candidates, $clearingDao);
      if ($winner === null) {
        return [];
      }
      $candidates = [$winner];
    }

    $results = [];
    foreach ($candidates as $candidate) {
      $result = $this->candidateToResult($candidate);
      if ($result !== null) {
        $results[] = $result;
      }
    }
    return $results;
  }

  /**
   * @brief Primary search: eligible uploads of the current user with
   *        matching package name
   *
   * Eligibility: uploads uploaded by the current user whose clearing group
   * is accessible to the user. Candidates are ranked by nearest version,
   * then date.
   * @param string $basePackageName
   * @param int[]|null $requestedVersionParts
   * @param int $uploaderUserId
   * @param \Fossology\Lib\Db\DbManager $dbManager
   * @param int[] $statusIds Upload clearing status IDs to filter; empty for all
   * @param int $limit Maximum number of candidates
   * @return array[] List of ['uploadId','groupId','statusId','versionParts',
   *                          'prerelease','timestamp'] ranked best-first, or []
   */
  private function primarySearchFromPlugin($basePackageName, $requestedVersionParts, $uploaderUserId, $dbManager, $statusIds, $limit)
  {
    $currentUserId = Auth::getUserId();
    $userDao = $GLOBALS['container']->get('dao.user');
    $currentUserGroups = $userDao->getUserGroupIds($currentUserId);
    if (empty($currentUserGroups) || $uploaderUserId <= 0) {
      return [];
    }

    $groupCount = count($currentUserGroups);
    $params = array_merge([$uploaderUserId], $currentUserGroups);
    if (!empty($statusIds)) {
      array_push($params, ...$statusIds);
    }
    $params[] = $currentUserId;
    $params[] = $basePackageName;
    $permPlaceholder = '$' . (count($params) - 1);
    $namePlaceholder = '$' . count($params);
    $statusOffset = $groupCount + 2;
    $statusList = $this->buildStatusPlaceholders($statusIds, $statusOffset);
    $statusClause = empty($statusIds) ? '' : " AND uc.status_fk IN ($statusList)";
    $groupIdPlaceholders = $this->buildPlaceholders($groupCount, 2);

    $stmtName = __METHOD__ . '.eligible';
    $dbManager->prepare($stmtName,
      "SELECT DISTINCT u.upload_pk, u.upload_filename, u.upload_ts, uc.group_fk, uc.status_fk
       FROM upload u
       JOIN upload_clearing uc ON uc.upload_fk = u.upload_pk
       WHERE u.upload_mode IN (100, 104)
         AND u.pfile_fk IS NOT NULL
         AND u.user_fk = $1
         AND uc.group_fk IN ($groupIdPlaceholders)
         AND EXISTS (SELECT 1 FROM group_user_member gum
                     WHERE gum.group_fk = uc.group_fk AND gum.user_fk = $permPlaceholder AND gum.group_perm >= 1)
         $statusClause
         AND u.upload_filename ILIKE '%' || $namePlaceholder || '%'
       ORDER BY u.upload_ts DESC
       LIMIT 200");
    $res = $dbManager->execute($stmtName, $params);
    $candidates = [];
    while ($row = $dbManager->fetchArray($res)) {
      $parsed = ReuseAutoDetect::parsePackageName($row['upload_filename']);
      if (strcasecmp($parsed['baseName'], $basePackageName) !== 0) {
        continue;
      }
      $candidates[] = [
        'uploadId' => intval($row['upload_pk']),
        'groupId' => intval($row['group_fk']),
        'statusId' => intval($row['status_fk']),
        'versionParts' => $parsed['versionParts'],
        'prerelease' => $parsed['prerelease'],
        'clearedAt' => null,
        'timestamp' => strtotime($row['upload_ts'])
      ];
    }
    $dbManager->freeResult($res);

    if (empty($candidates)) {
      return [];
    }
    ReuseAutoDetect::rankCandidates($candidates, $requestedVersionParts);
    return array_slice($candidates, 0, $limit);
  }

  /**
   * @brief Build comma-separated placeholder list starting at offset
   * @param int $count Number of placeholders
   * @param int $start Offset (1-based)
   * @return string e.g. "$2,$3,$4"
   */
  private function buildPlaceholders($count, $start = 1)
  {
    $placeholders = [];
    for ($i = 0; $i < $count; $i++) {
      $placeholders[] = '$' . ($start + $i);
    }
    return implode(',', $placeholders);
  }

  /**
   * @brief Build status placeholder list
   * @param int[] $statusIds
   * @param int $start Offset for placeholder numbering
   * @return string e.g. "$2,$3"
   */
  private function buildStatusPlaceholders($statusIds, $start)
  {
    return $this->buildPlaceholders(count($statusIds), $start);
  }
}

register_plugin(new ReuserPlugin());
