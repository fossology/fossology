<?php
/***********************************************************
 Copyright (C) 2008-2011 Hewlett-Packard Development Company, L.P.

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

define("TITLE_ui_default", _("Welcome to FOSSology"));

class ui_default extends FO_Plugin
  {
  var $Name       = "Default";
  var $Title      = TITLE_ui_default;
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
    $text1 = _("is a framework for software analysis tools.");
	$V .= "<b>$text</b> $text1 ";

	$V .= _("With it, you can:");
	$V .= "<ul>\n";
    $text = _("Upload files into the fossology repository.");
	$V .= "<li>$text\n";

    $text = _("Unpack files (tar, bz2, iso's, and many others) into its component files.");
	$V .= "<li>$text\n";
    
    $text = _("Browse upload file trees.");
	$V .= "<li>$text\n";

    $text = _("View file contents and meta data.");
	$V .= "<li>$text\n";

    $text = _("Scan for software licenses.");
	$V .= "<li>$text\n";

    $text = _("Scan for copyrights and other author information.");
	$V .= "<li>$text\n";

    $text = _("Tag files and attach notes.");
    $V .= "<li>$text\n";

    $text = _("Report files with your a custom classification scheme.");
    $V .= "<li>$text\n";

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
