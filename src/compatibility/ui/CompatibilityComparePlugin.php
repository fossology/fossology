<?php
/*
 SPDX-FileCopyrightText: © 2026 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Compatibility\UI;

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Dao\CompatibilityDao;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Plugin\DefaultPlugin;
use Symfony\Component\HttpFoundation\Request;

class CompatibilityComparePlugin extends DefaultPlugin
{
  const NAME = 'ui_compatibility_compare';

  /** @var CompatibilityDao */
  private $compatibilityDao;

  /** @var UploadDao */
  private $uploadDao;

  function __construct()
  {
    parent::__construct(self::NAME, array(
        self::TITLE => _("Compare Components (Compatibility)"),
        self::PERMISSION => Auth::PERM_READ,
        self::REQUIRES_LOGIN => true
    ));
    $this->compatibilityDao = $this->getObject('dao.compatibility');
    $this->uploadDao = $this->getObject('dao.upload');
  }

  function preInstall()
  {
    $text = _("Compare Components (Compatibility)");
    menu_insert("UploadMulti::Compare&nbsp;Components", 0, self::NAME, $text);
  }

  protected function handle(Request $request)
  {
    $groupId = Auth::getGroupId();
    $uploadIds = $request->get('uploads') ?: array();
    
    $folderId = intval($request->get('folder'));
    if ($folderId > 0 && empty($uploadIds)) {
      $folderDao = $this->getObject('dao.folder');
      $uploads = $folderDao->getFolderUploads($folderId);
      foreach ($uploads as $upload) {
        $uploadIds[] = $upload->getId();
      }
    }

    $validUploadIds = [];
    foreach ($uploadIds as $uploadId) {
      if (!empty($uploadId) && $this->uploadDao->isAccessible($uploadId, $groupId)) {
        $validUploadIds[] = $uploadId;
      }
    }

    if (empty($validUploadIds)) {
      return new \Symfony\Component\HttpFoundation\Response("No valid uploads selected.", 400);
    }

    $incompatiblePairs = $this->compatibilityDao->getIncompatiblePairsForUploads($validUploadIds);

    $vars = [];
    $vars['uploads'] = $validUploadIds;
    $vars['incompatiblePairs'] = $incompatiblePairs;

    return $this->render('compatibility-compare.html.twig', $this->mergeWithDefault($vars));
  }
}

register_plugin(new CompatibilityComparePlugin());
