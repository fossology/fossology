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

/**
 * \file ajax-fileUpload
 * \brief display to upload from file form
 *
 * \version "$Id: ajax-fileUpload.php 3989 2011-03-26 02:54:38Z rrando $"
 */

define("TITLE_ajax_fileUpload", _("Upload a New File"));

/**
 * \class ajax_fileUpload extend from FO_Plugin
 * \brief display to upload from file form
 */
class ajax_fileUpload extends FO_Plugin {

  public $Name = "ajax_fileUpload";
  public $Title = TITLE_ajax_fileUpload;
  public $Version = "1.0";
  public $Dependency = array();
  public $DBaccess = PLUGIN_DB_UPLOAD;
  public $NoHTML     = 1; /* This plugin needs no HTML content help */
  public $LoginFlag = 0;

  /**
   * \brief Generate the text for this plugin.
   */
  function Output()
  {
    $Name = NULL;

    if ($this->State != PLUGIN_STATE_READY) {
      return;
    }
    $V = "";
    switch ($this->OutputType) {
      case "XML":
        break;
      case "HTML":

        /* Set default values */
        if (empty($GetURL)) {
          $GetURL = 'http://';
        }

        /* Display the form */

        $usageFile = _("Your FOSSology server has imposed a maximum file size of");
        $usageFile .= " ".  ini_get('post_max_size') . " ";
        $usageFile .= _("bytes.");
        $V .= $usageFile;
        $V .= "<form name='file' id='file' enctype='multipart/form-data' method='post'>\n"; // no url = this url
        $V .= "<input type='hidden' name='uploadform' value='fileupload'>\n";
        $V .= "<ol>\n";
        $text = _("Select the folder for storing the uploaded file:");
        $V .= "<li>$text\n";
        $V .= "<select name='folder'>\n";
        $V .= FolderListOption(-1, 0);
        $V .= "</select><P />\n";
        $text = _("Select the file to upload:");
        $V .= "<li>$text<br />\n";
        $V .= "<input name='getfile' size='60' type='file' /><br><br />\n";
        $text = _("(Optional) Enter a viewable name for this file.  ");
        $Note = _("NOTE: ");
        $text1 = _("If no name is provided, then the uploaded file name will be used.");
        $V .= "<li>$text<b>$Note</b>$text1<br />\n";
        $V .= "<INPUT type='text' name='name' size=60 value='" . htmlentities($Name) . "'/><br />\n";
        $V .= "</ol>\n";
        $text = _("It may take time to transmit the file from your computer to this server. Please be patient.");
        $V .= "$text<br>\n";
        $text = _("Upload");
        $V .= "<input type='submit' value='$text!'
                //onclick='UpFileResults_Get(\"" .Traceback_uri() . "?mod=ajax_fileUpload\")'>\n";
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
  }
};
$NewPlugin = new ajax_fileUpload();
?>
