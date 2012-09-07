<?php
/***********************************************************
 Copyright (C) 2011-2012 Hewlett-Packard Development Company, L.P.

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

/**
 * \file bucket-diff.php
 * \brief Compare Buckets Browser
 */

define("TITLE_ui_diff_buckets", _("Compare Buckets Browser"));

class ui_diff_buckets extends FO_Plugin
{
  var $Name       = "bucketsdiff";
  var $Title      = TITLE_ui_diff_buckets;
  var $Version    = "1.0";
  var $Dependency = array("browse","view");
  var $DBaccess   = PLUGIN_DB_READ;
  var $LoginFlag  = 0;
  var $ColumnSeparatorStyleL = "style='border:solid 0 #006600; border-left-width:2px;padding-left:1em'";
  var $threshold  = 150;  /* cut point for removing by eval order, hardcode for v1  */

  /**
   * \brief Create and configure database tables
   */
  function Install()
  {
    global $PG_CONN;
    if (empty($PG_CONN)) { return(1); } /* No DB */

    return(0);
  } // Install()

  /**
   * @brief Customize submenus.
   */
  function RegisterMenus()
  {
    /* at this stage you have to call this plugin with a direct URL
       that displays both trees to compare.
     */
    return 0;
  } // RegisterMenus()


  /**
   * @brief This is called before the plugin is used.
   * It should assume that Install() was already run one time
   * (possibly years ago and not during this object's creation).
   * @return true on success, false on failure.
   * A failed initialize is not used by the system.
   * \note This function must NOT assume that other plugins are installed.
   */
  function Initialize()
  {
    global $_GET;

    if ($this->State != PLUGIN_STATE_INVALID) { return(1); } // don't re-run
    if ($this->Name !== "") // Name must be defined
    {
      global $Plugins;
      $this->State=PLUGIN_STATE_VALID;
      array_push($Plugins,$this);
    }

    return($this->State == PLUGIN_STATE_VALID);
  } // Initialize()

  /**
   * @brief Get uploadtree info for a given uploadtree_pk.
   * @param $Uploadtree_pk
   * @return array with uploadtree record and:\n
   *   agent_pk\n
   *   bucketagent_pk\n
   *   nomosagent_pk\n
   *   bucketpool_pk\n
   */
  function GetTreeInfo($Uploadtree_pk)
  {
    global $PG_CONN;

    $TreeInfo = GetSingleRec("uploadtree", "WHERE uploadtree_pk = $Uploadtree_pk");
    $TreeInfo['agent_pk'] = LatestAgentpk($TreeInfo['upload_fk'], "nomos_ars");

   /* Get the ars_pk of the scan to display, also the select list  */
    $ars_pk = GetArrayVal("ars", $_GET);
    $BucketSelect = SelectBucketDataset($TreeInfo['upload_fk'], $ars_pk, "selectbdata",
                                        "onchange=\"addArsGo('newds','selectbdata');\"");
    $TreeInfo['ars_pk'] = $ars_pk;
    if ($ars_pk == 0)
    {
      /* No bucket data for this upload */
      return $BucketSelect;  // $BucketSelect is error message
    }

    /* Get scan keys */
    $where = "where ars_pk=$ars_pk";
    $row = GetSingleRec("bucket_ars", $where);
    if (empty($row)) Fatal("No bucket data $where", __FILE__, __LINE__);
    $TreeInfo['bucketagent_pk'] = $row["agent_fk"];
    $TreeInfo['nomosagent_pk'] = $row["nomosagent_fk"];
    $TreeInfo['bucketpool_pk'] = $row["bucketpool_fk"];
    unset($row);

    return $TreeInfo;
  } 


  /**
   * @brief Given an $Uploadtree_pk, return a string with the histogram for the directory BY bucket.
   * @return a string with the histogram for the directory BY bucket.
   */
  function UploadHist($Uploadtree_pk, $TreeInfo, $BucketDefArray)
  {
    global $PG_CONN;

    $HistStr = '';
    $lft = $TreeInfo['lft'];
    $rgt = $TreeInfo['rgt'];
    $upload_pk = $TreeInfo['upload_fk'];
    $agent_pk = $TreeInfo['agent_pk'];
    $bucketagent_pk = $TreeInfo['bucketagent_pk'];
    $nomosagent_pk = $TreeInfo['nomosagent_pk'];
    $bucketpool_pk = $TreeInfo['bucketpool_pk'];

    /*select all the buckets for entire tree for this bucketpool */
    $sql = "SELECT distinct(bucket_fk) as bucket_pk, 
                   count(bucket_fk) as bucketcount, bucket_reportorder
              from bucket_file, bucket_def,
                  (SELECT distinct(pfile_fk) as PF from uploadtree 
                     where upload_fk=$upload_pk 
                       and ((ufile_mode & (1<<28))=0)
                       and uploadtree.lft BETWEEN $lft and $rgt) as SS
              where PF=pfile_fk and agent_fk=$bucketagent_pk 
                    and bucket_file.nomosagent_fk=$nomosagent_pk
                    and bucket_pk=bucket_fk
                    and bucketpool_fk=$bucketpool_pk
              group by bucket_fk,bucket_reportorder
              order by bucket_reportorder asc";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $historows = pg_fetch_all($result);
    pg_free_result($result);

if (false)
{
    /* Show dataset list */
    if (!empty($BucketSelect))
    {
      $action = Traceback_uri() . "?mod=bucketbrowser&upload=$upload_pk&item=$Uploadtree_pk";

      $HistStr .= "<script type='text/javascript'>
function addArsGo(formid, selectid ) 
{
var selectobj = document.getElementById(selectid);
var ars_pk = selectobj.options[selectobj.selectedIndex].value;
document.getElementById(formid).action='$action'+'&ars='+ars_pk;
document.getElementById(formid).submit();
return;
}
</script>";

      /* form to select new dataset (ars_pk) */
      $HistStr .= "<form action='$action' id='newds' method='POST'>\n";
      $HistStr .= $BucketSelect;
      $HistStr .= "</form>";
    }
}

    /* any rows? */
    if (count($historows) == 0) return $HistStr;

    $sql = "select bucketpool_name from bucketpool where bucketpool_pk=$bucketpool_pk";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $row = pg_fetch_assoc($result);
    $bucketpool_name = $row['bucketpool_name'];
    pg_free_result($result);

    /* Write bucket histogram to $HistStr  */
    $TotalCount = 0;
    $NoLicFound = 0;
    $HistStr .= "<table border=1 id='histogram'>\n";

    $text = _("Count");
    $HistStr .= "<tr><th >$text</th>";

    $text = _("Files");
    $HistStr .= "<th >$text</th>";

    $text = _("Bucket");
    $HistStr .= "<th align=left>$text</th></tr>\n";

    if(empty($historows))
    {
      return;
    }
    foreach ($historows as $row)
    {
      $TotalCount += $row['bucketcount'];
      $bucket_pk = $row['bucket_pk'];
      $bucketcount = $row['bucketcount'];
      $bucket_name = $BucketDefArray[$bucket_pk]['bucket_name'];
      $bucket_color = $BucketDefArray[$bucket_pk]['bucket_color'];

      /*  Count  */
      $HistStr .= "<tr><td align='right' style='background-color:$bucket_color'>$row[bucketcount]</td>";

      /*  Show  */
      $ShowTitle = _("Click Show to list files with this license.");
      $HistStr .= "<td align='center'><a href='";
      $HistStr .= Traceback_uri();

      $text = _("Show");
      $HistStr .= "?mod=list_bucket_files&bapk=$bucketagent_pk&item=$Uploadtree_pk&bpk=$bucket_pk&bp=$bucketpool_pk&napk=$nomosagent_pk" . "'>$text</a></td>";

      /*  Bucket name  */
      $HistStr .= "<td align='left'>";
      $HistStr .= "<a id='$bucket_pk' onclick='FileColor_Get(\"" . Traceback_uri() . "?mod=ajax_filebucket&bapk=$bucketagent_pk&item=$Uploadtree_pk&bucket_pk=$bucket_pk\")'";
      $HistStr .= ">$bucket_name </a>";
      $HistStr .= "</td>";
      $HistStr .= "</tr>\n";
    }
    $HistStr .= "</table>\n";
    $HistStr .= "<p>\n";

    return($HistStr);
  } // UploadHist()



  /**
   * @brief Return the entire <td> ... </td> for $Child file listing table
   *        differences are highlighted.
   * @param $Child
   * @param $agent_pk
   * @param $OtherChild
   * @param $BucketDefArray
   *
   * @return the entire html <td> ... </td> for $Child file listing table
   * differences are highlighted.
   */
  function ChildElt($Child, $agent_pk, $OtherChild, $BucketDefArray)
  {
    $UniqueTagArray = array();
    $bucketstr = $Child['bucketstr'];

    /* If both $Child and $OtherChild are specified,
     * reassemble bucketstr and highlight the differences 
     */
    if ($OtherChild and $OtherChild)
    {
      $bucketstr = "";
      foreach ($Child['bucketarray'] as $bucket_pk)
      {
        $bucket_color = $BucketDefArray[$bucket_pk]['bucket_color'];
        $BucketStyle = "style='color:#606060;background-color:$bucket_color'";  
        $DiffStyle = "style='background-color:$bucket_color;text-decoration:underline;text-transform:uppercase;border-style:outset'";  
        $bucket_name = $BucketDefArray[$bucket_pk]['bucket_name'];

        if (!empty($bucketstr)) $bucketstr .= ", ";
        if (in_array($bucket_pk, $OtherChild['bucketarray']))
        {
          /* license is in both $Child and $OtherChild */
          $Style = $BucketStyle;
        }
        else
        {
          /* license is missing from $OtherChild */
          $Style = $DiffStyle;
        }
        $bucketstr .= "<span $Style>$bucket_name</span>";
      }
    }

    $ColStr = "<td id='$Child[uploadtree_pk]' align='left'>";
    $ColStr .= "$Child[linkurl]";
    /* show buckets under file name */
    $ColStr .= "<br>";
    $ColStr .= "<span style='position:relative;left:1em'>";
    $ColStr .= $bucketstr;
    $ColStr .= "</span>";
    $ColStr .= "</td>";

    /* display file links if this is a real file */
    $ColStr .= "<td valign='top'>";
    $uploadtree_tablename = GetUploadtreeTableName($Child['upload_fk']);
    $ColStr .= FileListLinks($Child['upload_fk'], $Child['uploadtree_pk'], $agent_pk, $Child['pfile_fk'], True, $UniqueTagArray, $uploadtree_tablename);
    $ColStr .= "</td>";
    return $ColStr;
  }


  /**
   * @brief Get a string with the html table rows comparing the two file lists.
   *  Each row contains 5 table fields.
   *  The third field is just for a column separator.
   *  If files match their fuzzyname then put on the same row.
   *  Highlight license differences.
   *  Unmatched fuzzynames go on a row of their own.
   * @param $Master
   * @param $agent_pk1
   * @param $agent_pk2
   * @param $bucketDefArray
   * @returns html table
   */
  function ItemComparisonRows($Master, $agent_pk1, $agent_pk2, $BucketDefArray)
  {
    $TableStr = "";
    $RowStyle1 = "style='background-color:#ecfaff'";  // pale blue
    $RowStyle2 = "style='background-color:#ffffe3'";  // pale yellow
    $RowNum = 0;

    foreach ($Master as $key => $Pair)
    {
      $RowStyle = (++$RowNum % 2) ? $RowStyle1 : $RowStyle2;
      $TableStr .= "<tr $RowStyle>";

      $Child1 = GetArrayVal("1", $Pair);
      $Child2 = GetArrayVal("2", $Pair);
      if (empty($Child1))
      {
        $TableStr .= "<td></td><td></td>";
        $TableStr .= "<td $this->ColumnSeparatorStyleL>&nbsp;</td>";
        $TableStr .= $this->ChildElt($Child2, $agent_pk2, $Child1, $BucketDefArray);
      }
      else if (empty($Child2))
      {
        $TableStr .= $this->ChildElt($Child1, $agent_pk1, $Child2, $BucketDefArray);
        $TableStr .= "<td $this->ColumnSeparatorStyleL>&nbsp;</td>";
        $TableStr .= "<td></td><td></td>";
      }
      else if (!empty($Child1) and !empty($Child2))
      {
        $TableStr .= $this->ChildElt($Child1, $agent_pk1, $Child2, $BucketDefArray);
        $TableStr .= "<td $this->ColumnSeparatorStyleL>&nbsp;</td>";
        $TableStr .= $this->ChildElt($Child2, $agent_pk2, $Child1, $BucketDefArray);
      }

      $TableStr .= "</tr>";
    }

    return($TableStr);
  } // ItemComparisonRows()


  /**
   * @brief Add bucket_pk array and string to Children array.
   * @param $TreeInfo
   * @param &$Children
   * @param $BucketDefArray
   * @return updated $Children
   */
  function AddBucketStr($TreeInfo, &$Children, $BucketDefArray)
  {
    if (!is_array($Children)) return;
    $agent_pk = $TreeInfo['agent_pk'];
    foreach($Children as &$Child)
    {
      $Child['bucketarray'] = GetFileBuckets($TreeInfo['nomosagent_pk'], $TreeInfo['bucketagent_pk'], $Child['uploadtree_pk'], $TreeInfo['bucketpool_pk']);

      $Child['bucketstr'] = GetFileBuckets_string($TreeInfo['nomosagent_pk'], $TreeInfo['bucketagent_pk'], $Child['uploadtree_pk'], $BucketDefArray, ",", True);
    }
  }


  /**
   * @brief Check all the buckets in $MyArray
   * @param $MyArray is array of bucket_pk's
   * @param $Threshold
   * @param $BucketDefArray
   *   Check all the buckets in $MyArray.
   * @return True if all the bucket_evalorder's are at or below $Threshold
   *   else return False if any are above $Threshold
   */
  function EvalThreshold($MyArray, $Threshold, $BucketDefArray)
  {
    foreach($MyArray as $bucket_pk) 
    {
      $bucket_evalorder = $BucketDefArray[$bucket_pk]['bucket_evalorder'];
      if ($bucket_evalorder > $Threshold) return False;
    }
    return True;
  }

  /** @brief remove files where all the buckets in both pairs
   * are below a bucket_evalorder threshold.
  function filter_evalordermin(&$Master, $BucketDefArray, $threshold)
  { 
    foreach($Master as $Key =>&$Pair)
    {
      $Pair1 = GetArrayVal("1", $Pair);
      $Pair2 = GetArrayVal("2", $Pair);

      if (empty($Pair1))
      {
        if ($this->EvalThreshold($Pair2['bucketarray'], $threshold, $BucketDefArray) == True)
          unset($Master[$Key]);
        else
          continue;
      }
      else if (empty($Pair2))
      {
        if ($this->EvalThreshold($Pair1['bucketarray'], $threshold, $BucketDefArray) == True)
          unset($Master[$Key]);
        else
          continue;
      }
      else 
      if (($this->EvalThreshold($Pair1['bucketarray'], $threshold, $BucketDefArray) == True)
          and ($this->EvalThreshold($Pair2['bucketarray'], $threshold, $BucketDefArray) == True))
        unset($Master[$Key]);
    }
    return;
  }   End of evalordermin */


  /**
   * @brief remove files that contain identical bucket lists
   * @param &$Master
   * @return updated $Master
   */
  function filter_samebucketlist(&$Master)
  { 
    foreach($Master as $Key =>&$Pair)
    {
      $Pair1 = GetArrayVal("1", $Pair);
      $Pair2 = GetArrayVal("2", $Pair);

      if (empty($Pair1) or empty($Pair2)) continue;
      if ($Pair1['bucketstr'] == $Pair2['bucketstr'])
        unset($Master[$Key]);
    }
    return;
  }  /* End of samebucketlist */

  /**
   * @brief Filter children
   * @param $filter:  none, samebucketlist
   * An empty or unknown filter is the same as "none"
   * @param &$Master
   * @param $BucketDefArray
   */
  function FilterChildren($filter, &$Master, $BucketDefArray)
  { 
//debugprint($Master, "Master");
    switch($filter)
    {
      case 'samebucketlist':
        $this->filter_samebucketlist($Master);
        break;
      default:
        break;
    }
  }


  /**
   * @brief HTML output
   * @param $Master
   * @param $uploadtree_pk1
   * @param $uploadtree_pk2
   * @param $in_uploadtree_pk1
   * @param $in_uploadtree_pk2
   * @param $filter
   * @param $TreeInfo1
   * @param $TreeInfo2
   * @param $BucketDefArray
   * @return HTML as string.
   */
  function HTMLout($Master, $uploadtree_pk1, $uploadtree_pk2, $in_uploadtree_pk1, $in_uploadtree_pk2, $filter, $TreeInfo1, $TreeInfo2, $BucketDefArray)
  {
    /* Initialize */
    $FreezeText = _("Freeze Path");
    $unFreezeText = _("Frozen, Click to unfreeze");
    $OutBuf = '';

    /******* javascript functions ********/
    $OutBuf .= "\n<script language='javascript'>\n";
    /* function to replace this page specifying a new filter parameter */
    $OutBuf .= "function ChangeFilter(selectObj, utpk1, utpk2){";
    $OutBuf .= "  var selectidx = selectObj.selectedIndex;";
    $OutBuf .= "  var filter = selectObj.options[selectidx].value;";
    $OutBuf .= '  window.location.assign("?mod=' . $this->Name .'&item1="+utpk1+"&item2="+utpk2+"&filter=" + filter); ';
    $OutBuf .= "}";

    /* Freeze function (path list in banner)
       FreezeColNo is the ID of the column to freeze: 1 or 2
       Toggle Freeze button label: Freeze Path <-> Unfreeze Path
       Toggle Freeze button background color: white to light blue
       Toggle which paths are frozen: if path1 freezes, then unfreeze path2.
       Rewrite urls: eg &item1 ->  &Fitem1
     */
    $OutBuf .= "function Freeze(FreezeColNo) {";
    $OutBuf .=  "var FreezeElt1 = document.getElementById('Freeze1');";
    $OutBuf .=  "var FreezeElt2 = document.getElementById('Freeze2');";

    /* change the freeze labels to denote their new status */
    $OutBuf .=  "if (FreezeColNo == '1')";
    $OutBuf .=  "{";
    $OutBuf .=    "if (FreezeElt1.innerHTML == '$unFreezeText') ";
    $OutBuf .=    "{"; 
    $OutBuf .=      "FreezeElt1.innerHTML = '$FreezeText';";
    $OutBuf .=      "FreezeElt1.style.backgroundColor = 'white';";
    $OutBuf .=    "}"; 
    $OutBuf .=    "else {"; 
    $OutBuf .=      "FreezeElt1.innerHTML = '$unFreezeText';";
    $OutBuf .=      "FreezeElt1.style.backgroundColor = '#EAF7FB';";
    $OutBuf .=      "FreezeElt2.innerHTML = '$FreezeText';";
    $OutBuf .=      "FreezeElt2.style.backgroundColor = 'white';";
    $OutBuf .=    "}"; 
    $OutBuf .=  "}";
    $OutBuf .=  "else {";
    $OutBuf .=    "if (FreezeElt2.innerHTML == '$unFreezeText') ";
    $OutBuf .=    "{"; 
    $OutBuf .=      "FreezeElt2.innerHTML = '$FreezeText';";
    $OutBuf .=      "FreezeElt2.style.backgroundColor = 'white';";
    $OutBuf .=    "}"; 
    $OutBuf .=    "else {"; 
    $OutBuf .=      "FreezeElt1.innerHTML = '$FreezeText';";
    $OutBuf .=      "FreezeElt1.style.backgroundColor = 'white';";
    $OutBuf .=      "FreezeElt2.innerHTML = '$unFreezeText';";
    $OutBuf .=      "FreezeElt2.style.backgroundColor = '#EAF7FB';";
    $OutBuf .=    "}"; 
    $OutBuf .=  "}";

    /* Alter the url to add freeze={column number}  */
    $OutBuf .=  "var i=0;";
    $OutBuf .=  "var linkid;";
    $OutBuf .=  "var linkelt;";
    $OutBuf .=  "var UpdateCol;";
    $OutBuf .=  "if (FreezeColNo == 1) UpdateCol=2;else UpdateCol=1;";
    $OutBuf .=  "var numlinks = document.links.length;";
    $OutBuf .=  "for (i=0; i < numlinks; i++)";
    $OutBuf .=  "{";
    $OutBuf .=    "linkelt = document.links[i];";
    $OutBuf .=    "if (linkelt.href.indexOf('col='+UpdateCol) >= 0)";
    $OutBuf .=    "{";
    $OutBuf .=      "linkelt.href = linkelt.href + '&freeze=' + FreezeColNo;";
    $OutBuf .=    "}";
    $OutBuf .=  "}";
    $OutBuf .= "}";
    $OutBuf .= "</script>\n";
    /******* END javascript functions  ********/


    /* Select list for filters */
    $SelectFilter = "<select name='diff_filter' id='diff_filter' onchange='ChangeFilter(this,$uploadtree_pk1, $uploadtree_pk2)'>";
    $Selected = ($filter == 'none') ? "selected" : "";
    $SelectFilter .= "<option $Selected value='none'>Remove nothing";

    $Selected = ($filter == 'samebucketlist') ? "selected" : "";
    $SelectFilter .= "<option $Selected value='samebucketlist'>Remove unchanged bucket lists";
    $SelectFilter .= "</select>";

    $StyleRt = "style='float:right'";
    $OutBuf .= "<a name='flist' href='#histo' $StyleRt > Jump to histogram </a><br>";
    
    /* Switch to license diff view */
    $text = _("Switch to license view");
    $switchURL = Traceback_uri();
    $switchURL .= "?mod=nomosdiff&item1=$uploadtree_pk1&item2=$uploadtree_pk2";
    $OutBuf .= "<a href='$switchURL' $StyleRt > $text </a> ";


//    $TableStyle = "style='border-style:collapse;border:1px solid black'";
    $TableStyle = "";
    $OutBuf .= "<table border=0 id='dirlist' $TableStyle>";

    /* Select filter pulldown */
    $OutBuf .= "<tr><td colspan=5 align='center'>Filter: $SelectFilter<br>&nbsp;</td></tr>";

    /* File path */
    $OutBuf .= "<tr>";
    $Path1 = Dir2Path($uploadtree_pk1);
    $Path2 = Dir2Path($uploadtree_pk2);
    $OutBuf .= "<td colspan=2>";
    $OutBuf .= Dir2BrowseDiff($Path1, $Path2, $filter, 1, $this);
    $OutBuf .= "</td>";
    $OutBuf .= "<td $this->ColumnSeparatorStyleL colspan=3>";
    $OutBuf .= Dir2BrowseDiff($Path1, $Path2, $filter, 2, $this);
    $OutBuf .= "</td></tr>";

    /* File comparison table */
    $OutBuf .= $this->ItemComparisonRows($Master, $TreeInfo1['agent_pk'], $TreeInfo2['agent_pk'], $BucketDefArray);

    /*  Separator row */
    $ColumnSeparatorStyleTop = "style='border:solid 0 #006600; border-top-width:2px; border-bottom-width:2px;'";
    $OutBuf .= "<tr>";
    $OutBuf .= "<td colspan=5 $ColumnSeparatorStyleTop>";
    $OutBuf .= "<a name='histo' href='#flist' style='float:right'> Jump to top </a>";
    $OutBuf .= "</a>";
    $OutBuf .= "</tr>";

    /* License histogram */
    $OutBuf .= "<tr>";
    $Tree1Hist = $this->UploadHist($uploadtree_pk1, $TreeInfo1, $BucketDefArray);
    $OutBuf .= "<td colspan=2 valign='top' align='center'>$Tree1Hist</td>";
    $OutBuf .= "<td $this->ColumnSeparatorStyleL>&nbsp;</td>";
    $Tree2Hist = $this->UploadHist($uploadtree_pk2, $TreeInfo2, $BucketDefArray);
    $OutBuf .= "<td colspan=2 valign='top' align='center'>$Tree2Hist</td>";
    $OutBuf .= "</tr></table>\n";

    $OutBuf .= "<a href='#flist' style='float:right'> Jump to top </a><p>";

    return $OutBuf;
  }


  /**
   * @brief Output(): 
   * Requires:\n
          filter: optional filter to apply\n
          item1:  uploadtree_pk of the column 1 tree\n
          item2:  uploadtree_pk of the column 2 tree\n
          newitem1:  uploadtree_pk of the new column 1 tree\n
          newitem2:  uploadtree_pk of the new column 2 tree\n
          freeze: column number (1 or 2) to freeze
   */
  function Output()
  {
    if ($this->State != PLUGIN_STATE_READY) { return(0); }

    $uTime = microtime(true);
    $V="";
/**/
    $UpdCache = GetParm("updcache",PARM_INTEGER);

    /* Remove "updcache" from the GET args and set $this->UpdCache
     * This way all the url's based on the input args won't be
     * polluted with updcache
     * Use Traceback_parm_keep to ensure that all parameters are in order
     */
    $CacheKey = "?mod=" . $this->Name . Traceback_parm_keep(array("item1","item2", "filter"));
    if ($UpdCache )
    {
      $UpdCache = $_GET['updcache'];
      $_SERVER['REQUEST_URI'] = preg_replace("/&updcache=[0-9]*/","",$_SERVER['REQUEST_URI']);
      unset($_GET['updcache']);
      $V = ReportCachePurgeByKey($CacheKey);
    }
    else
      $V = ReportCacheGet($CacheKey);
/**/

    if (empty($V))  // no cache exists
    {
    $filter = GetParm("filter",PARM_STRING);
    if (empty($filter)) $filter = "none";
    $FreezeCol = GetParm("freeze",PARM_INTEGER);
    $in_uploadtree_pk1 = GetParm("item1",PARM_INTEGER);
    $in_uploadtree_pk2 = GetParm("item2",PARM_INTEGER);

    if (empty($in_uploadtree_pk1) || empty($in_uploadtree_pk2))
      Fatal("Bad input parameters.  Both item1 and item2 must be specified.", __FILE__, __LINE__);
    $in_newuploadtree_pk1 = GetParm("newitem1",PARM_INTEGER);
    $in_newuploadtree_pk2 = GetParm("newitem2",PARM_INTEGER);
    $uploadtree_pk1  = $in_uploadtree_pk1;
    $uploadtree_pk2 = $in_uploadtree_pk2;

    if (!empty($in_newuploadtree_pk1))
    {
      if ($FreezeCol != 2)
        $uploadtree_pk2  = NextUploadtree_pk($in_newuploadtree_pk1, $in_uploadtree_pk2);
      $uploadtree_pk1  = $in_newuploadtree_pk1;
    }
    else
    if (!empty($in_newuploadtree_pk2))
    {
      if ($FreezeCol != 1)
        $uploadtree_pk1 = NextUploadtree_pk($in_newuploadtree_pk2, $in_uploadtree_pk1);
      $uploadtree_pk2 = $in_newuploadtree_pk2;
    }

    $newURL = Traceback_dir() . "?mod=" . $this->Name . "&item1=$uploadtree_pk1&item2=$uploadtree_pk2";
    if (!empty($filter)) $newURL .= "&filter=$filter";

    // rewrite page with new uploadtree_pks */
    if (($uploadtree_pk1 != $in_uploadtree_pk1)
        || ($uploadtree_pk2 != $in_uploadtree_pk2))
    {
print <<< JSOUT
<script type="text/javascript">
  window.location.assign('$newURL');
</script>
JSOUT;
    }

    $TreeInfo1 = $this->GetTreeInfo($uploadtree_pk1);
    $TreeInfo2 = $this->GetTreeInfo($uploadtree_pk2);
    $ErrText = _("No license data for tree %d.  Use Jobs > Agents to schedule a license scan.");
    $ErrMsg= '';
    if ($TreeInfo1['agent_pk'] == 0)
    {
      $ErrMsg = sprintf($ErrText, 1);
    }
    else
    if ($TreeInfo2['agent_pk'] == 0)
    {
      $ErrMsg = sprintf($ErrText, 2);
    }
    else
    {
      $BucketDefArray = initBucketDefArray($TreeInfo1['bucketpool_pk']);

      /* Get list of children */
      $Children1 = GetNonArtifactChildren($uploadtree_pk1);
      $Children2 = GetNonArtifactChildren($uploadtree_pk2);

      /* Add fuzzyname to children */
      FuzzyName($Children1);  // add fuzzyname to children
      FuzzyName($Children2);  // add fuzzyname to children

      /* add element licstr to children */
      $this->AddBucketStr($TreeInfo1, $Children1, $BucketDefArray);
      $this->AddBucketStr($TreeInfo2, $Children2, $BucketDefArray);

      /* Master array of children, aligned.   */
      $Master = MakeMaster($Children1, $Children2);
      
      /* add linkurl to children */
      FileList($Master, $TreeInfo1['agent_pk'], $TreeInfo2['agent_pk'], $filter, $this, $uploadtree_pk1, $uploadtree_pk2);

      /* Apply filter */
      $this->FilterChildren($filter, $Master, $BucketDefArray);
    }

      switch($this->OutputType)
      {
      case "XML":
        break;
      case "HTML":
        if ($ErrMsg)
          $V .= $ErrMsg;
        else
          $V .= $this->HTMLout($Master, $uploadtree_pk1, $uploadtree_pk2, $in_uploadtree_pk1, $in_uploadtree_pk2, $filter, $TreeInfo1, $TreeInfo2, $BucketDefArray);
        break;
      case "Text":
        break;
      default:
      }
      $Cached = false;
    }
    else
      $Cached = true;

    if (!$this->OutputToStdout) { return($V); }
    print "$V";
    $Time = microtime(true) - $uTime;  // convert usecs to secs
    $text = _("Elapsed time: %.2f seconds");
    printf( "<small>$text</small>", $Time);

/**/
    if ($Cached) 
    {
      $text = _("cached");
      $text1 = _("Update");
      echo " <i>$text</i>   <a href=\"$_SERVER[REQUEST_URI]&updcache=1\"> $text1 </a>";
    }
    else
    {
      //  Cache Report if this took longer than 1/2 second
      if ($Time > 0.5) ReportCachePut($CacheKey, $V);
    }
/**/
    return;
  }  /* End Output() */

}  /* End Class */

$NewPlugin = new ui_diff_buckets;
$NewPlugin->Initialize();

?>
