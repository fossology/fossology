<?php
/*
 SPDX-FileCopyrightText: © 2010-2014 Hewlett-Packard Development Company, L.P.
 SPDX-FileCopyrightText: © 2015-2022 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Dao\SearchHelperDao;
use Fossology\Lib\Db\DbManager;

class search extends FO_Plugin
{
  protected $MaxPerPage  = 100;  /* maximum number of result items per page */
  /** @var UploadDao */
  private $uploadDao;

  /** @var SearchHelperDao */
  private $searchHelperDao;

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
    $this->searchHelperDao = $GLOBALS['container']->get('dao.searchhelperdao');
  }

  function PostInitialize()
  {
    $this->State = PLUGIN_STATE_READY;
    return $this->State;
  }

  function loadUploads()
  {
    $allUploadsPre = $this->uploadDao->getActiveUploadsArray();
    $filteredUploadsList = array();

    return array_filter($allUploadsPre, function($uploadObj){
      if ($this->uploadDao->isAccessible($uploadObj->getId(), Auth::getGroupId())) {
        return true;
      }
      return false;
    });
  }

  /**
   * \brief Customize submenus.
   */
  function RegisterMenus()
  {
    menu_insert("Main::" . $this->MenuList, $this->MenuOrder, $this->Name, $this->MenuTarget);

    // For all other menus, permit coming back here.
    $fromMod = GetParm("mod", PARM_STRING);

    // Always keep paging
    $keep = array("page");

    // Keep upload context when coming from other views (license browser, etc.)
    $keep[] = "upload";
    $keep[] = "folder";
    $keep[] = "show";

    // Only keep item when coming from Browse (tree-scoped)
    if ($fromMod === "browse") {
      $keep[] = "item";
    }

    $URI = $this->Name . Traceback_parm_keep($keep);

    $Item = GetParm("item", PARM_INTEGER);
    if (! empty($Item)) {
      if (GetParm("mod", PARM_STRING) == $this->Name) {
        menu_insert("Browse::Search", 1);
      } else {
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
    if ($Count == 0) {
      $Outbuf .= _("No matching files.\n");
      return $Outbuf;
    }
    if (($Page > 0) || ($Count >= $this->MaxPerPage)) {
      $Uri = Traceback_uri() . "?mod=" . $this->Name . $GETvars;
      $PageChoices = MenuEndlessPage($Page, ($Count >= $this->MaxPerPage), $Uri) .
        "<P />\n";
      $Outbuf .= $PageChoices;
    } else {
      $PageChoices = "";
    }
    $Outbuf .= UploadtreeFileList($UploadtreeRecs, "browse", "view", $Page * $this->MaxPerPage + 1);

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
    if ($this->State != PLUGIN_STATE_READY) {
      return;
    }
    if ($this->OutputType != 'HTML') {
      return;
    }

    $this->vars['baseuri'] = Traceback_uri();
    $CriteriaCount = 0;
    $GETvars = "";

    // loads list of all uploads to put in Search filter select field
    $uploadsArray = $this->loadUploads();
    $this->vars['uploadsArray'] = $uploadsArray;

    $Item = GetParm("item", PARM_INTEGER);
    /* Show path if searching an item tree (don't show on global searches) */
    if ($Item) {
      $this->vars['pathOfItem'] = Dir2Browse($this->Name, $Item, NULL, 1, NULL) .
        "<P />\n";
      $GETvars .= "&item=$Item";
    }

    $searchtype = GetParm("searchtype", PARM_STRING);
    $GETvars .= "&searchtype=" . urlencode($searchtype);
    if ($searchtype == 'containers') {
      $this->vars["ContainersChecked"] = "checked=\"checked\"";
    } else if ($searchtype == 'directory') {
      $this->vars["DirectoryChecked"] = "checked=\"checked\"";
    } else {
      $this->vars["AllFilesChecked"] = "checked=\"checked\"";
    }

    $Filename = GetParm("filename", PARM_RAW);
    if (! empty($Filename)) {
      $CriteriaCount ++;
      $GETvars .= "&filename=" . urlencode($Filename);
      $this->vars["Filename"] = $Filename;
    }

    /**
     * Preserve upload context when opening the search page from a scoped view
     * (e.g. License Browser / Browse views). This fixes #1853 where clicking
     * Search would lose the current upload scope and default back to “All uploads”.
     */
    $Upload = GetParm("upload", PARM_INTEGER);
    $this->vars["Upload"] = (empty($Upload) ? 0 : $Upload);

    $SelectedUploadName = "All uploads";
    if ($Upload != 0) {
      $CriteriaCount++;
      $GETvars .= "&upload=" . urlencode($Upload);
      foreach ($uploadsArray as $row) {
        if ($row->getId() == $Upload) {
          $SelectedUploadName = $row->getFilename() . " from " .
            Convert2BrowserTime(date("Y-m-d H:i:s", $row->getTimestamp()));
          break;
        }
      }
    }

    $tag = GetParm("tag", PARM_RAW);
    if (!empty($tag)) {
      $CriteriaCount++;
      $GETvars .= "&tag=" . urlencode($tag);
      $this->vars["tag"] = $tag;
    }

    $SizeMin = GetParm("sizemin", PARM_TEXT);
    if (! empty($SizeMin) && is_numeric($SizeMin) && ($SizeMin >= 0)) {
      $SizeMin = intval($SizeMin);
      $CriteriaCount ++;
      $GETvars .= "&sizemin=$SizeMin";
      $this->vars["SizeMin"] = $SizeMin;
    }

    $SizeMax = GetParm("sizemax", PARM_TEXT);
    if (! empty($SizeMax) && is_numeric($SizeMax) && ($SizeMax >= 0)) {
      $SizeMax = intval($SizeMax);
      $CriteriaCount ++;
      $GETvars .= "&sizemax=$SizeMax";
      $this->vars["SizeMax"] = $SizeMax;
    }

    $License = GetParm("license", PARM_RAW);
    if (! empty($License)) {
      $CriteriaCount ++;
      $GETvars .= "&license=" . urlencode($License);
      $this->vars["License"] = $License;
    }

    $Copyright = GetParm("copyright", PARM_RAW);
    if (! empty($Copyright)) {
      $CriteriaCount ++;
      $GETvars .= "&copyright=" . urlencode($Copyright);
      $this->vars["Copyright"] = $Copyright;
    }

    $Limit = GetParm("limit", PARM_INTEGER);
    if (!empty($Limit)) {
      $GETvars .= "&limit=" . urlencode($Limit);
      $this->MaxPerPage = $Limit;
    }

    $Page = GetParm("page", PARM_INTEGER);
    if (!empty($Page)) {
      $GETvars .= "&page=" . urlencode($Page);
    }

    $this->vars["postUrl"] = Traceback_uri() . "?mod=" . self::getName();

    if ($CriteriaCount) {
      if (empty($Page)) {
        $Page = 0;
      }
      $UploadtreeRecsResult = $this->searchHelperDao->GetResults(
        $Item,
        $Filename,
        $Upload,
        $tag,
        $Page,
        $Limit,
        $SizeMin,
        $SizeMax,
        $searchtype,
        $License,
        $Copyright,
        $this->uploadDao,
        Auth::getGroupId()
      );
      $html = "<hr>\n";
      $message = _("The indented search results are same files in different folders");
      $html .= "<H4>$message</H4>\n";
      $text = $UploadtreeRecsResult[1] . " " . _("Files matching");
      $html .= "<H2>$text " . htmlentities($Filename) . " in " . htmlentities($SelectedUploadName) . "</H2>\n";
      $html .= $this->HTMLResults($UploadtreeRecsResult[0], $Page, $GETvars);
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
