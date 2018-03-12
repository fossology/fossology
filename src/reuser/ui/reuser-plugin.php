<?php
/***********************************************************
 * Copyright (C) 2014-2018, Siemens AG
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

namespace Fossology\Reuser;

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Dao\FolderDao;
use Fossology\Lib\Plugin\DefaultPlugin;
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

  public function __construct()
  {
    parent::__construct(self::NAME, array(
        self::TITLE => _("Automatic Clearing Decision Reuser"),
        self::PERMISSION => Auth::PERM_WRITE
    ));
    
    $this->folderDao = $this->getObject('dao.folder');
  }

  function getAllUploads()
  {
    $allFolder = $this->folderDao->getAllFolderIds();
    $result = array();
    for($i=0; $i < sizeof($allFolder); $i++)
    {
      $listObject = $this->prepareFolderUploads($allFolder[$i]);
      foreach ($listObject as $key => $value)
      {
        $result[explode(",",$key)[0]] = $value;
      }
    }
    return $result;
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
      $uploadsById = "";
      if(empty($folderId) || empty($trustGroupId))
      {
        $uploadsById = $this->getAllUploads();
      }
      else
      {
        $uploadsById = $this->prepareFolderUploads($folderId, $trustGroupId);
      }
      return new JsonResponse($uploadsById, JsonResponse::HTTP_OK);
    }
    
    return new Response('called without valid method', Response::HTTP_METHOD_NOT_ALLOWED);
  }

  public function getFolderIdAndTrustGroup($folderGroup)
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
    if ($this->folderDao->isWithoutReusableFolders($vars['folderStructure']))
    {
      return '';
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
}

register_plugin(new ReuserPlugin());
