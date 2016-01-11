<?php
/***********************************************************
 * Copyright (C) 2015 Siemens AG
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

use Fossology\Lib\Auth\Auth;
use Symfony\Component\HttpFoundation\Request;

class UploadUrlPage extends UploadPageBase
{
  const NAME = 'upload_url';
  
  const NAME_PARAM = 'name';
  const ACCEPT_PARAM = 'accept';
  const REJECT_PARAM = 'reject';
  const GETURL_PARAM = 'geturl';
  const LEVEL_PARAM = 'level';
  
  public function __construct()
  {
    parent::__construct(self::NAME, array(
        self::TITLE => _("Upload from URL"),
        self::MENU_LIST => "Upload::From URL",
        self::DEPENDENCIES => array("agent_unpack", "showjobs"),
        self::PERMISSION => Auth::PERM_WRITE
    ));
  }
  
  protected function handleUpload(Request $request)
  {
    $folderId = intval($request->get(self::FOLDER_PARAMETER_NAME));
    $description = stripslashes($request->get(self::DESCRIPTION_INPUT_NAME));
    $description = $this->basicShEscaping($description);
    
    $getUrlThatMightIncludeSpaces = trim($request->get(self::GETURL_PARAM));
    $getURL = str_replace(" ", "%20", $getUrlThatMightIncludeSpaces);

    if (empty($getURL)) 
    {
      return array(false, _("Invalid URL"), $description);
    }
    if (preg_match("@^((http)|(https)|(ftp))://([[:alnum:]]+)@i", $getURL) != 1) 
    {
      return array(false, _("Invalid URL"), $description);
    }
    $getURL = $this->basicShEscaping($getURL);

    $name = $request->get(self::NAME_PARAM);
    if (empty($name)) {
      $name = basename($getURL);
    }
    $shortName = basename($name);
    if (empty($shortName)) {
      $shortName = $name;
    }

    /* Create an upload record. */
    $mode = (1 << 2); // code for "it came from wget"
    $userId = Auth::getUserId();
    $groupId = Auth::getGroupId();
    $public = $request->get('public');
    $publicPermission = ($public == self::PUBLIC_ALL) ? Auth::PERM_READ : Auth::PERM_NONE;

    $uploadId = JobAddUpload($userId, $groupId, $shortName, $getURL, $description, $mode, $folderId, $publicPermission);
    if (empty($uploadId)) {
      $text = _("Failed to insert upload record");
      return array(false, $text, $description);
    }

    $level = intval($request->get(self::LEVEL_PARAM));
    if ($level < 0)
    {
      $level = 1;
    }

    /* first trim, then get rid of whitespaces before and after each comma letter */
    $accept = preg_replace('/\s*,\s*/', ',', trim($request->get(self::ACCEPT_PARAM)));
    $accept = $this->basicShEscaping($accept);
    $reject = preg_replace('/\s*,\s*/', ',', trim($request->get(self::REJECT_PARAM)));
    $reject = $this->basicShEscaping($reject);
    
    /* Create the job: job "wget" */
    $jobId = JobAddJob($userId, $groupId, "wget", $uploadId);
    if (empty($jobId) || ($jobId < 0)) {
      return array(false, _("Failed to insert job record"), $description);
    }

    $jqArgs = "$uploadId - $getURL -l $level ";
    if (!empty($accept)) {
      $jqArgs .= "-A $accept ";
    }
    $jqArgs .= empty($reject) ? "-R index.html* " : "-R $reject,index.html* ";

    $jobqueueId = JobQueueAdd($jobId, "wget_agent", $jqArgs, NULL, NULL);
    if (empty($jobqueueId)) {
      return array(false, "Failed to insert task 'wget_agent' into job queue", $description);
    }
    
    $message = $this->postUploadAddJobs($request, $shortName, $uploadId, $jobId, $jobqueueId);
    return array(true, $message, $description);
  }
  
  protected function handleView(Request $request, $vars)
  {
    $vars['geturlField'] = self::GETURL_PARAM;
    $vars['nameField'] = self::NAME_PARAM;
    $vars['acceptField'] = self::ACCEPT_PARAM;
    $vars['rejectField'] = self::REJECT_PARAM;
    $vars['levelField'] = self::LEVEL_PARAM;
    return $this->render("upload_url.html.twig", $this->mergeWithDefault($vars));
  }
}

register_plugin(new UploadUrlPage());
