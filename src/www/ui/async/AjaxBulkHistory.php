<?php
/*
 SPDX-FileCopyrightText: © 2014 Siemens AG
 Author: Daniele Fognini, Johannes Najjar

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

class AjaxBulkHistory extends DefaultPlugin
{
  const NAME = "bulk-history";
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
    if ($action === 'deleteBulk') {
      return $this->deleteBulkEntry($request);
    }
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
    $bulkHistory = $this->clearingDao->getBulkHistory($itemTreeBounds, $groupId, $onlyTried);
    return $this->render("bulk-history.html.twig",$this->mergeWithDefault(array('bulkHistory'=>$bulkHistory)));
  }
  /**
   * Delete a bulk history entry
   * @param Request $request
   * @return Response
   */
  protected function deleteBulkEntry(Request $request)
  {
    $bulkId = intval($request->get('bulkId'));
    if (empty($bulkId)) {
      return new Response('Invalid bulk ID', Response::HTTP_BAD_REQUEST);
    }
    /** @var Session */
    $session = $this->getObject('session');
    $groupId = $session->get(Auth::GROUP_ID);
    $userId = $session->get(Auth::USER_ID);
    if (!$this->clearingDao->canUserDeleteBulkEntry($bulkId, $userId, $groupId)) {
      return new Response('Permission denied', Response::HTTP_FORBIDDEN);
    }
    try {
      $this->clearingDao->deleteBulkEntry($bulkId, $groupId);
      return new JsonResponse(['status' => 'success'], Response::HTTP_OK);
    } catch (\Exception $e) {
      return new JsonResponse(['status' => 'error', 'message' => 'Deletion failed: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
  }
}

register_plugin(new AjaxBulkHistory());
