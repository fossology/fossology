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
 * ajax-optsForm
 * \brief display to upload from url form
 *
 * @version "$Id$"
 * Created on Feb 14, 2011 by Mark Donohoe
 */

define("TITLE_ajax_optsForm", _("Upload from a URL"));

class ajax_optsForm extends FO_Plugin
{
  public $Name = "ajax_optsForm";
  public $Title = TITLE_ajax_optsForm;
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
        /* Display more options */
        $choice .= "<h3>Additional Upload Options</h3>\n";
        $choice .= "<form name='options' enctype='multipart/form-data' method='post'>\n";
        $choice .= "<input type='radio' name='opts' value='srv' onclick='UploadSrv_Get(\"" .Traceback_uri() . "?mod=ajax_srvUpload\")' />Upload a File from the FOSSology Server<br />\n";
        $choice .= "<input type='radio' name='opts' value='osn' onclick='UploadOsN_Get(\"" .Traceback_uri() . "?mod=ajax_oneShotNomos\")' />Analyze a single file for licenses<br />\n";
        $choice .= "<input type='radio' name='opts' value='copy' onclick='UploadCopyR_Get(\"" .Traceback_uri() . "?mod=ajax_oneShotCopyright\")' />Analyze a single file for Copyrights, Email and URL's<br />\n";

        $choice .= "\n<div>\n
                   <hr>
                   <p id='optsform'></p>
                   </div>";
        $choice .= "</form>\n";
        break;
      case "Text":
        break;
      default:
        break;
    }
    if (!$this->OutputToStdout) {
      return ($choice);
    }
    print ("$choice");
    return;
  } // Output()
};
$NewPlugin = new ajax_optsForm();
?>