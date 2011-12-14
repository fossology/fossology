<?php
/***********************************************************
 Copyright (C) 2008-2011 Hewlett-Packard Development Company, L.P.

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

define("TITLE_jobs_showjobs_upload", _("View Jobs By Upload"));

/**
 * \class jobs_showjobs_upload extend from FO_Plugin
 * \brief select the uploaded project to view
 */
class jobs_showjobs_upload extends FO_Plugin
{
  var $Name       = "jobs_showjobs_upload";
  var $Title      = TITLE_jobs_showjobs_upload;
  var $MenuList   = "Jobs::Job History";
  var $Version    = "1.0";
  var $Dependency = array("showjobs");
  var $DBaccess   = PLUGIN_DB_UPLOAD;

  /**
   * \brief Register additional menus.
   */
  function RegisterMenus()
  {
    if ($this->State != PLUGIN_STATE_READY) {
      return(0);
    } // don't run
  }

  /**
   * \brief Generate the text for this plugin.
   */
  function Output()
  {
    if ($this->State != PLUGIN_STATE_READY) {
      return;
    }
    $V="";
    switch($this->OutputType)
    {
      case "XML":
        break;
      case "HTML":
        /* If this is a POST, then process the request. */
        $UploadPk = GetParm('upload',PARM_INTEGER);
        if (!empty($UploadPk))
        {
        }

        /* Create the AJAX (Active HTTP) javascript for doing the reply
         and showing the response. */
        $V .= ActiveHTTPscript("Uploads");
        $V .= "<script language='javascript'>\n";
        $V .= "function Uploads_Reply()\n";
        $V .= "  {\n";
        $V .= "  if ((Uploads.readyState==4) && (Uploads.status==200))\n";
        $V .= "    {\n";
        /* Remove all options */
        $V .= "    document.formy.upload.innerHTML = Uploads.responseText;\n";
        /* Add new options */
        $V .= "    }\n";
        $V .= "  }\n";
        $V .= "</script>\n";

        /* Build HTML form */
        $URI = Traceback_uri();
        $V .= "<form name='formy' action='$URI' method='GET'>\n";
        $V .= "<input type='hidden' name='mod' value='showjobs'>";
        $V .= "<input type='hidden' name='show' value='summary'>";
        $V .= "<input type='hidden' name='history' value='1'>\n";
        $text = _("View the scheduled jobs associated with an uploaded file.");
        $V .= "<P>$text<P>\n";
        $V .= "<ol>\n";
        $text = _("Select the folder containing the file to view: ");
        $V .= "<li>$text";
        $V .= "<select name='folder' ";
        $V .= "onLoad='Uploads_Get((\"" . Traceback_uri() . "?mod=upload_options&folder=-1' ";
        $V .= "onChange='Uploads_Get(\"" . Traceback_uri() . "?mod=upload_options&folder=\" + document.formy.folder.value)'>\n";
        $V .= FolderListOption(-1,0);
        $V .= "</select><P />\n";

        $text = _("Select the uploaded project to view:");
        $V .= "<li>$text";
        $V .= "<BR><select name='upload' size='10'>\n";
        $List = FolderListUploads(-1);
        foreach($List as $L)
        {
          $V .= "<option value='" . $L['upload_pk'] . "'>";
          $V .= htmlentities($L['name']);
          if (!empty($L['upload_desc']))
          {
            $V .= " (" . htmlentities($L['upload_desc']) . ")";
          }
          $V .= "</option>\n";
        }
        $V .= "</select><P />\n";
        $V .= "</ol>\n";
        $V .= "<input type='submit' value='View Jobs!'>\n";
        $V .= "</form>\n";
        break;
      case "Text":
        break;
      default:
        break;
    }
    if (!$this->OutputToStdout) {
      return($V);
    }
    print("$V");
    return;
  }
};
$NewPlugin = new jobs_showjobs_upload;
$NewPlugin->Initialize();
?>
