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
use Fossology\Lib\Data\Upload\Upload;
use Fossology\Lib\Plugin\DefaultPlugin;
use Fossology\Lib\Util\StringOperation;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

include_once(__DIR__ . "/../agent/version.php");

class ReuserAgentPlugin extends DefaultPlugin
{
  const NAME = "agent_reuser";

  const REUSE_FOLDER_SELECTOR_NAME = 'reuseFolderSelectorName';
  const UPLOAD_TO_REUSE_SELECTOR_NAME = 'uploadToReuse';
  const FOLDER_PARAMETER_NAME = 'folder';

  public $AgentName = "reuser";
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
    $folderId = intval($request->get(self::FOLDER_PARAMETER_NAME));
    $ajaxMethodName = $request->get('do');

    if ($ajaxMethodName == "getUploads")
    {
      $uploadsById = $this->prepareFolderUploads($folderId);
      return new JsonResponse($uploadsById, JsonResponse::HTTP_OK);
    }
    
    return new Response('called without valid method', Response::HTTP_METHOD_NOT_ALLOWED);
  }
  
  
  /**
   * @param Request $request
   * @return string
   */
  public function renderContent(Request $request, &$vars)
  {
    $folderId = intval($request->get(self::FOLDER_PARAMETER_NAME));
    if (empty($folderId) && !empty($vars['folderStructure']))
    {
      $folderId = $vars['folderStructure'][0][FolderDao::FOLDER_KEY]->getId();
    }
    
    $vars['reuseFolderSelectorName'] = self::REUSE_FOLDER_SELECTOR_NAME;
    $vars['uploadToReuseSelectorName'] = self::UPLOAD_TO_REUSE_SELECTOR_NAME;
    $vars['folderUploads'] = $this->prepareFolderUploads($folderId);
    
    $renderer = $GLOBALS['container']->get('twig.environment');
    return $renderer->loadTemplate('agent_reuser.html.twig')->render($vars);
  }
  
  /**
   * @param Request $request
   * @return string
   */
  public function renderFoot(Request $request, &$vars)
  {
    $renderer = $GLOBALS['container']->get('twig.environment');
    $script = $renderer->loadTemplate('agent_reuser.js.twig')->render($vars);
    return "<script>$script</script>";
  }
  
  public function preInstall()
  {
    menu_insert("ParmAgents::" . $this->title, 0, $this->name);
  }
  
  /**
   * @param int $folderId
   * @return Upload[]
   */
  protected function prepareFolderUploads($folderId)
  {
    $folderUploads = $this->folderDao->getFolderUploads($folderId);

    $uploadsById = array();
    foreach ($folderUploads as $upload)
    {
      $uploadsById[$upload->getId()] = $upload->getFilename() . _(" from ") . date("Y-m-d H:i",$upload->getTimestamp());
    }
    return $uploadsById;
  }
  
  
  public function scheduleAgent($jobId, $uploadId, &$errorMsg, $request)
  {
    $reuseUploadId = intval($request->get(self::UPLOAD_TO_REUSE_SELECTOR_NAME));
    if ($reuseUploadId == 0)
    {
      $errorMsg .= 'no reuse upload id given';
      return -1;
    }
    
    $this->createPackageLink($uploadId, $reuseUploadId);

    $jobQueueId = \IsAlreadyScheduled($jobId, $this->AgentName, $uploadId);
    if ($jobQueueId != 0)
    {
      return $jobQueueId;
    }

    $dependencies = array("agent_adj2nest");
    return $this->doAgentAdd($jobId, $uploadId, $errorMsg, $dependencies, $uploadId);
  }

  /**
   * @param int $jobId
   * @param int $uploadId
   * @param &string $errorMsg - error message on failure
   * @param array $dependencies
   * @param string|null $jqargs (optional) jobqueue.jq_args
   * @return
   * * jqId  Successfully queued
   * *   0   Not queued, latest version of agent has previously run successfully
   * *  -1   Not queued, error, error string in $ErrorMsg
   **/
  protected function doAgentAdd($jobId, $uploadId, &$errorMsg, $dependencies, $jqargs = "")
  {
    $deps = array();
    foreach ($dependencies as $dependency)
    {
      $dep = $this->implicitAgentAdd($jobId, $uploadId, $errorMsg, $dependency);
      if ($dep == -1)
      {
        return -1;
      }
      $deps[] = $dep;
    }

    if (empty($jqargs))
    {
      $jqargs = $uploadId;
    }
    $jobQueueId = \JobQueueAdd($jobId, $this->AgentName, $jqargs, "", $deps, NULL);
    if (empty($jobQueueId))
    {
      $errorMsg = "Failed to insert agent $this->AgentName into job queue. jqargs: $jqargs";
      return -1;
    }
    $success = \fo_communicate_with_scheduler("database", $output, $errorMsg);
    if (!$success)
    {
      $errorMsg .= "\n" . $output;
    }

    return $jobQueueId;
  }

  /**
   * @param int $jobId
   * @param int $uploadId
   * @param &string $errorMsg
   * @param string $pluginName
   * @return int
   */
  protected function implicitAgentAdd($jobId, $uploadId, &$errorMsg, $pluginName)
  {
    $depPlugin = &\plugin_find($pluginName);
    if (!$depPlugin)
    {
      $errorMsg = "Invalid plugin name: $pluginName";
      return -1;
    }
    return $depPlugin->AgentAdd($jobId, $uploadId, $errorMsg, array());
  }


  
  /**
   * @param int $uploadId
   * @param int $reuseUploadId
   * @internal description
   */
  protected function createPackageLink($uploadId, $reuseUploadId)
  {
    /** @var PackageDao */
    $packageDao = $GLOBALS['container']->get('dao.package');
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

    $this->uploadDao->addReusedUpload($uploadId, $reuseUploadId);
  }

}

register_plugin(new ReuserAgentPlugin());
