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

class ui_welcome extends FO_Plugin
  {
  var $Name       = "Getting Started";
  var $Title      = "Getting Started with FOSSology";
  var $Version    = "1.0";
  var $MenuList   = "Help::Getting Started";
  var $DBaccess   = PLUGIN_DB_NONE;
  var $LoginFlag  = 0;

  function Output()
    {
    if ($this->State != PLUGIN_STATE_READY) { return; }
    $V="";
    switch($this->OutputType)
      {
      case "XML":
	break;
      case "HTML":
	$V .= "<H1>The FOSSology Toolset</H1>
FOSSology is a framework for software analysis tools. The current tools identify licenses in software, allow browsing uploaded file hierarchies, and extracts MIME type and meta data information.

<H1>FOSSology's Graphical User Interface</H1>
This website is an interface into the FOSSology project. With it, you can:
<ul>
<li>Upload files to analyze.";
	if (@$_SESSION['UserLevel'] < PLUGIN_DB_UPLOAD)
	  {
	  $V .= " (Login access required.)";
	  }
	$V .= "
<li>Unpack and store the data within the files for analysis.
<li>Invoke specialized agents to scan and analyze the files.";
	if (@$_SESSION['UserLevel'] < PLUGIN_DB_ANALYZE)
	  {
	  $V .= " (Login access required.)";
	  }
	$V .= "
<li>Store and display the analyzed results.
</ul>

<H1>How to Begin.....</H1>
The menu at the top contains all the primary capabilities of FOSSology.
<ul>
<li><b>Browse</b>: If you don't know where to start, try browsing the currently uploaded projects.
<li><b>Search</b>: Look through the uploaded projects for specific files.
<li><b>Login</b>: Depending on your account's access rights, you may be able to upload files, schedule analysis tasks, or even add new users.
</ul>

<H1>Inside FOSSology</H1>
Some parts of FOSSology helpful to know about are:
<ul>
<li>Software Repository - Stores files downloaded for analysis.
<li>Database - Stores user accounts, file information, and analysis results.
<li>Agents - Performs analysis of files and data found in the Software Repository and Database.
<li>Scheduler - Runs the agents, making efficient use of available resources.
<li>Web GUI ­ provides user access to FOSSology.
</ul>
Existing functionality is accessable from the user interface.

<H1>Need Help</H1>
Try one of the following resources:
<ul>
<li>Help tab
<li><a href='http://fossology.org/'>FOSSology documentation Wiki</a>
<li><a href='http://fossbazaar.org/'>FOSSbazaar</a> web site
</ul>";
	break;
      case "Text":
	break;
      default:
	break;
      }
    if (!$this->OutputToStdout) { return($V); }
    print($V);
    return;
    }

  };
$NewPlugin = new ui_welcome;
$NewPlugin->Initialize();
?>
