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

use Fossology\Lib\Dao\CopyrightDao;

define("TITLE_copyrightHistogram", _("Copyright/Email/URL Browser NEW"));

class CopyrightHistogram  extends FO_Plugin {

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
    $this->Name = "copyrighthistogram";
    $this->Title = TITLE_copyrightHistogram;
    $this->Version = "1.0";
    $this->Dependency = array();
    $this->DBaccess = PLUGIN_DB_READ;
    $this->LoginFlag = 0;
    $this->NoMenu = 0;

    parent::__construct();

    global $container;
    $this->copyrightDao = $container->get('dao.copyright');


  }

private  function getCopyrightData($upload_pk, $Uploadtree_pk, $filter, $uploadtree_tablename, $Agent_pk) {
  list($ordercount, $ordercopyright) = $this->getOrderings();

  $type = 'statement';
  $rows = $this->copyrightDao->getCopyrights($upload_pk,$Uploadtree_pk, $uploadtree_tablename ,$Agent_pk, 0,$type,$filter);

  $CopyrightCount = 0;
  $UniqueCopyrightCount = 0;


  /* The filtering in the table header links will be obsolete with datatables... So once we have that the whole function is obsolete as we can use the more general getSingleType */
  $VCopyright = "<table border=1 width='100%' id='copyright'>\n";
  $text = _("Count");
  $text1 = _("Files");
  $text2 = _("Copyright Statements");
  $VCopyright .= "<tr><th>";
  $VCopyright .= "<a href=?mod=" . "$this->Name" . Traceback_parm_keep(array("upload", "item", "filter", "agent")) . "&orderBy=count&orderc=$ordercount>$text</a>";
  $VCopyright .= "</th>";
  $VCopyright .= "<th width='10%'>$text1</th>";
  $VCopyright .= "<th>";
  $VCopyright .= "<a href=?mod=" . "$this->Name" . Traceback_parm_keep(array("upload", "item", "filter", "agent")) . "&orderBy=copyright&ordercp=$ordercopyright>$text2</a>";
  $VCopyright .= "</th>";
  $VCopyright .= "</th></tr>\n";

  foreach ($rows as $row)
  {
    $VCopyright .= $this->fillTableRow($row, $UniqueCopyrightCount, $CopyrightCount, $Uploadtree_pk, $Agent_pk, true ,$filter, $type);
  }

  $VCopyright .= "</table>\n";
  $VCopyright .= "<p>\n";
  $text = _("Unique Copyrights");
  $text1 = _("Total Copyrights");
  $VCopyright .= "$text: $UniqueCopyrightCount<br>\n";
  $NetCopyright = $CopyrightCount;
  $VCopyright .= "$text1: $NetCopyright";

  return $VCopyright;
}


private function getSingleType($type,$description,$descriptionUnique,$descriptionTotal, $upload_pk, $uploadtreeId, $filter, $uploadtree_tablename, $Agent_pk){

  $output = "<table border=1 width='100%'id='copyright".$type."'>\n";
  $output .= "</table>\n";

      $tableColumns = '[
      { "sTitle" : "'._("Count").'", "sClass": "right" ,"sWidth" : "5%" },
      { "sTitle" : "'.$description.'", "sClass": "left"}
    ]';
    $tableSorting = json_encode($this->returnSortOrder());



      $dataTableConfig =
        '{  "bServerSide": true,
            "sAjaxSource": "?mod=copyrightHistogram-processPost",
            "fnServerData": function ( sSource, aoData, fnCallback ) {
              aoData.push( { "name":"upload" , "value" : "'.$upload_pk.'" } );
              aoData.push( { "name":"item" , "value" : "'.$uploadtreeId.'" } );
              aoData.push( { "name":"agent" , "value" : "'.$Agent_pk.'" } );
              aoData.push( { "name":"type" , "value" : "'.$type.'" } );
              aoData.push( { "name":"filter" , "value" : "'.$filter.'" } );
              $.getJSON( sSource, aoData, fnCallback ).fail( function() {
              if (confirm("You are not logged in. Go to login page?"))
                window.location.href="'.  Traceback_uri(). '?mod=auth";
            });
          },
      "aoColumns": '.$tableColumns.',
      "aaSorting": '.$tableSorting.',
      "iDisplayLength": 50,
      "bProcessing": true,
      "bStateSave": true,
      "bRetrieve": true
    }';


    $output   .= "<script>
              function createTable".$type."() {
                    var otable = $('#copyright".$type."').dataTable(". $dataTableConfig . ");
                    // var settings = otable.fnSettings(); // for debugging
                    return otable;
                }
            </script>";




  return $output;

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
  private function getTableContent($upload_pk, $Uploadtree_pk, $filter, $Agent_pk)
  {

    $type = 'statement';
    $decription = _("Copyright");
    $descriptionUnique = _("Unique Copyrights");
    $descriptionTotal = _("Total Copyrights");


    $VCopyright  =  $this->getSingleType($type,$decription,$descriptionUnique,$descriptionTotal, $upload_pk, $Uploadtree_pk, $filter, $this->uploadtree_tablename, $Agent_pk);

    $type = 'email';
    $decription = _("Email");
    $descriptionUnique = _("Unique Emails");
    $descriptionTotal = _("Total Emails");

    $VEmail =  $this->getSingleType($type,$decription,$descriptionUnique,$descriptionTotal, $upload_pk, $Uploadtree_pk, $filter, $this->uploadtree_tablename, $Agent_pk);


    $type = 'url';
    $decription = _("URL");
    $descriptionUnique = _("Unique URLs");
    $descriptionTotal = _("Total URLs");

    $VUrl=  $this->getSingleType($type,$decription,$descriptionUnique,$descriptionTotal, $upload_pk, $Uploadtree_pk, $filter, $this->uploadtree_tablename, $Agent_pk);

    return array( $VCopyright, $VEmail, $VUrl);
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
  function ShowUploadHist($upload_pk, $Uploadtree_pk, $Uri, $filter, $uploadtree_tablename, $Agent_pk)
  {
    $V=""; // total return value

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

    list( $VCopyright, $VEmail, $VUrl) = $this->getTableContent($upload_pk,$Uploadtree_pk, $filter, $Agent_pk);

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

            $Selected = ($filter == 'legal') ? "selected" : "";
            $text = _("Show all");
            $SelectFilter .= "<option $Selected value='all'>$text";

            $text = _("Show all legal copyrights");
            $SelectFilter .= "<option $Selected value='legal'>$text";

            $text = _("Show files without licenses");
            $Selected = ($filter == 'nolics') ? "selected" : "";
            $SelectFilter .= "<option $Selected value='nolics'>$text";

            $SelectFilter .= "</select>";
            $OutBuf .= $SelectFilter;

            $OutBuf .= $this->ShowUploadHist($Upload,$Item, $Uri, $filter, $uploadtree_tablename, $Agent_pk);
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
    $output .= "\n<script src=\"scripts/copyrightHist.js\" type=\"text/javascript\"></script>\n";
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






  static public function returnSortOrder () {
    $defaultOrder = array (
        array(0, "desc"),
        array(1, "desc"),
    );
    return $defaultOrder;
  }
};

$NewPlugin = new CopyrightHistogram;
$NewPlugin->Initialize();