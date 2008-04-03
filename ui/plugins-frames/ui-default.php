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

class ui_default extends Plugin
  {
  var $Type=PLUGIN_UI;
  var $Name="Default";
  var $Version="1.0";
  var $MenuList="";

  var $Dependency=array("topnav","treenav","basenav");

  function OutputDetail()
    {
    $V="";
    if ($this->State != PLUGIN_STATE_READY) { return; }
    switch($this->OutputType)
      {
      case "XML":
	break;
      case "HTML":
	$Uri = $_SERVER['REQUEST_URI'];
	$V .= "<title>FOSSology Repo Viewer</title>\n";
	$V .= "<frameset rows='106,*' border=0>\n";
	$V .= "  <frame name=topnav src='$Uri?mod=topnav'>\n";
	$V .= "  <frameset cols='25%,*' border=5 onResize='if (navigator.family == 'nn4') window.location.reload()'>\n";
	$V .= "    <frame name=treenav src='$Uri?mod=treenav'>\n";
	$V .= "    <frame name=basenav src='$Uri?mod=basenav'>\n";
	$V .= "  </frameset>\n";
	$V .= "</frameset>\n";
	$V .= "<noframes>\n";
	$V .= "<h1>Your browser does not appear to support frames</h1>\n";
	$V .= "</noframes>\n";
	break;
      case "Text":
	break;
      default:
	break;
      }
    if (!$this->OutputToStdout) { return($V); }
    print("$V");
    return;
    }

  };
$NewPlugin = new ui_default;
$NewPlugin->Initialize();
?>
