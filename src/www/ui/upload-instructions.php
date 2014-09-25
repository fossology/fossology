<?php
/***********************************************************
 Copyright (C) 2008-2013 Hewlett-Packard Development Company, L.P.

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

define("TITLE_upload_instructions", _("Upload Instructions"));

/**
 * \class upload_instructions extend from FO_Plugin
 * \brief Instructions for upload function
 */
class upload_instructions extends FO_Plugin
{
  function __construct()
  {
    $this->Name       = "upload_instructions";
    $this->Title      = TITLE_upload_instructions;
    $this->MenuList   = "Upload::Instructions";
    $this->MenuOrder  = 10;
    $this->Dependency = array();
    $this->DBaccess   = PLUGIN_DB_WRITE;
    parent::__construct();
  }

  /**
   * \brief Generate the text for this plugin.
   */
  function htmlContent()
  {
    $Uri = Traceback_uri();
    $html = _("FOSSology has many options for importing and uploading files for analysis.\n");
    $html .= _("The options vary based on <i>where</i> the data to upload is located.\n");
    $html .= _("The data may be located:\n");
    $html .= "<ul>\n";
    $text = _("On your browser system");
    $html .= "<li><b>$text</b>.\n";
    $text = _("Use the");
    $text1 = _("Upload File");
    $text2 = _("option to select and upload the file.");
    $html .= "$text <a href='${Uri}?mod=upload_file'>$text1</a> $text2\n";
    $html .= _("While this can be very convenient (particularly if the file is not readily accessible online),\n");
    $html .= _("uploading via your web browser can be slow for large files,\n");
    $html .= _("and files larger than 650 Megabytes may not be uploadable.\n");
    $html .= "<P />\n";
    $text = _("On a remote server");
    $html .= "<li><b>$text</b>.\n";
    $text = _("Use the");
    $text1 = _("Upload from URL");
    $text2 = _("option to specify a remote server.");
    $html .= "$text <a href='${Uri}?mod=upload_url'>$text1</a> $text2\n";
    $html .= _("This is the most flexible option, but the URL must denote a publicly accessible HTTP, HTTPS, or FTP location.\n");
    $html .= _("URLs that require authentication or human interactions cannot be downloaded through this automated system.\n");
    $html .= "<P />\n";
    $text = _("On the FOSSology web server");
    $html .= "<li><b>$text</b>.\n";
    $text = _("Use the");
    $text1 = _("Upload from Server");
    $text2 = _("option to specify a file or path on the server.");
    $html .= "$text <a href='${Uri}?mod=upload_srv_files'>$text1</a> $text2\n";
    $html .= _("This option is intended for developers who have mounted directories containing source trees.\n");
    $html .= _("The directory must be accessible via the web server's user.\n");
    $html .= "<P />\n";
    $text = _("On the version control system");
    $html .= "<li><b>$text</b>.\n";
    $text = _("Use the");
    $text1 = _("Upload from Version Control System");
    $text2 = _("option to specify URL of a repo.");
    $html .= "$text <a href='${Uri}?mod=upload_vcs'>$text1</a> $text2\n";
    $html .= "<P />\n";
    $html .= _("If your system is configured to use multiple agent servers, the data area must be\n");
    $html .= _("mounted and accessible to the FOSSology user (fossy) on every agent system.  See\n");
    $text = _("the section");
    $text1 = _("Configuring the Scheduler in the ");
    $text2 = _("Scheduler documentation");
    $html .= "$text <em>$text1</em><a href='http://www.fossology.org/projects/fossology/wiki/Sched-20'>$text2</a>.\n";
    $html .= "</ul>\n";
    if (false) $html .= $this->asciiUnrock();
    return $html;
  }
  
  private function asciiUnrock(){
    $V= '';
    $V .= "<P />\n";
    $V .= _("Select the type of upload based on where the data is located:\n");
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
    $text = _("Your computer");
    $V .= "<td bgcolor='white' align='center'><a href='${Uri}?mod=upload_file'>$text</a></td>";
    $V .= "<td bgcolor='blue'>&nbsp;</td>";
    $V .= "<td bgcolor='white'> &rarr; </td>";
    $V .= "<td bgcolor='blue'>&nbsp;</td>";
    $text = _("FOSSology web server");
    $V .= "<td bgcolor='white' align='center'><a href='${Uri}?mod=upload_srv_files'>$text</a></td>";
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
    $text = _("Remote web or FTP server");
    $V .= "<td bgcolor='white' align='center'><a href='${Uri}?mod=upload_url'>$text</a></td>";
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
    return $V;
  }
}
$NewPlugin = new upload_instructions;
$NewPlugin->Initialize();
