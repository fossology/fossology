<?php
/*
/*
 SPDX-FileCopyrightText: © 2023 Simran Nigam <nigamsimran14@gmail.com>
 SPDX-FileContributor: Simran Nigam <nigamsimran14@gmail.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * @namespace Fossology::DelAgent::UI::Page
 * @brief UI namespace for DelAgent
 */
namespace Fossology\DelAgent\UI\Page;

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Dao\FolderDao;
use Fossology\Lib\Plugin\DefaultPlugin;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * @class BrowseUploadDelete
 * @brief UI plugin to delete uploaded files
 */
class BrowseUploadDelete extends DefaultPlugin
{
  const NAME = "browse_upload_delete";

  /** @var UploadDao */
  private $uploadDao;

  /** @var FolderDao */
  private $folderDao;

  function __construct()
  {
    parent::__construct(self::NAME, array(
        self::TITLE => _("Delete Uploaded File"),
        self::PERMISSION => Auth::PERM_ADMIN,
        self::REQUIRES_LOGIN => true
    ));

    global $container;
    $this->uploadDao = $container->get('dao.upload');
    $this->folderDao = $container->get('dao.folder');
  }

    /**
   * \brief Customize submenus.
   */
  function RegisterMenus()
  {
    $text = _("Delete Browse File");
    menu_insert("Browse-Pfile::Delete",0,$this->Name,$text);
  } // RegisterMenus()

  /**
   * @copydoc Fossology::Lib::Plugin::DefaultPlugin::handle()
   * @see Fossology::Lib::Plugin::DefaultPlugin::handle()
   */
  protected function handle(Request $request)
  {
    $vars = array();

    $uploadpk = $request->get('upload');
    $folderId = $request->get('folder');

    if (!empty($uploadpk)) {
      /**
      * @var AdminUploadDelete $adminUploadDelete
      */
      $adminUploadDelete = plugin_find("admin_upload_delete");
      $adminUploadDelete->TryToDelete($uploadpk, $folderId);
    }
    return new RedirectResponse(Traceback_uri() . '?mod=' . 'showjobs');
  }
}

register_plugin(new BrowseUploadDelete());
