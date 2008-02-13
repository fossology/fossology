<?php
/***********************************************************
 Copyright (C) 2008 Hewlett-Packard Development Company, L.P.

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

/*************************************************************
 Code for creating a menu list (2D linked list) from a set of plugins.
 *************************************************************/

class menu
  {
  var $Name="";		// name of the menu item
  var $URI=NULL;	// URI for the plugin (everything after the "?mod=")
  var $Order=0;		// Used for ordering menu items
  var $Target=NULL;	// recommended name of window for showing results
  var $MaxDepth=0;	// How deep is SubMenu?
  var $SubMenu=NULL;	// list to submenu list
  };

/*********************************
 Global array: don't touch!
 *********************************/
$MenuList=array();
$MenuMaxDepth=0;	// how deep is the tree (for UI display)

/*****************************************
 menu_cmp(): Compare two menu items for sorting.
 *****************************************/
function menu_cmp(&$a,&$b)
{
  if ($a->Order > $b->Order)  { return(-1); }
  if ($a->Order < $b->Order)  { return(1); }
  return(strcmp($a->Name,$b->Name));
} // menu_cmp()

/***********************************************
 menu_insert_r(): Given a Path, order level for the last
 item, and a plugin name, insert the menu item.
 This is VERY recursive and returns the new menu.
 If $URI is blank, nothing is added.
 $LastOrder is used for grouping items in order.
 ***********************************************/
function menu_insert_r(&$Menu,$Path,$LastOrder=0,$Target=NULL,$URI=NULL,$Depth)
{
  $AddNew=0;
  $PathParts = explode("::",$Path,2);
  if (!isset($PathParts[0]) || !strcmp($PathParts[0],""))
	{ return(0); } // nothing to do
  if (!isset($PathParts[1])) { $Order = $LastOrder; }
  else { $Order = -1; }

  /*****
   $Menu is the top of the list.
   $M is an object in the list.
   *****/

  /* Check if the name exists in the array */
  $M=NULL;
  if (is_array($Menu))
    {
    foreach($Menu as $Key => $Val)
      {
      if (!strcmp($Val->Name,$PathParts[0])) { $M = &$Menu[$Key]; break;}
      }
    }

  /* if it does not exist in the array, then add it */
  if (empty($M))
    {
    $AddNew=1;
    $M = new menu;
    $M->Name = $PathParts[0];
    }

  /* $M is set! See if we need to traverse submenus */
  if ($Order == -1)
    {
    $Depth = menu_insert_r($M->SubMenu,$PathParts[1],$LastOrder,$Target,$URI,$Depth+1);
    $NewDepth = $Depth + 1;
    if ($M->MaxDepth < $NewDepth)
	{
	$M->MaxDepth = $NewDepth;
	}
    }
  else
    {
    /* No traversal -- save the final values */
    $M->Order = $Order;
    $M->Target = $Target;
    $M->URI = $URI;
    }

  if ($AddNew == 1)
    {
    if (isset($Menu)) { array_push($Menu,$M); }
    else { $Menu = array($M); }
    usort($Menu,menu_cmp);
    }

  global $MenuMaxDepth;
  if ($Depth+1 > $MenuMaxDepth) { $MenuMaxDepth=$Depth+1; }
  return($M->MaxDepth);
} // menu_insert_r()

/***********************************************
 menu_insert(): Given a Path, order level for the last
 item, and optional plugin name, insert the menu item.
 ***********************************************/
function menu_insert($Path,$LastOrder=0,$URI=NULL,$Target=NULL)
{
  global $MenuList;
  menu_insert_r(&$MenuList,$Path,$LastOrder,$Target,$URI,0);
} // menu_insert()

/***********************************************
 menu_find(): Given a top-level menu name, return
 the list of sub-menus below it and max depth of menu.
 $Name may be a "::" separated list.
 ***********************************************/
function menu_find($Name,&$MaxDepth,$Menu=NULL)
{
  global $MenuList;
  if (empty($Menu)) { $Menu = $MenuList; }
  if (empty($Name)) { return($Menu); }
  $PathParts = explode("::",$Name,2);
  foreach($Menu as $Key => $Val)
    {
    if ($Val->Name == $PathParts[0])
	{
	if (empty($PathParts[1]))
		{
		$MaxDepth = $Val->MaxDepth;
		return($Val->SubMenu);
		}
	else { return(menu_find($Val->SubMenu,$PathParts[1],$MaxDepth)); }
	}
    }
  return(NULL);
} // menu_find()

/***********************************************
 menu_to_1html(): Take a menu and render it as
 one HTML line.  This ignores submenus!
 If $ShowAll==0, then items without hyperlinks are hidden.
 ***********************************************/
function menu_to_1html(&$Menu,$ShowRefresh=1,$ShowAll=1)
{
  $V = "";
  $First=1;
  if (!empty($Menu))
    {
    foreach($Menu as $Val)
      {
      if (!empty($Val->URI))
	{
	if (!$First) { $V .= " | "; }
	$V .= "<a href='" . Traceback_uri() . "?mod=" . $Val->URI . "'>";
	$V .= $Val->Name;
	$V .= "</a>";
	}
      else if ($ShowAll)
	{
	if (!$First) { $V .= " | "; }
	$V .= $Val->Name;
	}
      $First=0;
      }
    }
  if ($ShowRefresh)
    {
    if (!$First) { $V .= " | "; }
    $V .= "<a href='" . Traceback() . "'>Refresh</a>";
    }
  return($V);
} // menu_to_1html()

/***********************************************
 menu_print(): Debugging code for printing the menu.
 This is recursive.
 ***********************************************/
function menu_print(&$Menu,$Indent)
{
  if (!isset($Menu)) { return; }
  foreach($Menu as $Key => $Val)
    {
    for($i=0; $i<$Indent; $i++)
      {
      print " ";
      }
    print "$Val->Name ($Val->Order,$Val->URI)\n";
    menu_print($Val->SubMenu,$Indent+1);
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
?>
