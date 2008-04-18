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
	$V .= "FOSSology has many options for importing and uploading files for analysis.\n";
	$V .= "The options vary based on <i>where</i> the data to upload is located.\n";
	$V .= "The data may be located:\n";
	$V .= "<ul>\n";
	$V .= "<li><b>On your browser system</b>.\n";
	$V .= "Use the <a href='${Uri}?mod=upload_file'>Upload File</a> option to select and upload the file.\n";
	$V .= "While this can be very convenient (particularly if the file is not readily accessable online),\n";
	$V .= "uploading via your web browser can be slow for large files,\n";
	$V .= "and files larger than 650 Megabytes may not be uploadable.\n";
	$V .= "<P />\n";
	$V .= "<li><b>On a remote server</b>.\n";
	$V .= "Use the <a href='${Uri}?mod=upload_url'>Upload from URL</a> option specify a remote server.\n";
	$V .= "This is the most flexible option, but the URL must denote a publicly accessible HTTP, HTTPS, or FTP location.\n";
	$V .= "URLs that require authentication or human interactions cannot be downloaded through this automated system.\n";
	$V .= "<P />\n";
	$V .= "<li><b>On the FOSSology web server</b>.\n";
	$V .= "Use the <a href='${Uri}?mod=upload_srv_files'>Upload from Server</a> option specify a file or path on the server.\n";
	$V .= "This option is intended for developers who have mounted directories containing source trees.\n";
	$V .= "The directory must be accessible via the web server's user.\n";
	$V .= "</ul>\n";
if (0)
  {
	$V .= "<P />\n";
	$V .= "Select the type of upload based on where the data is located:\n";
	/* ASCII ART ROCKS! */
	$V .= "<table border=0>\n";
	$V .= "<tr>";
	$V .= "<td bgcolor='white'>&nbsp;</td>";
	$V .= "<td bgcolor='blue'>&nbsp;</td>";
	$V .= "<td bgcolor='blue'>&nbsp;</td>";
	$V .= "<td bgcolor='blue'>&nbsp;</td>";
	$V .= "<td bgcolor='white'>&nbsp;</td>";
	$V .= "<td bgcolor='blue'>&nbsp;</td>";
	$V .= "<td bgcolor='blue'>&nbsp;</td>";
	$V .= "<td bgcolor='blue'>&nbsp;</td>";
	$V .= "<td bgcolor='white'>&nbsp;</td>";
	$V .= "</tr><tr>";
	$V .= "<td bgcolor='white'>&nbsp;</td>";
	$V .= "<td bgcolor='blue'>&nbsp;</td>";
	$V .= "<td bgcolor='white' align='center'><a href='${Uri}?mod=upload_file'>Your computer</a></td>";
	$V .= "<td bgcolor='blue'>&nbsp;</td>";
	$V .= "<td bgcolor='white'> &rarr; </td>";
	$V .= "<td bgcolor='blue'>&nbsp;</td>";
	$V .= "<td bgcolor='white' align='center'><a href='${Uri}?mod=upload_srv_files'>FOSSology web server</a></td>";
	$V .= "<td bgcolor='blue'>&nbsp;</td>";
	$V .= "<td bgcolor='white'>&nbsp;</td>";
	$V .= "</tr><tr>";
	$V .= "<td bgcolor='white'>&nbsp;</td>";
	$V .= "<td bgcolor='blue'>&nbsp;</td>";
	$V .= "<td bgcolor='blue'>&nbsp;</td>";
	$V .= "<td bgcolor='blue'>&nbsp;</td>";
	$V .= "<td bgcolor='white'>&nbsp;</td>";
	$V .= "<td bgcolor='blue'>&nbsp;</td>";
	$V .= "<td bgcolor='blue'>&nbsp;</td>";
	$V .= "<td bgcolor='blue'>&nbsp;</td>";
	$V .= "<td bgcolor='white'>&nbsp;</td>";
	$V .= "</tr><tr>";
	$V .= "<td bgcolor='white'>&nbsp;</td>";
	$V .= "<td bgcolor='white'>&nbsp;</td>";
	$V .= "<td bgcolor='white'>&nbsp;</td>";
	$V .= "<td bgcolor='white'>&nbsp;</td>";
	$V .= "<td bgcolor='white'>&nbsp;</td>";
	$V .= "<td bgcolor='white'>&nbsp;</td>";
	$V .= "<td bgcolor='white' align='center'>&darr;</td>";
	$V .= "<td bgcolor='white'>&nbsp;</td>";
	$V .= "<td bgcolor='white'>&nbsp;</td>";
	$V .= "</tr><tr>";
	$V .= "<td bgcolor='white'>&nbsp;</td>";
	$V .= "<td bgcolor='white'>&nbsp;</td>";
	$V .= "<td bgcolor='white'>&nbsp;</td>";
	$V .= "<td bgcolor='white'>&nbsp;</td>";
	$V .= "<td bgcolor='white'>&nbsp;</td>";
	$V .= "<td bgcolor='blue'>&nbsp;</td>";
	$V .= "<td bgcolor='blue'>&nbsp;</td>";
	$V .= "<td bgcolor='blue'>&nbsp;</td>";
	$V .= "<td bgcolor='white'>&nbsp;</td>";
	$V .= "</tr><tr>";
	$V .= "<td bgcolor='white'>&nbsp;</td>";
	$V .= "<td bgcolor='white'>&nbsp;</td>";
	$V .= "<td bgcolor='white'>&nbsp;</td>";
	$V .= "<td bgcolor='white'>&nbsp;</td>";
	$V .= "<td bgcolor='white'>&nbsp;</td>";
	$V .= "<td bgcolor='blue'>&nbsp;</td>";
	$V .= "<td bgcolor='white' align='center'><a href='${Uri}?mod=upload_url'>Remote web or FTP server</a></td>";
	$V .= "<td bgcolor='blue'>&nbsp;</td>";
	$V .= "<td bgcolor='white'>&nbsp;</td>";
	$V .= "</tr><tr>";
	$V .= "<td bgcolor='white'>&nbsp;</td>";
	$V .= "<td bgcolor='white'>&nbsp;</td>";
	$V .= "<td bgcolor='white'>&nbsp;</td>";
	$V .= "<td bgcolor='white'>&nbsp;</td>";
	$V .= "<td bgcolor='white'>&nbsp;</td>";
	$V .= "<td bgcolor='blue'>&nbsp;</td>";
	$V .= "<td bgcolor='blue'>&nbsp;</td>";
	$V .= "<td bgcolor='blue'>&nbsp;</td>";
	$V .= "<td bgcolor='white'>&nbsp;</td>";
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
