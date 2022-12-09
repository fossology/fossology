<?php
/*
 SPDX-FileCopyrightText: © 2015-2017 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Dao\FolderDao;
use Fossology\Lib\Plugin\DefaultPlugin;
use Fossology\Lib\UI\FolderNav;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminContentDelete extends DefaultPlugin
{
  const NAME = 'content_unlink';

  /** @var FolderDao */
  private $folderDao;

  function __construct()
  {
    parent::__construct(self::NAME, array(
        self::TITLE => _("Unlink upload or folder"),
        self::MENU_LIST => "Organize::Folders::Unlink Content",
        self::PERMISSION => Auth::PERM_ADMIN,
        self::REQUIRES_LOGIN => TRUE
    ));
    $this->folderDao = $GLOBALS['container']->get('dao.folder');
  }

  /**
   * @param Request $request
   * @return Response
   */
  protected function handle(Request $request)
  {
    $userId = Auth::getUserId();
    $vars = array();

    $folderContentId = intval($request->get('foldercontent'));
    if ($folderContentId) {
      try {
        $this->folderDao->removeContent($folderContentId);
      } catch (Exception $ex) {
        $vars['message'] = $ex->getMessage();
      }
    }

    $rootFolderId = $this->folderDao->getRootFolder($userId)->getId();
    /* @var $uiFolderNav FolderNav */
    $uiFolderNav = $GLOBALS['container']->get('ui.folder.nav');
    $vars['folderTree'] = $uiFolderNav->showFolderTree($rootFolderId);

    return $this->render('admin_content_delete.html.twig', $this->mergeWithDefault($vars));
  }
}

register_plugin(new AdminContentDelete());
