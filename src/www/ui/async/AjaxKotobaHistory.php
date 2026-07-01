<?php
/*
 SPDX-FileCopyrightText: © 2025 Harshit Gandhi <gandhiharshit716@gmail.com>
 SPDX-FileCopyrightText: © Fossology contributors

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
use Symfony\Component\HttpFoundation\JsonResponse;
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
        self::PERMISSION => Auth::PERM_WRITE
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
    $action = $request->get('do');
    if ($action === 'deleteKotoba') {
      return $this->deleteKotobaEntry($request);
    }
    $uploadId = intval($request->get('upload'));
    if (empty($uploadId)) {
      return new Response('Missing upload parameter', Response::HTTP_BAD_REQUEST);
    }
    $uploadTreeId = intval($request->get('item'));
    if (empty($uploadTreeId)) {
      return new Response('Missing item parameter', Response::HTTP_BAD_REQUEST);
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

  /**
   * Delete a kotoba history entry
   * @param Request $request
   * @return Response
   */
  protected function deleteKotobaEntry(Request $request)
  {
    $reportinfo = $request->get('reportinfo');
    if (empty($reportinfo)) {
      return new Response('Invalid reportinfo', Response::HTTP_BAD_REQUEST);
    }
    /** @var Session */
    $session = $this->getObject('session');
    $groupId = $session->get(Auth::GROUP_ID);
    $userId = $session->get(Auth::USER_ID);
    if (!$this->clearingDao->canUserDeleteKotobaEntry($reportinfo, $userId, $groupId)) {
      return new Response('Permission denied', Response::HTTP_FORBIDDEN);
    }
    try {
      $this->clearingDao->deleteKotobaEntry($reportinfo, $groupId);
      return new JsonResponse(['status' => 'success'], Response::HTTP_OK);
    } catch (\Exception $e) {
      return new JsonResponse(['status' => 'error', 'message' => 'Deletion failed: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
  }
}

register_plugin(new AjaxKotobaHistory());

