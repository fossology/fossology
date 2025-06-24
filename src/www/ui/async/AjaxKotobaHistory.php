<?php
/*
 SPDX-FileCopyrightText: © 2025 Harshit Gandhi <gandhiharshit716@gmail.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\UI\Ajax;

use Fossology\Lib\Dao\ClearingDao;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Data\Tree\ItemTreeBounds;
use Fossology\Lib\Plugin\DefaultPlugin;
use Fossology\Lib\Auth\Auth;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;

class AjaxKotobaHistory extends DefaultPlugin
{
  const NAME = "kotoba-history";
  /** @var UploadDao */
  private $uploadDao;
  /** @var ClearingDao */
  private $clearingDao;

  function __construct()
  {
    parent::__construct(self::NAME, array(
        self::PERMISSION => Auth::PERM_READ
    ));

    $this->uploadDao = $this->getObject('dao.upload');
    $this->clearingDao = $this->getObject('dao.clearing');
  }

  /**
   * @param Request $request
   * @return Response
   */
  protected function handle(Request $request)
  {
    $uploadId = intval($request->get('upload'));
    if (empty($uploadId)) {
      return;
    }
    $uploadTreeId = intval($request->get('item'));
    if (empty($uploadTreeId)) {
      return;
    }
    $onlyTried = !$request->get('all');

    $uploadTreeTableName = $this->uploadDao->getUploadtreeTableName($uploadId);
    /** @var ItemTreeBounds */
    $itemTreeBounds = $this->uploadDao->getItemTreeBounds($uploadTreeId, $uploadTreeTableName);
    /** @var Session */
    $session = $this->getObject('session');
    $groupId = $session->get(Auth::GROUP_ID);
    $kotobaHistory = $this->clearingDao->getKotobaHistory($itemTreeBounds, $groupId, $onlyTried);
    return $this->render("kotoba-history.html.twig",$this->mergeWithDefault(array('kotobaHistory'=>$kotobaHistory)));
  }
}

register_plugin(new AjaxKotobaHistory());

