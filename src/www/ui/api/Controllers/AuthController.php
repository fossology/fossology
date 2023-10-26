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
use Fossology\UI\Api\Exceptions\HttpConflictException;
use Fossology\UI\Api\Exceptions\HttpErrorException;
use Fossology\UI\Api\Exceptions\HttpInternalServerErrorException;
use Fossology\UI\Api\Exceptions\HttpNotFoundException;
use Fossology\UI\Api\Exceptions\HttpTooManyRequestException;
use Fossology\UI\Api\Helper\ResponseHelper;
use Fossology\UI\Api\Helper\RestHelper;
use Fossology\UI\Api\Models\ApiVersion;
use Fossology\UI\Api\Models\TokenRequest;
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
   */
  public function createNewJwtToken($request, $response, $args)
  {
    if (Auth::getRestTokenType() == Auth::TOKEN_OAUTH) {
      throw new HttpBadRequestException("Request to create tokens blocked. " .
        "Use OAuth clients.");
    }
    $tokenRequestBody = $this->getParsedBody($request);
    $tokenRequest = TokenRequest::fromArray($tokenRequestBody,
      ApiVersion::getVersion($request));

    $this->restHelper->validateTokenRequest($tokenRequest->getTokenExpire(),
      $tokenRequest->getTokenName(), $tokenRequest->getTokenScope());
    // Request is in correct format.
    $authHelper = $this->restHelper->getAuthHelper();
    if (!$authHelper->checkUsernameAndPassword($tokenRequest->getUsername(),
      $tokenRequest->getPassword())) {
      throw new HttpNotFoundException("Username or password incorrect.");
    }

    $userId = $this->restHelper->getUserId();
    $key    = bin2hex(
      openssl_random_pseudo_bytes(RestHelper::TOKEN_KEY_LENGTH / 2));
    try {
      $jti = $this->dbHelper->insertNewTokenKey($userId,
        $tokenRequest->getTokenExpire(), $tokenRequest->getTokenScope(),
        $tokenRequest->getTokenName(), $key);
    } catch (DuplicateTokenKeyException $e) {
      // Key already exists, try again.
      $key = bin2hex(
        openssl_random_pseudo_bytes(RestHelper::TOKEN_KEY_LENGTH / 2));
      try {
        $jti = $this->dbHelper->insertNewTokenKey($userId,
          $tokenRequest->getTokenExpire(), $tokenRequest->getTokenScope(),
          $tokenRequest->getTokenName(), $key);
      } catch (DuplicateTokenKeyException $e) {
        // New key also failed, give up!
        throw new HttpTooManyRequestException("Please try again later.");
      } catch (DuplicateTokenNameException $e) {
        throw new HttpConflictException($e->getMessage(), $e);
      }
    } catch (DuplicateTokenNameException $e) {
      throw new HttpConflictException($e->getMessage(), $e);
    }
    if (! empty($jti['jti'])) {
      $theJwtToken = $this->restHelper->getAuthHelper()->generateJwtToken(
        $tokenRequest->getTokenExpire(), $jti['created_on'], $jti['jti'],
        $tokenRequest->getTokenScope(), $key);
      return $response->withJson([
        "Authorization" => "Bearer " . $theJwtToken
      ], 201);
    }
    throw new HttpInternalServerErrorException("Please try again later.");
  }
}
