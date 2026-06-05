<?php
/*
 SPDX-FileCopyrightText: © Fossology contributors

 SPDX-License-Identifier: GPL-2.0-only
*/

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Dao\FolderDao;
use Fossology\Lib\Plugin\DefaultPlugin;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class Folder extends DefaultPlugin
{
  const NAME = 'folder';

  /** @var FolderDao */
  private $folderDao;

  public function __construct()
  {
    parent::__construct(self::NAME, array(
        self::TITLE => _('Folder'),
        self::MENU_LIST => 'Organize::Folders',
        self::PERMISSION => Auth::PERM_WRITE,
        self::REQUIRES_LOGIN => TRUE
    ));
    $this->folderDao = $this->getObject('dao.folder');
  }

  /**
   * @param Request $request
   * @return Response
   */
  protected function handle(Request $request)
  {
    $action = $request->get('action', 'create');
    if (!in_array($action, array('create', 'delete', 'edit', 'move', 'unlink'))) {
      $action = 'create';
    }

    if ($action === 'unlink' && !Auth::isAdmin()) {
      $action = 'create';
    }

    $vars = array();
    $dbManager = $this->getObject('db.manager');
    $userId = Auth::getUserId();
    $rootFolderId = GetUserRootFolder();

    if ($request->isMethod('POST')) {
      if ($action === 'create') {
        $parentId = intval($request->get('parentid'));
        $newFolder = $request->get('newname');
        $desc = $request->get('description');
        if ($parentId && $newFolder) {
          $folderName = trim($newFolder);
          if (!empty($folderName)) {
            $parentExists = $this->folderDao->getFolder($parentId);
            if ($parentExists) {
              $folderWithSameNameUnderParent = $this->folderDao->getFolderId($folderName, $parentId);
              if (!empty($folderWithSameNameUnderParent)) {
                $vars['message'] = _("Folder") . " " . htmlentities($newFolder) . " " . _("Exists");
              } else {
                $this->folderDao->createFolder($folderName, $desc, $parentId);
                $vars['message'] = _("Folder") . " " . htmlentities($newFolder) . " " . _("Created");
              }
            } else {
              $vars['message'] = _("Parent folder does not exist");
            }
          }
        }
      } elseif ($action === 'delete') {
        $folder = $request->get('folder');
        if (!empty($folder)) {
          $splitFolder = explode(" ", $folder);
          if (count($splitFolder) >= 2) {
            $folderId = intval($splitFolder[1]);
            $sql = "SELECT folder_name FROM folder join users on (users.user_pk = folder.user_fk or users.user_perm = 10) where folder_pk = $1 and users.user_pk = $2;";
            $FolderRow = $dbManager->getSingleRow($sql, array($folderId, $userId), __METHOD__."GetRowWithFolderName");
            if (!empty($FolderRow['folder_name'])) {
              $folderDelete = plugin_find('admin_folder_delete');
              if ($folderDelete) {
                $rc = $folderDelete->Delete($folder, $userId);
                if (empty($rc)) {
                  $vars['message'] = _("Deletion of folder ") . $FolderRow['folder_name'] . _(" added to job queue");
                } else {
                  $vars['message'] = _("Deletion of ") . $FolderRow['folder_name'] . _(" failed: ") . $rc;
                }
              } else {
                $rc = $this->deleteFolderDirectly($folder, $userId);
                if (empty($rc)) {
                  $vars['message'] = _("Deletion of folder ") . $FolderRow['folder_name'] . _(" added to job queue");
                } else {
                  $vars['message'] = _("Deletion of ") . $FolderRow['folder_name'] . _(" failed: ") . $rc;
                }
              }
            } else {
              $vars['message'] = _("Cannot delete this folder :: Permission denied");
            }
          } else {
            $vars['message'] = _("Invalid folder selection");
          }
        }
      } elseif ($action === 'edit') {
        $folderId = intval($request->get('oldfolderid'));
        $newName = $request->get('newname');
        $newDesc = $request->get('newdesc');
        if ($folderId) {
          $sql = 'SELECT * FROM folder where folder_pk = $1;';
          $Row = $dbManager->getSingleRow($sql, array($folderId), __METHOD__."Get");
          if ($Row['folder_pk'] == $folderId) {
            $newName = trim($newName);
            if (empty($newName)) {
              $newName = $Row['folder_name'];
            }
            if (empty($newDesc)) {
              $newDesc = $Row['folder_desc'];
            }
            $sql = 'UPDATE folder SET folder_name = $1, folder_desc = $2 WHERE folder_pk = $3;';
            $dbManager->getSingleRow($sql, array($newName, $newDesc, $folderId), __METHOD__."Set");
            $vars["message"] = _("Folder Properties changed");
          }
        }
      } elseif ($action === 'move') {
        $folderContentIds = $request->get('foldercontent', array());
        $parentFolderId = intval($request->get('toFolder'));
        $isCopyRequest = $request->get('copy');

        $message = "";
        for ($i = 0; $i < sizeof($folderContentIds); $i++) {
          $folderContentId = intval($folderContentIds[$i]);
          if ($folderContentId && $parentFolderId && $isCopyRequest) {
            try {
              $this->folderDao->copyContent($folderContentId, $parentFolderId);
            } catch (\Exception $ex) {
              $message .= $ex->getMessage();
            }
          } elseif ($folderContentId && $parentFolderId) {
            try {
              $this->folderDao->moveContent($folderContentId, $parentFolderId);
            } catch (\Exception $ex) {
              $message .= $ex->getMessage();
            }
          }
        }
        $vars['message'] = $message;
      } elseif ($action === 'unlink') {
        $folderContentId = intval($request->get('foldercontent'));
        if ($folderContentId) {
          try {
            $this->folderDao->removeContent($folderContentId);
          } catch (\Exception $ex) {
            $vars['message'] = $ex->getMessage();
          }
        }
      }
    }

    // Populate variables depending on current action
    if ($action === 'create') {
      $vars['folderOptions'] = FolderListOption($rootFolderId, 0);
    } elseif ($action === 'delete') {
      $vars['deleteFolderOptions'] = FolderListOption(-1, 0, 1, -1, true);
    } elseif ($action === 'edit') {
      $folderSelectId = intval($request->get('selectfolderid'));
      if (empty($folderSelectId)) {
        $folderSelectId = FolderGetTop();
      }
      $sql = 'SELECT * FROM folder WHERE folder_pk = $1;';
      $FolderRow = $dbManager->getSingleRow($sql, array($folderSelectId), __METHOD__."getFolderRow");

      $vars["onchangeURI"] = '?mod=folder&action=edit&selectfolderid=';
      $vars["folderListOption"] = FolderListOption(-1, 0, 1, $folderSelectId);
      $vars["folder_name"] = $FolderRow['folder_name'];
      $vars["folder_desc"] = $FolderRow['folder_desc'];
      $vars["folderSelectId"] = $folderSelectId;
    } elseif ($action === 'move') {
      $rootFolderId = $this->folderDao->getRootFolder($userId)->getId();
      $uiFolderNav = $this->getObject('ui.folder.nav');
      $vars['folderTree'] = $uiFolderNav->showFolderTree($rootFolderId);
      $vars['folderStructure'] = $this->folderDao->getFolderStructure($rootFolderId);
    } elseif ($action === 'unlink') {
      $rootFolderId = $this->folderDao->getRootFolder($userId)->getId();
      $uiFolderNav = $this->getObject('ui.folder.nav');
      $vars['folderTree'] = $uiFolderNav->showFolderTree($rootFolderId);
    }

    $vars['activeAction'] = $action;
    $vars['isAdmin'] = Auth::isAdmin();

    return $this->render('folder.html.twig', $this->mergeWithDefault($vars));
  }

  /**
   * @param string $folderpk
   * @param int $userId
   * @return string|null
   */
  private function deleteFolderDirectly($folderpk, $userId)
  {
    $splitFolder = explode(" ", $folderpk);
    if (count($splitFolder) < 2) {
      return _("Invalid folder selection");
    }
    $folderId = intval($splitFolder[1]);
    if (!$this->folderDao->isFolderAccessible($folderId, $userId)) {
      return _("No access to delete this folder");
    }
    /* Can't remove top folder */
    if ($folderId == FolderGetTop()) {
      return _("Can Not Delete Root Folder");
    }
    /* Get the folder's name */
    $FolderName = FolderGetName($folderId);
    /* Prepare the job: job "Delete" */
    $groupId = Auth::getGroupId();
    $jobpk = JobAddJob($userId, $groupId, "Delete Folder: $FolderName");
    if (empty($jobpk) || ($jobpk < 0)) {
      return _("Failed to create job record");
    }
    /* Add job: job "Delete" has jobqueue item "delagent" */
    $jqargs = "DELETE FOLDER $folderpk";
    $jobqueuepk = JobQueueAdd($jobpk, "delagent", $jqargs, NULL, NULL);
    if (empty($jobqueuepk)) {
      return _("Failed to place delete in job queue");
    }

    /* Tell the scheduler to check the queue. */
    $success = fo_communicate_with_scheduler("database", $output, $error_msg);
    if (!$success) {
      return $error_msg . "\n" . $output;
    }

    return null;
  }
}

register_plugin(new Folder());