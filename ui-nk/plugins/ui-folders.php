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

class ui_folders extends Plugin
  {
  var $Name="folders";
  var $Version="1.0";
  var $MenuList="Tools::Folders (refresh)";
  var $MenuTarget="treenav";
  var $Dependency=array("db");

  /***********************************************************
   GetTopFolder(): Return the ID of the top-most folder.
   In the future, there will be USER-specific trees.
   This is a stub for selecting by user-name.
   ***********************************************************/
  function GetTopFolder()
    {
    global $Plugins;
    $DB = &$Plugins[plugin_find_id("db")];
    if (!empty($username)) { $Where = "WHERE user_name='$username'"; }
    else { $Where = ""; }
    /* Get the list of folders */
    $Results = $DB->Action("SELECT root_folder_fk FROM users $Where ORDER BY user_pk ASC LIMIT 1;");
    $Row = $Results[0];
    return($Row['root_folder_fk']);
    } // GetTopFolder()

  /***********************************************************
   ListFolderOption(): Create the tree, using OPTION tags.
   The caller must already have created the FORM and SELECT tags.
   It returns the full HTML.
   This is recursive!
   NOTE: If there is a recursive loop in the folder table, then
   this will loop INFINITELY.
   ***********************************************************/
  function ListFolderOption($ParentFolder,$Depth)
    {
    global $Plugins;
    $DB = &$Plugins[plugin_find_id("db")];
    if ($ParentFolder == "") { return; }
    $V="";
    $V .= "<option value='$ParentFolder'>\n";
    if ($Depth != 0) { $V .= "&nbsp;&nbsp;"; }
    for($i=1; $i < $Depth; $i++) { $V .= "&nbsp;&nbsp;"; }
    /* Load this folder's name */
    $Results = $DB->Action("SELECT folder_name FROM folder WHERE folder_pk=$ParentFolder LIMIT 1;");
    $Name = trim($Results[0]['folder_name']);
    if ($Name == "") { $Name = "[default]"; }
    /* Load any subfolders */
    $Results = $DB->Action("SELECT folder_pk FROM leftnav WHERE parent=$ParentFolder ORDER BY name;");
    /* Now create the HTML */
    $V .= htmlentities($Name);
    $V .= "</option>\n";
    if (isset($Results[0]['folder_pk']))
      {
      $Hide="";
      if ($Depth > 0) { $Hide = "style='display:none;'"; }
      $V .= "<div id='TreeDiv-$ParentFolder' $Hide>\n";
      foreach($Results as $R)
        {
	$V .= $this->ListFolderOption($R['folder_pk'],$Depth+1);
	}
      $V .= "</div>\n";
      }
    return($V);
    } // ListFolderOption()

  /***********************************************************
   ListFolderTree(): Create the tree, using DIVs.
   It returns the full HTML.
   This is recursive!
   NOTE: If there is a recursive loop in the folder table, then
   this will loop INFINITELY.
   ***********************************************************/
  function ListFolderTree($ParentFolder,$Depth)
    {
    global $Plugins;
    $DB = &$Plugins[plugin_find_id("db")];
    if ($ParentFolder == "") { return; }
    $V="";
    if ($Depth != 0) { $V .= "<font color='white'>+&nbsp;</font>"; }
    for($i=1; $i < $Depth; $i++) { $V .= "<font class='item' color='white'>+&nbsp;</font>"; }
    /* Load this folder's name */
    $Results = $DB->Action("SELECT folder_name FROM folder WHERE folder_pk=$ParentFolder LIMIT 1;");
    $Name = trim($Results[0]['folder_name']);
    if ($Name == "") { $Name = "[default]"; }
    /* Load any subfolders */
    $Results = $DB->Action("SELECT folder_pk FROM leftnav WHERE parent=$ParentFolder ORDER BY name;");
    /* Now create the HTML */
    if ($Depth > 0) { $V .= "<font class='item'>"; }
    else { $V .= "<font>"; }
    if (isset($Results[0]['folder_pk']))
      {
      $V .= '<a href="javascript:ShowHide(' . "'TreeDiv-$ParentFolder'" . ')">+</a>';
      }
    else
      {
      $V .= "<font color='gray'>&ndash;</font>";
      }
    $V .= "&nbsp;</font>" . htmlentities($Name) . "<br>\n";
    if (isset($Results[0]['folder_pk']))
      {
      $Hide="";
      if ($Depth > 0) { $Hide = "style='display:none;'"; }
      $V .= "<div id='TreeDiv-$ParentFolder' $Hide>\n";
      foreach($Results as $R)
        {
	$V .= $this->ListFolderTree($R['folder_pk'],$Depth+1);
	}
      $V .= "</div>\n";
      }
    return($V);
    } /* ListFolderTree() */

  /***********************************************************
   Output(): This function returns the scheduler status.
   ***********************************************************/
  function Output()
    {
    if ($this->State != PLUGIN_STATE_READY) { return(0); }
    $V="";
    global $Plugins;
    $DB = &$Plugins[plugin_find_id("db")];
    switch($this->OutputType)
      {
      case "XML":
	break;
      case "HTML":
	/* Create Javascript to show/hide named elements (will be DIVs) */
	$V .= "<script language='javascript'>\n";
	$V .= "<!--\n";
	$V .= "function ShowHide(name)\n";
	$V .= "  {\n";
	$V .= "  if (name.length < 1) { return; }\n";
if (0){
	$V .= "  if (document.getElementById(name).style.display == 'none')\n";
	$V .= "    { document.getElementById(name).style.display = 'block'; }\n";
	$V .= "  else\n";
	$V .= "    { document.getElementById(name).style.display = 'none'; }\n";
}
else
{
	$V .= "  var Element, State;\n";
	$V .= "  if (document.getElementById) // standard\n";
	$V .= "    { Element = document.getElementById(name); }\n";
	$V .= "  else if (document.all) // IE 4, 5, beta 6\n";
	$V .= "    { Element = document.all[name]; }\n";
	$V .= "  else // if (document.layers) // Netscape 4 and older\n";
	$V .= "    { Element = document.layers[name]; }\n";
	$V .= "  State = Element.style;\n";
	$V .= "  if (State.display == 'none') { State.display='block'; }\n";
	$V .= "  else { State.display='none'; }\n";
}
	$V .= "  }\n";
	$V .= "-->\n";
	$V .= "</script>\n";

	$V .= "<style type='text/css'>\n";
	$V .= ".text { height:24px; font:normal 10pt verdana, arial, helvetica; }\n";
	$V .= ".item { height:24px; border-style: none; border-left: thin dotted; border-color: gray; font:normal 10pt verdana, arial, helvetica; }\n";
	$V .= "a { text-decoration:none; }\n";
	$V .= "div { padding:0; margin:0; }\n";
	$V .= "</style>\n";

	$V .= "<font style='text-decoration:none; height:24px; font:normal 10pt verdana, arial, helvetica;'>\n";

	/* Display the tree */
	$V .= $this->ListFolderTree($this->GetTopFolder(),0);
	$V .= "</font>\n";
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
$NewPlugin = new ui_folders;
$NewPlugin->Initialize();

?>
