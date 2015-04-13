<?php
/***********************************************************
 * Copyright (C) 2014-2015, Siemens AG
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * version 2 as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 ***********************************************************/

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Dao\FolderDao;
use Fossology\Lib\Dao\PackageDao;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Data\Upload\UploadProgress;
use Fossology\Lib\Plugin\DefaultPlugin;
use Fossology\Lib\Util\StringOperation;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

include_once(__DIR__ . "/../agent/version.php");

class ReuserPlugin extends DefaultPlugin
{
  const NAME = "plugin_reuser";

  const REUSE_FOLDER_SELECTOR_NAME = 'reuseFolderSelectorName';
  const UPLOAD_TO_REUSE_SELECTOR_NAME = 'uploadToReuse';
  const FOLDER_PARAMETER_NAME = 'folder';

  public $AgentName = 'agent_reuser';
  /** @var FolderDao */
  private $folderDao;
  /** @var UploadDao */
  private $uploadDao;

  public function __construct()
  {
    parent::__construct(self::NAME, array(
        self::TITLE => _("Automatic Clearing Decision Reuser"),
        self::PERMISSION => Auth::PERM_WRITE
    ));

    $this->folderDao = $this->getObject('dao.folder');
    $this->uploadDao = $this->getObject('dao.upload');
  }
    

  /**
   * @param Request $request
   * @return Response
   */
  protected function handle(Request $request)
  {
    $this->folderDao->ensureTopLevelFolder();
    list($folderId, $trustGroupId) = $this->getFolderIdAndTrustGroup($request->get(self::FOLDER_PARAMETER_NAME));
    $ajaxMethodName = $request->get('do');

    if ($ajaxMethodName == "getUploads")
    {
      $uploadsById = $this->prepareFolderUploads($folderId, $trustGroupId);
      return new JsonResponse($uploadsById, JsonResponse::HTTP_OK);
    }
    
    return new Response('called without valid method', Response::HTTP_METHOD_NOT_ALLOWED);
  }
  
  protected function getFolderIdAndTrustGroup($folderGroup)
  {
    $folderGroupPair = explode(',', $folderGroup,2);
    if (count($folderGroupPair) == 2) {
      list($folder, $trustGroup) = $folderGroupPair;
      $folderId = intval($folder);
      $trustGroupId = intval($trustGroup);
    }
    else
    {
      $trustGroupId = Auth::getGroupId();
      $folderId = 0;
    }
    return array($folderId, $trustGroupId);
  }
  
  /**
   * @param array $vars
   * @return string
   */
  public function renderContent(&$vars)
  {
    if (!array_key_exists('folderStructure', $vars))
    {
      $rootFolderId = $this->folderDao->getRootFolder(Auth::getUserId())->getId();
      $vars['folderStructure'] = $this->folderDao->getFolderStructure($rootFolderId);
    }
    $pair = array_key_exists(self::FOLDER_PARAMETER_NAME, $vars) ? $vars[self::FOLDER_PARAMETER_NAME] : '';

    list($folderId, $trustGroupId) = $this->getFolderIdAndTrustGroup($pair);
    if (empty($folderId) && !empty($vars['folderStructure']))
    {
      $folderId = $vars['folderStructure'][0][FolderDao::FOLDER_KEY]->getId();
    }
    
    $vars['reuseFolderSelectorName'] = self::REUSE_FOLDER_SELECTOR_NAME;
    $vars['folderParameterName'] = self::FOLDER_PARAMETER_NAME;
    $vars['uploadToReuseSelectorName'] = self::UPLOAD_TO_REUSE_SELECTOR_NAME;
    $vars['folderUploads'] = $this->prepareFolderUploads($folderId, $trustGroupId);
    
    $renderer = $this->getObject('twig.environment');
    return $renderer->loadTemplate('agent_reuser.html.twig')->render($vars);
  }
  
  /**
   * @param array $vars
   * @return string
   */
  public function renderFoot(&$vars)
  {
    $vars['reuseFolderSelectorName'] = self::REUSE_FOLDER_SELECTOR_NAME;
    $vars['folderParameterName'] = self::FOLDER_PARAMETER_NAME;
    $vars['uploadToReuseSelectorName'] = self::UPLOAD_TO_REUSE_SELECTOR_NAME;
    $renderer = $this->getObject('twig.environment');
    return $renderer->loadTemplate('agent_reuser.js.twig')->render($vars);
  }
  
  public function preInstall()
  {
    menu_insert("ParmAgents::" . $this->title, 0, $this->name);
  }
  
  /**
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
    foreach ($folderUploads as $uploadProgress)
    {
      $key = $uploadProgress->getId().','.$uploadProgress->getGroupId();
      $display = $uploadProgress->getFilename() . _(" from ") . date("Y-m-d H:i",$uploadProgress->getTimestamp()) . ' ('. $uploadProgress->getStatusString() . ')';
      $uploadsById[$key] = $display;
    }
    return $uploadsById;
  }
  
  
  public function scheduleAgent($jobId, $uploadId, &$errorMsg, $request)
  {
    $reuseUploadPair = explode(',', $request->get(self::UPLOAD_TO_REUSE_SELECTOR_NAME), 2);
    if (count($reuseUploadPair) == 2) {
      list($reuseUploadId, $reuseGroupId) = $reuseUploadPair;
    }
    else
    {
      $errorMsg .= 'no reuse upload id given';
      return -1;
    }
    
    $reuseMode = intval($request->get('reuseMode'));
    $this->createPackageLink($uploadId, $reuseUploadId, $reuseGroupId, $reuseMode);
    
    $agent = plugin_find($this->AgentName);
    return $agent->doAgentAdd($jobId, $uploadId, $errorMsg, array("agent_adj2nest"), $uploadId);
  }

  public function AgentHasResults($uploadId)
  {
    $agent = plugin_find($this->AgentName);
    return $agent->AgentHasResults($uploadId);
  }
  
  /**
   * @param int $uploadId
   * @param int $reuseUploadId
   * @param int $reuseGroupId
   * @param int $reuseMode
   * @internal description
   */
  protected function createPackageLink($uploadId, $reuseUploadId, $reuseGroupId, $reuseMode=0)
  {
    /** @var PackageDao */
    $packageDao = $this->getObject('dao.package');
    $newUpload = $this->uploadDao->getUpload($uploadId);
    $uploadForReuse = $this->uploadDao->getUpload($reuseUploadId);

    $package = $packageDao->findPackageForUpload($reuseUploadId);

    if ($package === null)
    {
      $packageName = StringOperation::getCommonHead($uploadForReuse->getFilename(), $newUpload->getFilename());
      $package = $packageDao->createPackage($packageName ?: $uploadForReuse->getFilename());
      $packageDao->addUploadToPackage($reuseUploadId, $package);
    }

    $packageDao->addUploadToPackage($uploadId, $package);

    $this->uploadDao->addReusedUpload($uploadId, $reuseUploadId, Auth::getGroupId(), $reuseGroupId, $reuseMode);
  }

}

register_plugin(new ReuserPlugin());
