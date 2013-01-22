<?php
/***********************************************************
 Copyright (C) 2012-2013 Hewlett-Packard Development Company, L.P.

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
 * \class change_owner extend from FO_Plugin
 * \brief move a upload from a place to another one
 */
define("TITLE_change_owner", _("Change Owner of an Upload"));

class change_owner extends FO_Plugin {
  var $Name = "change_owner";
  var $Title   = TITLE_change_owner;
  var $Version = "1.0";
  var $MenuList = "Admin::Change Owner";
  var $Dependency = array();
  var $DBaccess = PLUGIN_DB_ADMIN;

  /**
   * \brief Change the owner (user_fk) of an upload.
   * \param $upload_pk
   * \param $newuser_pk
   *
   * \return string status message
   */
  function ChangeOwner($upload_pk, $newuser_pk)
  {
    global $PG_CONN;

    $sql = "UPDATE upload SET user_fk = '$newuser_pk' WHERE upload_pk=$upload_pk";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $row = pg_fetch_assoc($result);
    pg_free_result($result);
    return ("Owner changed.");
  } // ChangeOwner()


  /**
   * \brief Generate the text for this plugin.
   */
  function Output() {
    global $Plugins;
    global $PG_CONN;
    if ($this->State != PLUGIN_STATE_READY) {
      return;
    }
    $V = "";
    switch ($this->OutputType) {
      case "XML":
        break;
      case "HTML":
        /* If this is a POST, then process the request. */
        $folderid = GetParm('folderid', PARM_INTEGER);
        $upload_pk = GetParm('upload_pk', PARM_INTEGER);
        $newuser_pk = GetParm('newuser_pk', PARM_INTEGER);
        if (!empty($upload_pk) && !empty($newuser_pk)) 
        {
            $V.= displayMessage($this->ChangeOwner($upload_pk, $newuser_pk));
        }

        /* Create the AJAX (Active HTTP) javascript for doing the reply
         and showing the response. */
        $V.= ActiveHTTPscript("Uploads");
        $V.= "<script language='javascript'>\n";
        $V.= "function Uploads_Reply()\n";
        $V.= "  {\n";
        $V.= "  if ((Uploads.readyState==4) && (Uploads.status==200))\n";
        $V.= "    {\n";
        /* Remove all options */
        $V.= "    document.formy.upload_pk.innerHTML = Uploads.responseText;\n";
        /* Add new options */
        $V.= "    }\n";
        $V.= "  }\n";
        $V.= "</script>\n";
        /* Build the  HTML form */
        $V.= "<form name='formy' method='post'>\n"; // no url = this url
        /* Display the form */
        $V.= "<form method='post'>\n"; // no url = this url
        $V.= "<ol>\n";
        $text = _("Select the folder containing the upload:  \n");
        $V.= "<li>$text";
        $V.= "<select name='folderid'\n";
        $V.= "onLoad='Uploads_Get((\"" . Traceback_uri() . "?mod=upload_options&folder=-1' ";
        $V.= "onChange='Uploads_Get(\"" . Traceback_uri() . "?mod=upload_options&folder=\" + this.value)'>\n";
        $V.= FolderListOption(-1, 0);
        $V.= "</select><P />\n";
        $text = _("Select the upload:  \n");
        $V.= "<li>$text";
        $V.= "<select name='upload_pk'>\n";
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
        $text = _("Select the new owner:  \n");
        $V.= "<li>$text";
        $UserArray = DB2KeyValArray("users", "user_pk", "user_name", "order by user_name");
        $V .= Array2SingleSelect($UserArray, "newuser_pk");
  
        $V.= "</ol>\n";
        $text = ("Change Owner");
        $V.= "<input type='submit' value='$text!'>\n";
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
};
$NewPlugin = new change_owner;
?>
