<?php
/*
 SPDX-FileCopyrightText: © 2008-2012 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

use Fossology\Lib\Db\DbManager;

define("TITLE_FOLDER_PROPERTIES", _("Edit Folder Properties"));

class folder_properties extends FO_Plugin
{

  /** @var DbManager */
  private $dbManager;

  function __construct()
  {
    $this->Name = "folder_properties";
    $this->Title = TITLE_FOLDER_PROPERTIES;
    $this->MenuList = "Organize::Folders::Edit Properties";
    $this->Dependency = array();
    $this->DBaccess = PLUGIN_DB_WRITE;
    parent::__construct();
    $this->dbManager = $GLOBALS['container']->get('db.manager');
  }

  /**
   * \brief Given a folder's ID and a name, alter
   * the folder properties.
   * Includes idiot checking since the input comes from stdin.
   * \return 1 if changed, 0 if failed.
   */
  function Edit($FolderId, $NewName, $NewDesc)
  {
    $sql = 'SELECT * FROM folder where folder_pk = $1;';
    $Row = $this->dbManager->getSingleRow($sql,array($FolderId),__METHOD__."Get");
    /* If the folder does not exist. */
    if ($Row['folder_pk'] != $FolderId) {
      return (0);
    }
    $NewName = trim($NewName);
    if (! empty($FolderId)) {
      // Reuse the old name if no new name was given
      if (empty($NewName)) {
        $NewName = $Row['folder_name'];
      }
      // Reuse the old description if no new description was given
      if (empty($NewDesc)) {
        $NewDesc = $Row['folder_desc'];
      }
    } else {
      return (0); // $FolderId is empty
    }
    /* Change the properties */
    $sql = 'UPDATE folder SET folder_name = $1, folder_desc = $2 WHERE folder_pk = $3;';
    $this->dbManager->getSingleRow($sql,array($NewName, $NewDesc, $FolderId),__METHOD__."Set");
    return (1);
  }

  /**
   * \brief Generate the text for this plugin.
   */
  public function Output()
  {
    /* If this is a POST, then process the request. */
    $FolderSelectId = GetParm('selectfolderid', PARM_INTEGER);
    if (empty($FolderSelectId)) {
      $FolderSelectId = FolderGetTop();
    }
    $FolderId = GetParm('oldfolderid', PARM_INTEGER);
    $NewName = GetParm('newname', PARM_TEXT);
    $NewDesc = GetParm('newdesc', PARM_TEXT);
    if (! empty($FolderId)) {
      $FolderSelectId = $FolderId;
      $rc = $this->Edit($FolderId, $NewName, $NewDesc);
      if ($rc == 1) {
        /* Need to refresh the screen */
        $text = _("Folder Properties changed");
        $this->vars["message"] = $text;
      }
    }
    /* Get the folder info */
    $sql = 'SELECT * FROM folder WHERE folder_pk = $1;';
    $Folder = $this->dbManager->getSingleRow($sql,array($FolderSelectId),__METHOD__."getFolderRow");

    /* Display the form */
    $formVars["onchangeURI"] = Traceback_uri() . "?mod=" . $this->Name . "&selectfolderid=";
    $formVars["folderListOption"] = FolderListOption(-1, 0, 1, $FolderSelectId);
    $formVars["folder_name"] = $Folder['folder_name'];
    $formVars["folder_desc"] = $Folder['folder_desc'];
    return $this->renderString("admin-folder-edit-form.html.twig",$formVars);
  }
}
$NewPlugin = new folder_properties;
