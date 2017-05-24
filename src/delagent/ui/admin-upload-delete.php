<?php
/***********************************************************
 Copyright (C) 2008-2013 Hewlett-Packard Development Company, L.P.
 Copyright (C) 2015-2017 Siemens AG

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

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Dao\UploadDao;
use \delagent\ui\DeleteMessages;
use \delagent\ui\DeleteResponse;
/**
 * \file admin_upload_delete.php
 * \brief delete a upload
 */

require_once "delete-helper.php";
define("TITLE_admin_upload_delete", _("Delete Uploaded File"));

/**
 * \class admin_upload_delete extend from FO_Plugin
 * \brief delete a upload, certainly, you need the permission 
 */
class admin_upload_delete extends FO_Plugin 
{
  /** @var UploadDao */
  private $uploadDao;

  function __construct()
  {
    $this->Name = "admin_upload_delete";
    $this->Title = TITLE_admin_upload_delete;
    $this->MenuList = "Organize::Uploads::Delete Uploaded File";
    $this->DBaccess = PLUGIN_DB_WRITE;
    parent::__construct();

    global $container;
    $this->uploadDao = $container->get('dao.upload');
  }

  /**
   * \brief Given a folder_pk, try to add a job after checking permissions.
   * \param $uploadpk - the upload(upload_id) you want to delete
   *
   * \return string with the message.
   */
  function TryToDelete($uploadpk)
  {
    $user_pk = Auth::getUserId();
    $group_pk = Auth::getGroupId();

    if(!$this->uploadDao->isEditable($uploadpk, Auth::getGroupId())){
      $returnMessage = DeleteMessages::NO_PERMISSION;
      return new DeleteResponse($returnMessage);
    }

    $rc = DeleteUpload(intval($uploadpk), $user_pk, $group_pk);

    if (! empty($rc)) {
      $returnMessage = DeleteMessages::SCHEDULING_FAILED;
      return new DeleteResponse($returnMessage);
    }

    /* Need to refresh the screen */
    $URL = Traceback_uri() . "?mod=showjobs&upload=$uploadpk ";
    $LinkText = _("View Jobs");
    $returnMessage = DeleteMessages::SUCCESS;
    return new DeleteResponse($returnMessage,
      " <a href=$URL>$LinkText</a>");
  }

  /*
   * \brief Generate the text for this plugin.
   */
  public function Output()
  {
    $V = "";
    /* If this is a POST, then process the request. */
    $uploadpks = GetParm('upload', PARM_RAW);
    if (!empty($uploadpks))
    {
      $V .= initDeletion($uploadpks);

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
    //$V.= "    document.formy.upload.innerHTML = Uploads.responseText;\n";
    $V.= "    document.getElementById('uploaddiv').innerHTML = '<BR><select name=\'upload\' multiple=multiple size=\'10\'>' + Uploads.responseText + '</select><P />';\n";
    /* Add new options */
    $V.= "    }\n";
    $V.= "  }\n";
    $V.= "</script>\n";
    /* Build HTML form */
    $V.= "<form name='formy' method='post'>\n"; // no url = this url
    $text = _("Select the uploaded file to");
    $text1 = _("delete");
    $V.= "$text <em>$text1</em>\n";
    $V.= "<ul>\n";
    $text = _("This will");
    $text1 = _("delete");
    $text2 = _("the upload file!");
    $V.= "<li>$text <em>$text1</em> $text2\n";
    $text = _("Be very careful with your selection since you can delete a lot of work!\n");
    $V.= "<li>$text";
    $text = _("All analysis only associated with the deleted upload file will also be deleted.\n");
    $V.= "<li>$text";
    $text = _("THERE IS NO UNDELETE. When you select something to delete, it will be removed from the database and file repository.\n");
    $V.= "<li>$text";
    $V.= "</ul>\n";
    $text = _("Select the uploaded file to delete:");
    $V.= "<P>$text<P>\n";
    $V.= "<ol>\n";
    $text = _("Select the folder containing the file to delete: ");
    $V.= "<li>$text";
    $V.= "<br />";
    $V.= "<select id='folder_select' name='folder' ";

    $V.= "onLoad='Uploads_Get((\"" . Traceback_uri() . "?mod=upload_options&folder=-1' ";
    $V.= "onChange='Uploads_Get(\"" . Traceback_uri() . "?mod=upload_options&folder=\" + this.value)'>\n";

    $root_folder_pk = GetUserRootFolder();
    $V.= FolderListOption($root_folder_pk, 0);
    $V.= "</select><P />\n";
    $text = _("Select the uploaded project to delete:");
    $V.= "<li>$text";
    $V.= "<div id='uploaddiv'>\n";
    $V.= "<BR><select multiple='multiple' name='upload[]' size='10'>\n";
    $List = FolderListUploads_perm($root_folder_pk, Auth::PERM_WRITE);
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
    $V.= "</div>\n";
    $V.= "</ol>\n";
    $text = _("Delete");
    $V.= "<input type='submit' value='$text'>\n";
    $V.= "</form>\n";

    $V.= <<<'EOT'
    <script>
      window.onload = function() {
          $('#folder_select').select2();
      }
    </script>
EOT;
    return $V;
  }
}
$NewPlugin = new admin_upload_delete;
