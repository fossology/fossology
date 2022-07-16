<?php
/*
 SPDX-FileCopyrightText: © 2019-2020 Siemens AG
 Author: Andreas J. Reichel <andreas.reichel@tngtech.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

include_once "search-helper.php";
require_once dirname(dirname(dirname(__FILE__))) . "/lib/php/common-repo.php";

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Dao\UploadDao;
use Symfony\Component\HttpFoundation\JsonResponse;

class AjaxNoticeFiles extends FO_Plugin
{
  /** @var UploadDao */
  private $uploadDao;

  function __construct()
  {
    $this->Name       = "ajax-notice-files";
    $this->Title      = _("Ajax Notice Files");
    $this->DBaccess   = PLUGIN_DB_READ;
    $this->OutputType = 'JSON';
    $this->OutputToStdout = true;
    parent::__construct();

    $this->uploadDao = $GLOBALS['container']->get('dao.upload');
  }

  function PostInitialize()
  {
    $this->State = PLUGIN_STATE_READY;
    return $this->State;
  }

  function Output()
  {
    global $PG_CONN;
    if ($this->State != PLUGIN_STATE_READY) {
      return;
    }

    $getuploadEntry = $this->uploadDao->getUploadEntry(GetParm("uploadTreeId", PARM_INTEGER));

    // Get the root of the upload tree where this item belongs to
    $parentBounds = $this->uploadDao->getParentItemBounds($getuploadEntry['upload_fk'], null);
    $uploadTreeId = $parentBounds->getItemId();

    // Search the upload tree for all files named NOTICE*
    $Item = $uploadTreeId;
    $Filename = "NOTICE%";
    $uploadId = $getuploadEntry['upload_fk'];
    $tag = "";
    $Page = 0;
    $Limit = 100;
    $SizeMin = "";
    $SizeMax = "";
    $searchtype = "allfiles";
    $License = "";
    $Copyright = "";

    $UploadtreeRecsResult = GetResults($Item, $Filename, $uploadId, $tag, $Page, $Limit, $SizeMin,
      $SizeMax, $searchtype, $License, $Copyright, $this->uploadDao,
      Auth::getGroupId(), $PG_CONN);

    foreach ($UploadtreeRecsResult[0] as $k => $res) {

      $pfilefk = $res['pfile_fk'];
      $repofile = RepPath($pfilefk);

      $UploadtreeRecsResult[0][$k]['repo_file'] = $repofile;

      // Get the contents of the file and attach it to the JSON output
      if (is_readable($repofile)) {
        $f = fopen($repofile, "r");
        if ($f === false) {
          continue;
        }
        $contents = fread($f, filesize($repofile));
        $contents_short = "<textarea style='overflow:auto;width:400px;height:80px;'>" .$contents. "</textarea>";
        $UploadtreeRecsResult[0][$k]['contents'] = $contents;
        $UploadtreeRecsResult[0][$k]['contents_short'] = $contents_short;
        fclose($f);
      }
    }
    return new JsonResponse($UploadtreeRecsResult[0]);
  }
}

$NewPlugin = new AjaxNoticeFiles;
$NewPlugin->Initialize();
