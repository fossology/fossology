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
	$V .= "a { text-decoration:none; color:black; }\n";
	$V .= "div { padding:0; margin:0; }\n";
	$V .= "</style>\n";

	$V .= "<font style='text-decoration:none; height:24px; font:normal 10pt verdana, arial, helvetica;'>\n";

	/* Display the tree */
	$V .= FolderListDiv(-1,0);
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
