<?php
/***********************************************************
 Copyright (C) 2011 Hewlett-Packard Development Company, L.P.

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

/* These are common functions for the file picker and
 * the diff tools.
 */

/*************************************************
 Restrict usage: Every PHP file should have this
 at the very beginning.
 This prevents hacking attempts.
 *************************************************/
global $GlobalReady;
if (!isset($GlobalReady)) { exit; }

/*****************************************
 PickGarbage: 
   Do garbage collection on table file_picker.
   History that hasn't been accessed (picked) in the last $ExpireDays is deleted.

   This executes roughly every $ExeFreq times
   it is called.

 Params: None

 Returns: None
 *****************************************/
function PickGarbage()
{
  global $PG_CONN;
  $ExpireDays = 60;  // max days to keep in pick history
  $ExeFreq = 100;

  if ( rand(1,$ExeFreq) != 1) return;

  $sql = "delete from file_picker where last_access_date < (now() - interval '$ExpireDays days')";
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
}


/* FuzzyName comparison function for diff tools */
function FuzzyCmp($Master1, $Master2)
{
  if (empty($Master1[1])) 
    $str1 = $Master1[2]['fuzzyname'];
  else 
    $str1 = $Master1[1]['fuzzyname'];

  if (empty($Master2[1])) 
    $str2 = $Master2[2]['fuzzyname'];
  else 
    $str2 = $Master2[1]['fuzzyname'];

  return strcasecmp($str1, $str2);
}


  /************************************************************
   * ApplicationPick
   * Return html to pick the application that will be called after
   * the items are identified.
   * Select list element ID is "apick"
   */
  function ApplicationPick($SLName, $SelectedVal, $label)
  {
    /* select the apps that are registered to accept item1, item2 pairs.
     * At this time (pre 2.x) we don't know enough about the plugins
     * to know if they can take a pair.  Till then, the list is
     * hardcoded.
     */
    $AppList = array("nomosdiff" => "License Difference",
                     "bucketsdiff" => "Bucket Difference");

    $Options = "id=apick";
    $SelectList = Array2SingleSelect($AppList, $SLName, $SelectedVal,
                                     false, true, $Options);
    $StrOut = "$SelectList $label";
    return $StrOut;
  }


  /***********************************************************
    Return the master array with aligned children.
    Each row contains aligning child records.
    $Master[n][1] = child 1 row
    $Master[n][2] = child 2 row
    If $Sort is true, the master rows are sorted by fuzzy name.
    If $Sort is false, the master rows are unsorted.
   ***********************************************************/
  function MakeMaster($Children1, $Children2, $Sort=true)
  {
    $Master = array();
    $row =  -1;   // Master row number

    if (!empty($Children1) && (!empty($Children2)))
    {
      foreach ($Children1 as $key1 => $Child1)
      {
        /* find complete name match */
        foreach ($Children2 as $key2 => $Child2)
        {
          if ($Child1['ufile_name'] == $Child2['ufile_name'])
          {
            $row++;
            $Master[$row][1] = $Child1;
            $Master[$row][2] = $Child2;
            unset($Children1[$key1]);
            unset($Children2[$key2]);
            break;
          }
        }
      }

      /* find fuzzy+extension match */
      foreach ($Children1 as $key1 => $Child1)
      {
        foreach ($Children2 as $key2 => $Child2)
        {
          if ($Child1['fuzzynameext'] == $Child2['fuzzynameext'])
          {
            $row++;
            $Master[$row][1] = $Child1;
            $Master[$row][2] = $Child2;
            unset($Children1[$key1]);
            unset($Children2[$key2]);
            break;
          }
        }
      }

      /* find files that only differ by 1 character in fuzzyext 
       * names must be over 3 characters long
       */
      foreach ($Children1 as $key1 => $Child1)
      {
        foreach ($Children2 as $key2 => $Child2)
        {
          if (strlen($Child1['fuzzynameext']) <= 3) continue;
          if (levenshtein($Child1['fuzzynameext'], $Child2['fuzzynameext']) == 1)
          {
            $row++;
            $Master[$row][1] = $Child1;
            $Master[$row][2] = $Child2;
            unset($Children1[$key1]);
            unset($Children2[$key2]);
            break;
          }
        }
      }

      /* Look for fuzzy match */
      foreach ($Children1 as $key1 => $Child1)
      {
        foreach ($Children2 as $key2 => $Child2)
        {
          if ($Child1['fuzzyname'] == $Child2['fuzzyname'])
          {
            $row++;
            $Master[$row][1] = $Child1;
            $Master[$row][2] = $Child2;
            unset($Children1[$key1]);
            unset($Children2[$key2]);
            break;
          }
        }
      }
    }

    /* Add in nonmatching Child1 recs */
    foreach ($Children1 as $Child)
    {
      $row++;
      $Master[$row][1] = $Child;
      $Master[$row][2] = array();
    }

    /* Remaining Child2 recs */
    foreach ($Children2 as $Child)
    {
      $row++;
      $Master[$row][1] = array();
      $Master[$row][2] = $Child;
    }

    /* Sort master by child1 */
    usort($Master, "FuzzyCmp");

    return($Master);
  } // MakeMaster()


  /***********************************************************
    FileList(): 
    FileList() adds the element linkurl to the $Master elements.
       linkurl - this is the entire formatted href inclusive <a to /a> 
   ***********************************************************/
  function FileList(&$Master, $agent_pk1, $agent_pk2, $filter, $plugin, $uploadtree_pk1, $uploadtree_pk2)
  {
    global $PG_CONN;
    global $Plugins;

    $ModLicView = &$Plugins[plugin_find_id("view-license")];

    if (!empty($Master))
    {
      foreach($Master as &$MasterRow)
      {
        if (!empty($MasterRow[1]))
          $MasterRow[1]["linkurl"] = GetDiffLink($MasterRow, 1, $agent_pk1, $filter, $plugin, $ModLicView, $uploadtree_pk1, $uploadtree_pk2);

        if (!empty($MasterRow[2]))
          $MasterRow[2]["linkurl"] = GetDiffLink($MasterRow, 2, $agent_pk2, $filter, $plugin, $ModLicView, $uploadtree_pk1, $uploadtree_pk2);
      }
    }
  } // FileList()


  /* GetDiffLink()
   * Return the link for one side of a diff element.
   */
  function GetDiffLink($MasterRow, $side, $agent_pk, $filter, $plugin, $ModLicView, $uploadtree_pk1, $uploadtree_pk2)
  {
    /* calculate opposite side number */
    if ($side == 1)
    {
      $OppositeSide = 2;
      $OppositeItem = $uploadtree_pk2;
    }
    else
    {
      $OppositeSide = 1;
      $OppositeItem = $uploadtree_pk1;
    }

    $OppositeChild = $MasterRow[$OppositeSide];
    $Child = $MasterRow[$side];

    /* if the opposite column element is empty, then use the original uploadtree_pk */
    if (empty($OppositeChild))
      $OppositeParm = "&item{$OppositeSide}=$OppositeItem";
    else
      $OppositeParm = "&item{$OppositeSide}=$OppositeChild[uploadtree_pk]";

    $IsDir = Isdir($Child['ufile_mode']);
    $IsContainer = Iscontainer($Child['ufile_mode']);

    /* Determine the hyperlink for non-containers to view-license  */
    if (!empty($Child['pfile_fk']) && !empty($ModLicView))
    {
      $LinkUri = Traceback_uri();
      $LinkUri .= "?mod=view-license&napk=$agent_pk&upload=$Child[upload_fk]&item=$Child[uploadtree_pk]";
    }
    else
    {
      $LinkUri = NULL;
    }

    /* Determine link for containers */
    if (Iscontainer($Child['ufile_mode']))
    {
      $Container_uploadtree_pk = $Child['uploadtree_pk'];
      $LicUri = "?mod=$plugin->Name&item{$side}=$Child[uploadtree_pk]{$OppositeParm}&col=$side";
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
    return $Flink;
  }


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


    
  /* FuzzyName
   * Add fuzzyname and fuzzynameext to $Children.
   * The fuzzy name is used to do fuzzy matches.
   * In this implementation the fuzzyname is just the filename
   * with numbers, punctuation, and the file extension removed.
   * fuzzynameext is the same as fuzzyname but with the file extension.
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
      $NoNumbNameext = preg_replace('/([0-9]|\.|-|_)/', "", $Child['ufile_name']);
      $Child['fuzzyname'] = $NoNumbName;
      $Child['fuzzynameext'] = $NoNumbName;
    }

    return;
  }  /* End of FuzzyName */


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
 *  $Path   - path array for tree
 *  $filter - filter portion of URL, optional
 *  $Column - which path is being emitted, column 1 or 2
 *  $Master - Master (from MakeMaster()) to be used for 
 *            determining matched pairs.
 ************************************************************/
function Dir2BrowseDiff ($Path, $filter, $Column, $plugin, $Master)
{
  global $Plugins;
  global $DB;

  /* data input assertions */
  $text = _("Dir2BrowseDiff(): Missing inputs");
  if ((count($Path) < 1) 
      or (($Column != 1) and ($Column != 2))
      or (empty($plugin)) or (empty($Master)))
    Fatal($text, __FILE__, __LINE__);

  $V = "";
  $filter_clause = (empty($filter)) ? "" : "&filter=$filter";

  /* Banner Box decorations */
  $V .= "<div style='border: double gray; background-color:lightyellow'>\n";

  /* Get/write the FOLDER list (in banner) */
  $text = _("Folder");
  $V .= "<b>$text</b>: ";
  $List = FolderGetFromUpload($Path[0]['upload_fk']);
  $Uri2 = Traceback_uri() . "?mod=$plugin->Name";

  /* Define Freeze button */
/* TEMPORARILY REMOVE Apr 27, 2011 - It's not clear that we need this and I broke it while
changing the Dir2PathDiff links.  Then new Dir2PathDiff links work so well
this doesn't seem to be necessary.
I don't want to fix this if we don't need
the freeze button (which is confusing UI element). 
Remove all the freeze code (here and in the diff plugins) in v 1.4.1 after we see 
how this goes in 1.4.

  $text = _("Freeze path");
  $id = "Freeze{$Column}";
  $alt =  _("Freeze this path so that selecting a new directory in the other path will not change this one.");
  $Options = "id='$id' onclick='Freeze(\"$Column\")' title='$alt'";
  $FreezeBtn = "<button type='button' $Options> $text </button>\n";
*/

  for($i=0; $i < count($List); $i++)
  {
    $Folder = $List[$i]['folder_pk'];
    $FolderName = htmlentities($List[$i]['folder_name']);
    $V .= "<b>$FolderName/</b> ";
  }

  $FirstPath=true; /* If firstpath is true, print FreezeBtn and starts a new line */
/* SEE above TEMP REMOVE 
  $V .= "&nbsp;&nbsp;&nbsp;$FreezeBtn";
*/
  $V .= "<br>";

  /* Show the path within the upload */
  $LastItem = $Path[count($Path)-1]['uploadtree_pk'];
  $OtherColumn = ($Column == 1) ? 2:1;
  foreach($Path as $Pathrec)
  {
    $Item = $Pathrec['uploadtree_pk'];
    $OtherItem = FindMatchingItem($Pathrec['uploadtree_pk'], $Column, $OtherColumn, $Master);
    if ($Column == 1)
    {
      $Item1 = $Item;
      $Item2 = $OtherItem;
    }
    else
    {
      $Item1 = $OtherItem;
      $Item2 = $Item;
    }
    $Name = "&nbsp;&nbsp;<b>" . $Pathrec['ufile_name'] . "/</b>";
    if (($Item != $LastItem) and ($OtherItem))
    {
      $href = "$Uri2&item1=$Item1&item2=$Item2{$filter_clause}&col=$Column";
      $V .= "<a href='$href'>$Name</a>";
    }
    else
      $V .= $Name;

    $V .= "<br>";
  }

  $V .= "</div>\n";  // for box
  return($V);
} // Dir2BrowseDiff()


/************************************************************
  FindMatchingItem()
  Find the item (uploadtree_pk) from $ItemColumn in $Master $SearchColumn
  that matches $Item
  No match returns an empty string;
************************************************************/
function FindMatchingItem($Item, $ItemColumn, $SearchColumn, $Master)
{
  foreach($Master as $Pair)
  {
    if (empty($Pair[1]) or empty($Pair[2])) continue;
    if ($Pair[$ItemColumn]['uploadtree_pk'] == $Item)
    {
      return $Pair[$SearchColumn]['uploadtree_pk'];
    }
  }
  return '';
}

?>
