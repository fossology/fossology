<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace Fossology\UI\Page;
use Symfony\Component\HttpFoundation\Request;
use Fossology\Lib\Auth\Auth;

class UploadUrlPage extends UploadPageBase
{
  const NAME = "upload_url22";
  
  const NAME_PARAM = 'name';
  const ACCEPT_PARAM = 'accept';
  const REJECT_PARAM = 'reject';
  const GETURL_PARAM = 'geturl';
  const LEVEL_PARAM = 'level';
  
  public function __construct()
  {
    parent::__construct(self::NAME, array(
        self::TITLE => _("Upload from an URL"),
        self::MENU_LIST => "Upload::From URL",
        self::DEPENDENCIES => array("agent_unpack", "showjobs"),
        self::PERMISSION => Auth::PERM_WRITE
    ));
  }
  protected function handleUpload(Request $request)
  {
    $folderId = intval($request->get(self::FOLDER_PARAMETER_NAME));
    $description = stripslashes($request->get(self::DESCRIPTION_INPUT_NAME));
    
    $getURL = trim($request->get(self::GETURL_PARAM));

    // url encode to allow spaces in URL
    $getURL = str_replace(" ", "%20", $getURL);

    if (empty($getURL)) 
    {
      return array(false, _("Invalid URL"), $description);
    }
    if (preg_match("@^((http)|(https)|(ftp))://([[:alnum:]]+)@i", $getURL) != 1) 
    {
      return array(false, _("Invalid URL"), $description);
    }

    $Name = $request->get(self::NAME_PARAM);
    if (empty($Name)) $Name = basename($getURL);
    $shortName = basename($Name);
    if (empty($shortName))  $shortName = $Name;

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

    /* Set default values */
    $level = $request->get(self::LEVEL_PARAM);
    if (empty($level) && !is_numeric($level) || $level < 0)
    {
      $level = 1;
    }

    /* first trim, then get rid of whitespaces before and after each comma letter */
    $accept = preg_replace('/\s*,\s*/', ',', trim($request->get(self::ACCEPT_PARAM)));
    $reject = preg_replace('/\s*,\s*/', ',', trim($request->get(self::REJECT_PARAM)));
    
    /* Create the job: job "wget" */
    $jobId = JobAddJob($userId, $groupId, "wget", $uploadId);
    if (empty($jobId) || ($jobId < 0))
      return array(false, _("Failed to insert job record"), $description);

    $jq_args = "$uploadId - $getURL -l $level ";
    if (!empty($accept)) {
      $jq_args .= "-A $accept ";
    }
    if (!empty($reject)) 
    {
      // reject the files index.html*
      $jq_args .= "-R $reject,index.html* ";
    } 
    else // reject the files index.html*
    {
      $jq_args .= "-R index.html* ";
    }

    $jobqueuepk = JobQueueAdd($jobId, "wget_agent", $jq_args, NULL, NULL);
    if (empty($jobqueuepk))
      return array(false, "Failed to insert task 'wget_agent' into job queue", $description);
    
    $message = $this->postUploadAddJobs($request, $shortName, $uploadId, $jobId, true);
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
