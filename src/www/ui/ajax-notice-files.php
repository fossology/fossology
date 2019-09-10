<?php
/***********************************************************
 Copyright (C) 2019 Siemens AG
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
include_once "common-repo.php";

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

    $uploadId = GetParm("upload", PARM_INTEGER);

    // Get the root of the upload tree where this item belongs to
    $parentBounds = $this->uploadDao->getParentItemBounds($uploadId, null);
    $uploadTreeId = $parentBounds->getItemId();

    // Search the upload tree for all files named NOTICE*
    $Item = $uploadTreeId;
    $Filename = "NOTICE%";
    $tag = "";
    $Page = 0;
    $SizeMin = "";
    $SizeMax = "";
    $searchtype = "allfiles";
    $License = "";
    $Copyright = "";

    $UploadtreeRecsResult = GetResults($Item, $Filename, $tag, $Page, $SizeMin,
      $SizeMax, $searchtype, $License, $Copyright, $this->uploadDao,
      Auth::getGroupId(), $PG_CONN);

    foreach ($UploadtreeRecsResult[0] as $k => $res) {

      // Get the real file name using RepPath from common-repo.php
      $ufilename = $res['ufile_name'];
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
        $UploadtreeRecsResult[0][$k]['contents'] = $contents;
        fclose($f);
      }
    }
    return new JsonResponse($UploadtreeRecsResult[0]);
  }
}

$NewPlugin = new AjaxNoticeFiles;
$NewPlugin->Initialize();
