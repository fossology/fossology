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

class upload_instructions extends FO_Plugin
{
  public $Name       = "upload_instructions";
  public $Title      = "Upload Instructions";
  public $Version    = "1.0";
  public $MenuList   = "Upload::Instructions";
  public $MenuOrder  = 10;
  public $Dependency = array("upload_file","upload_srv_files","upload_url");
  public $DBaccess   = PLUGIN_DB_UPLOAD;

  /*********************************************
   Output(): Generate the text for this plugin.
   *********************************************/
  function Output()
  {
    if ($this->State != PLUGIN_STATE_READY) { return; }
    $V="";
    switch($this->OutputType)
    {
      case "XML":
	break;
      case "HTML":
	/* Display instructions */
	$Url = Traceback_uri();
	$V .= _("FOSSology has many options for importing and uploading files for analysis.\n");
$text = _("where");
$text1 = _(" the data to upload is located.\n");
	$V .= "The options vary based on <i>$text</i>$text1";
	$V .= _("The data may be located:\n");
	$V .= "<ul>\n";
$text = _("On your browser system");
$text1 = _(".\n");
	$V .= "<li><b>$text</b>$text1";
$text = _("Upload File");
$text1 = _(" option to select and upload the file.\n");
	$V .= "Use the <a href='${Uri}?mod=upload_file'>$text</a>$text1";
	$V .= "While this can be very convenient (particularly if the file is not readily accessible online),\n";
	$V .= _("uploading via your web browser can be slow for large files,\n");
	$V .= _("and files larger than 650 Megabytes may not be uploadable.\n");
	$V .= "<P />\n";
$text = _("On a remote server");
$text1 = _(".\n");
	$V .= "<li><b>$text</b>$text1";
$text = _("Upload from URL");
$text1 = _(" option to specify a remote server.\n");
	$V .= "Use the <a href='${Uri}?mod=upload_url'>$text</a>$text1";
	$V .= _("This is the most flexible option, but the URL must denote a publicly accessible HTTP, HTTPS, or FTP location.\n");
	$V .= _("URLs that require authentication or human interactions cannot be downloaded through this automated system.\n");
	$V .= "<P />\n";
$text = _("On the FOSSology web server");
$text1 = _(".\n");
	$V .= "<li><b>$text</b>$text1";
$text = _("Upload from Server");
$text1 = _(" option to specify a file or path on the server.\n");
	$V .= "Use the <a href='${Uri}?mod=upload_srv_files'>$text</a>$text1";
	$V .= _("This option is intended for developers who have mounted directories containing source trees.\n");
	$V .= _("The directory must be accessible via the web server's user.\n");
	$V .= "<P />\n";
	$V .= _("If your system is configured to use multiple agent servers, the data area must be\n");
	$V .= "mounted and accessible to the FOSSology user (fossy) on every agent system.  See\n";
$text = _("Configuring the Scheduler");
$text1 = _("Scheduler documentation");
	$V .= "the section <em>$text</em><a href='http://fossology.org/scheduler'>$text1</a>.\n";
	$V .= "</ul>\n";
if (0)
  {
	$V .= "<P />\n";
	$V .= _("Select the type of upload based on where the data is located:\n");
	/* ASCII ART ROCKS! */
	$V .= "<table border=0>\n";
	$V .= "<tr>";
$text = _("&nbsp;");
	$V .= "<td bgcolor='white'>$text</td>";
$text = _("&nbsp;");
	$V .= "<td bgcolor='blue'>$text</td>";
$text = _("&nbsp;");
	$V .= "<td bgcolor='blue'>$text</td>";
$text = _("&nbsp;");
	$V .= "<td bgcolor='blue'>$text</td>";
$text = _("&nbsp;");
	$V .= "<td bgcolor='white'>$text</td>";
$text = _("&nbsp;");
	$V .= "<td bgcolor='blue'>$text</td>";
$text = _("&nbsp;");
	$V .= "<td bgcolor='blue'>$text</td>";
$text = _("&nbsp;");
	$V .= "<td bgcolor='blue'>$text</td>";
$text = _("&nbsp;");
	$V .= "<td bgcolor='white'>$text</td>";
	$V .= "</tr><tr>";
$text = _("&nbsp;");
	$V .= "<td bgcolor='white'>$text</td>";
$text = _("&nbsp;");
	$V .= "<td bgcolor='blue'>$text</td>";
$text = _("Your computer");
$text1 = _("");
	$V .= "<td bgcolor='white' align='center'><a href='${Uri}?mod=upload_file'>$text</a></td>$text1";
$text = _("&nbsp;");
	$V .= "<td bgcolor='blue'>$text</td>";
$text = _(" &rarr; ");
	$V .= "<td bgcolor='white'>$text</td>";
$text = _("&nbsp;");
	$V .= "<td bgcolor='blue'>$text</td>";
$text = _("FOSSology web server");
$text1 = _("");
	$V .= "<td bgcolor='white' align='center'><a href='${Uri}?mod=upload_srv_files'>$text</a></td>$text1";
$text = _("&nbsp;");
	$V .= "<td bgcolor='blue'>$text</td>";
$text = _("&nbsp;");
	$V .= "<td bgcolor='white'>$text</td>";
	$V .= "</tr><tr>";
$text = _("&nbsp;");
	$V .= "<td bgcolor='white'>$text</td>";
$text = _("&nbsp;");
	$V .= "<td bgcolor='blue'>$text</td>";
$text = _("&nbsp;");
	$V .= "<td bgcolor='blue'>$text</td>";
$text = _("&nbsp;");
	$V .= "<td bgcolor='blue'>$text</td>";
$text = _("&nbsp;");
	$V .= "<td bgcolor='white'>$text</td>";
$text = _("&nbsp;");
	$V .= "<td bgcolor='blue'>$text</td>";
$text = _("&nbsp;");
	$V .= "<td bgcolor='blue'>$text</td>";
$text = _("&nbsp;");
	$V .= "<td bgcolor='blue'>$text</td>";
$text = _("&nbsp;");
	$V .= "<td bgcolor='white'>$text</td>";
	$V .= "</tr><tr>";
$text = _("&nbsp;");
	$V .= "<td bgcolor='white'>$text</td>";
$text = _("&nbsp;");
	$V .= "<td bgcolor='white'>$text</td>";
$text = _("&nbsp;");
	$V .= "<td bgcolor='white'>$text</td>";
$text = _("&nbsp;");
	$V .= "<td bgcolor='white'>$text</td>";
$text = _("&nbsp;");
	$V .= "<td bgcolor='white'>$text</td>";
$text = _("&nbsp;");
	$V .= "<td bgcolor='white'>$text</td>";
$text = _("&darr;");
	$V .= "<td bgcolor='white' align='center'>$text</td>";
$text = _("&nbsp;");
	$V .= "<td bgcolor='white'>$text</td>";
$text = _("&nbsp;");
	$V .= "<td bgcolor='white'>$text</td>";
	$V .= "</tr><tr>";
$text = _("&nbsp;");
	$V .= "<td bgcolor='white'>$text</td>";
$text = _("&nbsp;");
	$V .= "<td bgcolor='white'>$text</td>";
$text = _("&nbsp;");
	$V .= "<td bgcolor='white'>$text</td>";
$text = _("&nbsp;");
	$V .= "<td bgcolor='white'>$text</td>";
$text = _("&nbsp;");
	$V .= "<td bgcolor='white'>$text</td>";
$text = _("&nbsp;");
	$V .= "<td bgcolor='blue'>$text</td>";
$text = _("&nbsp;");
	$V .= "<td bgcolor='blue'>$text</td>";
$text = _("&nbsp;");
	$V .= "<td bgcolor='blue'>$text</td>";
$text = _("&nbsp;");
	$V .= "<td bgcolor='white'>$text</td>";
	$V .= "</tr><tr>";
$text = _("&nbsp;");
	$V .= "<td bgcolor='white'>$text</td>";
$text = _("&nbsp;");
	$V .= "<td bgcolor='white'>$text</td>";
$text = _("&nbsp;");
	$V .= "<td bgcolor='white'>$text</td>";
$text = _("&nbsp;");
	$V .= "<td bgcolor='white'>$text</td>";
$text = _("&nbsp;");
	$V .= "<td bgcolor='white'>$text</td>";
$text = _("&nbsp;");
	$V .= "<td bgcolor='blue'>$text</td>";
$text = _("Remote web or FTP server");
$text1 = _("");
	$V .= "<td bgcolor='white' align='center'><a href='${Uri}?mod=upload_url'>$text</a></td>$text1";
$text = _("&nbsp;");
	$V .= "<td bgcolor='blue'>$text</td>";
$text = _("&nbsp;");
	$V .= "<td bgcolor='white'>$text</td>";
	$V .= "</tr><tr>";
$text = _("&nbsp;");
	$V .= "<td bgcolor='white'>$text</td>";
$text = _("&nbsp;");
	$V .= "<td bgcolor='white'>$text</td>";
$text = _("&nbsp;");
	$V .= "<td bgcolor='white'>$text</td>";
$text = _("&nbsp;");
	$V .= "<td bgcolor='white'>$text</td>";
$text = _("&nbsp;");
	$V .= "<td bgcolor='white'>$text</td>";
$text = _("&nbsp;");
	$V .= "<td bgcolor='blue'>$text</td>";
$text = _("&nbsp;");
	$V .= "<td bgcolor='blue'>$text</td>";
$text = _("&nbsp;");
	$V .= "<td bgcolor='blue'>$text</td>";
$text = _("&nbsp;");
	$V .= "<td bgcolor='white'>$text</td>";
	$V .= "</tr>";
	$V .= "</table>\n";
  }
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
$NewPlugin = new upload_instructions;
$NewPlugin->Initialize();
?>
