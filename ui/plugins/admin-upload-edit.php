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
/**
 * edit upload file properties
 *
 * @param
 *
 * @return
 *
 * @version "$Id$"
 *
 * Created on Jul 16, 2008
 */
global $GlobalReady;
if (!isset($GlobalReady)) {
  exit;
}
class upload_properties extends FO_Plugin {
  var $Name = "upload_properties";
  public $Title = "Edit Upload Properties";
  var $Version = "1.0";
  var $MenuList = "Organize::Uploads::Edit Properties";
  var $Dependency = array(
    "db"
    );
    var $DBaccess = PLUGIN_DB_WRITE;
    /**
     * function EditUploadProperites
     *
     */
    function EditUploadProperties($FolderId, $uploadId, $NewName, $NewDesc) {
      global $Plugins;
      global $DB;
      /*
       * No need to check $FolderId, as it's checked in Output, uploadId
       * is set in the select, so no need to check it.  Check for NewName
       * and NewDesc being empty, set if not empty.
       */
      $set = 0;
      if (!empty($NewName)) {
        /*
           Use pfile_fk to select the correct entry in the upload tree, artifacts
           (e.g. directories of the upload do not have pfiles).
         */
        $Sql = "SELECT pfile_fk FROM upload WHERE upload_pk=$uploadId;";
        $pfile = $DB->Action($Sql);
        $pfileFk = $pfile[0]['pfile_fk'];
        $Sql = "SELECT ufile_name FROM uploadtree WHERE upload_fk=$uploadId
        AND pfile_fk=$pfileFk;";
        $oFN = $DB->Action($Sql);
        $oldFileName = basename($oFN[0]['ufile_name']);

        $Sql = "UPDATE uploadtree SET ufile_name='$NewName' ".
              "WHERE upload_fk=$uploadId AND pfile_fk=$pfileFk;";
        $Results = $DB->Action($Sql);
        $Row = $Results[0];
        $set = 1;
      }
      /* Note using this method, there is no way for the user to create a
       * 'blank' i.e. empty description, they can set it to "" or '' but
       * not 'nothing' (NULL).
       */
      if (!empty($NewDesc)) {
        $Sql = "UPDATE upload SET upload_desc='$NewDesc' WHERE upload_pk=$uploadId;";
        $Results = $DB->Action($Sql);
        $Row = $Results[0];
        $set = 1;
      }
      return ($set);
    }
    /*********************************************
     Output(): Generate the text for this plugin.
     *********************************************/
    function Output() {
      if ($this->State != PLUGIN_STATE_READY) {
        return;
      }
      $V = "";
      global $DB;
      switch ($this->OutputType) {
        case "XML":
          break;
        case "HTML":
          /* If this is a POST, then process the request. */
          $FolderSelectId = GetParm('oldfolderid', PARM_INTEGER);
          if (empty($FolderSelectId)) {
            $FolderSelectId = FolderGetTop();
          }
          $uploadId = GetParm('uploadid', PARM_INTEGER);
          $NewName = GetParm('newname', PARM_TEXT);
          $NewDesc = GetParm('newdesc', PARM_TEXT);
          $rc = $this->EditUploadProperties($FolderSelectId, $uploadId, $NewName, $NewDesc);
          if ($rc == 1) {
            $V.= displayMessage('Upload Properties changed');
          }
          $V.= "<p>The upload properties that can be changed are the upload name and
                 description.  First select the folder that the upload is stored in.  " . "Then select the upload to edit. Then enter the new values. If no " . "value is entered, then the corresponding field will not be changed.</p>";
          /* Get the folder info */
          $Results = $DB->Action("SELECT * FROM folder WHERE folder_pk = '$FolderSelectId';");
          $Folder = & $Results[0];
          /* Create the AJAX (Active HTTP) javascript for doing the reply
           and showing the response. */
          $V.= ActiveHTTPscript("Uploads");
          $V.= "<script language='javascript'>\n";
          $V.= "function Uploads_Reply()\n";
          $V.= "  {\n";
          $V.= "  if ((Uploads.readyState==4) && (Uploads.status==200))\n";
          $V.= "    {\n";
          /* Remove all options */
          $V.= "    document.formy.uploadid.innerHTML = Uploads.responseText;\n";
          /* Add new options */
          $V.= "    }\n";
          $V.= "  }\n";
          $V.= "</script>\n";
          /* Build the HTML form */
          $V.= "<form name='formy' method='post'>\n"; // no url = this url
          $V.= "<ol>\n";
          $V.= "<li>Select the folder that contains the upload:  \n";
          $V.= "<select name='oldfolderid'\n";
          $V.= "onLoad='Uploads_Get((\"" . Traceback_uri() . "?mod=upload_options&folder=-1' ";
          $V.= "onChange='Uploads_Get(\"" . Traceback_uri() . "?mod=upload_options&folder=\" + this.value)'>\n";
          $V.= FolderListOption(-1, 0);
          $V.= "</select><P />\n";
          $V.= "<li>Select the upload you wish to edit:  \n";
          $V.= "<select name='uploadid'>\n";
          $List = FolderListUploads(-1);
          foreach($List as $L) {
            $V.= "<option value='" . $L['upload_pk'] . "'>";
            $V.= htmlentities($L['name']);
            if (!empty($L['upload_desc'])) {
              $V.= " (" . htmlentities($L['upload_desc']) . ")";
            }
            if (!empty($L['upload_ts'])) {
              $V.= " :: " . substr($L['upload_ts'], 0, 19);
            }
            $V.= "</option>\n";
          }
          $V.= "</select><P />\n";
          $V.= "<li>Change upload name:  \n";
          $V.= "<INPUT type='text' name='newname' size=40 value=\"" . htmlentities($Folder['upload_filename'], ENT_COMPAT) . "\" />\n";
          $V.= "<P /><li>Change upload description:  \n";
          $V.= "<INPUT type='text' name='newdesc' size=60 value=\"" . htmlentities($Folder['upload_desc'], ENT_COMPAT) . "\" />\n";
          //$V .= "<P /><li>Change Upload Source Location:  \n";
          //$V .= "<INPUT type='text' name='newsrc' size=60 value=\"" . htmlentities($Folder['folder_src'],ENT_COMPAT) . "\" />\n";
          $V.= "</ol>\n";
          $V.= "<input type='submit' value='Edit!'>\n";
          $V.= "</form>\n";
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
}
$NewPlugin = new upload_properties;
?>
