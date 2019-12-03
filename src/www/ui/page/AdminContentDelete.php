<?php
/***********************************************************
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
