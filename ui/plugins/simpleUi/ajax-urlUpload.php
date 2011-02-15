<?php
/*
 Copyright (C) 2011 Hewlett-Packard Development Company, L.P.

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
 */

global $GlobalReady;
if (!isset($GlobalReady)) {
  exit;
}

/**
 * ajax-urlUpload
 * \brief display to upload from url form
 *
 * @version "$Id$"
 * Created on Feb 14, 2011 by Mark Donohoe
 */

define("TITLE_ajax_urlUpload", _("Upload from a URL"));

class ajax_urlUpload extends FO_Plugin
{
  public $Name = "ajax_urlUpload";
  public $Title = TITLE_ajax_urlUpload;
  public $Version = "1.0";
  public $Dependency = array();
  public $DBaccess = PLUGIN_DB_UPLOAD;
  public $NoHTML     = 1; /* This plugin needs no HTML content help */
  public $LoginFlag = 0;

  /*
   Output(): Generate the text for this plugin.
   */
  function Output()
  {
    if ($this->State != PLUGIN_STATE_READY) {
      return;
    }
    $V = "";
    switch ($this->OutputType)
    {
      case "XML":
        break;
      case "HTML":

        /* Set default values */
        if (empty($GetURL))
        {
          $GetURL = 'http://';
        }
        /* Display instructions */
        $V .= _("This option permits uploading a single file (which may be iso, tar, rpm, jar, zip, bz2, msi, cab, etc.) from a remote web or FTP server to FOSSology.\n");
        $V .= _("The file to upload must be accessible via a URL and must not require human interaction ");
        $V .= _("such as login credentials.\n");
        /* Display the form */
        $V .= "<form name='url' id='url' enctype='multipart/form-data' method='post'>\n"; // no url = this url
        $V .= "<input type='hidden' name='uploadform' value='urlupload'>\n";
        $V .= "<ol>\n";
        $text = _("Select the folder for storing the uploaded file:");
        $V .= "<li>$text\n";
        $V .= "<select name='folder'>\n";
        $V .= FolderListOption(-1, 0);
        $V .= "</select><P />\n";
        $text = _("Enter the URL to the file:");
        $V .= "<li>$text<br />\n";
        $V .= "<INPUT type='text' name='geturl' size=60 value='" . htmlentities($GetURL) . "'/><br />\n";
        $text = _("NOTE");
        $text1 = _(": If the URL requires authentication or navigation to access, then the upload will fail. Only provide a URL that goes directly to the file. The URL can begin with HTTP://, HTTPS://, or FTP://.");
        $V .= "<b>$text</b>$text1<P />\n";
        $text = _("(Optional) Enter a viewable name for this file:");
        $V .= "<li>$text<br />\n";
        $text = _("NOTE");
        $text1 = _(": If no name is provided, then the uploaded file name will be used.");
        $V .= "<b>$text</b>$text1<P />\n";
        $V .= "<INPUT type='text' name='name' size=60 value='" . htmlentities($Name) . "'/><br />\n";
        $V .= "</ol>\n";
        $text = _("Upload");
        $V .= "<input type='submit' value='$text!'>\n";
        $V .= "</form>\n";
        break;
      case "Text":
        break;
      default:
        break;
    }
    if (!$this->OutputToStdout) {
      return ($V);
    }
    print ("$V");
    return;
  } // Output()
};
$NewPlugin = new ajax_urlUpload();
?>