<?php
/*
 SPDX-FileCopyrightText: © 2018 Siemens AG
 Author: Gaurav Mishra <mishra.gaurav@siemens.com>

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @file
 * @brief Controller for auth queries
 */

namespace Fossology\UI\Api\Controllers;

use Fossology\Lib\Auth\Auth;
use Psr\Http\Message\ServerRequestInterface;
use Fossology\UI\Api\Helper\RestHelper;
use Fossology\UI\Api\Models\Info;
use Fossology\UI\Api\Models\InfoType;
use Fossology\Lib\Exceptions\DuplicateTokenKeyException;
use Fossology\Lib\Exceptions\DuplicateTokenNameException;
use Fossology\UI\Api\Helper\ResponseHelper;

/**
 * @class AuthController
 * @brief Controller for Auth requests
 */
class AuthController extends RestController
{

  /**
   * Respond to OPTIONS requests with an empty 204 response
   *
   * @param ServerRequestInterface $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   */
  public function optionsVerification($request, $response, $args)
  {
    return $response->withStatus(204);
  }

  /**
   * Get the JWT authentication headers for the user
   *
   * @param ServerRequestInterface $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   */
  public function createNewJwtToken($request, $response, $args)
  {
    if (Auth::getRestTokenType() == Auth::TOKEN_OAUTH) {
      $error = new Info(400,
        "Request to create tokens blocked. Use OAuth clients.",
        InfoType::ERROR);
      return $response->withJson($error->getArray(), $error->getCode());
    }
    $tokenRequestBody = $this->getParsedBody($request);
    $paramsRequired = [
      "username",
      "password",
      "token_name",
      "token_scope",
      "token_expire"
    ];
    $returnVal = null;

    if (! $this->arrayKeysExists($tokenRequestBody, $paramsRequired)) {
      $error = new Info(400,
        "Following parameters are required in the request body: " .
        join(",", $paramsRequired), InfoType::ERROR);
      $returnVal = $response->withJson($error->getArray(), $error->getCode());
    } else {
      $tokenValid = $this->restHelper->validateTokenRequest(
        $tokenRequestBody["token_expire"], $tokenRequestBody["token_name"],
        $tokenRequestBody["token_scope"]);
      if ($tokenValid !== true) {
        $returnVal = $response->withJson($tokenValid->getArray(),
          $tokenValid->getCode());
      } else {
        // Request is in correct format.
        $authHelper = $this->restHelper->getAuthHelper();
        if ($authHelper->checkUsernameAndPassword($tokenRequestBody["username"],
          $tokenRequestBody["password"])) {
          $userId = $this->restHelper->getUserId();
          $expire = $tokenRequestBody["token_expire"];
          $scope  = $tokenRequestBody["token_scope"];
          $name   = $tokenRequestBody["token_name"];
          $key    = bin2hex(
            openssl_random_pseudo_bytes(RestHelper::TOKEN_KEY_LENGTH / 2));
          try {
            $jti = $this->dbHelper->insertNewTokenKey($userId, $expire,
              RestHelper::SCOPE_DB_MAP[$scope], $name, $key);
          } catch (DuplicateTokenKeyException $e) {
            // Key already exists, try again.
            $key = bin2hex(
              openssl_random_pseudo_bytes(RestHelper::TOKEN_KEY_LENGTH / 2));
            try {
              $jti = $this->dbHelper->insertNewTokenKey($userId, $expire,
                RestHelper::SCOPE_DB_MAP[$scope], $name, $key);
            } catch (DuplicateTokenKeyException $e) {
              // New key also failed, give up!
              $error = new Info(429, "Please try again later.", InfoType::ERROR);
              $returnVal = $response->withHeader('Retry-After', 2)->withJson(
                $error->getArray(), $error->getCode());
            }
          } catch (DuplicateTokenNameException $e) {
            $error = new Info($e->getCode(), $e->getMessage(), InfoType::ERROR);
            $returnVal = $response->withJson($error->getArray(),
              $error->getCode());
          }
          if (isset($jti['jti']) && ! empty($jti['jti'])) {
            $theJwtToken = $this->restHelper->getAuthHelper()->generateJwtToken(
              $expire, $jti['created_on'], $jti['jti'], $scope, $key);
            $returnVal = $response->withJson([
              "Authorization" => "Bearer " . $theJwtToken
            ], 201);
          }
        } else {
          $error = new Info(404, "Username or password incorrect.",
            InfoType::ERROR);
          $returnVal = $response->withJson($error->getArray(), $error->getCode());
        }
      }
    }
    return $returnVal;
  }

  /**
   * @brief Check if a list of keys exists in associative array.
   *
   * This function takes a list of keys which should appear in an associative
   * array. The function flips the key array to make it as an associative array.
   * It then uses the array_diff_key() to compare the two arrays.
   *
   * @param array $array Associative array to check keys against
   * @param array $keys  Array of keys to check
   * @return boolean True if all keys exists, false otherwise.
   * @uses array_flip()
   * @uses array_diff_key()
   */
  private function arrayKeysExists($array, $keys)
  {
    return !array_diff_key(array_flip($keys), $array);
  }
}

