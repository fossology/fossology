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

include_once(__DIR__ . "/../agent/version.php");

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
   * @return array Key as upload id
   */
  function getAllUploads()
  {
    $trustGroupId = Auth::getGroupId();
    $folderUploads = $this->folderDao->getAllUploadsForGroup($trustGroupId);
    $uploadsById = array();
    foreach ($folderUploads as $uploadProgress) {
      $key = $uploadProgress->getId();
      $display = $uploadProgress->getFilename() . _(" from ")
            . Convert2BrowserTime(date("Y-m-d H:i:s", $uploadProgress->getTimestamp()))
            . ' (' . $uploadProgress->getStatusString() . ')';
      $uploadsById[$key] = $display;
    }
    return $uploadsById;
  }

  /**
   * @copydoc Fossology::Lib::Plugin::DefaultPlugin::handle()
   * @see Fossology::Lib::Plugin::DefaultPlugin::handle()
   */
  protected function handle(Request $request)
  {
    $this->folderDao->ensureTopLevelFolder();
    $ajax = $request->get('do');

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
        $filename = trim($request->get('filename', ''));
      if ($filename === '') {
          return new JsonResponse([], JsonResponse::HTTP_OK);
      }
      try {
          $result = $this->autoDetectReuseFromFilename($filename);
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
   * Creates an array of uploads with `<upload_id,group_id>` as the key and
   * `<upload_name> from <Y-m-d H:i> (<status>)` as value.
   * @param int $folderId
   * @param int $trustGroupId
   * @return UploadProgress[]
   */
  protected function prepareFolderUploads($folderId, $trustGroupId=null)
  {
    if (empty($trustGroupId)) {
      $trustGroupId = Auth::getGroupId();
    }
    $folderUploads = $this->folderDao->getFolderUploads($folderId, $trustGroupId);

    $uploadsById = array();
    foreach ($folderUploads as $uploadProgress) {
      $key = $uploadProgress->getId().','.$uploadProgress->getGroupId();
      $display = $uploadProgress->getFilename() . _(" from ")
               . Convert2BrowserTime(date("Y-m-d H:i:s",$uploadProgress->getTimestamp()))
               . ' ('. $uploadProgress->getStatusString() . ')';
      $uploadsById[$key] = $display;
    }
    return $uploadsById;
  }
  /**
   * @brief Extract base package name from filename by removing version suffix
   * @param string $filename
   * @return string
   */
  private function extractBasePackageNameFromPlugin($filename)
  {
    if (empty($filename)) {
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
   * @brief Auto-detect best reuse upload for a given filename
   * @param string $filename
   * @return array ['uploadId'=>int, 'groupId'=>int, 'display'=>string] or []
   */
  private function autoDetectReuseFromFilename($filename)
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
    if (empty($statusIds)) {
      $statusIds = [3];
    }

    $dbManager = $GLOBALS['container']->get('db.manager');
    $basePackageName = $this->extractBasePackageNameFromPlugin($filename);
    if (empty($basePackageName)) {
      return [];
    }

    $uploaderUserId = Auth::getUserId();

    $statusType = new \Fossology\Lib\Data\UploadStatus();
    $match = $this->primarySearchFromPlugin($basePackageName, $uploaderUserId, $dbManager, $statusIds);
    if ($match !== null) {
      $uploadDao = $GLOBALS['container']->get('dao.upload');
      $upload = $uploadDao->getUpload($match['uploadId']);
      if ($upload !== null) {
        $statusName = $statusType->getTypeName($match['statusId']);
        $display = $upload->getFilename() . _(" from ")
          . Convert2BrowserTime(date("Y-m-d H:i:s", $upload->getTimestamp()))
          . ' (' . $statusName . ')';
        return ['uploadId' => $match['uploadId'], 'groupId' => $match['groupId'], 'display' => $display];
      }
    }

    return [];
  }

  /**
   * @brief Primary search: package name matching (UI-level)
   * @param string $basePackageName
   * @param int $uploaderUserId
   * @param \Fossology\Lib\Db\DbManager $dbManager
   * @param int[] $statusIds Upload clearing status IDs to filter
   * @return array|null ['uploadId'=>int, 'groupId'=>int, 'statusId'=>int] or null
   */
  private function primarySearchFromPlugin($basePackageName, $uploaderUserId, $dbManager, $statusIds)
  {
    $currentUserId = Auth::getUserId();
    $userDao = $GLOBALS['container']->get('dao.user');
    $uploaderGroups = $userDao->getUserGroupIds($uploaderUserId);
    $currentUserGroups = $userDao->getUserGroupIds($currentUserId);

    $matchTypes = [
      'exact' => function($filename) use ($basePackageName) {
        $candidateBase = $this->extractBasePackageNameFromPlugin($filename);
        return strcasecmp($candidateBase, $basePackageName) === 0 && $candidateBase === $basePackageName;
      },
      'case_insensitive' => function($filename) use ($basePackageName) {
        $candidateBase = $this->extractBasePackageNameFromPlugin($filename);
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
      $params = array_merge([$uploaderUserId], $statusIds, [$basePackageName]);
      $statusOffset = 2;
      $statusCount = count($statusIds);
      $namePlaceholder = '$' . ($statusCount + 2);
      $statusList = $this->buildStatusPlaceholders($statusIds, $statusOffset);
      $dbManager->prepare($stmtName,
        "SELECT DISTINCT u.upload_pk, u.upload_filename, uc.group_fk, uc.status_fk
         FROM upload u
         JOIN upload_clearing uc ON uc.upload_fk = u.upload_pk
         WHERE u.upload_mode IN (100, 104)
           AND u.pfile_fk IS NOT NULL
           AND uc.group_fk IN ($groupIdPlaceholders)
           AND uc.status_fk IN ($statusList)
           AND EXISTS (SELECT 1 FROM group_user_member gum WHERE gum.group_fk = uc.group_fk AND gum.user_fk = $permPlaceholder AND gum.group_perm >= 2)
           AND u.upload_filename ILIKE '%' || $namePlaceholder || '%'
         ORDER BY u.upload_ts DESC
         LIMIT 200");
      $priorityQueries[] = ['stmt' => $stmtName, 'params' => $params];
    }

    if (!empty($uploaderGroups)) {
      $stmtName = __METHOD__ . '.sameGroup';
      $groupCount = count($uploaderGroups);
      $statusCount = count($statusIds);
      $permPlaceholder = '$' . ($groupCount + $statusCount + 1);
      $namePlaceholder = '$' . ($groupCount + $statusCount + 2);
      $params = array_merge($uploaderGroups, $statusIds, [$currentUserId, $basePackageName]);
      $statusOffset = $groupCount + 1;
      $statusList = $this->buildStatusPlaceholders($statusIds, $statusOffset);
      $groupIdPlaceholders = $this->buildPlaceholders($groupCount);
      $dbManager->prepare($stmtName,
        "SELECT DISTINCT u.upload_pk, u.upload_filename, uc.group_fk, uc.status_fk
         FROM upload u
         JOIN upload_clearing uc ON uc.upload_fk = u.upload_pk
         WHERE u.upload_mode IN (100, 104)
           AND u.pfile_fk IS NOT NULL
           AND uc.group_fk IN ($groupIdPlaceholders)
           AND uc.status_fk IN ($statusList)
           AND EXISTS (SELECT 1 FROM group_user_member gum WHERE gum.group_fk = uc.group_fk AND gum.user_fk = $permPlaceholder AND gum.group_perm >= 2)
           AND u.upload_filename ILIKE '%' || $namePlaceholder || '%'
         ORDER BY u.upload_ts DESC
         LIMIT 200");
      $priorityQueries[] = ['stmt' => $stmtName, 'params' => $params];
    }

    if (!empty($currentUserGroups)) {
      $stmtName = __METHOD__ . '.anyAccessible';
      $groupCount = count($currentUserGroups);
      $statusCount = count($statusIds);
      $permPlaceholder = '$' . ($groupCount + $statusCount + 1);
      $namePlaceholder = '$' . ($groupCount + $statusCount + 2);
      $params = array_merge($currentUserGroups, $statusIds, [$currentUserId, $basePackageName]);
      $statusOffset = $groupCount + 1;
      $statusList = $this->buildStatusPlaceholders($statusIds, $statusOffset);
      $groupIdPlaceholders = $this->buildPlaceholders($groupCount);
      $dbManager->prepare($stmtName,
        "SELECT DISTINCT u.upload_pk, u.upload_filename, uc.group_fk, uc.status_fk
         FROM upload u
         JOIN upload_clearing uc ON uc.upload_fk = u.upload_pk
         WHERE u.upload_mode IN (100, 104)
           AND u.pfile_fk IS NOT NULL
           AND uc.group_fk IN ($groupIdPlaceholders)
           AND uc.status_fk IN ($statusList)
           AND EXISTS (SELECT 1 FROM group_user_member gum WHERE gum.group_fk = uc.group_fk AND gum.user_fk = $permPlaceholder AND gum.group_perm >= 2)
           AND u.upload_filename ILIKE '%' || $namePlaceholder || '%'
         ORDER BY u.upload_ts DESC
         LIMIT 200");
      $priorityQueries[] = ['stmt' => $stmtName, 'params' => $params];
    }

    foreach ($priorityQueries as $pq) {
      $res = $dbManager->execute($pq['stmt'], $pq['params']);
      $candidates = [];
      while ($row = $dbManager->fetchArray($res)) {
        $candidates[] = [
          'upload_pk' => intval($row['upload_pk']),
          'upload_filename' => $row['upload_filename'],
          'group_fk' => intval($row['group_fk']),
          'status_fk' => intval($row['status_fk'])
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
          $clearingDao = $GLOBALS['container']->get('dao.clearing');
          return $clearingDao->getMostRecentlyClearedUpload($matched);
        }
      }
    }

    return null;
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
