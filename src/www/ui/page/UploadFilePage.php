<?php
/***********************************************************
 * Copyright (C) 2008-2013 Hewlett-Packard Development Company, L.P.
 * Copyright (C) 2014-2015 Siemens AG
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

namespace Fossology\UI\Page;

use agent_adj2nest;
use Fossology\UI\Page\UploadPageBase;
use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Dao\FolderDao;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Plugin\DefaultPlugin;
use Fossology\Lib\UI\MenuHook;
use Monolog\Logger;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * \brief Upload a file from the users computer using the UI.
 */
class UploadFilePage extends UploadPageBase
{
  const FILE_INPUT_NAME = 'fileInput';


  public function __construct()
  {
    parent::__construct(self::NAME, array(
        self::TITLE => _("Upload a New File"),
        self::MENU_LIST => "Upload::From File",
        self::DEPENDENCIES => array("agent_unpack", "showjobs"),
        self::PERMISSION => Auth::PERM_WRITE
    ));
  }


  /**
   * @param Request $request
   * @return Response
   */
  protected function handleView(Request $request, $vars)
  {
    /*$this->folderDao->ensureTopLevelFolder();
    
    $folderId = intval($request->get(self::FOLDER_PARAMETER_NAME));
    $description = stripslashes($request->get(self::DESCRIPTION_INPUT_NAME));

    $message = "";
    if ($request->isMethod(Request::METHOD_POST))
    {
      $uploadFile = $request->files->get(self::FILE_INPUT_NAME);

      if ($uploadFile !== null && !empty($folderId))
      {
        list($successful, $vars['message']) = $this->handleFileUpload($request, $folderId, $uploadFile, $description);
        $description = $successful ? null : $description;
      }
      else
      {
        $message = "Error: no file selected";
      }
    }

    $vars = getBaseVars();
    $vars['message'] = $message;
    $vars['descriptionInputValue'] = $description ?: "";
    $vars['descriptionInputName'] = self::DESCRIPTION_INPUT_NAME;
    $vars['folderParameterName'] = self::FOLDER_PARAMETER_NAME;
    $vars['upload_max_filesize'] = ini_get('upload_max_filesize');
    $vars['agentCheckBoxMake'] = '';
    $vars['fileInputName'] = self::FILE_INPUT_NAME;

    $rootFolder = $this->folderDao->getRootFolder(Auth::getUserId());
    $folderStructure = $this->folderDao->getFolderStructure($rootFolder->getId());
    if (empty($folderId) && !empty($folderStructure))
    {
      $folderId = $folderStructure[0][FolderDao::FOLDER_KEY]->getId();
    }
    $vars['folderStructure'] = $folderStructure;
    $vars['baseUrl'] = $request->getBaseUrl();
    $vars['moduleName'] = $this->getName();
    $vars[self::FOLDER_PARAMETER_NAME] = $request->get(self::FOLDER_PARAMETER_NAME);
    
    $parmAgentList = MenuHook::getAgentPluginNames("ParmAgents");
    $vars['parmAgentContents'] = array();
    $vars['parmAgentFoots'] = array();
    foreach($parmAgentList as $parmAgent) {
      $agent = plugin_find($parmAgent);
      $vars['parmAgentContents'][] = $agent->renderContent($vars);
      $vars['parmAgentFoots'][] = $agent->renderFoot($vars);
    }
    
    $session = $request->getSession();
    $session->set(self::UPLOAD_FORM_BUILD_PARAMETER_NAME, time().':'.$_SERVER['REMOTE_ADDR']);
    $vars['uploadFormBuild'] = $session->get(self::UPLOAD_FORM_BUILD_PARAMETER_NAME);
    $vars['uploadFormBuildParameterName'] = self::UPLOAD_FORM_BUILD_PARAMETER_NAME;

    if (@$_SESSION[Auth::USER_LEVEL] >= PLUGIN_DB_WRITE)
    {
      $skip = array("agent_unpack", "agent_adj2nest", "wget_agent");
      $vars['agentCheckBoxMake'] = AgentCheckBoxMake(-1, $skip);
    }
*/

    $vars['fileInputName'] = self::FILE_INPUT_NAME;
    return $this->render("upload_file.html.twig", $this->mergeWithDefault($vars));
  }

  /**
   * @brief Process the upload request.
   */
  function handleUpload(Request $request)
  {
    global $MODDIR;
    global $SYSCONFDIR;

    define("UPLOAD_ERR_EMPTY", 5);
    define("UPLOAD_ERR_INVALID_FOLDER_PK", 100);
    define("UPLOAD_ERR_RESEND", 200);
    $upload_errors = array(
        UPLOAD_ERR_OK => _("No errors."),
        UPLOAD_ERR_INI_SIZE => _("Larger than upload_max_filesize ") . ini_get('upload_max_filesize'),
        UPLOAD_ERR_FORM_SIZE => _("Larger than form MAX_FILE_SIZE."),
        UPLOAD_ERR_PARTIAL => _("Partial upload."),
        UPLOAD_ERR_NO_FILE => _("No file."),
        UPLOAD_ERR_NO_TMP_DIR => _("No temporary directory."),
        UPLOAD_ERR_CANT_WRITE => _("Can't write to disk."),
        UPLOAD_ERR_EXTENSION => _("File upload stopped by extension."),
        UPLOAD_ERR_EMPTY => _("File is empty or you don't have permission to read the file."),
        UPLOAD_ERR_INVALID_FOLDER_PK => _("Invalid Folder."),
        UPLOAD_ERR_RESEND => _("This seems to be a resent file.")
    );
    
    $folderId = intval($request->get(self::FOLDER_PARAMETER_NAME));
    $description = stripslashes($request->get(self::DESCRIPTION_INPUT_NAME));
    $uploadedFile = $request->files->get(self::FILE_INPUT_NAME);


    if ($request->getSession()->get(self::UPLOAD_FORM_BUILD_PARAMETER_NAME)
        != $request->get(self::UPLOAD_FORM_BUILD_PARAMETER_NAME))
    {
      $UploadFile['error'] = UPLOAD_ERR_RESEND;
      return array(false, $upload_errors[$UploadFile['error']], $description);
    }

    if ($uploadedFile->getSize() == 0 && $uploadedFile->getError() == 0) {
      return array(false, $upload_errors[UPLOAD_ERR_EMPTY], $description);
    } else if ($uploadedFile->getSize() >= UploadedFile::getMaxFilesize()) {
      return array(false, $upload_errors[UPLOAD_ERR_INI_SIZE] . _(" is  really ") . $uploadedFile->getSize() . " bytes.", $description);
    }

    if (empty($folderId)) {
      return array(false, $upload_errors[UPLOAD_ERR_INVALID_FOLDER_PK], $description);
    }

    if(!$uploadedFile->isValid()) {
      return array(false, $uploadedFile->getErrorMessage(), $description);
    }

    $originalFileName = $uploadedFile->getClientOriginalName();

    $public = $request->get('public');
    $publicPermission = ($public == self::PUBLIC_ALL) ? Auth::PERM_READ : Auth::PERM_NONE;

    /* Create an upload record. */
    $uploadMode = (1 << 3); // code for "it came from web upload"
    $userId = Auth::getUserId();
    $groupId = Auth::getGroupId();
    $uploadId = JobAddUpload($userId, $groupId, $originalFileName, $originalFileName, $description, $uploadMode, $folderId, $publicPermission);

    if (empty($uploadId))
    {
      return array(false, _("Failed to insert upload record"), $description);
    }

    try
    {
      $uploadedTempFile = $uploadedFile->move($uploadedFile->getPath(), $uploadedFile->getFilename() . '-uploaded')->getPathname();
    } catch (FileException $e)
    {
      return array(false, _("Could not save uploaded file"), $description);
    }

    $projectGroup = $GLOBALS['SysConf']['DIRECTORIES']['PROJECTGROUP'] ?: 'fossy';
    $wgetAgentCall = "$MODDIR/wget_agent/agent/wget_agent -C -g $projectGroup -k $uploadId '$uploadedTempFile' -c '$SYSCONFDIR'";
    $wgetOutput = array();
    exec($wgetAgentCall, $wgetOutput, $wgetReturnValue);
    unlink($uploadedTempFile);

    if ($wgetReturnValue != 0)
    {
      $message = implode(' ', $wgetOutput);
      if (empty($message))
      {
        $message = _("File upload failed.  Error:") . $wgetReturnValue;
      }
      return array(false, $message, $description);
    }
    
    $message = $this->postUploadAddJobs($request, $originalFileName, $uploadId);
        
    return array(true, $message, $description);
  }

}

register_plugin(new UploadFilePage());
