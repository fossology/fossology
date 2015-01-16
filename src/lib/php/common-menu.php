<?php
/***********************************************************
 Copyright (C) 2008-2012 Hewlett-Packard Development Company, L.P.

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
 * \file common-menu.php
 * \brief common menu functions
 */
const MENU_PATH_SEPARATOR = "::";

/**
 * \brief
 *  Code for creating a menu list (2D linked list) from a set of plugins.
 */
class menu {
  var $Name = ""; // name of the menu item
  var $URI = NULL; // URI for the plugin (everything after the "?mod=")
  var $HTML = NULL; // HTML to include (if provided, used in place of all else)
  var $Order = 0; // Used for ordering menu items
  var $Target = NULL; // recommended name of window for showing results
  var $MaxDepth = 0; // How deep is SubMenu?
  var $SubMenu = NULL;
  public $FullName; // list to submenu list

};
/*********************************
 Global array: don't touch!
 *********************************/
$MenuList = array();
$MenuMaxDepth = 0; // how deep is the tree (for UI display)
/**
 * \brief Create a "First Prev 1 2 ... Next Last" page links for paged output.
 *
 * \param $Page       Page number of the current page
 * \param $TotalPage  Last page number
 * \param $Uri        URL of the page being displayed. "&page=" will be appended to the URL
 *
 * \return string containing menu html
 */
function MenuPage($Page, $TotalPage, $Uri = '') {
  $V = "<font class='text'><center>";
  if (empty($Uri)) {
    $Uri = Traceback();
  }
  $Uri = preg_replace("/&page=[^&]*/", "", $Uri);
  /* Create first page */
  if ($Page > 0) {
    $text = _("First");
    $text1 = _("Prev");
    $V.= "<a href='$Uri&page=0'>[$text]</a> ";
    $V.= "<a href='$Uri&page=" . ($Page - 1) . "'>[$text1]</a> ";
    if ($Page > 9) {
      $V.= " ... ";
    }
  }
  /* Create previous list page */
  for ($i = $Page - 9;$i < $Page;$i++) {
    if ($i >= 0) {
      $V.= "<a href='$Uri&page=$i'>" . ($i + 1) . "</a> ";
    }
  }
  /* Show current page number */
  $V.= "<b>" . ($Page + 1) . "</b>";
  /* Create next page */
  for ($i = $Page + 1;($i <= $TotalPage) && ($i < $Page + 9);$i++) {
    $V.= " <a href='$Uri&page=$i'>" . ($i + 1) . "</a>";
  }
  if ($Page < $TotalPage) {
    if ($Page < $TotalPage - 9) {
      $V.= " ...";
    }
    $text = _("Next");
    $text1 = _("Last");
    $V.= " <a href='$Uri&page=" . ($Page + 1) . "'>[$text]</a>";
    $V.= " <a href='$Uri&page=" . ($TotalPage) . "'>[$text1]</a>";
  }
  $V.= "</center></font>";
  return ($V);
} // MenuPage
/**
 * \brief Create a "First Prev 1 2 ... Next" page links for paged output. 
 *
 * \param $Page  Page number of the current page
 * \param $Next  true display "Next" and false don't display
 * \param $Uri   URL of the page being displayed. "&page=" will be appended to the URL
 *
 * \return string containing menu html
 */
function MenuEndlessPage($Page, $Next = 1, $Uri = '') {
  $V = "<font class='text'><center>";
  if (empty($Uri)) {
    $Uri = Traceback();
  }
  $Uri = preg_replace("/&page=[^&]*/", "", $Uri);
  /* Create first page */
  if ($Page > 0) {
    $text = _("First");
    $text1 = _("Prev");
    $V.= "<a href='$Uri&page=0'>[$text]</a> ";
    $V.= "<a href='$Uri&page=" . ($Page - 1) . "'>[$text1]</a> ";
    if ($Page > 9) {
      $V.= " ... ";
    }
  }
  /* Create previous list page */
  for ($i = $Page - 9;$i < $Page;$i++) {
    if ($i >= 0) {
      $V.= "<a href='$Uri&page=$i'>" . ($i + 1) . "</a> ";
    }
  }
  /* Show current page number */
  $V.= "<b>" . ($Page + 1) . "</b>";
  /* Create next page */
  if ($Next) {
    $text = _("Next");
    $i = $Page + 1;
    $V.= " <a href='$Uri&page=$i'>" . ($i + 1) . "</a>";
    $V.= " ... <a href='$Uri&page=$i'>[$text]</a>";
  }
  $V.= "</center></font>";
  return ($V);
} // MenuEndlessPage()
/**
 * \brief Compare two menu items for sorting.
 *
 * \param $a menu a
 * \param $b menu b
 *
 * \return -1 a > b
 *         1  a < b
 *         0  a->Order = b->Order and a->Name = b->Name
 */
function menu_cmp(&$a, &$b) {
  if ($a->Order > $b->Order) {
    return (-1);
  }
  if ($a->Order < $b->Order) {
    return (1);
  }
  $rc = strcmp($a->Name, $b->Name);
  return (strcmp($a->Name, $b->Name));
} // menu_cmp()
/**
 * \brief Given a Path, order level for the last
 * item, and a plugin name, insert the menu item.
 * This is VERY recursive and returns the new menu.
 * If $URI is blank, nothing is added.
 *
 * @param $menuItems
 * @param $path
 * @param $pathRemainder
 * @param $LastOrder
 * @param $Target
 * @param $URI
 * @param $HTML
 * @param $Title
 * @return int the max depth of menu
 */
function menu_insert_r(&$menuItems, $path, $pathRemainder, $LastOrder, $Target, $URI, $HTML, &$Title)
{
  $splitPath = explode(MENU_PATH_SEPARATOR, $pathRemainder, 2);
  $pathElement = count($splitPath) > 0 ? $splitPath[0] : null;
  $pathRemainder = count($splitPath) > 1 ? $splitPath[1] : null;
  $hasPathComponent = $pathElement !== null && $pathElement !== "";

  if (!$hasPathComponent) {
    return 0;
  }

  $isLeaf = $pathRemainder === null;
  $menuItemsExist = isset($menuItems) && is_array($menuItems);

  $currentMenuItem = NULL;
  if ($menuItemsExist) {
    foreach($menuItems as &$menuItem) {
      // need to escape the [ ] or the string will not match
      if (!strcmp($menuItem->Name, $pathElement) && strcmp($menuItem->Name, "\\[BREAK\\]")) {
        $currentMenuItem = $menuItem;
        break;
      }
      else if (!strcmp($menuItem->Name, "\\[BREAK\\]") && ($menuItem->Order == $LastOrder)) {
        $currentMenuItem = $menuItem;
        break;
      }
    }
  }

  $path[] = $pathElement;
  $FullName = str_replace(" ", "_", implode(MENU_PATH_SEPARATOR, $path));

  $sortItems = false;
  $currentItemIsMissing = empty($currentMenuItem);
  if ($currentItemIsMissing) {
    $currentMenuItem = new menu;
    $currentMenuItem->Name = $pathElement;
    $currentMenuItem->FullName = $FullName;

    if (!$menuItemsExist)
    {
      $menuItems = array();
    }
    array_push($menuItems, $currentMenuItem);
    $sortItems = true;
  }

  /* $M is set! See if we need to traverse submenus */
  if ($isLeaf) {
    if ($LastOrder != 0) {
      if ($currentMenuItem->Order != $LastOrder) {
        $sortItems = true;
      }
      $currentMenuItem->Order = $LastOrder;
    }
    $currentMenuItem->Target = $Target;
    $currentMenuItem->URI = $URI;
    $currentMenuItem->HTML = $HTML;
    $currentMenuItem->Title = $Title;
  }
  else {
    $Depth = menu_insert_r($currentMenuItem->SubMenu, $path, $pathRemainder, $LastOrder, $Target, $URI, $HTML, $Title);
    $currentMenuItem->MaxDepth = max ($currentMenuItem->MaxDepth, $Depth + 1);
  }

  if ($sortItems) {
    usort($menuItems, 'menu_cmp');
  }

  array_pop($path);
  return ($currentMenuItem->MaxDepth);
} // menu_insert_r()


/**
 * \brief menu_insert(): Given a Path, order level for the last
 * item, and optional plugin name, insert the menu item.
 *
 * \param $Path      path of the new menu item
 * \param $LastOrder is used for grouping items in order.
 * \param $Target    target of the new menu item
 * \param $URI       URL link of the new menu item
 * \param $HTML      HTML of the new menu item
 * \param $Title     Title of the new menu item
 */
function menu_insert($Path, $LastOrder = 0, $URI = NULL, $Title = NULL, $Target = NULL, $HTML = NULL) 
{
  global $MenuList;
  menu_insert_r($MenuList, array(), $Path, $LastOrder, $Target, $URI, $HTML, $Title);
} // menu_insert()


/**
 * \brief Given a top-level menu name, find
 * the list of sub-menus below it and max depth of menu.
 * NOTICE this this function returns the sub menus of $Name, NOT the menu specified
 * by $Name.
 *
 * \todo rename this function to menu_find_submenus.
 *
 * \param $Name      top-level menu name, may be a "::" separated list.
 * \param $MaxDepth  max depth of menu, returned value
 * \param $Menu      menu object array (default is global $MenuList)
 *
 * \return array of sub-menus.  $MaxDepth is also returned
 */
function menu_find($Name, &$MaxDepth, $Menu = NULL) 
{
  global $MenuList;
  if (empty($Menu)) {
    $Menu = $MenuList;
  }
  if (empty($Name)) {
    return ($Menu);
  }
  $PathParts = explode(PATH_SEPARATOR, $Name, 2);
  foreach($Menu as $Key => $Val) {
    if ($Val->Name == $PathParts[0]) {
      if (empty($PathParts[1])) {
        $MaxDepth = $Val->MaxDepth;
        return ($Val->SubMenu);
      }
      else {
        return (menu_find($PathParts[1], $MaxDepth, $Val->SubMenu));
      }
    }
  }
  return (NULL);
} // menu_find()


/**
 * \brief Take a menu and render it as
 * one HTML line.  This ignores submenus!
 * This is commonly called the "micro-menu".
 *
 * \param $Menu          menu list need to show as HTML
 * \param $ShowRefresh   If $ShowRefresh==1, show Refresh
 * \param $ShowTraceback If $ShowTraceback==1, show Tracback
 * \param $ShowAll       If $ShowAll==0, then items without hyperlinks are hidden.
 *
 * \return HTML string
 */
$menu_to_1html_counter = 0;
function menu_to_1html($Menu, $ShowRefresh = 1, $ShowTraceback = 0, $ShowAll = 1) 
{
  $showFullName = array_key_exists('fullmenudebug', $_SESSION) && $_SESSION['fullmenudebug'] == 1;

  $V = "";
  $Std = "";
  global $menu_to_1html_counter;
  if ($ShowTraceback) {
    global $Plugins;
    $Refresh = & $Plugins[plugin_find_id("refresh") ];
    if (!empty($Refresh)) {
      $text = _("Traceback");
      $URL = Traceback_dir() . "?" . $Refresh->GetRefresh();
      $Std.= "<a href='$URL' target='_top'>$text</a>";
    }
  }
  if ($ShowRefresh) {
    if (!empty($Std)) {
      $Std.= " | ";
    }
    $text = _("Refresh");
    $Std.= "<a href='" . Traceback() . "'>$text</a>";
  }
  $First = 1;
  if (!empty($Menu)) {
    foreach($Menu as $Val) {
      if ($Val->Name == "[BREAK]") {
        if (!$First) {
          $V.= " &nbsp;&nbsp;&bull;&nbsp;&nbsp; ";
        }
        $First = 1;
      }
      else if (!empty($Val->HTML)) {
        $V.= $Val->HTML;
        $First = 0;
      }
      else if (!empty($Val->URI)) {
        if (!$First) {
          $V.= " | ";
        }
        $V.= "<a href='" . Traceback_uri() . "?mod=" . $Val->URI . "'";
        if (!empty($Val->Title)) {
          $V.= " title='" . htmlentities($Val->Title, ENT_QUOTES) . "'";
        }
        $V.= ">";
        if ($showFullName) {
          $V.= $Val->FullName . "(" . $Val->Order . ")";
        }
        else {
          $V.= $Val->Name;
        }
        $V.= "</a>";
        $First = 0;
      }
      else if ($ShowAll) {
        if (!$First) {
          $V.= " | ";
        }
        if ($showFullName) {
          $V.= $Val->FullName . "(" . $Val->Order . ")";
        }
        else {
          $V.= $Val->Name;
        }
        $First = 0;
      }
    }
  }
  if (!empty($Std)) {
    if (!$First) {
      $V.= " &nbsp;&nbsp;&bull;&nbsp;&nbsp; ";
    }
    $V.= $Std;
    $Std = NULL;
  }
  $menu_to_1html_counter++;
  return ("<div id='menu1html-$menu_to_1html_counter' align='right' style='padding:0px 5px 0px 5px'><small>$V</small></div>");
} // menu_to_1html()


/**
 * \brief Take a menu and render it as
 * one HTML line with items in a "[name]" list.
 * This ignores submenus!
 *
 * \param $Menu     menu list need to show as list
 * \param $Parm     a list of parameters to add to the URL.
 * \param $Pre      string before "[name]"
 * \param $Post     string after "[name]"
 * \param $ShowAll  If $ShowAll==0, then items without hyperlinks are hidden.
 * \param $upload_id upload id
 * 
 * \return one HTML line with items in a "[name]" list
 */
function menu_to_1list($Menu, &$Parm, $Pre = "", $Post = "", $ShowAll = 1, $upload_id  = "") 
{
  $showFullName = array_key_exists('fullmenudebug', $_SESSION) && $_SESSION['fullmenudebug'] == 1;

  $V = "";
  if (!empty($Menu)) {
    foreach($Menu as $Val) {
      if (!empty($Val->HTML)) {
        $V.= $Pre;
        $V.= $Val->HTML;
        $V.= $Post;
      }
      else if (!empty($Val->URI)) {
        if (!empty($upload_id) && "tag" == $Val->URI)
        {
          $tagstatus = TagStatus($upload_id);
          if (0 == $tagstatus) break; // tagging on this upload is disabled
        }
        $V.= $Pre;
        $V.= "[<a href='" . Traceback_uri() . "?mod=" . $Val->URI . "&" . $Parm . "'";
        if (!empty($Val->Title)) {
          $V.= " title='" . htmlentities($Val->Title, ENT_QUOTES) . "'";
        }
        $V.= ">";
        if ($showFullName) {
          $V.= $Val->FullName . "(" . $Val->Order . ")";
        }
        else {
          $V.= $Val->Name;
        }
        $V.= "</a>]";
        $V.= $Post;
      }
      else if ($ShowAll) {
        $V.= $Pre;
        $V.= "[";
        if ($showFullName) {
          $V.= $Val->FullName . "(" . $Val->Order . ")";
        }
        else {
          $V.= $Val->Name;
        }
        $V.= "]";
        $V.= $Post;
      }
    }
  }
  return ($V);
} // menu_to_1list()


/**
 * \brief Debugging code for printing the menu.
 * This is recursive.
 *
 * \param $Menu    menu list to be printed
 * \param $Indent  indent char
 */
function menu_print(&$Menu, $Indent) 
{
  if (!isset($Menu)) {
    return;
  }
  foreach($Menu as $Key => $Val) {
    for ($i = 0;$i < $Indent;$i++) {
      print " ";
    }
    print "$Val->Name ($Val->Order,$Val->URI)\n";
    menu_print($Val->SubMenu, $Indent + 1);
  }
} // menu_print()

// DEBUG CODE
/**********
 if (0)
 {
 menu_insert("abc::def::",0,"");
 menu_insert("Applications::Accessories::Dictionary",0,"");
 menu_insert("Applications::Accessories::Ark",0,"");
 menu_insert("Places::Computer",3,"");
 menu_insert("Places::CD/DVD Creator",3,"");
 menu_insert("Places::Home Folder",4,"");
 menu_insert("Places::Network Servers",2,"");
 menu_insert("Places::Search for Files...",0,"");
 menu_insert("Places::Desktop",4,"");
 menu_insert("Places::Recent Documents",0,"");
 menu_insert("Places::Connect to Server...",2,"");
 menu_insert("Applications::Accessories::Calculator",0,"");
 menu_print($MenuList,0);
 print "Max depth: $MenuMaxDepth\n";
 }
 **********/


/**
 * \brief Remove a menu object (based on an object name)
 *  from a menu list.
 *
 * For example,
 *   $mymenu = menu_find("Browse-Pfile", $MenuDepth);
 *   $myNewMenu = menu_remove($mymenu, "Compare");
 *
 * \param $Menu   menu list the menu item remove from
 * \param $RmName remove name of menu
 *
 * \return a new menu list without $RmName
 */
function menu_remove($Menu, $RmName)
{
  $NewArray = array();
  foreach ($Menu as $MenuObj)
  {
    if ($MenuObj->Name != $RmName) $NewArray[] = $MenuObj;
  }
  return $NewArray;
}
?>
