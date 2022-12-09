<?php
/*
 SPDX-FileCopyrightText: © 2015 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Dao\FolderDao;
use Fossology\Lib\Plugin\DefaultPlugin;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminContentMove extends DefaultPlugin
{
  const NAME = 'content_move';

  /** @var FolderDao */
  private $folderDao;

  function __construct()
  {
    parent::__construct(self::NAME, array(
        self::TITLE => _("Move upload or folder"),
        self::MENU_LIST => "Organize::Folders::Move or Copy",
        self::PERMISSION => Auth::PERM_WRITE,
        self::REQUIRES_LOGIN => TRUE
    ));
    $this->folderDao = $this->getObject('dao.folder');
  }

  protected function RegisterMenus()
  {
    parent::RegisterMenus();
    if (!$this->isRequiresLogin() || $this->isLoggedIn()) {
      menu_insert("Main::Organize::Uploads::Move or Copy", 0, $this->name, $this->name);
    }
  }

  /**
   * @param Request $request
   * @return Response
   */
  protected function handle(Request $request)
  {
    $userId = Auth::getUserId();
    $vars = array();

    $folderContentIds = $request->get('foldercontent', array());
    $parentFolderId = intval($request->get('toFolder'));
    $isCopyRequest = $request->get('copy');

    $vars['message'] = $this->performAction($folderContentIds, $parentFolderId, $isCopyRequest);

    $rootFolderId = $this->folderDao->getRootFolder($userId)->getId();
    /* @var $uiFolderNav FolderNav */
    $uiFolderNav = $this->getObject('ui.folder.nav');
    $vars['folderTree'] = $uiFolderNav->showFolderTree($rootFolderId);
    $vars['folderStructure'] = $this->folderDao->getFolderStructure($rootFolderId);
    return $this->render('admin_content_move.html.twig', $this->mergeWithDefault($vars));
  }

  /**
   * Move/Copy content of one folder to other.
   * @param integer[] $folderContentIds Upload ids to copy/move
   * @param integer $parentFolderId Destination folder id
   * @param boolean $isCopyRequest  Set true to copy, false to move
   * @return string Empty string on success, error message on failure.
   */
  private function performAction($folderContentIds, $parentFolderId, $isCopyRequest)
  {
    $message = "";
    for ($i = 0; $i < sizeof($folderContentIds); $i++) {
      $folderContentId = intval($folderContentIds[$i]);
      if ($folderContentId && $parentFolderId && $isCopyRequest) {
        try {
          $this->folderDao->copyContent($folderContentId, $parentFolderId);
        } catch (Exception $ex) {
          $message .= $ex->getMessage();
        }
      } elseif ($folderContentId && $parentFolderId) {
        try {
          $this->folderDao->moveContent($folderContentId, $parentFolderId);
        } catch (Exception $ex) {
          $message .= $ex->getMessage();
        }
      }
    }
    return $message;
  }

  /**
   * Move/Copy content of one folder to other.
   * @param integer[] $uploadIds Upload ids to copy/move
   * @param integer $parentFolderId Destination folder id
   * @param boolean $isCopyRequest  Set true to copy, false to move
   * @return string Empty string on success, error message on failure.
   * @sa performAction()
   */
  public function copyContent($uploadIds, $parentFolderId, $isCopyRequest)
  {
    return $this->performAction($uploadIds, $parentFolderId, $isCopyRequest);
  }
}

register_plugin(new AdminContentMove());