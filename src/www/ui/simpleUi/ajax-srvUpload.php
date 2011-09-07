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
 * ajax-srvUpload
 * \brief display to upload from srv form
 *
 * @version "$Id: ajax-srvUpload.php 3942 2011-03-17 23:24:33Z rrando $"
 * Created on Feb 14, 2011 by Mark Donohoe
 */

define("TITLE_ajax_srvUpload", _("Upload from a srv"));

class ajax_srvUpload extends FO_Plugin
{
  public $Name = "ajax_srvUpload";
  public $Title = TITLE_ajax_srvUpload;
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

        $topText = _("This option only uploads files located on the FOSSology
        web server. Use this option if you have large source directories to
        analyze. If your file is located elsewhere, then use one of the other
        upload options.");
        /* Display instructions */
        $V .= $topText;

        /* Display the form */
        $V.= "<form name='uploadsrv' method='post'>\n"; // no url = this url
        $V .= "<input type='hidden' name='uploadform' value='srvupload'>\n";
        $V.= "<ol>\n";
        $text = _("Select the folder for storing the upload:");
        $V.= "<li>$text\n";
        $V.= "<select name='folder'>\n";
        $V.= FolderListOption(-1, 0);
        $V.= "</select>\n";
        $text = _("Select the directory or file(s) on the server to upload. ");
        $Note = _("NOTE: ");
        $text1 = _("Contents under a directory will be recursively included.
        If you specify a regular expression for the filename, then multiple
        filenames will be selected.");
        $V.= "<p><li>$text<strong>$Note</strong>$text1<br />\n";
        $V.= "<input type='text' name='sourcefiles' size='60' value='" . htmlentities($SourceFiles, ENT_QUOTES) . "'/><br />\n";
        $text = _("Files can be placed in alphabetized sub-folders for organization.");
        $V.= "<p><li>$text\n";
        $V.= "<br /><input type='radio' name='groupnames' value='0'";
        if ($GroupNames != '1') {
          $V.= " checked";
        }
        $V.= " />Disable alphabetized sub-folders";
        $V.= "<br /><input type='radio' name='groupnames' value='1'";
        if ($GroupNames == '1') {
          $V.= " checked";
        }
        $V.= " />Enable alphabetized sub-folders";
        $text = _("(Optional) Enter a viewable name for this Upload. ");

        $Note = _("NOTE: ");
        $text1 = _("If no name is provided, then the uploaded file name will be used.");
        $V.= "<p><li>$text<b>$Note</b>$text1<br />\n";
        $V.= "<INPUT type='text' name='name' size=60 value='" . htmlentities($Name, ENT_QUOTES) . "' /><br />\n";
        $V.= "</ol>\n";
        $text = _("Upload");
        $V.= "<input type='submit' value='$text!'>\n";
        $V.= "</form>\n";
    }
    if (!$this->OutputToStdout) {
      return ($V);
    }
    print ("$V");
    return;
  } // Output()
};
$NewPlugin = new ajax_srvUpload();
?>