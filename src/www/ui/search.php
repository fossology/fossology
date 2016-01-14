<?php
/***********************************************************
 Copyright (C) 2010-2014 Hewlett-Packard Development Company, L.P.
 Copyright (C) 2015 Siemens AG

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
use Fossology\Lib\Dao\UploadDao;

class search extends FO_Plugin
{
  protected $MaxPerPage  = 100;  /* maximum number of result items per page */
  /** @var UploadDao */
  private $uploadDao;
  
  function __construct()
  {
    $this->Name       = "search";
    $this->Title      = _("Search");
    $this->MenuList   = "Search";
    $this->MenuOrder  = 90;
    $this->Dependency = array("browse");
    $this->DBaccess   = PLUGIN_DB_READ;
    $this->LoginFlag  = 0;
    parent::__construct();
    
    $this->uploadDao = $GLOBALS['container']->get('dao.upload');
  }

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
        menu_insert("Browse::Search", 1, $URI, $this->MenuList);
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
   * \param $searchtype "containers", "directory" or "allfiles"
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
      $row = $this->uploadDao->getUploadEntry($Item);
      if (empty($row))
      {
        $text = _("Invalid URL, nonexistant item");
        return "<h2>$text $Item</h2>";
      }
      $lft = $row["lft"];
      $rgt = $row["rgt"];
      $upload_pk = $row["upload_fk"];
 
       /* Check upload permission */
       if (!$this->uploadDao->isAccessible($upload_pk, Auth::getGroupId())) {
        return $UploadtreeRecs;
      }
    }

    /* Start the result select stmt */
    $SQL = "SELECT DISTINCT uploadtree_pk, parent, upload_fk, uploadtree.pfile_fk, ufile_mode, ufile_name FROM uploadtree";

    if ($searchtype != "directory") {
      if (!empty($License))
      {
        $SQL .= ", ( SELECT license_ref.rf_shortname, license_file.rf_fk, license_file.pfile_fk
                  FROM license_file JOIN license_ref ON license_file.rf_fk = license_ref.rf_pk) AS pfile_ref";
      }
      if (!empty($Copyright))
      {
        $SQL .= ",copyright";
      }
    }

    /* Figure out the tag_pk's of interest */
    if (!empty($tag))
    {
      $sql = "select tag_pk from tag where tag ilike '" . pg_escape_string($tag) . "'";
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
      if ($NeedAnd) $SQL .= " AND";
      $SQL .= " ufile_name ilike '". pg_escape_string($Filename) . "'";
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
    if ($searchtype == 'containers')
    {
      if ($NeedAnd) $SQL .= " AND"; 
      $SQL .= " ((ufile_mode & (1<<29))!=0) AND ((ufile_mode & (1<<28))=0)";
      $NeedAnd=1;
    }
    $dir_ufile_mode = 536888320;
    if ($searchtype == 'directory')
    {
      if ($NeedAnd) $SQL .= " AND"; 
      $SQL .= " ((ufile_mode & (1<<29))!=0) AND ((ufile_mode & (1<<28))=0) AND (ufile_mode != $dir_ufile_mode) and pfile_fk != 0";
      $NeedAnd=1;
    }

    /** license and copyright */
    if ($searchtype != "directory") {
      if (!empty($License)) {
        if ($NeedAnd) $SQL .= " AND";

        $SQL .= " uploadtree.pfile_fk=pfile_ref.pfile_fk and pfile_ref.rf_shortname ilike '" . pg_escape_string($License) . "'";
        $NeedAnd = 1;
      }
      if (!empty($Copyright)) {
        if ($NeedAnd) $SQL .= " AND";
        $SQL .= " uploadtree.pfile_fk=copyright.pfile_fk and copyright.content ilike '%" . pg_escape_string($Copyright) . "%'";
      }
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
        if (!$this->uploadDao->isAccessible($row['upload_fk'], Auth::getGroupId())) {
          continue;
        }
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
    {
      $PageChoices = "";
    }
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
    if ($this->OutputType != 'HTML') { return; }

    $this->vars['baseuri'] = Traceback_uri();
    $CriteriaCount = 0;
    $GETvars="";

    $Item = GetParm("item",PARM_INTEGER);
    /* Show path if searching an item tree  (don't show on global searches) */
    if ($Item) 
    {
      $this->vars['pathOfItem'] = Dir2Browse($this->Name,$Item,NULL,1,NULL) . "<P />\n";
      $GETvars .= "&item=$Item";
    }

    $searchtype = GetParm("searchtype",PARM_STRING);
    $GETvars .= "&searchtype=" . urlencode($searchtype);
    if ($searchtype == 'containers')
    {
      $this->vars["ContainersChecked"] = "checked=\"checked\"";
    }
    else if ($searchtype == 'directory')
    {
      $this->vars["DirectoryChecked"] = "checked=\"checked\"";
    }
    else
    {
      $this->vars["AllFilesChecked"] = "checked=\"checked\"";
    }

    $Filename = GetParm("filename",PARM_RAW);
    if (!empty($Filename)) 
    {
      $CriteriaCount++;
      $GETvars .= "&filename=" . urlencode($Filename);
      $this->vars["Filename"] = $Filename;
    }

    $tag = GetParm("tag",PARM_RAW);
    if (!empty($tag))
    {
      $CriteriaCount++;
      $GETvars .= "&tag=" . urlencode($tag);
      $this->vars["tag"] = $tag;
    }

    $SizeMin = GetParm("sizemin",PARM_TEXT);
    if (!empty($SizeMin) && ($SizeMin >= 0))
    { 
      $SizeMin=intval($SizeMin); 
      $CriteriaCount++;
      $GETvars .= "&sizemin=$SizeMin";
      $this->vars["SizeMin"] = $SizeMin;
    }

    $SizeMax = GetParm("sizemax",PARM_TEXT);
    if (!empty($SizeMax) && ($SizeMax >= 0))
    { 
      $SizeMax=intval($SizeMax); 
      $CriteriaCount++;
      $GETvars .= "&sizemax=$SizeMax";
      $this->vars["SizeMax"] = $SizeMax;
    }

    $License = GetParm("license",PARM_RAW);
    if (!empty($License))
    {
      $CriteriaCount++;
      $GETvars .= "&license=" . urlencode($License);
      $this->vars["License"] = $License;
    }

    $Copyright = GetParm("copyright",PARM_RAW);
    if (!empty($Copyright))
    {
      $CriteriaCount++;
      $GETvars .= "&copyright=" . urlencode($Copyright);
      $this->vars["Copyright"] = $Copyright;
    }

    $Page = GetParm("page",PARM_INTEGER);

    $this->vars["postUrl"] = Traceback_uri() . "?mod=" . self::getName();

    if ($CriteriaCount)
    {
      if (empty($Page)) { $Page = 0; }
      $html = "<hr>\n";
      $text = _("Files matching");
      $html .= "<H2>$text " . htmlentities($Filename) . "</H2>\n";
      $UploadtreeRecs = $this->GetResults($Item,$Filename,$tag,$Page,$SizeMin,$SizeMax,$searchtype,$License, $Copyright);
      $html .= $this->HTMLResults($UploadtreeRecs, $Page, $GETvars, $License, $Copyright);
      $this->vars["result"] = $html;
    }
  }

  public function getTemplateName()
  {
    return "ui-search.html.twig";
  }
}

$NewPlugin = new search;
$NewPlugin->Initialize();
