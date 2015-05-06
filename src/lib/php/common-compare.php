<?php
/***********************************************************
 Copyright (C) 2011-2012 Hewlett-Packard Development Company, L.P.

 This library is free software; you can redistribute it and/or
 modify it under the terms of the GNU Lesser General Public
 License version 2.1 as published by the Free Software Foundation.

 This library is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 Lesser General Public License for more details.

 You should have received a copy of the GNU Lesser General Public License
 along with this library; if not, write to the Free Software Foundation, Inc.0
 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
***********************************************************/

/**
 * \file common-compare.php
 * \brief These are common functions for the file picker and
 * the diff tools.
 */


/**
 * \brief FuzzyName comparison function for diff tools
 *
 * \param $Master1 - master1 to compare
 * \param $Master2 - master2 to compare
 *
 * \return fuzzyname string
 */
function FuzzyCmp($Master1, $Master2)
{
  $key1 = empty($Master1[1]) ? 2 : 1;
  $str1 = $Master1[$key1]['fuzzyname'];
  $key2 = empty($Master2[1]) ? 2 : 1;
  $str2 = $Master2[$key2]['fuzzyname'];
  return strcasecmp($str1, $str2);
}


/**
 * \brief Generate the master array with aligned children.
 *
 * Each row contains aligning child records. \n
 * $Master[n][1] = child 1 row \n
 * $Master[n][2] = child 2 row \n
 * If $Sort is true, the master rows are sorted by fuzzy name. \n
 * If $Sort is false, the master rows are unsorted. \n
 *
 * \param $Children1 - child 1 row
 * \param $Children2 - child 2 row
 *
 * \return master array with aligned children
 */
function MakeMaster($Children1, $Children2)
{
  $Master = array();
  $row =  -1;   // Master row number

  if (!empty($Children1) && (!empty($Children2)))
  {
    foreach ($Children1 as $Child1)
    {
      $done = false;
      $row++;

      /* find complete name match */
      foreach ($Children2 as $key => $Child2)
      {
        if ($Child1['ufile_name'] == $Child2['ufile_name'])
        {
          $Master[$row][1] = $Child1;
          $Master[$row][2] = $Child2;
          unset($Children2[$key]);
          $done = true;
          break;
        }
      }

      /* find fuzzy+extension match */
      if (!$done) foreach ($Children2 as $key => $Child2)
      {
        if ($Child1['fuzzynameext'] == $Child2['fuzzynameext'])
        {
          $Master[$row][1] = $Child1;
          $Master[$row][2] = $Child2;
          unset($Children2[$key]);
          $done = true;
          break;
        }
      }

      /* find files that only differ by 1 character in fuzzyext */
      if (!$done) foreach ($Children2 as $key => $Child2)
      {
        if (levenshtein($Child1['fuzzynameext'], $Child2['fuzzynameext']) == 1)
        {
          $Master[$row][1] = $Child1;
          $Master[$row][2] = $Child2;
          unset($Children2[$key]);
          $done = true;
          break;
        }
      }

      /* Look for fuzzy match */
      if (!$done) foreach ($Children2 as $key => $Child2)
      {
        if ($Child1['fuzzyname'] == $Child2['fuzzyname'])
        {
          $Master[$row][1] = $Child1;
          $Master[$row][2] = $Child2;
          unset($Children2[$key]);
          $done = true;
          break;
        }
      }

      /* no match so add it in by itself */
      if (!$done) 
      {
        $Master[$row][1] = $Child1;
        $Master[$row][2] = array();
      }
    }
  }

  /* Remaining Child2 recs */
  foreach ($Children2 as $Child)
  {
    $row++;
    $Master[$row][1] = '';
    $Master[$row][2] = $Child;
  }

  /* Sort master by child1 */
  usort($Master, "FuzzyCmp");

  return($Master);
} // MakeMaster()


/**
 * \brief adds the element linkurl to the $Master elements.
 *
 * linkurl - this is the entire formatted href inclusive <a to /a>
 *
 * \param $Master - master
 * \param $agent_pk1 - agent id 1
 * \param $agent_pk2 - agent id 2
 * \param $filter - filter
 * \param $plugin
 * \param $uploadtree_pk1 - uploadtree id 1
 * \param $uploadtree_pk2 - uploadtree id 2
 */
function FileList(&$Master, $agent_pk1, $agent_pk2, $filter, $plugin, $uploadtree_pk1, $uploadtree_pk2)
{
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


/**
 * \brief generate the link for one side of a diff element.
 *
 * \param $MasterRow - Master row
 * \param $side
 * \param $agent_pk - agent id
 * \param $filter - filter
 * \param $plugin
 * \param $ModLicView
 * \param $uploadtree_pk1 - uploadtree pk1
 * \param $uploadtree_pk2 - uploadtree pk2
 *
 * \return the link for one side of a diff element
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

    
/**
 * \brief Add fuzzyname and fuzzynameext to $Children.
 * The fuzzy name is used to do fuzzy matches.
 * In this implementation the fuzzyname is just the filename
 * with numbers, punctuation, and the file extension removed.
 * fuzzynameext is the same as fuzzyname but with the file extension.
 * 
 * \param $Children child list
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


/**
 * \brief Return a string which is a linked path to the file.
 *  This is a modified Dir2Browse() to support browsediff links.
 *  \param $Path1 - path array for tree 1
 *  \param $Path2 - path array for tree 2
 *  \param $filter - filter portion of URL, optional
 *  \param $Column - which path is being emitted, column 1 or 2
 *  \param $plugin - plugin pointer of the caller ($this)
 ************************************************************/
function Dir2BrowseDiff ($Path1, $Path2, $filter, $Column, $plugin)
{
  if ((count($Path1) < 1) || (count($Path2) < 1))
  {
    return "No path specified";
  }
  $filter_clause = (empty($filter)) ? "" : "&filter=$filter";
  $Path = ($Column == 1) ? $Path1 : $Path2;
  $Last = $Path[count($Path)-1];

  /* Banner Box decorations */
  $V = "<div style='border: double gray; background-color:lightyellow'>\n";

  /* Get/write the FOLDER list (in banner) */
  $text = _("Folder");
  $V .= "<b>$text</b>: ";
  $List = FolderGetFromUpload($Path[0]['upload_fk']);
  $Uri2 = Traceback_uri() . "?mod=$plugin->Name";

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
    $PathElt1 = @$Path1[$PathLev];
    $PathElt2 = @$Path2[$PathLev];  // temporarily ignore notice of missing Path2[PathLev]
    $PathElt = ($Column == 1) ? $PathElt1: $PathElt2;
    /* Prevent a malformed href if any path information is missing */
    $UseHref = (!empty($PathElt1) and (!empty($PathElt2)));
    if ($UseHref and ($PathElt != $Last))
    {
      $href = "$Uri2&item1=$PathElt1[uploadtree_pk]&item2=$PathElt2[uploadtree_pk]{$filter_clause}&col=$Column";
      $V .= "<a href='$href'>";
    }
    if (!$FirstPath)
    {
      $V .= "<br>";
    }
    $V .= "&nbsp;&nbsp;<b>" . $PathElt['ufile_name'] . "/</b>";
    if ($UseHref and ( $PathElt != $Last))
    {
      $V .= "</a>";
    }
    $FirstPath = false;
  }

  $V .= "</div>\n";  // for box
  return($V);
}
