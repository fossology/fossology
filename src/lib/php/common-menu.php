<?php
/*
 SPDX-FileCopyrightText: © 2008-2012 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: LGPL-2.1-only
*/
/**
 * \file
 * \brief Common menu functions
 */
const MENU_PATH_SEPARATOR = "::";   ///< Separator used between menu paths
const MENU_BREAK = "[BREAK]";       ///< Break menu at this

/**
 * \class menu
 * \brief
 * Code for creating a menu list (2D linked list) from a set of plugins.
 */
class menu
{
  var $Name = "";       ///< Name of the menu item
  var $URI = NULL;      ///< URI for the plugin (everything after the "?mod=")
  var $HTML = NULL;     ///< HTML to include (if provided, used in place of all else)
  var $Order = 0;       ///< Used for ordering menu items
  var $Target = NULL;   ///< Recommended name of window for showing results
  var $MaxDepth = 0;    ///< How deep is SubMenu?
  var $SubMenu = NULL;  ///< Sub menu to show
  public $FullName;     ///< List to submenu list

  /**
   * Return the name of the menu
   * @param boolean $showFullName If true, return FullName and order, else
   * return Name
   * @return string
   */
  public function getName($showFullName=false)
  {
    if ($showFullName) {
      return $this->FullName . "(" . $this->Order . ")";
    }
    return $this->Name;
  }
}

/*********************************
 Global array: don't touch!
 *********************************/
$MenuList = array();  ///< Global menu list array
$MenuMaxDepth = 0;    ///< How deep is the tree (for UI display)
/**
 * \brief Create a "First Prev 1 2 ... Next Last" page links for paged output.
 *
 * \param int $Page       Page number of the current page
 * \param int $TotalPage  Last page number
 * \param string $Uri     URL of the page being displayed. "&page=" will be
 * appended to the URL
 *
 * \return String containing menu HTML
 */
function MenuPage($Page, $TotalPage, $Uri = '')
{
  $V = "<ul class='pagination pagination-sm justify-content-center'>";
  if (empty($Uri)) {
    $Uri = Traceback();
  }
  $Uri = preg_replace("/&page=[^&]*/", "", $Uri);
  /* Create first page */
  if ($Page > 0) {
    $text = _("First");
    $text1 = _("Prev");
    $V.= "<li class='page-item'><a class='page-link' href='$Uri&page=0'>$text</a></li>";
    $V.= "<li class='page-item'><a class='page-link' href='$Uri&page=" . ($Page - 1) . "'>$text1</a></li>";
    if ($Page > 9) {
      $V.= " ... ";
    }
  }
  /* Create previous list page */
  for ($i = $Page - 9;$i < $Page;$i++) {
    if ($i >= 0) {
      $V.= "<li class='page-item'><a class='page-link' href='$Uri&page=$i'>" . ($i + 1) . "</a></li>";
    }
  }
  /* Show current page number */
  $V.= "<li class='page-item active'><a class='page-link' href='#'>" . ($Page + 1) . "</a></li>";
  /* Create next page */
  for ($i = $Page + 1;($i <= $TotalPage) && ($i < $Page + 9);$i++) {
    $V.= "<li class='page-item'><a class='page-link' href='$Uri&page=$i'>" . ($i + 1) . "</a></li>";
  }
  if ($Page < $TotalPage) {
    if ($Page < $TotalPage - 9) {
      $V.= " ...";
    }
    $text = _("Next");
    $text1 = _("Last");
    $V.= "<li class='page-item'><a class='page-link' href='$Uri&page=" . ($Page + 1) . "'>$text</a></li>";
    $V.= "<li class='page-item'><a class='page-link' href='$Uri&page=" . ($TotalPage) . "'>$text1</a></li>";
  }
  $V.= "</ul>";
  return ($V);
} // MenuPage

/**
 * \brief Create a "First Prev 1 2 ... Next" page links for paged output.
 *
 * \param int $Page   Page number of the current page
 * \param bool $Next  True display "Next" and false don't display
 * \param string $Uri URL of the page being displayed. "&page=" will be appended to the URL
 *
 * \return String containing menu HTML
 */
function MenuEndlessPage($Page, $Next = 1, $Uri = '')
{
  $V = "<center><ul class='pagination pagination-sm justify-content-center'>";
  if (empty($Uri)) {
    $Uri = Traceback();
  }
  $Uri = preg_replace("/&page=[^&]*/", "", $Uri);
  /* Create first page */
  if ($Page > 0) {
    $text = _("First");
    $text1 = _("Prev");
    $V.= "<li class='page-item'><a class='page-link' href='$Uri&page=0'>$text</a></li>";
    $V.= "<li class='page-item'><a class='page-link' href='$Uri&page=" . ($Page - 1) . "'>$text1</a></li>";
    if ($Page > 9) {
      $V.= " ... ";
    }
  }
  /* Create previous list page */
  for ($i = $Page - 9;$i < $Page;$i++) {
    if ($i >= 0) {
      $V.= "<li class='page-item'><a class='page-link' href='$Uri&page=$i'>" . ($i + 1) . "</a></li>";
    }
  }
  /* Show current page number */
  $V.= "<li class='page-item active'><a class='page-link' href='#'>" . ($Page + 1) . "</a></li>";
  /* Create next page */
  if ($Next) {
    $text = _("Next");
    $i = $Page + 1;
    $V.= "<li class='page-item'><a class='page-link' href='$Uri&page=$i'>" . ($i + 1) . "</a></li>";
    $V.= "<li class='page-item'><a class='page-link' href='$Uri&page=$i'>$text</a></li>";
  }
  $V.= "</ul></center>";
  return ($V);
} // MenuEndlessPage()

/**
 * \brief Compare two menu items for sorting.
 *
 * \param &$a menu a
 * \param &$b menu b
 *
 * \return -1 a > b\n
 *         1  a < b\n
 *         0  a->Order = b->Order and a->Name = b->Name
 */
function menu_cmp($a, $b)
{
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
 *
 * This is VERY recursive and returns the new menu.
 * If $URI is blank, nothing is added.
 *
 * @param[in,out] array &$menuItems Array of menu items. If null is passed,
 * new array is created.
 * @param array $path    Path of the menu item
 * @param string $pathRemainder
 * @param int $LastOrder  Order (position) of last menu item
 * @param string $Target  Name of the Menu target
 * @param string $URI     URI of the menu
 * @param string $HTML    HTML of the menu
 * @param string &$Title  Title of the menu
 * @return int The max depth of menu
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
    foreach ($menuItems as &$menuItem) {
      // need to escape the [ ] or the string will not match
      if (!strcmp($menuItem->Name, $pathElement) && strcmp($menuItem->Name, MENU_BREAK)) {
        $currentMenuItem = $menuItem;
        break;
      } else if (!strcmp($menuItem->Name, MENU_BREAK) && ($menuItem->Order == $LastOrder)) {
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

    if (! $menuItemsExist) {
      $menuItems = array();
    }
    $menuItems[] = $currentMenuItem;
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
  } else {
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
 * \brief Given a Path, order level for the last
 * item, and optional plugin name, insert the menu item.
 *
 * \param string $Path   Path of the new menu item
 * \param int $LastOrder Is used for grouping items in order.
 * \param string $URI    URL link of the new menu item
 * \param string $Title  Title of the new menu item
 * \param string $Target Target of the new menu item
 * \param string $HTML   HTML of the new menu item
 */
function menu_insert($Path, $LastOrder = 0, $URI = NULL, $Title = NULL, $Target = NULL, $HTML = NULL)
{
  global $MenuList;
  menu_insert_r($MenuList, array(), $Path, $LastOrder, $Target, $URI, $HTML, $Title);
} // menu_insert()


/**
 * \brief Given a top-level menu name, find
 * the list of sub-menus below it and max depth of menu.
 *
 * \note this this function returns the sub menus of $Name, NOT the menu specified
 * by $Name.
 *
 * \todo Rename this function to menu_find_submenus.
 *
 * \param string $Name        Top-level menu name, may be a "::" separated list.
 * \param[out] int &$MaxDepth Max depth of menu, returned value
 * \param menu $Menu          menu object array (default is global $MenuList)
 *
 * \return Array of sub-menus.  $MaxDepth is also returned
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
  $PathParts = explode('::', $Name, 2);
  foreach ($Menu as $Val) {
    if ($Val->Name == $PathParts[0]) {
      if (empty($PathParts[1])) {
        $MaxDepth = $Val->MaxDepth;
        return ($Val->SubMenu);
      } else {
        return (menu_find($PathParts[1], $MaxDepth, $Val->SubMenu));
      }
    }
  }
  return (null);
} // menu_find()


$menu_to_1html_counter = 0;  ///< Counter used by menu_to_1html()
/**
 * \brief Take a menu and render it as one HTML line.
 *
 * This ignores submenus!
 * This is commonly called the "micro-menu".
 *
 * \param menu $Menu          menu list need to show as HTML
 * \param bool $ShowRefresh   If true, show Refresh
 * \param bool $ShowTraceback If true, show Tracback
 * \param bool $ShowAll       If false, then items without hyperlinks are hidden.
 *
 * \return HTML string
 */
function menu_to_1html($Menu, $ShowRefresh = 1, $ShowTraceback = 0, $ShowAll = 1)
{
  $showFullName = isset($_SESSION) && array_key_exists('fullmenudebug', $_SESSION) && $_SESSION['fullmenudebug'] == 1;

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
    foreach ($Menu as $Val) {
      if ($Val->Name == MENU_BREAK) {
        if (!$First) {
          $V .= " &nbsp;&nbsp;&bull;";
          if ($showFullName) {
            $V .= getFullNameAddition($Val);
          }
          $V .= "&nbsp;&nbsp; ";
        }
        $First = 1;
      } else if (!empty($Val->HTML)) {
        $V.= $Val->HTML;
        if ($showFullName) {
          $V .= getFullNameAddition($Val);

        }
        $First = 0;
      } else if (!empty($Val->URI)) {
        if (!$First) {
          $V.= " | ";
        }
        $V.= "<a href='" . Traceback_uri() . "?mod=" . $Val->URI . "'";
        if (!empty($Val->Title)) {
          $V.= " title='" . htmlentities($Val->Title, ENT_QUOTES) . "'";
        }
        $V.= ">";
        if ($showFullName) {
          $V.= $Val->FullName . getFullNameAddition($Val);
        } else {
          $V.= $Val->Name;
        }
        $V.= "</a>";
        $First = 0;
      } else if ($ShowAll) {
        if (!$First) {
          $V.= " | ";
        }
        if ($showFullName) {
          $V.= $Val->FullName . getFullNameAddition($Val);
        } else {
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
    $Std = null;
  }
  $menu_to_1html_counter++;
  return ("<div id='menu1html-$menu_to_1html_counter' align='right' style='padding:0px 5px 0px 5px'><small>$V</small></div>");
}

/**
 * Get the additional string for menu full name
 * @param menu $menu menu
 * @return string
 */
function getFullNameAddition(menu $menu)
{
  return "(" . $menu->Order . ")";
} // menu_to_1html()


/**
 * \brief Take a menu and render it as
 * one HTML line with items in a "[name]" list.
 *
 * \note This ignores submenus!
 *
 * \param menu $Menu      menu list need to show as list
 * \param string &$Parm   A list of parameters to add to the URL.
 * \param string $Pre     String before "[name]"
 * \param string $Post    String after "[name]"
 * \param bool $ShowAll   If false, then items without hyperlinks are hidden.
 * \param int  $upload_id Upload id
 *
 * \return one HTML line with items in a "[name]" list
 */
function menu_to_1list($Menu, &$Parm, $Pre = "", $Post = "", $ShowAll = 1, $upload_id  = "")
{
  if (empty($Menu)) {
    return '';
  }

  $showFullName = isset($_SESSION) && array_key_exists('fullmenudebug', $_SESSION) && $_SESSION['fullmenudebug'] == 1;
  $V = "";

  foreach ($Menu as $Val) {
    if (!empty($Val->HTML)) {
      $entry = $Val->HTML;
    } else if (!empty($Val->URI)) {
      if (!empty($upload_id) && "tag" == $Val->URI) {
        $tagstatus = TagStatus($upload_id);
        if (0 == $tagstatus) {
          break; // tagging on this upload is disabled
        }
      }

      $entry = "[<a href='" . Traceback_uri() . "?mod=" . $Val->URI . "&" . $Parm . "'";
      if (!empty($Val->Title)) {
        $entry .= " title='" . htmlentities($Val->Title, ENT_QUOTES) . "'";
      }
      $entry .= ">" ;
      $entry .= $Val->getName($showFullName);
      $entry .= "</a>]";
    } else if ($ShowAll) {
      $entry = "[" . $Val->getName($showFullName) . "]";
    } else {
      continue;
    }
    $V .= $Pre . $entry . $Post;
  }
  return $V;
}


/**
 * \brief Debugging code for printing the menu.
 *
 * \note This is recursive.
 *
 * \param menu &$Menu   menu list to be printed
 * \param int  $Indent  Indentations to add
 */
function menu_print(&$Menu, $Indent)
{
  if (!isset($Menu)) {
    return;
  }
  foreach ($Menu as $Val) {
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
 * \code
 *   $mymenu = menu_find("Browse-Pfile", $MenuDepth);
 *   $myNewMenu = menu_remove($mymenu, "Compare");
 * \endcode
 *
 * \param menu $Menu     menu list the menu item remove from
 * \param string $RmName Remove name of menu
 *
 * \return A new menu list without $RmName
 */
function menu_remove($Menu, $RmName)
{
  $NewArray = array();
  foreach ($Menu as $MenuObj) {
    if ($MenuObj->Name != $RmName) {
      $NewArray[] = $MenuObj;
    }
  }
  return $NewArray;
}
