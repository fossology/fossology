<?php
/***********************************************************
 Copyright (C) 2013 Hewlett-Packard Development Company, L.P.
 Copyright (C) 2015 Siemens AG

 This program is free software; you can redistribute it and/or
 modify it under the terms of the GNU General Public License
 version 2 as published by the Free Software Foundation.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License along
 with this program; if not, write to the Free Software Foundation, Inc.,
 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
***********************************************************/

namespace Fossology\UI\Page;

use Fossology\UI\Page\UploadPageBase;
use Fossology\Lib\Auth\Auth;
use Symfony\Component\HttpFoundation\Request;

/**
 * \brief Upload from some Version Conntrol System using the UI.
 */
class UploadVcsPage extends UploadPageBase
{
  const NAME = "upload_vcs";
  const GETURL_PARAM = 'geturl';

  public function __construct()
  {
    parent::__construct(self::NAME, array(
        self::TITLE => _("Upload from Version Control System"),
        self::MENU_LIST => "Upload::From Version Control System",
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
    $vars['vcstypeField'] = 'vcstype';
    $vars['usernameField'] = 'username';
    $vars['passwdField'] = 'passwd';
    $vars['geturlField'] = self::GETURL_PARAM;
    $vars['nameField'] = 'name';
    $this->renderer->clearTemplateCache();
    $this->renderer->clearCacheFiles();
    return $this->render("upload_vcs.html.twig", $this->mergeWithDefault($vars));
  }

  /**
   * @brief Process the upload request.
   */
  protected function handleUpload(Request $request)
  {
    global $MODDIR;
    global $SYSCONFDIR;
    global $Plugins;
    
    $folderId = intval($request->get(self::FOLDER_PARAMETER_NAME));
    $description = stripslashes($request->get(self::DESCRIPTION_INPUT_NAME));
    $description = $this->basicShEscaping($description);

    $getUrlThatMightIncludeSpaces = trim($request->get(self::GETURL_PARAM));
    $getUrl = str_replace(" ", "%20", $getUrlThatMightIncludeSpaces);

    if (empty($getUrl)) 
    {
      return array(false, _("Empty URL") . $getUrl, $description);
    }
    if (preg_match("@^((http)|(https))://([[:alnum:]]+)@i", $getUrl) != 1) 
    {
      return array(false, _("Invalid URL") . $getUrl, $description);
    }
    $getUrl = $this->basicShEscaping($getUrl);

    if ($request->getSession()->get(self::UPLOAD_FORM_BUILD_PARAMETER_NAME)
        != $request->get(self::UPLOAD_FORM_BUILD_PARAMETER_NAME))
    {
      $text = _("This seems to be a resent file.");
      return array(false, $text, $description);
    }

    if (empty($folderId)) {
      $text = _("Invalid Folder.");
      return array(false, $text, $description);
    }

    $public = $request->get('public');
    $publicPermission = ($public == self::PUBLIC_ALL) ? Auth::PERM_READ : Auth::PERM_NONE;

    $Name = trim($request->get('name'));
    if (empty($Name))
    {
      $Name = basename($getUrl);
    }
    $ShortName = basename($Name);
    if (empty($ShortName))
    {
      $ShortName = $Name;
    }

    /* Create an upload record. */
    $uploadMode = (1 << 2); // code for "it came from wget"
    $userId = Auth::getUserId();
    $groupId = Auth::getGroupId();
    $uploadId = JobAddUpload($userId, $groupId, $ShortName, $getUrl, $description, $uploadMode, $folderId, $publicPermission);
    if (empty($uploadId))
    {
      $text = _("Failed to insert upload record");
      return array(false, $text, $description);
    }

    /* Create the job: job "wget" */
    $jobpk = JobAddJob($userId, $groupId, "wget", $uploadId);
    if (empty($jobpk) || ($jobpk < 0))
    {
      $text = _("Failed to insert job record");
      return array(false, $text, $description);
    }

    $VCSType = trim($request->get('vcstype'));
    $VCSType = $this->basicShEscaping($VCSType);
    $jq_args = "$uploadId - $getUrl $VCSType ";

    $Username = trim($request->get('username'));
    $Username = $this->basicShEscaping($Username);
    if (!empty($Username))
    {
      $jq_args .= "--username $Username ";
    }

    $Passwd = trim($request->get('passwd'));
    $Passwd = $this->basicShEscaping($Passwd);
    if (!empty($Passwd)) 
    {
      $jq_args .= "--password $Passwd";
    } 

    $jobqueuepk = JobQueueAdd($jobpk, "wget_agent", $jq_args, NULL, NULL);
    if (empty($jobqueuepk))
    {
      $text = _("Failed to insert task 'wget_agent' into job queue");
      return array(false, $text, $description);
    }
    /* schedule agents */
    $unpackplugin = &$Plugins[plugin_find_id("agent_unpack") ];
    $ununpack_jq_pk = $unpackplugin->AgentAdd($jobpk, $uploadId, $ErrorMsg, array("wget_agent"));
    if ($ununpack_jq_pk < 0)
    {
      return array(false, _($ErrorMsg), $description);
    }

    $adj2nestplugin = &$Plugins[plugin_find_id("agent_adj2nest") ];
    $adj2nest_jq_pk = $adj2nestplugin->AgentAdd($jobpk, $uploadId, $ErrorMsg, array());
    if ($adj2nest_jq_pk < 0)
    {
      return array(false, _($ErrorMsg), $description);
    }

    AgentCheckBoxDo($jobpk, $uploadId);

    $msg = "";
    /** check if the scheudler is running */
    $status = GetRunnableJobList();
    if (empty($status))
    {
      $msg .= _("Is the scheduler running? ");
    }
    $Url = Traceback_uri() . "?mod=showjobs&upload=$uploadId";
    $text = _("The upload");
    $text1 = _("has been queued. It is");
    $msg .= "$text $Name $text1 ";
    $keep =  "<a href='$Url'>upload #" . $uploadId . "</a>.\n";
    return array(true, $msg.$keep, $description);
  }
}
register_plugin(new UploadVcsPage());