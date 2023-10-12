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
use Fossology\Lib\Exceptions\DuplicateTokenKeyException;
use Fossology\Lib\Exceptions\DuplicateTokenNameException;
use Fossology\UI\Api\Exceptions\HttpBadRequestException;
use Fossology\UI\Api\Exceptions\HttpErrorException;
use Fossology\UI\Api\Exceptions\HttpInternalServerErrorException;
use Fossology\UI\Api\Exceptions\HttpNotFoundException;
use Fossology\UI\Api\Exceptions\HttpTooManyRequestException;
use Fossology\UI\Api\Helper\ResponseHelper;
use Fossology\UI\Api\Helper\RestHelper;
use Psr\Http\Message\ServerRequestInterface;

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
   * @throws HttpErrorException
   * @throws DuplicateTokenNameException
   */
  public function createNewJwtToken($request, $response, $args)
  {
    if (Auth::getRestTokenType() == Auth::TOKEN_OAUTH) {
      throw new HttpBadRequestException("Request to create tokens blocked. " .
        "Use OAuth clients.");
    }
    $tokenRequestBody = $this->getParsedBody($request);
    $paramsRequired = [
      "username",
      "password",
      "token_name",
      "token_scope",
      "token_expire"
    ];

    if (! $this->arrayKeysExists($tokenRequestBody, $paramsRequired)) {
      throw new HttpBadRequestException("Following parameters are required " .
        "in the request body: " . join(",", $paramsRequired));
    }
    $this->restHelper->validateTokenRequest(
      $tokenRequestBody["token_expire"], $tokenRequestBody["token_name"],
      $tokenRequestBody["token_scope"]);
    // Request is in correct format.
    $authHelper = $this->restHelper->getAuthHelper();
    if (!$authHelper->checkUsernameAndPassword($tokenRequestBody["username"],
      $tokenRequestBody["password"])) {
      throw new HttpNotFoundException("Username or password incorrect.");
    }

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
        throw new HttpTooManyRequestException("Please try again later.");
      }
    } catch (DuplicateTokenNameException $e) {
      throw new HttpErrorException($e->getMessage(), $e->getCode(), $e);
    }
    if (! empty($jti['jti'])) {
      $theJwtToken = $this->restHelper->getAuthHelper()->generateJwtToken(
        $expire, $jti['created_on'], $jti['jti'], $scope, $key);
      return $response->withJson([
        "Authorization" => "Bearer " . $theJwtToken
      ], 201);
    }
    throw new HttpInternalServerErrorException("Please try again later.");
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

