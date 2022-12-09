<?php
/*
 SPDX-FileCopyrightText: © 2012-2013 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/
use Fossology\Lib\Auth\Auth;


/**
 * \file admin_tag_manage.php
 * \brief "Enable/Disable tag
 */

define("TITLE_ADMIN_TAG_MANAGE", _("Enable/Disable Tag Display"));

class admin_tag_manage extends FO_Plugin
{
  function __construct()
  {
    $this->Name       = "admin_tag_manage";
    $this->Title      = TITLE_ADMIN_TAG_MANAGE;
    $this->MenuList = "Admin::Tag::Enable/Disable Tag";
    $this->Version = "1.3";
    $this->DBaccess = PLUGIN_DB_ADMIN;
    parent::__construct();
  }


  /**
   * \brief Enable/Disable Tag on one folder(all uploads under this folder) or one upload
   *
   * \param $folder_id - folder id
   * \param $upload_id - upload id
   * \param $manage - enable or disable
   *
   * \return return null when no uploads to manage, return 1 after setting
   */
  function ManageTag($folder_id, $upload_id, $manage)
  {
    global $PG_CONN;

    /** no operation */
    if (empty($manage)) {
      return;
    }
    if (empty($folder_id) && empty($upload_id)) {
      return;
    }

    /** get upload list */
    $upload_list = array();
    if (!empty($upload_id)) {
      $upload_list[0] = array('upload_pk'=>$upload_id);
    } else {
      $upload_list = FolderListUploadsRecurse($folder_id, NULL, Auth::PERM_WRITE); // want to manage all uploads under a folder
    }

    foreach ($upload_list as $upload) {
      $upload_id = $upload['upload_pk'];

      if ("Enable" === $manage) {
        $manage_value = false;
      } else {
        $manage_value = true;
      }

      /** check if this upload has been disabled */
      $sql = "select * from tag_manage where upload_fk = $upload_id and is_disabled = true;";
      $result = pg_query($PG_CONN, $sql);
      DBCheckResult($result, $sql, __FILE__, __LINE__);
      $count = pg_num_rows($result);
      pg_free_result($result);
      if (empty($count) && $manage_value == true) { // has not been disabled, and want to disable this upload
        $sql = "INSERT INTO tag_manage(upload_fk, is_disabled) VALUES($upload_id, true);";
        $result = pg_query($PG_CONN, $sql);
        DBCheckResult($result, $sql, __FILE__, __LINE__);
        pg_free_result($result);
      } else if ($count == 1 && $manage_value == false) { // has been disabled, and want to enable this upload
        $sql = "delete from tag_manage where upload_fk = $upload_id;";
        $result = pg_query($PG_CONN, $sql);
        DBCheckResult($result, $sql, __FILE__, __LINE__);
        pg_free_result($result);
      }
    }
    return 1;
  }


  public function Output()
  {
    $V="";
    $upload_id = GetParm('upload', PARM_INTEGER);
    $manage = GetParm('manage', PARM_TEXT);
    /* If this is a POST, then process the request. */
    $Folder = GetParm('folder',PARM_INTEGER);
    if (empty($Folder)) {
      $Folder = FolderGetTop();
    }

    $rc = $this->ManageTag($Folder, $upload_id, $manage);
    if (1 == $rc) {

      $text1 = _("all uploads in folder");
      $text2 = _("in folder");
      $folder_path = FolderGetName($Folder);
      $upload_name = GetUploadName($upload_id);

      if (empty($upload_id)) {
        $text = $text1;
      } else {
        $text = "'$upload_name' $text2";
      }

      $Msg = "$manage $text '$folder_path'";
      $this->vars['message'] = $Msg;
      // reset form fields
    }

    /**
     * Create the AJAX (Active HTTP) javascript for doing the reply
     * and showing the response.
     * get upload list under one folder
     */
    $V .= ActiveHTTPscript("Uploads");
    $V .= "<script language='javascript'>\n";
    $V .= "function Uploads_Reply()\n";
    $V .= "  {\n";
    $V .= "  if ((Uploads.readyState==4) && (Uploads.status==200))\n";
    $V .= "    {\n";
    $V .= "    document.getElementById('tagdiv').innerHTML = '<select size=\'10\' name=\'upload\' onChange=\'Tagging_Get(\"" . Traceback_uri() . "?mod=upload_tagging&upload=\" + this.value)\'>' + Uploads.responseText+ '</select><P/>';\n";
    $V .= "    document.getElementById('manage_tag').style.display= 'none';\n";
    $V .= "    document.getElementById('manage_tag_all').style.display= 'block';\n";
    $V .= "    }\n";
    $V .= "  }\n";
    $V .= "</script>\n";

    /** select one upload */
    $V .= ActiveHTTPscript("Tagging");
    $V .= "<script language='javascript'>\n";
    $V .= "function Tagging_Reply()\n";
    $V .= "  {\n";
    $V .= "  if ((Tagging.readyState==4) && (Tagging.status==200))\n";
    $V .= "    {\n";
    $V .= "    document.getElementById('manage_tag_all').style.display= 'none';\n";
    $V .= "    document.getElementById('manage_tag').style.display= 'block';\n";
    $V .= "    document.getElementById('manage_tag').innerHTML = Tagging.responseText;\n";
    $V .= "    }\n";
    $V .= "  }\n";
    $V .= "</script>\n";

    $V .= "<form name='formy' method='post'>\n"; // no url = this url
    $V .= _("Displaying tags while browsing can be slow for large uploads.  This interface allows you to select an upload to disable (or enable) the tag display.  By default the tag display is enabled.<p>\n");

    $V .= "<ol>\n";
    $text = _("Select the folder containing the upload you wish to enable/disable:");
    $V .= "<li>$text<br>\n";
    $V .= "<select name='folder'\n";
    $V .= "onLoad='Uploads_Get((\"" . Traceback_uri() . "?mod=upload_options&folder=$Folder' ";
    $V .= "onChange='Uploads_Get(\"" . Traceback_uri() . "?mod=upload_options&folder=\" + this.value)' ";
    $V .= "class='ui-render-select2'>\n";
    $V .= FolderListOption(-1,0,1,$Folder);
    $V .= "</select><P />\n";

    $text = _("Select the upload to  enable/disable:");
    $V .= "<li>$text<br>";
    $V .= "<div id='tagdiv'>\n";
    $V .= "<select size='10' name='upload' onChange='Tagging_Get(\"" . Traceback_uri() . "?mod=upload_tagging&upload=\" + this.value)'>\n";
    $List = FolderListUploads_perm($Folder, Auth::PERM_WRITE);
    foreach ($List as $L) {
      $V .= "<option value='" . $L['upload_pk'] . "'>";
      $V .= htmlentities($L['name']);
      if (! empty($L['upload_desc'])) {
        $V .= " (" . htmlentities($L['upload_desc']) . ")";
      }
      $V .= "</option>\n";
    }
    $V .= "</select><P />\n";
    $V .= "</div>\n";
    $V .= "<div id='manage_tag_all'>";
    $text = _("Disable");
    $V .= "<input type='submit' name='manage'  value='$text'>\n";
    $text = _("Enable");
    $V .= "<input type='submit' name='manage' value='$text'>\n";
    $V .=  "</div>";

    $V .= "<div id='manage_tag'>";
    $V .=  "</div>";

    return $V;
  }
}
$NewPlugin = new admin_tag_manage;
$NewPlugin->Initialize();
