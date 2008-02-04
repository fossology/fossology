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

class ui_menu extends Plugin
  {
  var $Name="menus";
  var $Version="1.0";
  var $MenuTarget="treenav";

  function PostInitialize()
    {
    global $Plugins;
    if ($this->State != PLUGIN_STATE_VALID) { return(0); } // don't run
    // Make sure dependencies are met
    foreach($this->Dependency as $key => $val)
      {
      $id = plugin_find_id($val);
      if ($id < 0) { Destroy(); return(0); } 
      } 
   
    // Add default menus (with no actions linked to plugins)
    menu_insert("Tools",10,NULL,NULL);
    menu_insert("Organize",8,NULL,NULL);
    menu_insert("Admin",6,NULL,NULL);
    menu_insert("Upload",4,NULL,NULL);

    // It worked, so mark this plugin as ready.
    $this->State = PLUGIN_STATE_READY;
    // Add this plugin to the menu
    if (!strcmp($this->MenuList,""))
      {
      menu_insert($this->MenuList,$this->MenuOrder,$this->MenuTarget,$this->Name);
      }
    return($this->State == PLUGIN_STATE_READY);
    }

  /********************************************
   menu_html(): Recursively generate the menu in HTML.
   ********************************************/
  function menu_html($Menu,$Indent)
    {
    if (!isset($Menu)) { return; }
    $V="";
    for($i=0; $i<$Indent; $i++) { $V .= " "; }
    if ($Indent == 0)
      {
      $V .= "<ul id='nav-$Indent'>\n";
      }
    else
      {
      $V .= "<ul class='nav-$Indent'>\n";
      }
    /*** NOTE: http://www.cssplay.co.uk/menus/final_drop.html identifies
         why menus fail for IE6. IE6 needs the </a> to exist outside the
	 submenus rather than before the submenus. ***/
    /*** Since his menus work under IE6 (and mine don't), I should
	 use one of his menus instead: http://www.cssplay.co.uk/menus/.
	 I'll make this change TBD...
         This looks like a good one:
	 http://www.cssplay.co.uk/menus/simple_vertical.html
     ***/
    foreach($Menu as $Key => $Val)
      {
      for($i=0; $i<$Indent; $i++) { $V .= " "; }
      $V .= '<li><a href="';
      if (isset($Val->PluginName) && ($Val->PluginName != ""))
	{
	$V .= Traceback_uri() . "?mod=" . $Val->PluginName;
	if (!isset($Val->Target) || ($Val->Target == ""))
	  {
	  $V .= '" target="basenav">';
	  }
	else
	  {
	  $V .= '" target="' . $Val->Target . '">';
	  }
	$V .= $Val->Name;
	}
      else
	{
	$V .= '#">' . $Val->Name;
	}
      if (isset($Val->SubMenu) && ($Indent > 0))
        {
	$V .= " <span>&raquo;</span>";
	}
      $V .= "</a>\n";
      $V .= $this->menu_html($Val->SubMenu,$Indent+1);
      }
    for($i=0; $i<$Indent; $i++) { $V .= " "; }
    $V .= "</ul>\n";
    return($V);
    } // menu_html()

  /********************************************
   Output(): Create the output.
   ********************************************/
  function Output()
    {
    global $MenuList;
    global $MenuMaxDepth;

    if ($this->State != PLUGIN_STATE_READY) { return(0); }
    // Put your code here
    $V="";
    switch($this->OutputType)
      {
      case "XML":
        break;
      case "HTML":
	/* Start with the style sheet */
	$V .= "<style type='text/css'>\n";
	/* Depth 0 is special: position is relative, colors are blue */
	$Depth = 0;
	$Label = "";
	if ($Depth < $MenuMaxDepth)
	  {
	  $V .= "\n/* CSS for Depth $Depth */\n";
	  $Label = "ul#nav-" . $Depth;
	  $V .= $Label . "\n";
	  $V .= "  { z-index:0; margin:0; padding:1px 0; list-style:none; width:100%; height:24px; font:normal 10pt verdana, arial, helvetica;}\n";
	  $Label .= " li";
	  $V .= $Label . "\n";
	  $V .= "  { margin:0; padding:0; display:block; float:left; position:relative; width:150px; }\n";
	  $V .= $Label . " a:link,\n";
	  $V .= $Label . " a:visited\n";
	  $V .= "  { padding:4px 0; text-decoration:none; color:white; background:darkblue; width:150px; display:block; }\n";
	  $V .= $Label . ":hover a,\n";
	  $V .= $Label . " a:hover,\n";
	  $V .= $Label . " a:active\n";
	  $V .= "  { padding:4px 0; color:white; background:blue; width:150px; display:block; }\n";
	  $V .= $Label . " a span\n";
	  $V .= "  { position:absolute; top:0; left:135px; font-size:12pt; color:white; }\n";
	  $Depth++;
	  }

	/* Depth 1 is special: position is absolute. Left is 0, top is 24 */
	if ($Depth < $MenuMaxDepth)
	  {
	  $V .= "\n/* CSS for Depth $Depth */\n";
	  $V .= $Label . " ul.nav-" . $Depth . "\n";
	  $V .= "  { z-index:1; margin:0; padding:1px 0; display:none; left:0px; width:150px; position:absolute; top:24px; }\n";
	  $V .= $Label . ":hover ul.nav-" . $Depth . "\n";
	  $V .= "  { display:block; }\n";
	  $Label .= " ul.nav-" . $Depth . " li";
	  $V .= $Label . "\n";
	  $V .= "  { margin:0; padding:0; display:block; position:relative; width:150px; }\n";
	  $V .= $Label . " a:link,\n";
	  $V .= $Label . " a:visited\n";
	  $V .= "  { padding:4px 0; color:white; background:darkred; width:150px; display:block; }\n";
	  $V .= $Label . ":hover a,\n";
	  $V .= $Label . " a:active,\n";
	  $V .= $Label . " a:hover\n";
	  $V .= "  { padding:4px 0; color:white; background:red; width:150px; display:block; }\n";
	  $V .= $Label . " a span\n";
	  $V .= "  { position:absolute; top:0; left:135px; font-size:12pt; color:white; }\n";
	  $Depth++;
	  }

	/* Depth 2+ is recursive: position is absolute. Left is 150*(Depth-1), top is 0 */
	for( ; $Depth < $MenuMaxDepth; $Depth++)
	  {
	  $V .= "\n/* CSS for Depth $Depth */\n";
	  $V .= $Label . " ul.nav-" . $Depth . "\n";
	  $V .= "  { z-index:$Depth; margin:0; padding:1px 0; display:none; left:150px; width:150px; position:absolute; top:-1px; }\n";
	  $V .= $Label . ":hover ul.nav-" . $Depth . "\n";
	  $V .= "  { display:block; }\n";
	  $Label .= " ul.nav-" . $Depth . " li";
	  $V .= $Label . "\n";
	  $V .= "  { margin:0; padding:0; display:block; position:relative; width:150px; }\n";
	  $V .= $Label . " a:link,\n";
	  $V .= $Label . " a:visited\n";
	  $V .= "  { padding:4px 0; color:white; background:darkred; width:150px; display:block; }\n";
	  $V .= $Label . ":hover a,\n";
	  $V .= $Label . " a:active,\n";
	  $V .= $Label . " a:hover\n";
	  $V .= "  { padding:4px 0; color:white; background:red; width:150px; display:block; }\n";
	  $V .= $Label . " a span\n";
	  $V .= "  { position:absolute; top:0; left:135px; font-size:12pt; color:white; }\n";
	  }

	$V .= "</style>\n";

	/* Then display the menu */
	$V .= $this->menu_html($MenuList,0);
        break;
      case "Text":
        break;
      default:
        break;
      }
    if (!$this->OutputToStdout) { return($V); }
    print "$V";
    return;
    }

  };
$NewPlugin = new ui_menu;
$NewPlugin->Initialize();

?>
