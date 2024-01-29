<?php
/*
 SPDX-FileCopyrightText: © 2019 Siemens AG
 Author: Gaurav Mishra <mishra.gaurav@siemens.com>

 SPDX-License-Identifier: GPL-2.0-only
*/
namespace Fossology\UI\Ajax;

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Plugin\DefaultPlugin;
use Fossology\UI\Api\Helper\AuthHelper;
use Fossology\UI\Api\Helper\DbHelper;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * @class AjaxManageToken
 * @brief Class to handle ajax calls to revoke an API token
 */
class AjaxManageToken extends DefaultPlugin
{

  const NAME = "manage-token";

  /** @var DbManager $dbManager
   * DB manager to use */
  private $dbManager;

  function __construct()
  {
    parent::__construct(self::NAME,
      array(
        self::PERMISSION => Auth::PERM_WRITE
      ));
    $this->dbManager = $this->getObject('db.manager');
  }

  /**
   * @brief Revoke an active API token
   * @param Request $request
   * @return Response Status as true if token is revoked or false on failure.
   */
  protected function handle(Request $request)
  {
    $task = GetParm('task', PARM_STRING);
    $tokenId = GetParm('token-id', PARM_STRING);
    $response = null;

    list($tokenPk, $userId) = explode(".", $tokenId);
    if (Auth::getUserId() != $userId) {
      $task = "invalid";
    } else {
      $verifySql = "SELECT user_fk FROM personal_access_tokens " .
                   "WHERE pat_pk = $1 LIMIT 1;";

      $row = $this->dbManager->getSingleRow($verifySql, [$tokenPk],
        __METHOD__ . ".verifyToken");
      if (empty($row) || $row['user_fk'] != $userId) {
        $task = "invalid";
      }
    }
    switch ($task) {
      case "reveal":
        $response = new JsonResponse($this->revealToken($tokenPk,
          $request->getHost()));
        break;
      case "revoke":
        $response = new JsonResponse($this->invalidateToken($tokenPk));
        break;
      default:
        $response = new JsonResponse(["status" => false], 400);
    }
    return $response;
  }

  /**
   * Regenerate the JWT token from DB, or get the client ID.
   *
   * @param int    $tokenPk  The token id
   * @param string $hostname Host issuing the token
   * @returns array Array with success status and token.
   */
  function revealToken($tokenPk, $hostname="")
  {
    global $container;
    /** @var DbHelper $restDbHelper */
    $restDbHelper = $container->get("helper.dbHelper");
    /** @var AuthHelper $authHelper */
    $authHelper = $container->get('helper.authHelper');
    $user_pk = Auth::getUserId();
    $jti = "$tokenPk.$user_pk";

    $tokenInfo = $restDbHelper->getTokenKey($tokenPk);
    if (!empty($tokenInfo['client_id'])) {
      return [
        "status" => true,
        "token" => $tokenInfo['client_id']
      ];
    }
    $tokenScope = $tokenInfo['token_scope'];

    $jwtToken = $authHelper->generateJwtToken($tokenInfo['expire_on'],
      $tokenInfo['created_on'], $jti, $tokenScope, $tokenInfo['token_key']);
    return array(
      "status" => true,
      "token" => $jwtToken
    );
  }

  /**
   * Mark a token as invalid/inactive.
   *
   * @param int $tokenPk  The token id to be revoked
   * @returns array Array with success status.
   */
  private function invalidateToken($tokenPk)
  {
    global $container;
    $restDbHelper = $container->get("helper.dbHelper");
    $restDbHelper->invalidateToken($tokenPk);
    return array(
      "status" => true
    );
  }
}

register_plugin(new AjaxManageToken());
