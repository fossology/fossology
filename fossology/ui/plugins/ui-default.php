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
$text = _("FOSSology");
$text1 = _("is a framework for software analysis tools. The current tools identify");
	$V .= "<b>$text</b> $text1\n";
	$V .= _("licenses in software, allow browsing of uploaded file hierarchies, and extract\n");
	$V .= _("MIME type and meta data information.\n");

	$V .= _("This website is an interface into the FOSSology project. With it, you can:\n");
	$V .= "<ul>\n";
$text = _("Browse uploaded files and content.");
	$V .= "<li>$text\n";
$text = _("View file contents and meta data.");
	$V .= "<li>$text\n";
$text = _("Display analysis results.");
	$V .= "<li>$text\n";
	if (@$_SESSION['UserLevel'] >= PLUGIN_DB_DOWNLOAD)
	  {
$text = _("Download files.");
	  $V .= "<li>$text\n";
	  }
	if (@$_SESSION['UserLevel'] >= PLUGIN_DB_UPLOAD)
	  {
$text = _("Upload files to analyze.");
	  $V .= "<li>$text\n";
$text = _("Unpack and store the data within the files for analysis.");
	  $V .= "<li>$text\n";
	  }
	if (@$_SESSION['UserLevel'] >= PLUGIN_DB_ANALYZE)
	  {
$text = _("Invoke specialized agents to scan and analyze the files.");
	  $V .= "<li>$text\n";
	  }
	if (@$_SESSION['UserLevel'] >= PLUGIN_DB_USERADMIN)
	  {
$text = _("Create and manage user accounts.");
	  $V .= "<li>$text\n";
	  }
	$V .= "</ul>\n";
	$V .= "<P />\n";

$text = _("Where to Begin...");
	$V .= "<b>$text</b><br />\n";
	$V .= _("The menu at the top contains all the primary capabilities of FOSSology.\n");
	$V .= "<ul>\n";
	if (plugin_find_id("browse") >= 0)
	  {
	  $V .= "<li><b>";
$text = _("Browse");
	  $V .= "<a href='" . Traceback_Uri() . "?mod=browse'>$text</a>";
$text = _(": If you don't know where to start, try browsing the currently uploaded projects.");
	  $V .= "</b>$text\n";
	  }
	if (plugin_find_id("search_file") >= 0)
	  {
	  $V .= "<li><b>";
$text = _("Search");
	  $V .= "<a href='" . Traceback_Uri() . "?mod=search_file'>$text</a>";
$text = _(": Look through the uploaded projects for specific files.");
	  $V .= "</b>$text\n";
	  }
	if (empty($_SESSION['UserId']))
	  {
	  $V .= "<li><b>";
	  if (plugin_find_id("auth") >= 0)
	    {
$text = _("Login");
	    $V .= "<a href='" . Traceback_Uri() . "?mod=auth'>$text</a>";
	    }
	  else
	    {
	    $V .= _("Login");
	    }
$text = _(": Depending on your account's access rights, you may be able to upload files, schedule analysis tasks, or even add new users.");
	  $V .= "</b>$text\n";
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
