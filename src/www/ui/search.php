<?php
/***********************************************************
 Copyright (C) 2010-2014 Hewlett-Packard Development Company, L.P.
 Copyright (C) 2015-2017 Siemens AG

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

include_once "search-helper.php";

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
    global $PG_CONN;
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
    if (!empty($SizeMin) && is_numeric($SizeMin) && ($SizeMin >= 0))
    { 
      $SizeMin=intval($SizeMin); 
      $CriteriaCount++;
      $GETvars .= "&sizemin=$SizeMin";
      $this->vars["SizeMin"] = $SizeMin;
    }

    $SizeMax = GetParm("sizemax",PARM_TEXT);
    if (!empty($SizeMax) && is_numeric($SizeMax) && ($SizeMax >= 0))
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
      $UploadtreeRecsResult = GetResults($Item,$Filename,$tag,$Page,$SizeMin,$SizeMax,$searchtype,$License, $Copyright, $this->uploadDao, Auth::getGroupId(), $PG_CONN);
      $html = "<hr>\n";
      $message = _("The indented search results are same files in different folders");
      $html .= "<H4>$message</H4>\n";
      $text = $UploadtreeRecsResult[1] . " " . _("Files matching");
      $html .= "<H2>$text " . htmlentities($Filename) . "</H2>\n";
      $html .= $this->HTMLResults($UploadtreeRecsResult[0], $Page, $GETvars, $License, $Copyright);
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
