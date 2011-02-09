<?php
/***********************************************************
 Copyright (C) 2010-2011 Hewlett-Packard Development Company, L.P.

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

/*************************************************
 Restrict usage: Every PHP file should have this
 at the very beginning.
 This prevents hacking attempts.
 *************************************************/
global $GlobalReady;
if (!isset($GlobalReady)) { exit; }


  /* FuzzyName comparison function */
  function FuzzyCmp($Child1, $Child2)
  {
    return strcasecmp($Child1['fuzzyname'], $Child2['fuzzyname']);
  }

define("TITLE_ui_nomos_diff", _("Compare License Browser"));

class ui_nomos_diff extends FO_Plugin
{
  var $Name       = "nomosdiff";
  var $Title      = TITLE_ui_nomos_diff;
  var $Version    = "1.0";
  // var $MenuList= "Jobs::License";
  var $Dependency = array("db","browse","view");
  var $DBaccess   = PLUGIN_DB_READ;
  var $LoginFlag  = 0;
  var $UpdCache   = 0;
  var $ColumnSeparatorStyleL = "style='border:solid 0 #006600; border-left-width:2px;padding-left:1em'";

  /***********************************************************
   Install(): Create and configure database tables
   ***********************************************************/
  function Install()
  {
    global $DB;
    if (empty($DB)) { return(1); } /* No DB */

    return(0);
  } // Install()

  /***********************************************************
   RegisterMenus(): Customize submenus.
   ***********************************************************/
  function RegisterMenus()
  {
/* at this stage you have to call this plugin with a direct URL
   that displays both trees to compare.
 */
    return 0;
  } // RegisterMenus()


  /***********************************************************
   Initialize(): This is called before the plugin is used.
   It should assume that Install() was already run one time
   (possibly years ago and not during this object's creation).
   Returns true on success, false on failure.
   A failed initialize is not used by the system.
   NOTE: This function must NOT assume that other plugins are installed.
   ***********************************************************/
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

    /* Remove "updcache" from the GET args and set $this->UpdCache
     * This way all the url's based on the input args won't be
     * polluted with updcache
     */
    if ($_GET['updcache'])
    {
      $this->UpdCache = $_GET['updcache'];
      $_SERVER['REQUEST_URI'] = preg_replace("/&updcache=[0-9]*/","",$_SERVER['REQUEST_URI']);
      unset($_GET['updcache']);
    }
    else
    {
      $this->UpdCache = 0;
    }
    return($this->State == PLUGIN_STATE_VALID);
  } // Initialize()

  /* return array with:
   * lft
   * rgt
   * upload_pk
   * count
   * agent_pk
   */
  function GetTreeInfo($Uploadtree_pk)
  {
    global $PG_CONN;
    global $Plugins;

    $TreeInfo = array();

    /*******  Get license names and counts  ******/
    /* Find lft and rgt bounds for this $Uploadtree_pk  */
    $sql = "SELECT lft,rgt,upload_fk FROM uploadtree 
              WHERE uploadtree_pk = $Uploadtree_pk";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $row = pg_fetch_assoc($result);
    $TreeInfo['lft'] = $lft = $row["lft"];
    $TreeInfo['rgt'] = $rgt = $row["rgt"];
    $TreeInfo['upload_pk'] = $upload_pk = $row["upload_fk"];
    pg_free_result($result);

    $TreeInfo['agent_pk'] = LatestNomosAgentpk($upload_pk);
    return $TreeInfo;
  } 


  /***********************************************************
   UploadHist(): Given an $Uploadtree_pk, 
    return a string with the histogram for the directory BY LICENSE.
   ***********************************************************/
  function UploadHist($Uploadtree_pk, $TreeInfo)
  {
    global $PG_CONN;

    $VLic = '';
    $lft = $TreeInfo['lft'];
    $rgt = $TreeInfo['rgt'];
    $upload_pk = $TreeInfo['upload_pk'];
//    $count = $TreeInfo['count'];
    $agent_pk = $TreeInfo['agent_pk'];

    /*  Get the counts for each license under this UploadtreePk*/
    $sql = "SELECT distinct(rf_shortname) as licname, 
                   count(rf_shortname) as liccount, rf_shortname
              from license_ref,license_file,
                  (SELECT distinct(pfile_fk) as PF from uploadtree 
                     where upload_fk=$upload_pk 
                       and uploadtree.lft BETWEEN $lft and $rgt) as SS
              where PF=pfile_fk and agent_fk=$agent_pk and rf_fk=rf_pk
              group by rf_shortname 
              order by liccount desc";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);

    /* Write license histogram to $VLic  */
    $LicCount = 0;
    $UniqueLicCount = 0;
    $NoLicFound = 0;
    $VLic .= "<table border=1 id='lichistogram'>\n";

    $text = _("Count");
    $VLic .= "<tr><th >$text</th>";

    $text = _("Files");
    $VLic .= "<th >$text</th>";

    $text = _("License Name");
    $VLic .= "<th align=left>$text</th></tr>\n";

    while ($row = pg_fetch_assoc($result))
    {
      $UniqueLicCount++;
      $LicCount += $row['liccount'];

      /*  Count  */
      $VLic .= "<tr><td align='right'>$row[liccount]</td>";

      /*  Show  */
      $ShowTitle = _("Click Show to list files with this license.");
      $VLic .= "<td align='center'><a href='";
      $VLic .= Traceback_uri();

      $text = _("Show");
      $VLic .= "?mod=list_lic_files&napk=$agent_pk&item=$Uploadtree_pk&lic=" . urlencode($row['rf_shortname']) . "' title='$ShowTitle'>$text</a></td>";

      /*  License name  */
      $VLic .= "<td align='left'>";
      $rf_shortname = rawurlencode($row['rf_shortname']);
      $VLic .= "<a id='$rf_shortname' onclick='FileColor_Get(\"" . Traceback_uri() . "?mod=ajax_filelic&napk=$agent_pk&item=$Uploadtree_pk&lic=$rf_shortname\")'";
      $VLic .= ">$row[licname] </a>";
      $VLic .= "</td>";
      $VLic .= "</tr>\n";
      if ($row['licname'] == "No License Found") $NoLicFound =  $row['liccount'];
    }
    pg_free_result($result);
    $VLic .= "</table>\n";
    $VLic .= "<p>\n";

    return($VLic);
  } // UploadHist()


  /***********************************************************
    FileList(): 
    FileList() adds the following new elements to $Children:
       linkurl - this is the entire formatted href inclusive <a to /a> 

    $OneTwo = 1 for the first column list, 2 for the second.
    $Children are non-artifact children of $Uploadtree_pk
   ***********************************************************/
  function FileList($Uploadtree_pk, $TreeInfo, $otheruploadtree_pk, $OneTwo, &$Children, $filter)
  {
    global $PG_CONN;
    global $Plugins;

    $TwoOne = ($OneTwo == 1) ? 2 : 1;

    $upload_pk = $TreeInfo['upload_pk'];
    $agent_pk = $TreeInfo['agent_pk'];
    $ModLicView = &$Plugins[plugin_find_id("view-license")];

    if (!empty($Children))
    {
      foreach($Children as &$Child)
      {
        if (empty($Child)) { continue; }

        $IsDir = Isdir($Child['ufile_mode']);
        $IsContainer = Iscontainer($Child['ufile_mode']);

        /* Determine the hyperlink for non-containers to view-license  */
        if (!empty($Child['pfile_fk']) && !empty($ModLicView))
        {
          $LinkUri = Traceback_uri();
          $LinkUri .= "?mod=view-license&napk=$agent_pk&upload=$upload_pk&item=$Child[uploadtree_pk]";
        }
        else
        {
          $LinkUri = NULL;
        }

        /* Determine link for containers */
        if (Iscontainer($Child['ufile_mode']))
        {
          //$Container_uploadtree_pk = DirGetNonArtifact($Child['uploadtree_pk']);
          $Container_uploadtree_pk = $Child['uploadtree_pk'];
          $LicUri = "?mod=$this->Name&item{$OneTwo}=$Uploadtree_pk&item{$TwoOne}=$otheruploadtree_pk&newitem{$OneTwo}=$Container_uploadtree_pk&col=$OneTwo";
          if (!empty($filter)) $LicUri .= "&filter=$filter";
        }
        else
        {
          $LicUri = NULL;
        }
  
        $HasHref = 0;
        $HasBold = 0;
        $Flink = "";
        if ($IsContainer)
        {
          $Flink = "<a href='$LicUri'>"; $HasHref=1;
          $Flink .= "<b>"; $HasBold=1;
        }
        else if (!empty($LinkUri)) 
        {
          $Flink .= "<a href='$LinkUri'>"; $HasHref=1;
        }
        $Flink .= $Child['ufile_name'];
        if ($IsDir) { $Flink .= "/"; };
        if ($HasBold) { $Flink .= "</b>"; }
        if ($HasHref) { $Flink .= "</a>"; }
        $Child["linkurl"] = $Flink;
      }
    }
  } // FileList()


  /***********************************************************
    ChildElt()
    Return the entire <td> ... </td> for $Child file listing table
    License differences are highlighted.
   ***********************************************************/
  function ChildElt($Child, $agent_pk, $OtherChild)
  {
    $licstr = $Child['licstr'];

    /* If both $Child and $OtherChild are specified,
     * reassemble licstr and highlight the differences 
     */
    if ($OtherChild and $OtherChild)
    {
      $licstr = "";
      $DiffLicStyle = "style='background-color:#ffa8a8'";  // mid red pastel
      foreach ($Child['licarray'] as $rf_pk => $rf_shortname)
      {
        if (!empty($licstr)) $licstr .= ", ";
        if (@$OtherChild['licarray'][$rf_pk])
        {
          /* license is in both $Child and $OtherChild */
          $licstr .= $rf_shortname;
        }
        else
        {
          /* license is missing from $OtherChild */
          $licstr .= "<span $DiffLicStyle>$rf_shortname</span>";
        }
      }
    }

    $ColStr = "<td id='$Child[uploadtree_pk]' align='left'>";
    $ColStr .= "$Child[linkurl]";
    /* show licenses under file name */
    $ColStr .= "<br>";
    $ColStr .= "<span style='position:relative;left:1em'>";
    $ColStr .= $licstr;
    $ColStr .= "</span>";
    $ColStr .= "</td>";

    /* display file links if this is a real file */
    $ColStr .= "<td valign='top'>";
    if ($Child['pfile_fk'])
      $ColStr .= FileListLinks($Child['upload_fk'], $Child['uploadtree_pk'], $agent_pk);
    $ColStr .= "</td>";
    return $ColStr;
  }

  /***********************************************************
    ItemComparisonRows()
    Return a string with the html table rows comparing the two file lists.
    Each row contains 5 table fields.
    The third field is just for a column separator.
    If files match their fuzzyname then put on the same row.
    Highlight license differences.
    Unmatched fuzzynames go on a row of their own.
   ***********************************************************/
  function ItemComparisonRows($Master, $agent_pk1, $agent_pk2)
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
        $TableStr .= $this->ChildElt($Child2, $agent_pk2, $Child1);
      }
      else if (empty($Child2))
      {
        $TableStr .= $this->ChildElt($Child1, $agent_pk1, $Child2);
        $TableStr .= "<td $this->ColumnSeparatorStyleL>&nbsp;</td>";
        $TableStr .= "<td></td><td></td>";
      }
      else if (!empty($Child1) and !empty($Child2))
      {
        $TableStr .= $this->ChildElt($Child1, $agent_pk1, $Child2);
        $TableStr .= "<td $this->ColumnSeparatorStyleL>&nbsp;</td>";
        $TableStr .= $this->ChildElt($Child2, $agent_pk2, $Child1);
      }

      $TableStr .= "</tr>";
    }

    return($TableStr);
  } // ItemComparisonRows()


  /***********************************************************
    AlignChildren
    Return array with aligned children.
    Each row contains aligning child records.
    $Master[n][1] = child 1 row
    $Master[n][2] = child 2 row
   ***********************************************************/
  function MakeMaster($Children1, $Children2)
  {
    $Master = array();
    $row =  0;

    if (!empty($Children1) && (!empty($Children2)))
    {
      $OneIdx = 0;  // $Children1 index
      $TwoIdx = 0;  // $Children2 index
      reset($Children1);
      reset($Children2);
      $Child1 = current($Children1);
      $Child2 = current($Children2);
      while (($Child1 !== false) and ($Child2 !== false)) 
      {
        $comp = strcasecmp($Child1['fuzzyname'], $Child2['fuzzyname']);
        if ($comp < 0) $comp = -1;
        else if ($comp > 0) $comp = 1;
        switch($comp)
        {
          case -1:
            /* Child1 < Child2  */
            $Master[$row][1] = $Child1;
            $Master[$row][2] = '';
            $Child1 = next($Children1);
            break;
          case 0:
            /* Child names match.  Put both in same table row. */
            $Master[$row][1] = $Child1;
            $Master[$row][2] = $Child2;
            $Child1 = next($Children1);
            $Child2 = next($Children2);
            break;
          case 1:
            /* Child1 > Child2  */
            $Master[$row][1] = '';
            $Master[$row][2] = $Child2;
            $Child2 = next($Children2);
            break;
        }
        $row++;
      }
    }

    /* Remaining Child1 recs */
    if ($Child1 !== false)
    {
      $Child = current($Children1);
      while($Child !== false)
      {
        $Master[$row][1] = $Child;
        $Master[$row][2] = '';
        $Child = next($Children1);
        $row++;
      }
    }

    /* Remaining Child2 recs */
    if ($Child2 !== false)
    {
      $Child = current($Children2);
      while($Child !== false)
      {
        $Master[$row][1] = '';
        $Master[$row][2] = $Child;
        $Child = next($Children2);
        $row++;
      }
    }

    return($Master);
  } // MakeMaster()


 /**
  * NextUploadtree_pk()
  * \brief Given an uploadtree_pk in tree A ($A_pk), find the similarly named
  *        one that is immediately under the uploadtree_pk in tree B ($B_pk).
  *
  * @param int   $A_pk      Tree A uploadtree_pk
  * @param int   $B_pk      Tree B uploadtree_pk
  *
  * @return int  New uploadtree_pk in Tree B
  */
  function NextUploadtree_pk($A_pk, $B_pk)
  {
    global $PG_CONN;

    /* look up the name of the $A_pk file */
    $sql = "SELECT ufile_name FROM uploadtree WHERE uploadtree_pk = $A_pk";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $row = pg_fetch_assoc($result);
    $AName = $row["ufile_name"];
    pg_free_result($result);

    $APhon = metaphone($AName);

    /* Loop throught all the files under $B_pk  and look
     * for the closest match.
     */
    $B_pk = DirGetNonArtifact($B_pk);
    $sql = "SELECT uploadtree_pk, ufile_name FROM uploadtree WHERE parent = $B_pk";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $BestDist = 99999;
    $BestPk = 0;
    while ($row = pg_fetch_assoc($result))
    {
      $ChildName = $row["ufile_name"];
      $ChildPhon = metaphone($ChildName);
      $PhonDist = levenshtein($APhon, $ChildPhon);
      if ($PhonDist < $BestDist)
      {
        $BestDist = $PhonDist;
        $BestPk = $row['uploadtree_pk'];
      }
    }
    pg_free_result($result);

    return $BestPk;
  }

  /* AddLicStr
   * Add license array to Children array.
   */
  function AddLicStr($TreeInfo, &$Children)
  {
    if (!is_array($Children)) return;
    $agent_pk = $TreeInfo['agent_pk'];
    foreach($Children as &$Child)
    {
      $Child['licarray'] = GetFileLicenses($agent_pk, 0, $Child['uploadtree_pk']);
      $Child['licstr'] = implode(", ", $Child['licarray']);
    }
  }

    
  /* FuzzyName
   * Add fuzzyname to $Children.
   * The fuzzy name is used to do fuzzy matches.
   * In this implementation the fuzzyname is just the filename
   * with numbers, punctuation, and the file extension removed.
   * Then sort $Children by fuzzyname.
   */
  function FuzzyName(&$Children)
  { 
    foreach($Children as $key1 => &$Child)
    {
      /* remove file extension */
      if (strstr($Child['ufile_name'], ".") !== false)
      {
        $Ext = GetFileExt($Child['ufile_name']);
        $ExtLen = strlen($Ext);
        $NoExtName = substr($Child['ufile_name'], 0, -1*$ExtLen);
      }
      else
        $NoExtName = $Child['ufile_name'];

      $NoNumbName = preg_replace('/([0-9]|\.|-|_)/', "", $NoExtName);
      $Child['fuzzyname'] = $NoNumbName;
    }

    usort($Children, "FuzzyCmp");
    return;
  }  /* End of FuzzyName */


  /* filter_samehash()
   * removes identical files
   * If a child pair are identical remove the master record 
   */
  function filter_samehash(&$Master)
  { 
    if (!is_array($Master)) return;

    foreach($Master as $Key =>&$Pair)
    {
      if (empty($Pair[1]) or empty($Pair[2])) continue;
      if (empty($Pair[1]['pfile_fk'])) continue;
      if (empty($Pair[2]['pfile_fk'])) continue;

      if ($Pair[1]['pfile_fk'] == $Pair[2]['pfile_fk']) 
          unset($Master[$Key]);
    }
    return;
  }  /* End of samehash */


  /* filter_samelic
   * removes files that have the same name and license list.
   */
  function filter_samelic(&$Master)
  { 
    foreach($Master as $Key =>&$Pair)
    {
      if (empty($Pair[1]) or empty($Pair[2])) continue;
      if (($Pair[1]['ufile_name'] == $Pair[2]['ufile_name'])
          && ($Pair[1]['licstr'] == $Pair[2]['licstr']))
          unset($Master[$Key]);
    }
    return;
  }  /* End of samelic */


  /* filter_samelicfuzzy
   * removes files that have the same fuzzyname, and same license list.
   */
  function filter_samelicfuzzy(&$Master)
  { 
    foreach($Master as $Key =>&$Pair)
    {
      if (empty($Pair[1]) or empty($Pair[2])) continue;
      if (($Pair[1]['fuzzyname'] == $Pair[2]['fuzzyname'])
          && ($Pair[1]['licstr'] == $Pair[2]['licstr']))
          unset($Master[$Key]);
    }
    return;
  }  /* End of samelic */


  /* filter_nolics
   * removes pairs of "No License Found"
   * Or pairs that only have one file and "No License Found"
   * Uses fuzzyname.
   */
  function filter_nolics(&$Master)
  { 
    foreach($Master as $Key =>&$Pair)
    {
      $Pair1 = GetArrayVal("1", $Pair);
      $Pair2 = GetArrayVal("2", $Pair);

      if (empty($Pair1))
      {
        if ($Pair2['licstr'] == 'No License Found')
          unset($Master[$Key]);
        else
          continue;
      }
      else if (empty($Pair2))
      {
        if ($Pair1['licstr'] == 'No License Found')
          unset($Master[$Key]);
        else
          continue;
      }
      else if (($Pair1['fuzzyname'] == $Pair2['fuzzyname'])
              and ($Pair1['licstr'] == 'No License Found'))
        unset($Master[$Key]);
    }
    return;
  }  /* End of nolics */

  /*
   * FilterChildren($filter, $Children1, $Children2)
   * $filter:  none, samelic, samehash
   * An empty or unknown filter is the same as "none"
   */
  function FilterChildren($filter, &$Master)
  { 
    switch($filter)
    {
      case 'samehash':
        $this->filter_samehash($Master);
        break;
      case 'samelic':
        $this->filter_samehash($Master);
        $this->filter_samelic($Master);
        break;
      case 'samelicfuzzy':
        $this->filter_samehash($Master);
        $this->filter_samelicfuzzy($Master);
        break;
      case 'nolics':
        $this->filter_samehash($Master);
        $this->filter_nolics($Master);
        $this->filter_samelicfuzzy($Master);
        break;
      default:
        break;
    }
  }


  /* CompareDir($Child1, $Child2)
   * Compare (recursively) the contents of two directories.
   * If they are identical (matching pfile's)
   * Then return True.  Else return False.
   */
  function CompareDir($Child1, $Child2)
  {
    global $PG_CONN;

    $sql = "select pfile_fk from uploadtree where upload_fk=$Child1[upload_fk] 
                   and lft between $Child1[lft] and $Child1[rgt]
            except
            select pfile_fk from uploadtree where upload_fk=$Child2[upload_fk] 
                   and lft between $Child2[lft] and $Child2[rgt]
            limit 1";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $numrows = pg_num_rows($result);
    pg_free_result($result);
    return ($numrows == 0) ? TRUE : FALSE;
  }   


/************************************************************
 * Dir2BrowseDiff(): 
 * Return a string which is a linked path to the file.
 *  This is a modified Dir2Browse() to support browsediff links.
 *  $Path1 - path array for tree 1
 *  $Path2 - path array for tree 2
 *  $filter - filter portion of URL, optional
 *  $Column - which path is being emitted, column 1 or 2
 ************************************************************/
function Dir2BrowseDiff ($Path1, $Path2, $filter, $Column)
{
  global $Plugins;
  global $DB;

  if ((count($Path1) < 1) || (count($Path2) < 1)) return "No path specified";

  $V = "";
  $filter_clause = (empty($filter)) ? "" : "&filter=$filter";
  $Path = ($Column == 1) ? $Path1 : $Path2;
  $Last = $Path[count($Path)-1];

  /* Banner Box decorations */
  $V .= "<div style='border: double gray; background-color:lightyellow'>\n";

  /* Get/write the FOLDER list (in banner) */
  $text = _("Folder");
  $V .= "<b>$text</b>: ";
  $List = FolderGetFromUpload($Path[0]['upload_fk']);
  $Uri2 = Traceback_uri() . "?mod=$this->Name";

  /* Define Freeze button */
  $text = _("Freeze path");
  $id = "Freeze{$Column}";
  $alt =  _("Freeze this path so that selecting a new directory in the other path will not change this one.");
  $Options = "id='$id' onclick='Freeze(\"$Column\")' title='$alt'";
  $FreezeBtn = "<button type='button' $Options> $text </button>\n";

  for($i=0; $i < count($List); $i++)
  {
    $Folder = $List[$i]['folder_pk'];
    $FolderName = htmlentities($List[$i]['folder_name']);
    $V .= "<b>$FolderName/</b> ";
  }

  $FirstPath=true; /* If firstpath is true, print FreezeBtn and starts a new line */
  $V .= "&nbsp;&nbsp;&nbsp;$FreezeBtn";
  $V .= "<br>";

  /* Show the path within the upload */
  for ($PathLev = 0; $PathLev < count($Path); $PathLev++)
  {
    @$PathElt1 = $Path1[$PathLev];
    @$PathElt2 = $Path2[$PathLev];  // temporarily ignore notice of missing Path2[PathLev]
    $PathElt = ($Column == 1) ? $PathElt1: $PathElt2;

    if ($PathElt != $Last)
    {
      $href = "$Uri2&item1=$PathElt1[uploadtree_pk]&item2=$PathElt2[uploadtree_pk]{$filter_clause}&col=$Column";
      $V .= "<a href='$href'>";
    }

    if (!$FirstPath) $V .= "<br>";
    $V .= "&nbsp;&nbsp;<b>" . $PathElt['ufile_name'] . "/</b>";

    if ($PathElt != $Last) $V .= "</a>";
    $FirstPath = false;
  }

  $V .= "</div>\n";  // for box
  return($V);
} // Dir2BrowseDiff()


  /***********************************************************
   HTMLout(): HTML output
   Returns HTML as string.
   ***********************************************************/
  function HTMLout($Master, $uploadtree_pk1, $uploadtree_pk2, $in_uploadtree_pk1, $in_uploadtree_pk2, $filter, $TreeInfo1, $TreeInfo2)
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
    $Selected = ($filter == 'samehash') ? "selected" : "";
    $SelectFilter .= "<option $Selected value='samehash'>1. Remove duplicate (same hash) files";
    $Selected = ($filter == 'samelic') ? "selected" : "";
    $SelectFilter .= "<option $Selected value='samelic'>2. Remove duplicate files (different hash) with unchanged licenses";
    $Selected = ($filter == 'samelicfuzzy') ? "selected" : "";
    $SelectFilter .= "<option $Selected value='samelicfuzzy'>2b. Same as 2 but fuzzy match file names";
    $Selected = ($filter == 'nolics') ? "selected" : "";
    $SelectFilter .= "<option $Selected value='nolics'>3. Same as 3. but also remove files with no license";
    $SelectFilter .= "</select>";


    $OutBuf .= "<a name='flist' href='#histo' style='float:right'> Jump to histogram </a><br>";

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
    $OutBuf .= $this->Dir2BrowseDiff($Path1, $Path2, $filter, 1);
    $OutBuf .= "</td>";
    $OutBuf .= "<td $this->ColumnSeparatorStyleL colspan=3>";
    $OutBuf .= $this->Dir2BrowseDiff($Path1, $Path2, $filter, 2);
    $OutBuf .= "</td></tr>";

    /* File comparison table */
    $OutBuf .= $this->ItemComparisonRows($Master, $TreeInfo1['agent_pk'], $TreeInfo2['agent_pk']);
 

    /*  Separator row */
    $ColumnSeparatorStyleTop = "style='border:solid 0 #006600; border-top-width:2px; border-bottom-width:2px;'";
    $OutBuf .= "<tr>";
    $OutBuf .= "<td colspan=5 $ColumnSeparatorStyleTop>";
    $OutBuf .= "<a name='histo' href='#flist' style='float:right'> Jump to top </a>";
    $OutBuf .= "</a>";
    $OutBuf .= "</tr>";

    /* License histogram */
    $OutBuf .= "<tr>";
    $Tree1Hist = $this->UploadHist($uploadtree_pk1, $TreeInfo1);
    $OutBuf .= "<td colspan=2 valign='top' align='center'>$Tree1Hist</td>";
    $OutBuf .= "<td $this->ColumnSeparatorStyleL>&nbsp;</td>";
    $Tree2Hist = $this->UploadHist($uploadtree_pk2, $TreeInfo2);
    $OutBuf .= "<td colspan=2 valign='top' align='center'>$Tree2Hist</td>";
    $OutBuf .= "</tr></table>\n";

    $OutBuf .= "<a href='#flist' style='float:right'> Jump to top </a><p>";

    return $OutBuf;
  }


  /***********************************************************
   Output(): This function returns the scheduler status.
   Parms:
          filter: optional filter to apply
          item1:  uploadtree_pk of the column 1 tree
          item2:  uploadtree_pk of the column 2 tree
          newitem1:  uploadtree_pk of the new column 1 tree
          newitem2:  uploadtree_pk of the new column 2 tree
          freeze: column number (1 or 2) to freeze
   ***********************************************************/
  function Output()
  {
    if ($this->State != PLUGIN_STATE_READY) { return(0); }

    $uTime = microtime(true);
    $V="";
    $filter = GetParm("filter",PARM_STRING);
    if (empty($filter)) $filter = "samehash";
    $FreezeCol = GetParm("freeze",PARM_INTEGER);
    $in_uploadtree_pk1 = GetParm("item1",PARM_INTEGER);
    $in_uploadtree_pk2 = GetParm("item2",PARM_INTEGER);

    if (empty($in_uploadtree_pk1) || empty($in_uploadtree_pk2))
      Fatal("FATAL: Bad input parameters.  Both item1 and item2 must be specified.", __FILE__, __LINE__);
    $in_newuploadtree_pk1 = GetParm("newitem1",PARM_INTEGER);
    $in_newuploadtree_pk2 = GetParm("newitem2",PARM_INTEGER);
    $uploadtree_pk1  = $in_uploadtree_pk1;
    $uploadtree_pk2 = $in_uploadtree_pk2;

    if (!empty($in_newuploadtree_pk1))
    {
      if ($FreezeCol != 2)
        $uploadtree_pk2  = $this->NextUploadtree_pk($in_newuploadtree_pk1, $in_uploadtree_pk2);
      $uploadtree_pk1  = $in_newuploadtree_pk1;
    }
    else
    if (!empty($in_newuploadtree_pk2))
    {
      if ($FreezeCol != 1)
        $uploadtree_pk1 = $this->NextUploadtree_pk($in_newuploadtree_pk2, $in_uploadtree_pk1);
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

/*
    $updcache = GetParm("updcache",PARM_INTEGER);
    if ($updcache)
      $this->UpdCache = $_GET['updcache'];
    else
      $this->UpdCache = 0;

     Use Traceback_parm_keep to ensure that all parameters are in order 
    $CacheKey = "?mod=" . $this->Name . Traceback_parm_keep(array("upload","item"));
    if ($this->UpdCache != 0)
    {
      $V = "";
      $Err = ReportCachePurgeByKey($CacheKey);
    }
    else
      $V = ReportCacheGet($CacheKey);
*/

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
      /* Get list of children */
      $Children1 = GetNonArtifactChildren($uploadtree_pk1);
      $Children2 = GetNonArtifactChildren($uploadtree_pk2);

      /* Add fuzzyname to children */
      $this->FuzzyName($Children1);  // add fuzzyname to children
      $this->FuzzyName($Children2);  // add fuzzyname to children

      /* add element licstr to children */
      $this->AddLicStr($TreeInfo1, $Children1);
      $this->AddLicStr($TreeInfo2, $Children2);
      
      /* add linkurl to children */
      $this->FileList($uploadtree_pk1, $TreeInfo1, $in_uploadtree_pk2, 1, $Children1, $filter);
      $this->FileList($uploadtree_pk2, $TreeInfo2, $in_uploadtree_pk1, 2, $Children2, $filter);

      /* Master array of children, aligned.   */
      $Master = $this->MakeMaster($Children1, $Children2);

      /* Apply filter */
      $this->FilterChildren($filter, $Master);
    }

    if (empty($V) )  // no cache exists
    {
      switch($this->OutputType)
      {
      case "XML":
        break;
      case "HTML":
        if ($ErrMsg)
          $V .= $ErrMsg;
        else
          $V .= $this->HTMLout($Master, $uploadtree_pk1, $uploadtree_pk2, $in_uploadtree_pk1, $in_uploadtree_pk2, $filter, $TreeInfo1, $TreeInfo2);
        break;
      case "Text":
        break;
      default:
      }
//      $Cached = false;
    }
//    else
//      $Cached = true;

//    if (!$this->OutputToStdout) { return($V); }
    print "$V";
    $Time = microtime(true) - $uTime;  // convert usecs to secs
    $text = _("Elapsed time: %.2f seconds");
    printf( "<small>$text</small>", $Time);

/*
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
*/
    return;
  }  /* End Output() */

}  /* End Class */

$NewPlugin = new ui_nomos_diff;
$NewPlugin->Initialize();

?>
