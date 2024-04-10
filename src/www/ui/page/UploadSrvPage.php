<?php
/*
 SPDX-FileCopyrightText: © 2008-2014 Hewlett-Packard Development Company, L.P.
 SPDX-FileCopyrightText: © 2015, 2018 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\UI\Page;

use Fossology\UI\Page\UploadPageBase;
use Fossology\Lib\Auth\Auth;
use Symfony\Component\HttpFoundation\Request;

class UploadSrvPage extends UploadPageBase
{
  const NAME = 'upload_srv_files';
  const NAME_PARAM = 'name';
  const SOURCE_FILES_FIELD = 'sourceFiles';

  public function __construct()
  {
    parent::__construct(self::NAME, array(
        self::TITLE => _("Upload from Server"),
        self::MENU_LIST => "Upload::From Server",
        self::DEPENDENCIES => array("agent_unpack", "showjobs"),
        self::PERMISSION => Auth::PERM_WRITE
    ));
  }

  function check_if_host_is_allowed($host)
  {
    global $SysConf;
    $sysConfig = $SysConf['SYSCONFIG'];
    if (array_key_exists('UploadFromServerAllowedHosts', $sysConfig)) {
      $hostListPre = $sysConfig['UploadFromServerAllowedHosts'];
      $hostList = explode(':', $hostListPre);
    } else {
      $hostList = array("localhost");
    }

    return in_array($host,$hostList);
  }

  /**
   * \brief checks, whether a normalized path starts with an path in the
   * whiteliste
   *
   * \param $path - the path to check
   *
   * \return boolean
   *
   */
  function check_by_whitelist($path)
  {
    global $SysConf;
    $sysConfig = $SysConf['SYSCONFIG'];
    if (array_key_exists('UploadFromServerWhitelist', $sysConfig)) {
      $whitelistPre = $sysConfig['UploadFromServerWhitelist'];
      $whitelist = explode(':', $whitelistPre);
    } else {
      $whitelist = array("/tmp");
    }

    foreach ($whitelist as $item) {
      if (substr($path, 0,strlen($item)) === trim($item)) {
        return true;
      }
    }
    return false;
  }

  /**
   * \brief chck if one file/dir has one permission
   *
   * \param $path - file path
   * \param $server - host name
   * \param $permission - permission x/r/w
   *
   * \return 1: yes; 0: no
   */
  function remote_file_permission($path, $server = 'localhost', $persmission = 'r')
  {
    /**
     * local file
     */
    if ($server === 'localhost' || empty($server)) {
      $temp_path = str_replace('\ ', ' ', $path); // replace '\ ' with ' '
      return @fopen($temp_path, $persmission);
    } else {
      return 1;  // don't do the file permission check if the file is not on the web server
    }
  }

  /**
   * \brief chck if one file/dir exist or not
   *
   * \param $path - file path
   * \param $server - host name
   *
   * \return 1: exist; 0: not
   */
  function remote_file_exists($path, $server = 'localhost')
  {
    /**
     * local file
     */
    if ($server === 'localhost' || empty($server)) {
      $temp_path = str_replace('\ ', ' ', $path); // replace '\ ' with ' '
      return file_exists($temp_path);
    } else {
      return 1;  // don't do the file exist check if the file is not on the web server
    }
  }

  /**
   * @param Request $request
   * @param $vars
   * @return Response
   */
  protected function handleView(Request $request, $vars)
  {
    $vars['sourceFilesField'] = self::SOURCE_FILES_FIELD;
    $vars['nameField'] = self::NAME_PARAM;
    $vars['hostlist'] = HostListOption();
    return $this->render("upload_srv.html.twig", $this->mergeWithDefault($vars));
  }

  /**
   * @brief Process the upload request.
   */
  protected function handleUpload(Request $request)
  {
    global $Plugins;

    define("UPLOAD_ERR_INVALID_FOLDER_PK", 100);
    define("UPLOAD_ERR_RESEND", 200);
    $uploadErrors = array(
        UPLOAD_ERR_INVALID_FOLDER_PK => _("Invalid Folder."),
        UPLOAD_ERR_RESEND => _("This seems to be a resent file.")
    );

    $folderId = intval($request->get(self::FOLDER_PARAMETER_NAME));
    $description = stripslashes($request->get(self::DESCRIPTION_INPUT_NAME));
    $description = $this->basicShEscaping($description);

    if ($request->getSession()->get(self::UPLOAD_FORM_BUILD_PARAMETER_NAME) !=
      $request->get(self::UPLOAD_FORM_BUILD_PARAMETER_NAME)) {
      return array(false, $uploadErrors[UPLOAD_ERR_RESEND], $description);
    }

    if (empty($folderId)) {
      return array(false, $uploadErrors[UPLOAD_ERR_INVALID_FOLDER_PK], $description);
    }

    $setGlobal = ($request->get('globalDecisions')) ? 1 : 0;

    $public = $request->get('public');
    $publicPermission = ($public == self::PUBLIC_ALL) ? Auth::PERM_READ : Auth::PERM_NONE;

    $sourceFiles = trim($request->get(self::SOURCE_FILES_FIELD));
    $sourceFiles = $this->basicShEscaping($sourceFiles);
    $host = $request->get('host') ?: "localhost";
    if (preg_match('/[^a-z.0-9]/i', $host)) {
      $text = _("The given host is not valid.");
      return array(false, $text, $description);
    }
    if (! $this->check_if_host_is_allowed($host)) {
      $text = _("You are not allowed to upload from the chosen host.");
      return array(false, $text,
        $description
      );
    }

    $name = $request->get(self::NAME_PARAM);

    if ((preg_match('/[*?%$]+/', $sourceFiles)) && empty($name)) {
      $text = _(
        "The file path contains a wildchar, you must provide a name for the upload.");
      return array(false, $text, $description);
    }

    if (empty($name)) {
      $name = basename($sourceFiles);
    }
    $shortName = $this->basicShEscaping(basename($name));
    if (empty($shortName)) {
      $shortName = $name;
    }
    if (strcmp($host,"localhost")) {
      $shortName = $host . ':' . $shortName;
    }

    $sourceFiles = $this->normalize_path($sourceFiles,$host);
    $sourceFiles = str_replace('|', '\|', $sourceFiles);
    $sourceFiles = str_replace(' ', '\ ', $sourceFiles);
    $sourceFiles = str_replace("\t", "\\t", $sourceFiles);
    if ($sourceFiles == FALSE) {
      $text = _("failed to normalize/validate given path");
      return array(false, $text, $description);
    }
    if ($this->check_by_whitelist($sourceFiles) === FALSE) {
      $text = _("no suitable prefix found in the whitelist") . ", " .
        _("you are not allowed to upload this file");
      return array(false, $text, $description);
    }
    if (! $this->path_is_pattern($sourceFiles) &&
      ! $this->remote_file_exists($sourceFiles, $host)) {
      $text = _("'$sourceFiles' does not exist.\n");
      return array(false, $text, $description);
    }
    if (! $this->path_is_pattern($sourceFiles) &&
      ! $this->remote_file_permission($sourceFiles, $host, "r")) {
      $text = _("Have no READ permission on '$sourceFiles'.\n");
      return array(false, $text, $description);
    }
    if (! $this->path_is_pattern($sourceFiles) && is_file($sourceFiles) &&
      filesize($sourceFiles) <= 0) {
      $text = _("You can not upload an empty file.\n");
      return array(false, $text, $description);
    }

    /* Create an upload record. */
    $uploadMode = (1 << 3); // code for "it came from web upload"
    $userId = Auth::getUserId();
    $groupId = Auth::getGroupId();
    $uploadId = JobAddUpload($userId, $groupId, $shortName, $sourceFiles,
      $description, $uploadMode, $folderId, $publicPermission, $setGlobal);

    if (empty($uploadId)) {
      $text = _("Failed to insert upload record");
      return array(false, $text, $description);
    }

      /* Prepare the job: job "wget" */
    $jobpk = JobAddJob($userId, $groupId, "wget", $uploadId);
    if (empty($jobpk) || ($jobpk < 0)) {
      $text = _("Failed to insert upload record");
      return array(false, $text, $description);
    }

    $jq_args = "$uploadId - $sourceFiles";

    $jobqueuepk = JobQueueAdd($jobpk, "wget_agent", $jq_args, "no", NULL, $host);
    if (empty($jobqueuepk)) {
      $text = _("Failed to insert task 'wget' into job queue");
      return array(false, $text, $description);
    }

    $ErrorMsg = "";

    /* schedule agents */
    $unpackplugin = &$Plugins[plugin_find_id("agent_unpack")];
    $unpackargs = intval($request->get('scm') == 1) ? '-I' : '';
    $ununpack_jq_pk = $unpackplugin->AgentAdd($jobpk, $uploadId, $ErrorMsg, array("wget_agent"), $unpackargs);
    if ($ununpack_jq_pk < 0) {
      return array(false, $text, _($ErrorMsg));
    }

    $adj2nestplugin = &$Plugins[plugin_find_id("agent_adj2nest")];
    $adj2nest_jq_pk = $adj2nestplugin->AgentAdd($jobpk, $uploadId, $ErrorMsg, array());
    if ($adj2nest_jq_pk < 0) {
      return array(false, $text, _($ErrorMsg));
    }

    $message = $this->postUploadAddJobs($request, $Name, $uploadId, $jobpk);
    return array(true, $message.$keep, $description, $uploadId);
  }
}
register_plugin(new UploadSrvPage());
