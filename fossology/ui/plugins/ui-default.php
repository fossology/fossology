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

class ui_default extends FO_Plugin
  {
  var $Name       = "Default";
  var $Title      = "Welcome to FOSSology";
  var $Version    = "2.0";
  var $MenuList   = "";
  var $LoginFlag  = 0;

  function Output()
    {
    $V="";
    if ($this->State != PLUGIN_STATE_READY) { return; }
    switch($this->OutputType)
      {
      case "XML":
	break;
      case "HTML":
	$V .= "<b>FOSSology</b> is a framework for software analysis tools. The current tools identify\n";
	$V .= "licenses in software, allow browsing of uploaded file hierarchies, and extract\n";
	$V .= "MIME type and meta data information.\n";

	$V .= "This website is an interface into the FOSSology project. With it, you can:\n";
	$V .= "<ul>\n";
	$V .= "<li>Browse uploaded files and content.\n";
	$V .= "<li>View file contents and meta data.\n";
	$V .= "<li>Display analysis results.\n";
	if (@$_SESSION['UserLevel'] >= PLUGIN_DB_DOWNLOAD)
	  {
	  $V .= "<li>Download files.\n";
	  }
	if (@$_SESSION['UserLevel'] >= PLUGIN_DB_UPLOAD)
	  {
	  $V .= "<li>Upload files to analyze.\n";
	  $V .= "<li>Unpack and store the data within the files for analysis.\n";
	  }
	if (@$_SESSION['UserLevel'] >= PLUGIN_DB_ANALYZE)
	  {
	  $V .= "<li>Invoke specialized agents to scan and analyze the files.\n";
	  }
	if (@$_SESSION['UserLevel'] >= PLUGIN_DB_USERADMIN)
	  {
	  $V .= "<li>Create and manage user accounts.\n";
	  }
	$V .= "</ul>\n";
	$V .= "<P />\n";

	$V .= "<b>Where to Begin...</b><br />\n";
	$V .= "The menu at the top contains all the primary capabilities of FOSSology.\n";
	$V .= "<ul>\n";
	$V .= "<li><b>";
	if (plugin_find_id("browse") >= 0)
	  {
	  $V .= "<a href='" . Traceback_Uri() . "?mod=browse'>Browse</a>";
	  }
	else
	  {
	  $V .= "Browse";
	  }
	$V .= "</b>: If you don't know where to start, try browsing the currently uploaded projects.\n";
	$V .= "<li><b>";
	if (plugin_find_id("search_file") >= 0)
	  {
	  $V .= "<a href='" . Traceback_Uri() . "?mod=search_file'>Search</a>";
	  }
	else
	  {
	  $V .= "Search";
	  }
	$V .= "</b>: Look through the uploaded projects for specific files.\n";
	if (empty($_SESSION['UserId']))
	  {
	  $V .= "<li><b>";
	  if (plugin_find_id("auth") >= 0)
	    {
	    $V .= "<a href='" . Traceback_Uri() . "?mod=auth'>Login</a>";
	    }
	  else
	    {
	    $V .= "Login";
	    }
	  $V .= "</b>: Depending on your account's access rights, you may be able to upload files, schedule analysis tasks, or even add new users.\n";
	  }
	$V .= "</ul>\n";

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
