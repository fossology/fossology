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
   * @brief Get all uploads accessible to curent user
   *
   * Gets all folders accessible by current user and iterate them. Find every
   * upload with in that folder and add data from prepareFolderUploads().
   * @return array Key as upload id
   */
  function getAllUploads()
  {
    $allFolder = $this->folderDao->getAllFolderIds();
    $result = array();
    for ($i=0; $i < sizeof($allFolder); $i++) {
      $listObject = $this->prepareFolderUploads($allFolder[$i]);
      foreach ($listObject as $key => $value) {
        $result[explode(",",$key)[0]] = $value;
      }
    }
    return $result;
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
}

register_plugin(new ReuserPlugin());
