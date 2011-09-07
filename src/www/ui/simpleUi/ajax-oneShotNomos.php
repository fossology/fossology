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
 * ajax-oneShotNomos
 * \brief display to upload from url form
 *
 * @version "$Id: ajax-oneShotNomos.php 3942 2011-03-17 23:24:33Z rrando $"
 * Created on Feb 15, 2011 by Mark Donohoe
 */

define("TITLE_ajax_oneShotNomos", _("Upload from a URL"));

class ajax_oneShotNomos extends FO_Plugin
{
  public $Name = "ajax_oneShotNomos";
  public $Title = TITLE_ajax_oneShotNomos;
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

    global $DB;
    global $DATADIR;
    global $PROJECTSTATEDIR;

    $V = "";
    switch ($this->OutputType)
    {
      case "XML":
        break;
      case "HTML":
        /* Display instructions */
        $V .= _("This analyzer allows you to upload a single file from your
        computer for license analysis.  \n");
        $V .= _("The analysis is done in real-time. Large files may take a
        while to upload.  Due to the time it takes to upload large files, this
        method is not recommended for files larger than a few hundred kilobytes.\n");

        /* Display the form */
        $V .= "<form enctype='multipart/form-data' method='post'>\n";
        $V .= "<input type='hidden' name='uploadform' value='oneShotNomos'>\n";
        $V .= "<ol>\n";
        $V .= _("<li>Select the file to upload:<br />\n");
        $V .= "<input name='licfile' size='60' type='file' /><br />\n";
        $V .= "</ol>\n";
        $V .= "<input type='hidden' name='showheader' value='1'>";
        $V .= "<br>\n";
        $text = _("Analyze");
        $V .= "<input type='submit' value='$text!'>\n";
        $V .= "</form>\n";

      case "Text":
        break;
      default:
        break;
    }
    if (!$this->OutputToStdout)
    {
      return ($V);
    }
    print ("$V");
    return;
  } // Output()
};
$NewPlugin = new ajax_oneShotNomos();
?>