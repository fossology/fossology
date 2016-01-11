<?php
/***********************************************************
 Copyright (C) 2015 Siemens AG

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
    if (!$this->isRequiresLogin() || $this->isLoggedIn())
    {
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
    
    $folderContentId = intval($request->get('foldercontent'));
    $parentFolderId = intval($request->get('toFolder'));
    if ($folderContentId && $parentFolderId && $request->get('copy'))
    {
      try{
        $this->folderDao->copyContent($folderContentId, $parentFolderId);
      }
      catch (Exception $ex) {
        $vars['message'] = $ex->getMessage();
      }
    }
    elseif ($folderContentId && $parentFolderId)
    {
      try{
        $this->folderDao->moveContent($folderContentId, $parentFolderId);
      }
      catch (Exception $ex) {
        $vars['message'] = $ex->getMessage();
      }
    }
    
    $rootFolderId = $this->folderDao->getRootFolder($userId)->getId();
    /* @var $uiFolderNav FolderNav */
    $uiFolderNav = $this->getObject('ui.folder.nav');
    $vars['folderTree'] = $uiFolderNav->showFolderTree($rootFolderId);
    $vars['folderStructure'] = $this->folderDao->getFolderStructure($rootFolderId);
   
    return $this->render('admin_content_move.html.twig', $this->mergeWithDefault($vars));
  }
}

register_plugin(new AdminContentMove());