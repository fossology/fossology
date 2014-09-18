<?php
/***********************************************************
 * Copyright (C) 2010-2014 Hewlett-Packard Development Company, L.P.
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

define("TITLE_copyrightHistogram", _("Copyright/Email/URL Browser NEW"));

class CopyrightHistogram  extends FO_Plugin {


  private $uploadtree_tablename;

  function __construct()
  {
    $this->Name = "copyrighthistogram";
    $this->Title = TITLE_copyrightHistogram;
    $this->Version = "1.0";
    $this->Dependency = array();
    $this->DBaccess = PLUGIN_DB_READ;
    $this->LoginFlag = 0;
    $this->NoMenu = 0;

    parent::__construct();

  }

  /**
   * \brief Combine copyright holders by name  \n
   * Input records contain: content and type \n
   * Output records: copyright_count, content, type, hash \n
   * where content has been simplified from
   * the raw records and hash is the md5 of this
   * new content.
   * \return If $hash non zero, only rows with that hash will
   * be returned.
   */
  function GroupHolders(&$rows, $hash)
  {
    /* Step 1: Clean up content, and add hash
     */
    $NumRows = count($rows);
    for($RowIdx = 0; $RowIdx < $NumRows; $RowIdx++)
    {
      if (MassageContent($rows[$RowIdx], $hash))
        unset($rows[$RowIdx]);
      /* debug to compare original with new content
       else
      {
      echo "<br>row $RowIdx: ".htmlentities($rows[$RowIdx]['content']) . "<br>";
      echo "row $RowIdx: ".htmlentities($rows[$RowIdx]['original']) . "<br>";
      }
      */
    }

    /* Step 2: sort the array by the new content */
    usort($rows, 'hist_rowcmp');

    /* Step 3: group content (remove dups, add counts) */
    $NumRows = count($rows);
    for($RowIdx = 1; $RowIdx < $NumRows; $RowIdx++)
    {
      if ($rows[$RowIdx]['content'] == $rows[$RowIdx-1]['content'])
      {
        $rows[$RowIdx]['copyright_count'] = $rows[$RowIdx-1]['copyright_count'] + 1;
        unset($rows[$RowIdx-1]);
      }
    }

    /** sorting */
    $ordercount = '-1';
    $ordercopyright = '-1';

    if (isset($_GET['orderc'])) $ordercount = $_GET['orderc'];
    if (isset($_GET['ordercp'])) $ordercopyright = $_GET['ordercp'];
    // sort by count
    if (1 == $ordercount) usort($rows, 'hist_rowcmp_count_desc');
    else if (0 == $ordercount) usort($rows, 'hist_rowcmp_count_asc');
    // sort by copyrigyht statement
    else if (1 == $ordercopyright) usort($rows, 'hist_rowcmp_desc');
    else if (0 == $ordercopyright) usort($rows, 'hist_rowcmp');
    else usort($rows, 'hist_rowcmp_count_desc'); // default as sorting by count desc

    /* note $rows indexes may not be contiguous due to unset in step 3 */
    return $rows;
  }  /* End GroupHolders() */


  /**
   * /return rows to process, and $upload_pk
   * If there are too many rows (see $MaxTreeRecs)
   *  then a text error message is returned, not an array.
   * If the optional $hash is supplied, only rows
   * with that hash will be returned.
   * @param $upload_pk
   * @param $Uploadtree_pk
   * @param $Agent_pk
   * @param int $hash
   * @param $filter
   * @return array|string
   */
  function GetRows(&$upload_pk, $Uploadtree_pk, $Agent_pk, $hash = 0, $filter)
  {
    global $PG_CONN;

    /*******  Get license names and counts  ******/
    /* Find lft and rgt bounds for this $Uploadtree_pk  */
    $sql = "SELECT lft,rgt,upload_fk FROM $this->uploadtree_tablename
              WHERE uploadtree_pk = $Uploadtree_pk";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $row = pg_fetch_assoc($result);
    $lft = $row["lft"];
    $rgt = $row["rgt"];
    $upload_pk = $row["upload_fk"];
    pg_free_result($result);

    /* Check for too many uploadtree rows to process.
     * This is arbitrarily set to 100000.  The copyright display
     * isn't very useful with more records and this check
     * give the user immediate feedback, as opposed to
     * waiting on a very long query.
     * $MaxTreeRecs / 2 = number of uploadtree entries
     */
    $MaxTreeRecs = 200000;
    if (($rgt - $lft) > $MaxTreeRecs)
    {
      $text = _("Too many rows to display");
      return "<h2>$text</h2>";
    }

    $sql = "";
    $sql_upload = "";
    if ('uploadtree_a' == $this->uploadtree_tablename) {
      $sql_upload = "upload_fk=$upload_pk and ";
    }
    if ($filter == "nolics")
    {
      /* find rf_pk for "No_license_found" or "Void" */
      $rf_clause = "";
      $NoLicStr = "No_license_found";
      $VoidLicStr = "Void";
      $sql_lr = "select rf_pk from license_ref where rf_shortname IN ('$NoLicStr', '$VoidLicStr')";
      $result = pg_query($PG_CONN, $sql_lr);
      DBCheckResult($result, $sql_lr, __FILE__, __LINE__);
      if (pg_num_rows($result) > 0)
      {
        $rows = pg_fetch_all($result);
        pg_free_result($result);
        foreach($rows as $row)
        {
          if (!empty($rf_clause)) $rf_clause .= " or ";
          $rf_clause .= " (rf_fk=$row[rf_pk])";
        }

        /* select copyright records that have No_license_found */
        $sql = "SELECT substring(content from 1 for 150) as content, type from copyright, license_file,
                (SELECT distinct(pfile_fk) as pf from $this->uploadtree_tablename
                  where $sql_upload $this->uploadtree_tablename.lft BETWEEN $lft and $rgt) as SS
               where copyright.pfile_fk=license_file.pfile_fk and ($rf_clause)
                     and copyright.pfile_fk=pf and copyright.agent_fk=$Agent_pk";
      }
    }

    if (empty($sql))
    {
      /* get all the copyright records for this uploadtree.  */
      $sql = "SELECT substring(content from 1 for 150) as content, type from copyright,
              (SELECT distinct(pfile_fk) as PF from $this->uploadtree_tablename
                 where $sql_upload $this->uploadtree_tablename.lft BETWEEN $lft and $rgt) as SS
              where PF=pfile_fk and agent_fk=$Agent_pk";
    }
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);

    if (pg_num_rows($result) == 0)
    {
      $text = _("No results to display.");
      return "<h2>$text</h2>";
    }

    $rows = pg_fetch_all($result);
    pg_free_result($result);

    /* Combine results to attempt to group copyright holders */
    $rows = $this->GroupHolders($rows, $hash);

    return $rows;
  }

//  /**
//   * \brief Customize submenus.
//   */
//  function RegisterMenus()
//  {
//    // For all other menus, permit coming back here.
//    $URI = $this->Name . Traceback_parm_keep(array("show","format","page","upload","item"));
//    $Item = GetParm("item",PARM_INTEGER);
//    $Upload = GetParm("upload",PARM_INTEGER);
//    if (!empty($Item) && !empty($Upload))
//    {
//      if (GetParm("mod",PARM_STRING) == $this->Name)
//      {
//        menu_insert("Browse::Copyright/Email/URL NEW",1);
//        menu_insert("Browse::[BREAK]",100);
//      }
//      else
//      {
//        $text = _("View copyright/email/url histogram");
//        menu_insert("Browse::Copyright/Email/URL NEW",10,$URI,$text);
//      }
//    }
//  } // RegisterMenus()

//  //TODO
//  private function ShowFolderCreateHistogramTable($Folder, $Show)
//  {
//    $tableColumns = '[
//      { "sTitle" : "'._("Upload Name and Description").'", "sClass": "left" },
//      { "sTitle" : "'._("Status").'", "sClass": "center" , "bSearchable": false},
//      { "sTitle" : "'._("Reject-job").'", "sClass": "center", "bSortable": false, "bSearchable": false,
//                      "mRender": function ( source, type, val ) { return rejectorColumn( source, type, val );}
//                  },
//      { "sTitle" : "'._("Assigned to").'", "sClass": "center" , "bSearchable": false},
//      { "sTitle" : "'._("Upload Date").'", "sClass": "center" , "sType": "string", "bSearchable": false},
//      { "sTitle" : "'._("Priority").'", "sClass": "center priobucket", "bSearchable": false,
//                      "mRender": function ( source, type, val ) { return prioColumn( source, type, val ); }
//                  }
//    ]';
//
//    $tableSorting = json_encode($this->returnSortOrder());
////    $tableLanguage = '[
////        { "sInfo" : "Showing _START_ to _END_ of _TOTAL_ files" },
////        { "sSearch" : "Search _INPUT_ in filenames" },
////        { "sLengthMenu" : "Display <select><option value=\"10\">10</option><option value=\"25\">25</option><option value=\"50\">50</option><option value=\"100\">100</option></select> files" }
////    ]';
//
//
//    $dataTableConfig =
//        '{  "bServerSide": true,
//            "sAjaxSource": "?mod=browse-processPost",
//            "fnServerData": function ( sSource, aoData, fnCallback ) {
//                 aoData.push( { "name":"folder" , "value" : "'.$Folder.'" } );
//            aoData.push( { "name":"show" , "value" : "'.$Show.'" } );
//            aoData.push( { "name":"assigneeSelected" , "value" : assigneeSelected } );
//            aoData.push( { "name":"statusSelected" , "value" : statusSelected } );
//            $.getJSON( sSource, aoData, fnCallback ).fail( function() {
//              if (confirm("You are not logged in. Go to login page?"))
//                window.location.href="'.  Traceback_uri(). '?mod=auth";
//            });
//          },
//      "aoColumns": '.$tableColumns.',
//      "aaSorting": '.$tableSorting.',
//      "iDisplayLength": 50,
//      "bProcessing": true,
//      "bStateSave": true,
//      "bRetrieve": true
//    }';
//
//
//    $VF   = "<script>
//              function createBrowseTable() {
//                    var otable = $('#browsetbl').dataTable(". $dataTableConfig . ");
//                    // var settings = otable.fnSettings(); // for debugging
//                    return otable;
//                }
//            </script>";
//
//    return $VF;
//
//  }



private  function getCopyrightData($Uploadtree_pk, $Uri, $filter, $uploadtree_tablename, $Agent_pk) {

}




  /**
   * @param $Uploadtree_pk
   * @param $filter
   * @param $Agent_pk
   * @param $ordercount
   * @param $ordercopyright
   * @param $rows
   * @return array
   */
  private function getTableContent($Uploadtree_pk, $filter, $Agent_pk)
  {

    $rows = $this->GetRows($upload_pk, $Uploadtree_pk, $Agent_pk, 0, $filter);
    if (!is_array($rows)) return array($rows, "","","");
    $errorMessage="";

    list($ordercount, $ordercopyright) = $this->getOrderings();

    $CopyrightCount = 0;
    $UniqueCopyrightCount = 0;

    $VCopyright = "<table border=1 width='100%' id='copyright'>\n";
    $text = _("Count");
    $text1 = _("Files");
    $text2 = _("Copyright Statements");
    $text3 = _("Email");
    $text4 = _("URL");
    $VCopyright .= "<tr><th>";
    $VCopyright .= "<a href=?mod=" . "$this->Name" . Traceback_parm_keep(array("upload", "item", "filter", "agent")) . "&orderBy=count&orderc=$ordercount>$text</a>";
    $VCopyright .= "</th>";
    $VCopyright .= "<th width='10%'>$text1</th>";
    $VCopyright .= "<th>";
    $VCopyright .= "<a href=?mod=" . "$this->Name" . Traceback_parm_keep(array("upload", "item", "filter", "agent")) . "&orderBy=copyright&ordercp=$ordercopyright>$text2</a>";
    $VCopyright .= "</th>";
    $VCopyright .= "</th></tr>\n";

    $EmailCount = 0;
    $UniqueEmailCount = 0;

    $VEmail = "<table border=1 width='100%'id='copyrightemail'>\n";
    $VEmail .= "<tr><th width='10%'>$text</th>";
    $VEmail .= "<th width='10%'>$text1</th>";
    $VEmail .= "<th>$text3</th></tr>\n";

    $UrlCount = 0;
    $UniqueUrlCount = 0;

    $VUrl = "<table border=1 width='100%' id='copyrighturl'>\n";
    $VUrl .= "<tr><th width='10%'>$text</th>";
    $VUrl .= "<th width='10%'>$text1</th>";
    $VUrl .= "<th>$text4</th></tr>\n";

    if (!is_array($rows))
      $VCopyright .= "<tr><td colspan=3>$rows</td></tr>";
    else
      foreach ($rows as $row)
      {
        $hash = $row['hash'];
        if ($row['type'] == 'statement')
        {
          $VCopyright .= $this->fillTableRow($row, $UniqueCopyrightCount, $CopyrightCount, $Uploadtree_pk, $Agent_pk, $hash, true ,$filter);
        } else if ($row['type'] == 'email')
        {
          $VEmail .= $this->fillTableRow($row, $UniqueEmailCount, $EmailCount, $Uploadtree_pk, $Agent_pk, $hash);
        } else if ($row['type'] == 'url')
        {
          $VUrl .= $this->fillTableRow($row, $UniqueUrlCount, $UrlCount, $Uploadtree_pk, $Agent_pk, $hash);
        }
      }

    $VCopyright .= "</table>\n";
    $VCopyright .= "<p>\n";
    $text = _("Unique Copyrights");
    $text1 = _("Total Copyrights");
    $VCopyright .= "$text: $UniqueCopyrightCount<br>\n";
    $NetCopyright = $CopyrightCount;
    $VCopyright .= "$text1: $NetCopyright";

    $VEmail .= "</table>\n";
    $VEmail .= "<p>\n";
    $text = _("Unique Emails");
    $text1 = _("Total Emails");
    $VEmail .= "$text: $UniqueEmailCount<br>\n";
    $NetEmail = $EmailCount;
    $VEmail .= "$text1: $NetEmail";

    $VUrl .= "</table>\n";
    $VUrl .= "<p>\n";
    $text = _("Unique URLs");
    $text1 = _("Total URLs");
    $VUrl .= "$text: $UniqueUrlCount<br>\n";
    $NetUrl = $UrlCount;
    $VUrl .= "$text1: $NetUrl";
    return array($errorMessage, $VCopyright, $VEmail, $VUrl);
  }



  /**
   * \brief Given an $Uploadtree_pk, display: \n
   * (1) The histogram for the directory BY LICENSE. \n
   * (2) The file listing for the directory.
   *
   * \param $Uploadtree_pk
   * \param $Uri
   * \param $filter
   * \param $uploadtree_tablename
   * \param $Agent_pk - agent id
   */
  function ShowUploadHist($Uploadtree_pk, $Uri, $filter, $uploadtree_tablename, $Agent_pk)
  {
    $V=""; // total return value
    $upload_pk = "";

    list($ChildCount, $VF) = $this->getFileListing($Uploadtree_pk, $Uri, $uploadtree_tablename, $Agent_pk, $upload_pk);
    /***************************************
    Problem: $ChildCount can be zero!
    This happens if you have a container that does not
    unpack to a directory.  For example:
    file.gz extracts to archive.txt that contains a license.
    Same problem seen with .pdf and .Z files.
    Solution: if $ChildCount == 0, then just view the license!
    $ChildCount can also be zero if the directory is empty.
     ***************************************/
    if ($ChildCount == 0)
    {
      $isADirectory = $this->isADirectory($Uploadtree_pk);
      if ($isADirectory) {
        return;
      }
      global $Plugins;
      $ModLicView = &$Plugins[plugin_find_id("copyrightview")];
      return($ModLicView->Output() );
    }

    list($errorMessage, $VCopyright, $VEmail, $VUrl) = $this->getTableContent($Uploadtree_pk, $filter, $Agent_pk);
    if(!empty($errorMessage)) return $errorMessage;

    /* Combine VF and VLic */
    $text = _("Jump to");
    $text1 = _("Emails");
    $text2 = _("Copyright Statements");
    $text3 = _("URLs");
    $V .= "<table border=0 width='100%'>\n";
    $V .= "<tr><td><a name=\"statements\"></a>$text: <a href=\"#emails\">$text1</a> | <a href=\"#urls\">$text3</a></td><td></td></tr>\n";
    $V .= "<tr><td valign='top' width='50%'>$VCopyright</td><td valign='top'>$VF</td></tr>\n";
    $V .= "<tr><td><a name=\"emails\"></a>Jump to: <a href=\"#statements\">$text2</a> | <a href=\"#urls\">$text3</a></td><td></td></tr>\n";
    $V .= "<tr><td valign='top' width='50%'>$VEmail</td><td valign='top'></td></tr>\n";
    $V .= "<tr><td><a name=\"urls\"></a>Jump To: <a href=\"#statements\">$text2</a> | <a href=\"#emails\">$text1</a></td><td></td></tr>\n";
    $V .= "<tr><td valign='top' width='50%'>$VUrl</td><td valign='top'></td></tr>\n";
    $V .= "</table>\n";
    $V .= "<hr />\n";

    return($V);
  } // ShowUploadHist()



  /**
   * \brief This function returns the scheduler status.
   */
  function Output()
  {
    $uTime = microtime(true);
    if ($this->State != PLUGIN_STATE_READY) {
      return(0);
    }
    $OutBuf="";
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

    /* Use Traceback_parm_keep to ensure that all parameters are in order */
    /********  disable cache to see if this is fast enough without it *****
    $CacheKey = "?mod=" . $this->Name . Traceback_parm_keep(array("upload","item","folder", "orderBy", "orderc", "ordercp")) . "&show=$Show";
    if ($this->UpdCache != 0)
    {
    $OutBuf .= "";
    $Err = ReportCachePurgeByKey($CacheKey);
    }
    else
    $OutBuf .= ReportCacheGet($CacheKey);
     ***********************************************/

    if (empty($OutBuf) )  // no cache exists
    {
      switch($this->OutputType)
      {
        case "XML":
          break;
        case "HTML":
          $OutBuf .= "\n<script language='javascript'>\n";
          /* function to replace this page specifying a new filter parameter */
          $OutBuf .= "function ChangeFilter(selectObj, upload, item){";
          $OutBuf .= "  var selectidx = selectObj.selectedIndex;";
          $OutBuf .= "  var filter = selectObj.options[selectidx].value;";
          $OutBuf .= '  window.location.assign("?mod=' . $this->Name .'&upload="+upload+"&item="+item +"&filter=" + filter); ';
          $OutBuf .= "}</script>\n";

          $OutBuf .= "<font class='text'>\n";

          /************************/
          /* Show the folder path */
          /************************/
          $OutBuf .= Dir2Browse($this->Name,$Item,NULL,1,"Browse",-1,'','',$uploadtree_tablename) . "<P />\n";
          if (!empty($Upload))
          {
            /** advanced interface allowing user to select dataset (agent version) */
            $Agent_name = "copyright";
            $dataset = "copyright_dataset";
            $arstable = "copyright_ars";
            /** get proper agent_id */
            $Agent_pk = GetParm("agent", PARM_INTEGER);
            if (empty($Agent_pk))
            {
              $Agent_pk = LatestAgentpk($Upload, $arstable);
            }

            if ($Agent_pk == 0)
            {
              /** schedule copyright */
              $OutBuf .= ActiveHTTPscript("Schedule");
              $OutBuf .= "<script language='javascript'>\n";
              $OutBuf .= "function Schedule_Reply()\n";
              $OutBuf .= "  {\n";
              $OutBuf .= "  if ((Schedule.readyState==4) && (Schedule.status==200))\n";
              $OutBuf .= "    document.getElementById('msgdiv').innerHTML = Schedule.responseText;\n";
              $OutBuf .= "  }\n";
              $OutBuf .= "</script>\n";

              $OutBuf .= "<form name='formy' method='post'>\n";
              $OutBuf .= "<div id='msgdiv'>\n";
              $OutBuf .= _("No data available.");
              $OutBuf .= "<input type='button' name='scheduleAgent' value='Schedule Agent'";
              $OutBuf .= "onClick='Schedule_Get(\"" . Traceback_uri() . "?mod=schedule_agent&upload=$Upload&agent=agent_copyright \")'>\n";
              $OutBuf .= "</input>";
              $OutBuf .= "</div> \n";
              $OutBuf .= "</form>\n";
              break;
            }

            $AgentSelect = AgentSelect($Agent_name, $Upload, true, $dataset, $dataset, $Agent_pk,
                "onchange=\"addArsGo('newds', 'copyright_dataset');\"");

            /** change the copyright  result when selecting one version of copyright */
            if (!empty($AgentSelect))
            {
              $action = Traceback_uri() . "?mod=copyrighthist&upload=$Upload&item=$Item";

              $OutBuf .= "<script type='text/javascript'>
                function addArsGo(formid, selectid)
                {
                  var selectobj = document.getElementById(selectid);
                  var Agent_pk = selectobj.options[selectobj.selectedIndex].value;
                  document.getElementById(formid).action='$action'+'&agent='+Agent_pk;
                  document.getElementById(formid).submit();
                  return;
                }
              </script>";

              /* form to select new dataset, show dataset */
              $OutBuf .= "<form action='$action' id='newds' method='POST'>\n";
              $OutBuf .= $AgentSelect;
              $OutBuf .= "</form>";
            }

            $Uri = preg_replace("/&item=([0-9]*)/","",Traceback());

            /* Select list for filters */
            $SelectFilter = "<select name='view_filter' id='view_filter' onchange='ChangeFilter(this,$Upload, $Item)'>";

            $text = _("Show all");
            $Selected = ($filter == 'none') ? "selected" : "";
            $SelectFilter .= "<option $Selected value='none'>$text";

            $text = _("Show files without licenses");
            $Selected = ($filter == 'nolics') ? "selected" : "";
            $SelectFilter .= "<option $Selected value='nolics'>$text";

            $SelectFilter .= "</select>";
            $OutBuf .= $SelectFilter;

            $OutBuf .= $this->ShowUploadHist($Item, $Uri, $filter, $uploadtree_tablename, $Agent_pk);
          }
          $OutBuf .= "</font>\n";
          $OutBuf .= $this->createJavaScriptBlock();
          break;
        case "Text":
          break;
        default:
      }

      /*  Cache Report */
      /********  disable cache to see if this is fast enough without it *****
      $Cached = false;
      ReportCachePut($CacheKey, $OutBuf);
       **************************************************/
    }
    else
      $Cached = true;

    if (!$this->OutputToStdout) {
      return($OutBuf);
    }
    print "$OutBuf";
    $Time = microtime(true) - $uTime;  // convert usecs to secs
    $text = _("Elapsed time: %.2f seconds");
    printf( "<small>$text</small>", $Time);

    /********  disable cache to see if this is fast enough without it *****
    $text = _("cached");
    $text1 = _("Update");
    if ($Cached) echo " <i>$text</i>   <a href=\"$_SERVER[REQUEST_URI]&updcache=1\"> $text1 </a>";
     **************************************************/
    return;
  }


  private function createJavaScriptBlock()
  {
    $output = "\n<script src=\"scripts/jquery-1.11.1.min.js\" type=\"text/javascript\"></script>\n";
    $output .="\n<script src=\"scripts/jquery.jeditable.mini.js\" type=\"text/javascript\"></script>\n";
    //    $output .="\n<script src=\"scripts/jquery.dataTables-1.9.4.min.js\" type=\"text/javascript\"></script>\n";
    $output .="\n<script src=\"scripts/jquery.dataTables.js\" type=\"text/javascript\"></script>\n";
    $output .="\n<script src=\"scripts/jquery.dataTables.editable.js\" type=\"text/javascript\"></script>\n";
    $output .= "\n<script src=\"scripts/jquery.plainmodal.min.js\" type=\"text/javascript\"></script>\n";
    return $output;
  }

  /**
   * @param $Uploadtree_pk
   * @param $Uri
   * @param $uploadtree_tablename
   * @param $Agent_pk
   * @param $upload_pk
   * @return array
   */
  private function getFileListing($Uploadtree_pk, $Uri, $uploadtree_tablename, $Agent_pk, $upload_pk)
  {
    $VF=""; // return values for file listing
    /*******    File Listing     ************/
    /* Get ALL the items under this Uploadtree_pk */
    $Children = GetNonArtifactChildren($Uploadtree_pk, $uploadtree_tablename);
    $ChildCount = 0;
    $ChildLicCount = 0;
    $ChildDirCount = 0; /* total number of directory or containers */
    foreach ($Children as $C)
    {
      if (Iscontainer($C['ufile_mode']))
      {
        $ChildDirCount++;
      }
    }

    $VF .= "<table border=0>";
    foreach ($Children as $C)
    {
      if (empty($C))
      {
        continue;
      }

      $IsDir = Isdir($C['ufile_mode']);
      $IsContainer = Iscontainer($C['ufile_mode']);
      $ModLicView = &$Plugins[plugin_find_id("copyrightview")];
      /* Determine the hyperlink for non-containers to view-license  */
      if (!empty($C['pfile_fk']) && !empty($ModLicView))
      {
        $LinkUri = Traceback_uri();
        $LinkUri .= "?mod=view-license&agent=$Agent_pk&upload=$upload_pk&item=$C[uploadtree_pk]";
      } else
      {
        $LinkUri = NULL;
      }

      /* Determine link for containers */
      if (Iscontainer($C['ufile_mode']))
      {
        $uploadtree_pk = DirGetNonArtifact($C['uploadtree_pk'], $uploadtree_tablename);
        $LicUri = "$Uri&item=" . $uploadtree_pk;
      } else
      {
        $LicUri = NULL;
      }

      /* Populate the output ($VF) - file list */
      /* id of each element is its uploadtree_pk */
      $LicCount = 0;

      $VF .= "<tr><td id='$C[uploadtree_pk]' align='left'>";
      $HasHref = 0;
      $HasBold = 0;
      if ($IsContainer)
      {
        $VF .= "<a href='$LicUri'>";
        $HasHref = 1;
        $VF .= "<b>";
        $HasBold = 1;
      } else if (!empty($LinkUri)) //  && ($LicCount > 0))
      {
        $VF .= "<a href='$LinkUri'>";
        $HasHref = 1;
      }
      $VF .= $C['ufile_name'];
      if ($IsDir)
      {
        $VF .= "/";
      };
      if ($HasBold)
      {
        $VF .= "</b>";
      }
      if ($HasHref)
      {
        $VF .= "</a>";
      }
      $VF .= "</td><td>";

      if ($LicCount)
      {
        $VF .= "[" . number_format($LicCount, 0, "", ",") . "&nbsp;";
        $VF .= "license" . ($LicCount == 1 ? "" : "s");
        $VF .= "</a>";
        $VF .= "]";
        $ChildLicCount += $LicCount;
      }
      $VF .= "</td>";
      $VF .= "</tr>\n";

      $ChildCount++;
    }
    $VF .= "</table>\n";
    return array($ChildCount, $VF);
  }

  /**
   * @param $Uploadtree_pk
   * @return bool
   */
  private function isADirectory($Uploadtree_pk)
  {
    global $PG_CONN;
    $sql = "SELECT * FROM $this->uploadtree_tablename WHERE uploadtree_pk = '$Uploadtree_pk';";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $row = pg_fetch_assoc($result);
    pg_free_result($result);
    $isADirectory = IsDir($row['ufile_mode']);
    return $isADirectory;
  }

  /**
   * @return array
   */
  private function getOrderings()
  {
    $orderBy = array('count', 'copyright');
    static $ordercount = 1;
    static $ordercopyright = 1;
    $order = "";
    /** sorting by count/copyright statement */
    if (isset($_GET['orderBy']) && in_array($_GET['orderBy'], $orderBy))
    {
      $order = $_GET['orderBy'];
      if (isset($_GET['orderc'])) $ordercount = $_GET['orderc'];
      if (isset($_GET['ordercp'])) $ordercopyright = $_GET['ordercp'];
      if ('count' == $order && 1 == $ordercount)
      {
        $ordercount = 0;
        return array($ordercount, $ordercopyright);
      } else if ('count' == $order && 0 == $ordercount)
      {
        $ordercount = 1;
        return array($ordercount, $ordercopyright);
      } else if ('copyright' == $order && 1 == $ordercopyright)
      {
        $ordercopyright = 0;
        return array($ordercount, $ordercopyright);
      } else if ('copyright' == $order && 0 == $ordercopyright)
      {
        $ordercopyright = 1;
        return array($ordercount, $ordercopyright);
      }return array($ordercount, $ordercopyright);
    }
    return array($ordercount, $ordercopyright);
  }

  /**
   * @param $row
   * @param $uniqueCount
   * @param $totalCount
   * @param $Uploadtree_pk
   * @param $Agent_pk
   * @param $hash
   * @param bool $normalizeString
   * @param string $filter
   * @return string
   */
  private function fillTableRow( $row, &$uniqueCount, &$totalCount, $Uploadtree_pk, $Agent_pk, $hash, $normalizeString=false ,$filter="" )
  {
    $uniqueCount++;
    $totalCount += $row['copyright_count'];
    $output = "<tr><td align='right'>$row[copyright_count]</td>";
    $output .= "<td align='center'><a href='";
    $output .= Traceback_uri();
    $URLargs = "?mod=copyrightlist&agent=$Agent_pk&item=$Uploadtree_pk&hash=" . $hash . "&type=" . $row['type'];
    if (!empty($filter)) $URLargs .= "&filter=$filter";
    $output .= $URLargs . "'>Show</a></td>";
    $output .= "<td align='left'>";


    if($normalizeString) {
      /* strip out characters we don't want to see
       This is a hack until the agent stops writing these chars to the db.
      */
      $S = $row['content'];
      $S = htmlentities($S);
      $S = str_replace("&Acirc;", "", $S); // comes from utf-8 copyright symbol
      $output .= $S;
    }
    else  {

      $output .= htmlentities($row['content']);
    }

    $output .= "</td>";
    $output .= "</tr>\n";

    return $output;
  }




//  static public function returnSortOrder () {
//    $defaultOrder = array (
//        array(5, "desc"),
//        array(0, "asc"),
//        array(3, "desc"),
//        array(1, "desc"),
//        array(4, "desc")
//    );
//    return $defaultOrder;
//  }
};

$NewPlugin = new CopyrightHistogram;
$NewPlugin->Initialize();