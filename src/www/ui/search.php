<?php
/***********************************************************
 Copyright (C) 2010-2014 Hewlett-Packard Development Company, L.P.

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

use Fossology\Lib\Auth\Auth;

define("TITLE_search", _("Search"));

class search extends FO_Plugin
{
  var $Name       = "search";
  var $Title      = TITLE_search;
  var $Version    = "1.0";
  var $MenuList   = "Search";
  var $MenuOrder  = 90;
  var $Dependency = array("browse");
  var $DBaccess   = PLUGIN_DB_READ;
  var $LoginFlag  = 0;
  var $MaxPerPage  = 100;  /* maximum number of result items per page */

  function PostInitialize()
  {
    $this->State = PLUGIN_STATE_READY;
    return $this->State;
  }

  /**
   * \brief Customize submenus.
   */
  function RegisterMenus()
  {
    menu_insert("Main::" . $this->MenuList,$this->MenuOrder,$this->Name,$this->MenuTarget);

    // For all other menus, permit coming back here.
    $URI = $this->Name . Traceback_parm_keep(array( "page", "item",));
    $Item = GetParm("item", PARM_INTEGER);
    if (!empty($Item)) {
      if (GetParm("mod", PARM_STRING) == $this->Name) {
        menu_insert("Browse::Search", 1);
      }
      else {
        $text = _("Search");
        menu_insert("Browse::Search", 1, $URI, $text);
      }
    }
  } // RegisterMenus()


  /**
   * \brief Given a filename, return all uploadtree.
   * \param $Item     uploadtree_pk of tree to search, if empty, do global search
   * \param $Filename filename or pattern to search for, false if unused
   * \param $tag      tag (or tag pattern mytag%) to search for, false if unused
   * \param $Page     display page number
   * \param $SizeMin  Minimum file size, -1 if unused
   * \param $SizeMax  Maximum file size, -1 if unused
   * \param $searchtype "containers" or "allfiles"
   * \return array of uploadtree recs.  Each record contains uploadtree_pk, parent, 
   *         upload_fk, pfile_fk, ufile_mode, and ufile_name
   */
  function GetResults($Item, $Filename, $tag, $Page, $SizeMin, $SizeMax, $searchtype, $License, $Copyright)
  {
    global $PG_CONN;
    $UploadtreeRecs = array();  // uploadtree record array to return
    $NeedTagfileTable = true;
    $NeedTaguploadtreeTable = true;

    if ($Item)
    {
      /* Find lft and rgt bounds for this $Uploadtree_pk  */
      $sql = "SELECT lft,rgt,upload_fk, pfile_fk FROM uploadtree WHERE uploadtree_pk = $Item;";
      $result = pg_query($PG_CONN, $sql);
      DBCheckResult($result, $sql, __FILE__, __LINE__);
      if (pg_num_rows($result) < 1)
      {
        pg_free_result($result);
        $text = _("Invalid URL, nonexistant item");
        return "<h2>$text $Uploadtree_pk</h2>";
      }
      $row = pg_fetch_assoc($result);
      $lft = $row["lft"];
      $rgt = $row["rgt"];
      $upload_pk = $row["upload_fk"];
 
       /* Check upload permission */
       $UploadPerm = GetUploadPerm($upload_pk);
       if ($UploadPerm < Auth::PERM_READ) return $UploadtreeRecs;

      pg_free_result($result);
    }

    /* Start the result select stmt */
    $SQL = "SELECT DISTINCT uploadtree_pk, parent, upload_fk, uploadtree.pfile_fk, ufile_mode, ufile_name FROM uploadtree";

    if (!empty($License))
    {
      $SQL .= ", ( SELECT license_ref.rf_shortname, license_file.rf_fk, license_file.pfile_fk
                  FROM license_file JOIN license_ref ON license_file.rf_fk = license_ref.rf_pk) AS pfile_ref";
    }
    if (!empty($Copyright))
    {
      $SQL .= ",copyright";
    }

    /* Figure out the tag_pk's of interest */
    if (!empty($tag))
    {
      $sql = "select tag_pk from tag where tag ilike '$tag'";
      $result = pg_query($PG_CONN, $sql);
      DBCheckResult($result, $sql, __FILE__, __LINE__);
      if (pg_num_rows($result) < 1)
      {
        /* tag doesn't match anything, so no results are possible */
        pg_free_result($result);
        return $UploadtreeRecs;
      }

      /* Make a list of the tag_pk's that satisfy the criteria */
      $tag_pk_array = pg_fetch_all($result);
      pg_free_result($result);

      /* add the tables needed for the tag query */
      $sql = "select tag_file_pk from tag_file limit 1";
      $result = pg_query($PG_CONN, $sql);
      DBCheckResult($result, $sql, __FILE__, __LINE__);
      if (pg_num_rows($result) < 1)
      {
        /* tag_file didn't have data, don't add the tag_file table for tag query */
        $NeedTagfileTable = false;
      }
      else {
        $SQL .= ", tag_file";
      }
      pg_free_result($result);

      /* add the tables needed for the tag query */
      $sql = "select tag_uploadtree_pk from tag_uploadtree limit 1";
      $result = pg_query($PG_CONN, $sql);
      DBCheckResult($result, $sql, __FILE__, __LINE__);
      if (pg_num_rows($result) < 1)
      {
        /* tag_uploadtree didn't have data, don't add the tag_uploadtree table for tag query */
        $NeedTaguploadtreeTable = false;
      }
      else {
        $SQL .= ", tag_uploadtree";
      }
      pg_free_result($result);

      if (!$NeedTagfileTable && !$NeedTaguploadtreeTable)
        $SQL .= ", tag_file, tag_uploadtree";
    }

    /* do we need the pfile table? Yes, if any of these are a search critieria.  */
    if (!empty($SizeMin) or !empty($SizeMax))
    {
      $SQL .= ", pfile where pfile_pk=uploadtree.pfile_fk ";
      $NeedAnd = true;
    }
    else
    {
      $SQL .= " where ";
      $NeedAnd = false;
    }

    /* add the tag conditions */
    if (!empty($tag))
    {
      if ($NeedAnd) $SQL .= " AND"; 
      $SQL .= "(";
      $NeedOr = false;
      foreach ($tag_pk_array as $tagRec)
      {
        if ($NeedOr) $SQL .= " OR";
        $SQL .= "(";
        $tag_pk = $tagRec['tag_pk'];
        if ($NeedTagfileTable && $NeedTaguploadtreeTable)
          $SQL .= "(uploadtree.pfile_fk=tag_file.pfile_fk and tag_file.tag_fk=$tag_pk) or (uploadtree_pk=tag_uploadtree.uploadtree_fk and tag_uploadtree.tag_fk=$tag_pk) ";
        else if ($NeedTaguploadtreeTable)
          $SQL .= "uploadtree_pk=tag_uploadtree.uploadtree_fk and tag_uploadtree.tag_fk=$tag_pk";
        else if ($NeedTagfileTable)
          $SQL .= "uploadtree.pfile_fk=tag_file.pfile_fk and tag_file.tag_fk=$tag_pk";
        else
          $SQL .= "(uploadtree.pfile_fk=tag_file.pfile_fk and tag_file.tag_fk=$tag_pk) or (uploadtree_pk=tag_uploadtree.uploadtree_fk and tag_uploadtree.tag_fk=$tag_pk) ";
        $SQL .= ")";
        $NeedOr=1;
      }
      $NeedAnd=1;
      $SQL .= ")";
    }

    if ($Filename) 
    {
      $Filename = str_replace("'","''",$Filename); // protect DB
      if ($NeedAnd) $SQL .= " AND"; 
      $SQL .= " ufile_name ilike '$Filename'";
      $NeedAnd=1;
    }

    if (!empty($SizeMin))
    {
      if ($NeedAnd)  $SQL .= " AND";
      $SQL .= " pfile.pfile_size >= $SizeMin";
      $NeedAnd=1;
    }

    if (!empty($SizeMax))
    {
      if ($NeedAnd)  $SQL .= " AND"; 
      $SQL .= " pfile.pfile_size <= $SizeMax";
      $NeedAnd=1;
    }

    if ($Item) 
    {
      if ($NeedAnd) $SQL .= " AND"; 
      $SQL .= "  upload_fk = $upload_pk AND lft >= $lft AND rgt <= $rgt";
      $NeedAnd=1;
    }

    /* search only containers */
    $dir_ufile_mode = 536888320;
    if ($searchtype == 'containers') 
    {
      if ($NeedAnd) $SQL .= " AND"; 
      $SQL .= " ((ufile_mode & (1<<29))!=0) AND ((ufile_mode & (1<<28))=0)";
      $NeedAnd=1;
    }
    if ($searchtype == 'directory')
    {
      if ($NeedAnd) $SQL .= " AND"; 
      $SQL .= " ((ufile_mode & (1<<29))!=0) AND ((ufile_mode & (1<<28))=0) AND (ufile_mode != $dir_ufile_mode) and pfile_fk != 0";
      $NeedAnd=1;
    }

    /** license and copyright */
    if (!empty($License))
    {
      if ($NeedAnd) $SQL .= " AND"; 

      $SQL .= " uploadtree.pfile_fk=pfile_ref.pfile_fk and pfile_ref.rf_shortname ilike '$License'";
      $NeedAnd=1;
    }
    if (!empty($Copyright))
    {
      if ($NeedAnd) $SQL .= " AND"; 
      $SQL .= " uploadtree.pfile_fk=copyright.pfile_fk and copyright.content ilike '%$Copyright%'";
    }

    $Offset = $Page * $this->MaxPerPage;
    $SQL .= " ORDER BY ufile_name, uploadtree.pfile_fk";
    $SQL .= " LIMIT $this->MaxPerPage OFFSET $Offset;";
    $result = pg_query($PG_CONN, $SQL);
    DBCheckResult($result, $SQL, __FILE__, __LINE__);
    if (pg_num_rows($result)) 
    {
      while ($row = pg_fetch_assoc($result))
      {
        $UploadPerm = GetUploadPerm($row['upload_fk']);
        if ($UploadPerm < Auth::PERM_READ) continue;
        $UploadtreeRecs[] = $row;
      }
    }
    pg_free_result($result);
    return($UploadtreeRecs);
  } // GetResults()


  /** 
   * \brief print search results to stdout
   * \param $UploadtreeRecs Array of search results (uploadtree recs)
   * \param $Page page number being displayed.
   * \param $GETvars GET variables
   * \return HTML to display record results
   */
  function HTMLResults($UploadtreeRecs, $Page, $GETvars)
  {
    $Outbuf = "";
    $PageChoices = "";
    $Count = count($UploadtreeRecs);
    if ($Count == 0)
    {
      $Outbuf .=  _("No matching files.\n");
      return $Outbuf;
    }
    if (($Page > 0) || ($Count >= $this->MaxPerPage))
    {
      $Uri = Traceback_uri() . "?mod=" . $this->Name . $GETvars;
      $PageChoices = MenuEndlessPage($Page, ($Count >= $this->MaxPerPage),$Uri) . "<P />\n";
      $Outbuf .= $PageChoices;
    }
    else
      $PageChoices = "";
    $Outbuf .= UploadtreeFileList($UploadtreeRecs, "browse","view",$Page*$this->MaxPerPage + 1);

    /* put page menu at the bottom, too */
    $Outbuf .= $PageChoices;
    
    return $Outbuf;
  }


  /**
   * \brief Display the loaded menu and plugins.
   */
  function Output()
  {
    if ($this->State != PLUGIN_STATE_READY) { return; }
    global $Plugins;

    $CriteriaCount = 0;
    $V="";
    $GETvars="";
    $Item = GetParm("item",PARM_INTEGER);
    
    if ($this->OutputType != 'HTML')
    {
      return;
    }

    /* Show path if searching an item tree  (don't show on global searches) */
    if ($Item) 
    {
      $V .= Dir2Browse($this->Name,$Item,NULL,1,NULL) . "<P />\n";
      $GETvars .= "&item=$Item";
    }

    $searchtype = GetParm("searchtype",PARM_STRING);
    $GETvars .= "&searchtype=" . urlencode($searchtype);

    $Filename = GetParm("filename",PARM_RAW);
    if (!empty($Filename)) 
    {
      $CriteriaCount++;
      $GETvars .= "&filename=" . urlencode($Filename);
    }

    $tag = GetParm("tag",PARM_RAW);
    if (!empty($tag))
    {
      $CriteriaCount++;
      $GETvars .= "&tag=" . urlencode($tag);
    }

    $SizeMin = GetParm("sizemin",PARM_TEXT);
    if (!empty($SizeMin) && ($SizeMin >= 0))
    { 
      $SizeMin=intval($SizeMin); 
      $CriteriaCount++;
      $GETvars .= "&sizemin=$SizeMin";
    }

    $SizeMax = GetParm("sizemax",PARM_TEXT);
    if (!empty($SizeMax) && ($SizeMax >= 0))
    { 
      $SizeMax=intval($SizeMax); 
      $CriteriaCount++;
      $GETvars .= "&sizemax=$SizeMax";
    }

    $License = GetParm("license",PARM_RAW);
    if (!empty($License))
    {
      $CriteriaCount++;
      $GETvars .= "&license=" . urlencode($License);
    }
    $Copyright = GetParm("copyright",PARM_RAW);
    if (!empty($Copyright))
    {
      $CriteriaCount++;
      $GETvars .= "&copyright=" . urlencode($Copyright);
    }

    $Page = GetParm("page",PARM_INTEGER);

    /*******  Input form  *******/
    $V .= "<form action='" . Traceback_uri() . "?mod=" . $this->Name . "' method='POST'>\n";

    /* searchtype:  'allfiles' or 'containers' */
    $ContainersChecked = "";
    $DirectoryChecked = "";
    $AllFilesChecked = "";
    if ($searchtype == 'containers') 
      $ContainersChecked = "checked=\"checked\"";
    else if ($searchtype == 'directory')
      $DirectoryChecked = "checked=\"checked\"";
    else
      $AllFilesChecked = "checked=\"checked\"";
    $text = _("Limit search to (Note: can not limit license and copyright search on containers)");
    $text1 = _("Containers only (rpms, tars, isos, etc), including directories.");
    $V .= "<u><i><b>$text:</b></i></u><br> <input type='radio' name='searchtype' value='containers' $ContainersChecked><b>$text1</b>\n";
    $text2 = _("Containers only (rpms, tars, isos, etc), excluding directories.");
    $V .= "<br> <input type='radio' name='searchtype' value='directory' $DirectoryChecked><b>$text2</b>\n";
    $text3 = _("All Files");
    $V .= "<br> <input type='radio' name='searchtype' value='allfiles' $AllFilesChecked><b>$text3</b>\n";

    $V .= "<p><u><i><b>" . _("You must choose one or more search criteria (not case sensitive).") . "</b></i></u>";
    $V .= "<ul>\n";

    /* filename */
    $text = _("Enter the filename to find: ");
    $V .= "<li><b>$text</b>";
    $V .= "<INPUT type='text' name='filename' size='40' value='" . htmlentities($Filename) . "'>\n";
    $V .= "<br>" . _("You can use '%' as a wild-card. ");
    $V .= _("For example, '%v3.war', or 'mypkg%.tar'.");

    /* tag  */
    $text = _("Tag to find");
    $V .= "<li><b>$text:</b>  <input name='tag' size='30' value='" . htmlentities($tag) . "'>\n";

    /* file size >= */
    $text = _("File size is");
    $text1 = _(" bytes\n");
    $V .= "<li><b>$text &ge; </b><input name='sizemin' size=10 value='$SizeMin'>$text1";

    /* file size <= */
    $text = _("File size is");
    $text1 = _(" bytes\n");
    $V .= "<li><b>$text &le; </b><input name='sizemax' size=10 value='$SizeMax'>$text1";
    $V .= "</ul>\n";

    $V .= "<ul>\n";

    $V .= "<p><u><i><b>" . _("You may also choose one or more optional search filters (not case sensitive).") . "</b></i></u>";
    /* license */
    $text = _("License");
    $V .= "<li><b>$text: </b><input name='license' value='$License'>";
    $V .= "<br>" . _("For example, 'AGPL%'.");
    $text = _("Copyright");
    $V .= "<li><b>$text: </b><input name='copyright' value='$Copyright'>";
    $V .= "<br>" . _("For example, 'fsf'.");

    $V .= "</ul>\n";

    $V .= "<input type='hidden' name='item' value='$Item'>\n";
    $text = _("Search");
    $V .= "<input type='submit' value='$text'>\n";
    $V .= "</form>\n";
    /*******  END Input form  *******/

    if ($CriteriaCount)
    {
      if (empty($Page)) { $Page = 0; }
      $V .= "<hr>\n";
      $text = _("Files matching");
      $V .= "<H2>$text " . htmlentities($Filename) . "</H2>\n";
      $UploadtreeRecs = $this->GetResults($Item,$Filename,$tag,$Page,$SizeMin,$SizeMax,$searchtype,$License, $Copyright);
      $V .= $this->HTMLResults($UploadtreeRecs, $Page, $GETvars, $License, $Copyright);
    } 

    $this->vars['content'] = $V;
  }

}
$NewPlugin = new search;
$NewPlugin->Initialize();
