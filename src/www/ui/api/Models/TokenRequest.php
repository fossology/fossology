<?php
/*
 SPDX-FileCopyrightText: © 2023 Siemens AG
 SPDX-FileContributor: Gaurav Mishra <mishra.gaurav@siemens.com>

 SPDX-License-Identifier: GPL-2.0-only
 */

namespace Fossology\UI\Api\Models;

use DateTime;
use Fossology\Lib\Util\ArrayOperation;
use Fossology\UI\Api\Exceptions\HttpBadRequestException;
use Fossology\UI\Api\Helper\RestHelper;

class TokenRequest
{
  /**
   * @var array $VERSION_1_KEYS
   * Keys for version 1 of the API
   */
  const VERSION_1_KEYS = ["username", "password", "token_name", "token_scope",
    "token_expire"];
  /**
   * @var array $VERSION_2_KEYS
   * Keys for version 2 of the API
   */
  const VERSION_2_KEYS = ["username", "password", "tokenName", "tokenScope",
    "tokenExpire"];
  /**
   * @var string $tokenName
   * Token Name
   */
  private $tokenName;
  /**
   * @var string $tokenScope
   * Token Scope
   */
  private $tokenScope;
  /**
   * @var DateTime $tokenExpire
   * Token Expiry
   */
  private $tokenExpire;
  /**
   * @var string $username
   * Username
   */
  private $username;
  /**
   * @var string $password
   * Password
   */
  private $password;

  /**
   * @param string $tokenName   Token Name
   * @param string $tokenScope  Token Scope
   * @param string $tokenExpire Token Expiry
   * @param string $username    Username
   * @param string $password    Password
   * @throws HttpBadRequestException If request is invalid
   */
  public function __construct(string $tokenName, string $tokenScope,
                              string $tokenExpire, string $username="",
                              string $password="")
  {
    $this->setTokenName($tokenName);
    $this->setTokenScope($tokenScope);
    $this->setTokenExpire($tokenExpire);
    $this->setUsername($username);
    $this->setPassword($password);
  }

  /**
   * @param string $tokenName
   * @return TokenRequest
   * @throws HttpBadRequestException
   */
  public function setTokenName(string $tokenName): TokenRequest
  {
    if (empty($tokenName)) {
      throw new HttpBadRequestException("Token name cannot be empty");
    }
    $this->tokenName = $tokenName;
    return $this;
  }

  /**
   * @param string $tokenScope On of `RestHelper::VALID_SCOPES`
   * @return TokenRequest
   * @throws HttpBadRequestException
   */
  public function setTokenScope(string $tokenScope): TokenRequest
  {
    $tokenScope = strtolower($tokenScope);
    if (!in_array($tokenScope, RestHelper::VALID_SCOPES)) {
      throw new HttpBadRequestException("Invalid scope provided");
    }
    $this->tokenScope = RestHelper::SCOPE_DB_MAP[$tokenScope];
    return $this;
  }

  /**
   * @param string $tokenExpire
   * @return TokenRequest
   * @throws HttpBadRequestException
   */
  public function setTokenExpire(string $tokenExpire): TokenRequest
  {
    $this->tokenExpire = DateTime::createFromFormat("Y-m-d", $tokenExpire);
    if ($this->tokenExpire === false) {
      throw new HttpBadRequestException("Invalid date format provided");
    }
    return $this;
  }

  /**
   * @param string $username
   * @return TokenRequest
   */
  public function setUsername(string $username): TokenRequest
  {
    $this->username = $username;
    return $this;
  }

  /**
   * @param string $password
   * @return TokenRequest
   */
  public function setPassword(string $password): TokenRequest
  {
    $this->password = $password;
    return $this;
  }

  /**
   * @return string
   */
  public function getTokenName(): string
  {
    return $this->tokenName;
  }

  /**
   * @return string
   */
  public function getTokenScope(): string
  {
    return $this->tokenScope;
  }

  /**
   * @return string
   */
  public function getTokenExpire(): string
  {
    return $this->tokenExpire->format('Y-m-d');
  }

  /**
   * @return string
   */
  public function getUsername(): string
  {
    return $this->username;
  }

  /**
   * @return string
   */
  public function getPassword(): string
  {
    return $this->password;
  }

  /**
   * @param array $input Request body
   * @param int $version Version
   * @return TokenRequest
   * @throws HttpBadRequestException
   */
  public static function fromArray(array $input, int $version): TokenRequest
  {
    if (! array_key_exists("username", $input)) {
      $input["username"] = "";
    }
    if (! array_key_exists("password", $input)) {
      $input["password"] = "";
    }
    if ($version == ApiVersion::V1) {
      if (! ArrayOperation::arrayKeysExists($input, self::VERSION_1_KEYS)) {
        throw new HttpBadRequestException("Not all required parameters sent.");
      }
      return new TokenRequest(
        $input["token_name"],
        $input["token_scope"],
        $input["token_expire"],
        $input["username"],
        $input["password"]
      );
    } else {
      if (! ArrayOperation::arrayKeysExists($input, self::VERSION_2_KEYS)) {
        throw new HttpBadRequestException("Not all required parameters sent.");
      }
      return new TokenRequest(
        $input["tokenName"],
        $input["tokenScope"],
        $input["tokenExpire"],
        $input["username"],
        $input["password"]
      );
    }
  }
}
