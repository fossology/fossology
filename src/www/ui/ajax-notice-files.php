<?php
/***********************************************************
 Copyright (C) 2019-2020 Siemens AG
 Author: Andreas J. Reichel <andreas.reichel@tngtech.com>

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
    $SizeMin = "";
    $SizeMax = "";
    $searchtype = "allfiles";
    $License = "";
    $Copyright = "";

    $UploadtreeRecsResult = GetResults($Item, $Filename, $uploadId, $tag, $Page, $SizeMin,
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
