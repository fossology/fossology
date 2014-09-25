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


use Fossology\Lib\Dao\CopyrightDao;

define("TITLE_copyrightHistogramProcessPost", _("Private: Browse post"));
class CopyrightHistogramProcessPost  extends FO_Plugin {
    /**
   * @var string
   */
  private $uploadtree_tablename;

    /**
   * @var CopyrightDao
   */

  private  $copyrightDao;

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
    global $container;
    $this->copyrightDao = $container->get('dao.copyright');
  }


  /**
   * \brief Display the loaded menu and plugins.
   */
  function Output()
  {

    if ($this->State != PLUGIN_STATE_READY) {
      return(0);
    }

    $upload = GetParm("upload",PARM_INTEGER);
    $item = GetParm("item",PARM_INTEGER);
    $agent_pk = GetParm("agent",PARM_STRING);
    $type = GetParm("type",PARM_STRING);
    $filter = GetParm("filter",PARM_STRING);
    /* check upload permissions */
    $UploadPerm = GetUploadPerm($upload);
    if ($UploadPerm < PERM_READ)
    {
      $text = _("Permission Denied");
      echo "<h2>$text<h2>";
      return;
    }

    $this->uploadtree_tablename = GetUploadtreeTableName($upload);


    header('Content-type: text/json');
    list($aaData, $iTotalRecords, $iTotalDisplayRecords) = $this->GetTableData($upload, $item, $agent_pk, $type, $filter);
    print(json_encode(array(
            'sEcho' => intval($_GET['sEcho']),
            'aaData' =>$aaData,
            'iTotalRecords' =>$iTotalRecords,
            'iTotalDisplayRecords' => $iTotalDisplayRecords
        )
    )
    );

  }

  /**
   * @param $row
   * @param $Uploadtree_pk
   * @param $Agent_pk
   * @param bool $normalizeString
   * @param string $filter
   * @param $type
   * @return array
   */
  private function fillTableRow( $row,  $Uploadtree_pk, $Agent_pk, $normalizeString=false ,$filter="", $type )
  {
//    $uniqueCount++;  I need to get this from extra queries
//    $totalCount += $row['copyright_count'];
    $output = array();
    $output[] =$row['copyright_count'];
    $link = "<a href='";
    $link .= Traceback_uri();
    $URLargs = "?mod=copyrightlist&agent=$Agent_pk&item=$Uploadtree_pk&hash=" . $row['hash'] . "&type=" . $type;
    if (!empty($filter)) $URLargs .= "&filter=$filter";
    $link .= $URLargs . "'>Show</a>";
    $output[]=$link;


    if($normalizeString) {
      /* strip out characters we don't want to see
       This is a hack until the agent stops writing these chars to the db.
      */
      $S = $row['content'];
      $S = htmlentities($S);
      $S = str_replace("&Acirc;", "", $S); // comes from utf-8 copyright symbol
      $output []= $S;
    }
    else  {

      $output []= htmlentities($row['content']);
    }
    return $output;
  }


  private function GetTableData($upload, $item, $agent_pk, $type, $filter)
  {
    $rows = $this->copyrightDao->getCopyrights($upload,$item,  $this->uploadtree_tablename ,$agent_pk, 0,$type,$filter);
    $aaData=array();
    if(!empty($rows))
    {
      foreach ($rows as $row)
      {
        $aaData [] = $this->fillTableRow($row,  $item, $agent_pk, false, $filter, $type);
      }
    }

//    $output .= "</table>\n";
//    $output .= "<p>\n";
//
//    $output .= "$descriptionUnique: $uniqueCount<br>\n";
//    $output .= "$descriptionTotal: $count";


    $iTotalRecords=8;
    $iTotalDisplayRecords=10;
    return array($aaData, $iTotalRecords, $iTotalDisplayRecords);

  }


};

$NewPlugin = new CopyrightHistogramProcessPost;
$NewPlugin->Initialize();