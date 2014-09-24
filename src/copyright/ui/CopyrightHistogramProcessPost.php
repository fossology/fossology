<?php
/***********************************************************
 * Copyright (C) 2014 Siemens AG
 * Author: J.Najjar
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


define("TITLE_copyrightHistogramProcessPost", _("Private: Browse post"));
class CopyrightHistogramProcessPost  extends FO_Plugin {
  function __construct()
  {
    $this->Name = "copyrightHistogram-processPost";
    $this->Title = TITLE_copyrightHistogramProcessPost;
    $this->Version = "1.0";
    $this->Dependency = array();
    $this->DBaccess = PLUGIN_DB_WRITE;
    $this->NoHTML = 1;
    $this->LoginFlag = 0;
    $this->NoMenu = 0;

    parent::__construct();
  }


  /**
   * \brief Display the loaded menu and plugins.
   */
  function Output()
  {

    if ($this->State != PLUGIN_STATE_READY) {
      return(0);
    }

    $Folder = GetParm("folder",PARM_INTEGER);
    $Upload = GetParm("upload",PARM_INTEGER);
    $Item = GetParm("item",PARM_INTEGER);
    $filter = GetParm("filter",PARM_STRING);

    /* check upload permissions */
    $UploadPerm = GetUploadPerm($Upload);
    if ($UploadPerm < PERM_READ)
    {
      $text = _("Permission Denied");
      echo "<h2>$text<h2>";
      return;
    }

    /* Get uploadtree_tablename */
    $uploadtree_tablename = GetUploadtreeTableName($Upload);
    $this->uploadtree_tablename = $uploadtree_tablename;


    header('Content-type: text/json');
    list($aaData, $iTotalRecords, $iTotalDisplayRecords) = $this->GetTableData($folder , $show);
    print(json_encode(array(
            'sEcho' => intval($_GET['sEcho']),
            'aaData' =>$aaData,
            'iTotalRecords' =>$iTotalRecords,
            'iTotalDisplayRecords' => $iTotalDisplayRecords
        )
    )
    );

  }


};

$NewPlugin = new CopyrightHistogramProcessPost;
$NewPlugin->Initialize();