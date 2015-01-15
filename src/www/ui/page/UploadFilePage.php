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

namespace Fossology\UI\Page;

use agent_adj2nest;
use Fossology\Lib\Dao\FolderDao;
use Fossology\Lib\Dao\PackageDao;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Data\Upload\Upload;
use Fossology\Lib\Plugin\DefaultPlugin;
use Monolog\Logger;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * \brief Upload a file from the users computer using the UI.
 */
class UploadFilePage extends DefaultPlugin
{
  const FILE_INPUT_NAME = 'fileInput';
  const UPLOAD_TO_REUSE_SELECTOR_NAME = 'uploadToReuse';

  const NAME = "upload_file";
  const FOLDER_PARAMETER_NAME = 'folder';
  const REUSE_FOLDER_SELECTOR_NAME = 'reuseFolderSelectorName';
  const DESCRIPTION_INPUT_NAME = 'descriptionInputName';
  const DESCRIPTION_VALUE = 'descriptionValue';

  /** @var FolderDao */
  private $folderDao;

  /** @var PackageDao */
  private $packageDao;

  /** @var UploadDao */
  private $uploadDao;

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
    $this->uploadDao = $this->getObject('dao.upload');
    $this->packageDao = $this->getObject('dao.package');
    $this->logger = $this->getObject('logger');
  }


  /**
   * @param Request $request
   * @return Response
   */
  protected function handle(Request $request)
  {
    $this->folderDao->ensureTopLevelFolder();

    $vars = array();
    $folderId = intval($request->get(self::FOLDER_PARAMETER_NAME));
    $description = stripslashes($request->get(self::DESCRIPTION_INPUT_NAME));
    $reuseUploadId = intval($request->get(self::UPLOAD_TO_REUSE_SELECTOR_NAME));
    $ajaxMethodName = $request->get('do');

    if ($ajaxMethodName == "getUploads")
    {
      return $this->getUploadsInFolder($folderId);
    } else
    {
      if ($request->isMethod('POST'))
      {
        $public = $request->get('public') == true;
        $uploadFile = $request->files->get(self::FILE_INPUT_NAME);

        if ($uploadFile !== null && !empty($folderId))
        {
          list($successful, $vars['message']) = $this->handleFileUpload($folderId, $uploadFile, $description, empty($public) ? PERM_NONE : PERM_READ, $reuseUploadId);
          $description = $successful ? null : $description;

        } else
        {
          $vars['message'] = "Error: no file selected";
        }
      }
    }

    $vars['descriptionInputValue'] = $description ?: "";
    $vars['descriptionInputName'] = self::DESCRIPTION_INPUT_NAME;
    $vars['folderParameterName'] = self::FOLDER_PARAMETER_NAME;
    $vars['upload_max_filesize'] = ini_get('upload_max_filesize');
    $vars['agentCheckBoxMake'] = '';
    $vars['fileInputName'] = self::FILE_INPUT_NAME;
    $vars['reuseFolderSelectorName'] = self::REUSE_FOLDER_SELECTOR_NAME;
    $vars['uploadToReuseSelectorName'] = self::UPLOAD_TO_REUSE_SELECTOR_NAME;
    global $SysConf;
    $rootFolder = $this->folderDao->getRootFolder($SysConf['auth']['UserId']);
    $folderStructure = $this->folderDao->getFolderStructure($rootFolder->getId());
    if (empty($folderId) && !empty($folderStructure))
    {
      $folderId = $folderStructure[0][FolderDao::FOLDER_KEY]->getId();
    }
    $vars['folderStructure'] = $folderStructure;
    $vars['folderUploads'] = $this->prepareFolderUploads($folderId);
    $vars['baseUrl'] = $request->getBaseUrl();
    $vars['moduleName'] = $this->getName();
    if (@$_SESSION['UserLevel'] >= PLUGIN_DB_WRITE)
    {
      $Skip = array("agent_unpack", "agent_adj2nest", "wget_agent");
      $vars['agentCheckBoxMake'] = AgentCheckBoxMake(-1, $Skip);
    }

    return $this->render("upload_file.html.twig", $this->mergeWithDefault($vars));
  }

  /**
   * @param int $folderId
   * @return Response
   */
  protected function getUploadsInFolder($folderId)
  {
    $uploadsById = $this->prepareFolderUploads($folderId);

    $content = json_encode($uploadsById);
    return new Response($content, Response::HTTP_OK, array('Content-type' => 'text/json'));
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
      $uploadsById[$upload->getId()] = $upload->getFilename() . _(" from ") . $upload->getTimestamp()->format("Y-m-d H:i");
    }
    return $uploadsById;
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
  function handleFileUpload($folderId, UploadedFile $uploadedFile, $description, $publicPermission, $reuseUploadId)
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
    $wgetOutput = array();
    exec($wgetAgentCall, $wgetOutput, $wgetReturnValue);
    unlink($uploadedTempFile);

    $jobId = JobAddJob($userId, $groupId, $originalFileName, $uploadId);
    global $Plugins;
    /** @var agent_adj2nest $adj2nestplugin */
    $adj2nestplugin = &$Plugins['agent_adj2nest'];

    $adj2nestplugin->AgentAdd($jobId, $uploadId, $errorMessage, $dependencies = array());
    AgentCheckBoxDo($jobId, $uploadId);

    if ($wgetReturnValue == 0)
    {
      if ($reuseUploadId > 0)
      {
        $this->createPackageLink($uploadId, $reuseUploadId);
      }

      $this->scheduleReuserAgent($jobId, $uploadId);

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
   * @param int $uploadId
   * @param int $reuseUploadId
   */
  private function createPackageLink($uploadId, $reuseUploadId)
  {
    $newUpload = $this->uploadDao->getUpload($uploadId);
    $uploadForReuse = $this->uploadDao->getUpload($reuseUploadId);

    $package = $this->packageDao->findPackageForUpload($reuseUploadId);

    if ($package === null)
    {
      $packageName = $this->determinePackageName($uploadForReuse->getFilename(), $newUpload->getFilename());

      $package = $this->packageDao->createPackage($packageName);

      $this->packageDao->addUploadToPackage($reuseUploadId, $package);
    }

    $this->packageDao->addUploadToPackage($uploadId, $package);

    $this->uploadDao->addReusedUpload($uploadId, $reuseUploadId);

  }

  private function determinePackageName($firstName, $secondName)
  {
    $name = "";

    $maxNumberOfCharsToCompare = min(strlen($firstName), strlen($secondName));
    for ($i = 0; $i < $maxNumberOfCharsToCompare; $i++)
    {
      $character = substr($firstName, $i, 1);
      $secondCharacter = substr($secondName, $i, 1);
      if ($character === $secondCharacter)
      {
        $name .= $character;
      } else
      {
        break;
      }
    }

    return strlen($name) > 0 ? $name : $firstName;
  }

  /**
   * @param int $jobId
   * @param int $uploadId
   */
  private function scheduleReuserAgent($jobId, $uploadId)
  {
    global $Plugins;
    $reuserAgent = &$Plugins['agent_reuser'];
    if ($reuserAgent)
    {
      $Dependencies = array();
      $ErrorMsg = "Bad thing";
      $reuserAgent->AgentAdd($jobId, $uploadId, $ErrorMsg, $Dependencies);
    }
  }

}

register_plugin(new UploadFilePage());