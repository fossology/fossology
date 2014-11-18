<?php
/***********************************************************
 * Copyright (C) 2008-2013 Hewlett-Packard Development Company, L.P.
 * Copyright (C) 2014 Siemens AG
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

use Fossology\Lib\Dao\FolderDao;
use Fossology\Lib\Plugin\DefaultPlugin;
use Monolog\Logger;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * \class upload_file extends from FO_Plugin
 * \brief Upload a file from the users computer using the UI.
 */
class UploadFilePage extends DefaultPlugin
{
  const FILE_INPUT_NAME = 'fileInput';

  const NAME = "upload_file";

  /** @var FolderDao */
  private $folderDao;

  /** @var Logger */
  private $logger;

  public function __construct()
  {
    parent::__construct(self::NAME, array(
        self::TITLE => _("Upload a New File"),
        self::MENU_LIST => "Upload::From File",
        self::DEPENDENCIES => array("agent_unpack", "showjobs"),
        self::PERMISSION => self::PERM_WRITE
    ));

    $this->folderDao = $this->getObject('dao.folder');
    $this->logger = $this->getObject('logger');
  }

  /**
   * @brief Process the upload request.
   *
   * @param int $folderId
   * @param UploadedFile $uploadedFile
   * @param string $description
   * @param int $publicPermission
   * @return null|string
   */
  function handleFileUpload($folderId, UploadedFile $uploadedFile, $description, $publicPermission)
  {
    global $MODDIR;
    global $SysConf;
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

    if (@$_SESSION['uploadformbuild'] != @$_REQUEST['uploadformbuild'])
    {
      $UploadFile['error'] = UPLOAD_ERR_RESEND;
      return $upload_errors[$UploadFile['error']];
    }

    $errorMessage = null;
    if ($uploadedFile->getSize() == 0 && $uploadedFile->getError() == 0)
      return array(false, $upload_errors[UPLOAD_ERR_EMPTY]);
    if (empty($folderId))
      return array(false, $upload_errors[UPLOAD_ERR_INVALID_FOLDER_PK]);

    $originalFileName = $uploadedFile->getClientOriginalName();

    /* Create an upload record. */
    $uploadMode = (1 << 3); // code for "it came from web upload"
    $userId = $SysConf['auth']['UserId'];
    $groupId = $SysConf['auth']['GroupId'];
    $uploadId = JobAddUpload($userId, $originalFileName, $originalFileName, $description, $uploadMode, $folderId, $publicPermission);
    if (empty($uploadId))
    {
      return array(false, _("Failed to insert upload record"));
    }

    try
    {
      $uploadedTempFile = $uploadedFile->move($uploadedFile->getPath(), $uploadedFile->getFilename() . '-uploaded')->getPathname();
    } catch (FileException $e)
    {
      return array(false, _("Could not save uploaded file"));
    }

    $wgetAgentCall = "$MODDIR/wget_agent/agent/wget_agent -C -g fossy -k $uploadId '$uploadedTempFile' -c '$SYSCONFDIR'";
    exec($wgetAgentCall, $wgetOutput = array(), $wgetReturnValue);
    unlink($uploadedTempFile);

    $jobId = JobAddJob($userId, $groupId, $originalFileName, $uploadId);
    global $Plugins;
    /** @var agent_adj2nest $adj2nestplugin */
    $adj2nestplugin = &$Plugins[plugin_find_id("agent_adj2nest")];

    $adj2nestplugin->AgentAdd($jobId, $uploadId, $errorMessage, $dependencies = array());
    AgentCheckBoxDo($jobId, $uploadId);

    if ($wgetReturnValue == 0)
    {
      $status = GetRunnableJobList();
      $message = empty($status) ? _("Is the scheduler running? ") : "";
      $jobUrl = Traceback_uri() . "?mod=showjobs&upload=$uploadId";
      $message .= _("The file") . " " . $originalFileName . " " . _("has been uploaded. It is") . ' <a href=' . $jobUrl . '>upload #' . $uploadId . "</a>.\n";
      return array(true, $message);
    } else
    {
      $message = implode(' ', $wgetOutput);
      if (empty($message)) $message = _("File upload failed.  Error:") . $wgetReturnValue;
      return array(false, $message);
    }
  }

  /**
   * @param Request $request
   * @return Response
   */
  protected function handle(Request $request)
  {
    $vars = array();
    $description = null;
    $folderId = intval($request->get('folder'));
    $reuseUploadId = intval($request->get('reuseUpload'));
    $ajaxMethodName = $request->get('do');

    if ($ajaxMethodName == "getUploads")
    {
      $folderUploads = $this->folderDao->getFolderUploads($folderId);

      $content = json_encode($folderUploads);
      return new Response($content, Response::HTTP_OK, array('Content-type' => 'text/json'));
    } else
    {
      if ($request->isMethod('POST'))
      {

        $description = $request->get('description');
        $public = $request->get('public') == true;
        $uploadFile = $request->files->get(self::FILE_INPUT_NAME);

        if ($uploadFile !== null && !empty($folderId))
        {
          list($successful, $vars['message']) = $this->handleFileUpload($folderId, $uploadFile, $description, empty($public) ? PERM_NONE : PERM_READ);
          $description = $successful ? null : $description;
        } else
        {
          $vars['message'] = "Error: no file selected";
        }
      }
    }

    $vars['description'] = $description ?: "";
    $vars['upload_max_filesize'] = ini_get('upload_max_filesize');
    $vars['agentCheckBoxMake'] = '';
    $vars['fileInputName'] = self::FILE_INPUT_NAME;
    $folderStructure = $this->folderDao->getFolderStructure();
    if (empty($folderId))
    {
      $folderId = $folderStructure[0]['folder']->getId();
    }
    $vars['folderStructure'] = $folderStructure;
    $vars['folderUploads'] = $this->folderDao->getFolderUploads($folderId);
    $vars['baseUrl'] = $request->getBaseUrl();
    $vars['moduleName'] = $this->getName();
    if (@$_SESSION['UserLevel'] >= PLUGIN_DB_WRITE)
    {
      $Skip = array("agent_unpack", "agent_adj2nest", "wget_agent");
      $vars['agentCheckBoxMake'] = AgentCheckBoxMake(-1, $Skip);
    }

    return $this->render("upload_file.html.twig", $this->mergeWithDefault($vars));
  }

}

register_plugin(new UploadFilePage());