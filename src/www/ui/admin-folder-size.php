<?php
/*
  SPDX-FileCopyrightText: © 2023 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

use Fossology\Lib\Db\DbManager;

define("TITLE_SIZE_DASHBOARD", _("Folder and upload dashboard"));

class size_dashboard extends FO_Plugin
{

  /** @var DbManager */
  private $dbManager;

  function __construct()
  {
    $this->Name = "size_dashboard";
    $this->Title = TITLE_SIZE_DASHBOARD;
    $this->MenuList = "Admin::Dashboards::Folder/Upload Proportions";
    $this->Dependency = array();
    $this->DBaccess = PLUGIN_DB_WRITE;
    parent::__construct();
    $this->dbManager = $GLOBALS['container']->get('db.manager');
  }

  /**
   * \brief Given a folder's ID
   * function will get size of the folder and uploads under it.
   * \return folder data.
   */
  function getFolderAndUploadSize($folderId)
  {
    $sql = 'INNER JOIN upload ON upload.pfile_fk=pfile.pfile_pk '.
           'INNER JOIN foldercontents ON upload.upload_pk=foldercontents.child_id '.
           'WHERE parent_fk=$1;';
    $statementName = __METHOD__."GetFolderSize";
    $folderSizesql = 'SELECT SUM(pfile_size) FROM pfile '.$sql;
    $row = $this->dbManager->getSingleRow($folderSizesql,array($folderId),$statementName);
    $folderSize = HumanSize($row['sum']);

    $statementName = __METHOD__."GetEachUploadSize";
    $this->dbManager->prepare($statementName, "SELECT upload_filename, pfile_size FROM pfile ".$sql);
    $res = $this->dbManager->execute($statementName, array($folderId));
    $results = $this->dbManager->fetchAll($res);
    $this->dbManager->freeResult($res);
    $var = '';
    foreach ($results as $result) {
      $var .= "<tr><td align='left'>".$result['upload_filename']."</td><td align='left' data-order='{$result['pfile_size']}'>".HumanSize($result['pfile_size'])."</td></tr>";
    }
    return [$var, $folderSize];
  }

  /**
   * \brief Generate the text for this plugin.
   */
  public function Output()
  {
    /* If this is a POST, then process the request. */
    $folderId = GetParm('selectfolderid', PARM_INTEGER);
    if (empty($folderId)) {
      $folderId = FolderGetTop();
    }
    list($tableVars, $wholeFolderSize) = $this->getFolderAndUploadSize($folderId);

    /* Display the form */
    $formVars["onchangeURI"] = Traceback_uri() . "?mod=" . $this->Name . "&selectfolderid=";
    $formVars["folderListOption"] = FolderListOption(-1, 0, 1, $folderId);
    $formVars["tableVars"] = $tableVars;
    $formVars["wholeFolderSize"] = $wholeFolderSize;
    return $this->renderString("admin-folder-size-form.html.twig", $formVars);
  }
}
$NewPlugin = new size_dashboard;
