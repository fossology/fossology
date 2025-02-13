<?php
/*
 SPDX-FileCopyrightText: © 2008-2011 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/
use Fossology\Lib\Dao\FolderDao;
use Fossology\Lib\Auth\Auth;
use Fossology\Lib\UI\FolderNav;

class folder_create extends FO_Plugin
{
  /** @var FolderDao */
  private $folderDao;
  
  function __construct()
  {
    $this->Name = "folder_create";
    $this->Title = _("Create a new Fossology folder");
    $this->MenuList = "Organize::Folders::Create";
    $this->Dependency = array();
    $this->DBaccess = PLUGIN_DB_WRITE;
    parent::__construct();
    $this->folderDao = $GLOBALS['container']->get('dao.folder');
  }

  /**
   * \brief Given a parent folder ID, a name and description,
   * create the named folder under the parent.
   *
   * Includes idiot checking since the input comes from stdin.
   *
   * @param $parentId - parent folder id
   * @param $newFolder - new folder name
   * @param $desc - new folder discription
   *
   * @return int 1 if created, 0 if failed
   */
  public function create($parentId, $newFolder, $desc)
  {
    $folderName = trim($newFolder);
    if (empty($folderName)) {
      return (0);
    }

    $parentExists = $this->folderDao->getFolder($parentId);
    if (! $parentExists) {
      return (0);
    }

    $folderWithSameNameUnderParent = $this->folderDao->getFolderId($folderName, $parentId);
    if (! empty($folderWithSameNameUnderParent)) {
      return 4;
    }

    $this->folderDao->createFolder($folderName, $desc, $parentId);
    return (1);
  }

  /**
   * \brief Generate the text for this plugin.
   */
  public function Output()
  {
    $vars = array();
    
    /* If this is a POST, then process the request. */
    $ParentId = GetParm('parentid', PARM_INTEGER);
    $NewFolder = GetParm('newname', PARM_TEXT);
    $Desc = GetParm('description', PARM_TEXT);
    if (! empty($ParentId) && ! empty($NewFolder)) {
      $rc = $this->create($ParentId, $NewFolder, $Desc);
      if ($rc == 1) {
        /* Need to refresh the screen */
        $text = _("Folder");
        $text1 = _("Created");
        $vars['message'] = "$text " . htmlentities($NewFolder) . " $text1";
      } else if ($rc == 4) {
        $text = _("Folder");
        $text1 = _("Exists");
        $vars['message'] = "$text " . htmlentities($NewFolder) . " $text1";
      }
    }

    $userId = Auth::getUserId();
    $rootFolderId = $this->folderDao->getRootFolder($userId)->getId();
    
    /* @var $uiFolderNav FolderNav */
    $uiFolderNav = $GLOBALS['container']->get('ui.folder.nav');
    $vars['folderTree'] = $uiFolderNav->showFolderTree($rootFolderId);
    $vars['folderOptions'] = FolderListOption($rootFolderId, 0);

    return $this->renderString("admin-folder-create-form.html.twig", $vars);
  }
}

$NewPlugin = new folder_create();
$NewPlugin->Initialize();