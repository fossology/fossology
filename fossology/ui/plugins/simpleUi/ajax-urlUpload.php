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

        // Set default values
        if (empty($GetURL))
        {
          $GetURL = 'http://';
        }
        if (empty($Level)) {
          $Level = 1;
        }
        
        $Name = $Accept = $Reject = NULL;
        
        /* Display instructions */
        $intro = _("Uploading from a URL or FTP site is often the most flexible
         option, but the URL must denote a publicly accessible HTTP, HTTPS,
         or FTP location.  URLs that require authentication or human
         interactions cannot be downloaded through this upload option.\n");
        $V .= $intro;
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
        $text = _("(Optional) Enter a viewable name for this file.");
        $V .= "<br><li>$text\n";
        $text = _("NOTE");
        $text1 = _(": If no name is provided, then the uploaded file name will be used.");
        $V .= "<b>$text</b>$text1<br />\n";
        $V .= "<INPUT type='text' name='name' size=60 value='" . htmlentities($Name) . "'/><br />\n";
        $V .= "</ol>\n";
        $V .= "<h3>Advanced Usage</h3>";
        $advInstruction = _("
        The optional fields below allow multiple files or directories to be
        uploaded by using comma-separated lists of name suffixes or patterns to
        select or exclude as entries to be uploaded.  These options are for users who
        are comfortable using wildcards and patterns. For example, to select all
        the iso files in the direcotory NewISOs, the select field would contain:
        'iso'.  If there is no exclusion list, then
        all files matching the selection criteria will be uploaded.   The
        recursion depth default setting is 1.  This allows uploading all files
        refrenced on the page link or all the files in a directory.  To include
        sub-directories, increase the recursion depth. Setting the
        recursion depth to more than five could result in very large data uploads
        which might use all the available disk space and slow the network down
        considerably.");
        $adv2 = _("Use this option with care.  A large amount of data could be
        uploaded.  Make sure the FOSSology server has enough room to hold the
        uploaded data and analysis.");
        $V .= "<p>$advInstruction</p><p>$adv2</p>\n";
        $text = _("Select list: Enter comma-separated lists of file name suffixes or patterns to select.");
        $V.= "<ol><li>$text\n";
        $note1 = _("NOTE: ");
        $wildCards = _("If any of the wildcard characters, *, ?, [ or ],
        appear as an element of the list, it will be treated as a pattern, rather than a suffix.");
        $V.= "<b>$note1</b>$wildCards<br />\n";
        $V.= "<INPUT type='text' name='accept' size=60 value='" . htmlentities($Accept) . "'/><P />\n";
        $text = _("Exclude list: Enter comma-separated lists of file name suffixes or
        patterns to exclude: the same rules for wildcards apply to this list.");
        $V.= "<li>$text<br />\n";
        $V.= "<INPUT type='text' name='reject' size=60 value='" . htmlentities($Reject) . "'/><P />\n";
$text = _("Maximum recursion depth: ");
        $V.= "<li>$text";
        $V.= "<INPUT type='text' name='level' size=1 value='" . htmlentities($Level) . "'/><P />\n";
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