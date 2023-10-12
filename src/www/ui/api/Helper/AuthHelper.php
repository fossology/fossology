<?php
/*
 SPDX-FileCopyrightText: © 2018, 2021 Siemens AG
 SPDX-FileCopyrightText: © 2021-2022 Orange
 Contributors: Piotr Pszczola, Bartlomiej Drozdz

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * @dir
 * @brief Helper functions for REST api use.
 * @file
 * @brief Provides authentication helper methods for REST api.
 * @namespace Fossology::UI::Api::Helper
 * @brief REST api helper classes
 */
namespace Fossology\UI\Api\Helper;

use Firebase\JWT\CachedKeySet;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Dao\UserDao;
use Fossology\UI\Api\Exceptions\HttpBadRequestException;
use Fossology\UI\Api\Exceptions\HttpForbiddenException;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\HttpFoundation\Session\Session;
use UnexpectedValueException;

/**
 * @class AuthHelper
 * @brief Provides helper methods for REST api
 */
class AuthHelper
{
  /**
   * @var Session $session
   * Current Symfony session
   */
  private $session;
  /**
   * @var UserDao $userDao
   * User DAO object
   */
  private $userDao;
  /**
   * @var DbHelper $dbHelper
   * DB helper for DB interaction.
   */
  private $dbHelper;

  /**
   * AuthHelper constructor.
   *
   * @param UserDao $userDao   User dao to use
   * @param Session $session   Session to use
   * @param DbHelper $dbhelper Db Helper to use
   */
  public function __construct(UserDao $userDao, Session $session,
    DbHelper $dbhelper)
  {
    $this->userDao = $userDao;
    $this->session = $session;
    $this->dbHelper = $dbhelper;
    if (!$this->session->isStarted()) {
      $this->session->setName('Login');
      $this->session->start();
    }
    JWT::$leeway = 30; // Set 30 seconds of leeway
  }

  /**
   * @brief Check the username and password against the database.
   *
   * If the user is not 'Default User' and is valid, this function also update
   * session using updateSession().
   * @param string $userName  Username
   * @param string $password  Password
   * @return boolean True if user is valid, false otherwise.
   * @sa updateSession()
   */
  public function checkUsernameAndPassword($userName, $password)
  {
    $authPlugin = $GLOBALS["container"]->get("helper.restHelper")->getPlugin('auth');
    return $authPlugin->checkUsernameAndPassword($userName, $password);
  }

  /**
   * Verify the JWT/oauth token sent by user.
   *
   * @param string $authHeader The "Authorization" header sent by user.
   * @param int $userId The user id as per the valid token.
   * @param string $tokenScope The scope of the token presented.
   * @return void
   * @throws HttpBadRequestException If the header is malformed.
   * @throws HttpForbiddenException  If the user is inactive.
   */
  public function verifyAuthToken($authHeader, &$userId, &$tokenScope)
  {
    global $SysConf;
    $jwtTokenMatch = null;
    $headerValid = preg_match(
      "/^bearer (([a-zA-Z0-9\-\_\+\/\=]+)\.([a-zA-Z0-9\-\_\+\/\=]+)\.([a-zA-Z0-9\-\_\+\/\=]+))$/i",
      $authHeader, $jwtTokenMatch);
    if (! $headerValid) {
      throw new HttpBadRequestException(
        "Authorization header is malformed or empty.");
    }
    $jwtToken           = $jwtTokenMatch[1];
    $jwtTokenPayload    = $jwtTokenMatch[3];
    $jwtTokenPayloadDecoded = JWT::jsonDecode(
      JWT::urlsafeB64Decode($jwtTokenPayload));

    $restToken = Auth::getRestTokenType();
    if (($restToken & Auth::TOKEN_OAUTH) == Auth::TOKEN_OAUTH &&
      property_exists($jwtTokenPayloadDecoded, 'iss') &&
      $jwtTokenPayloadDecoded->{'iss'} == $SysConf['SYSCONFIG']['OidcIssuer']
    ) {
      $this->validateOauthLogin(
        $jwtToken,
        $userId,
        $tokenScope
      );
    } else if (($restToken & Auth::TOKEN_TOKEN) == Auth::TOKEN_TOKEN &&
      ! property_exists($jwtTokenPayloadDecoded, 'iss')
    ) {
      $this->validateTokenLogin(
        $jwtToken,
        $jwtTokenPayloadDecoded,
        $userId,
        $tokenScope
      );
    } else {
      throw new HttpForbiddenException("Invalid token type sent.");
    }

    $isUserActive = $this->userDao->isUserIdActive($userId);
    if (!$isUserActive) {
      throw new HttpForbiddenException("User inactive.");
    }
  }

  /**
   * Check if the given date is expired (is past).
   *
   * @param string $date Date in `Y-m-d` format
   * @return boolean True if the date is of past.
   */
  private function isDateExpired($date)
  {
    if (empty($date)) { // oauth clients do not have expiry
      return false;
    }
    return strtotime("today") > strtotime($date);
  }

  /**
   * Check if the token is still active and not expired.
   *
   * @param array $valuesFromDb Values from DB.
   * @param int   $tokenId Token id (pat_pk)
   * @throws HttpForbiddenException If the token is expired.
   */
  public function isTokenActive($valuesFromDb, $tokenId)
  {
    if ($valuesFromDb['active'] == "f") {
      throw new HttpForbiddenException("Token expired.");
    } elseif ($this->isDateExpired($valuesFromDb['expire_on']) &&
      $valuesFromDb['active'] == "t") {
      $this->dbHelper->invalidateToken($tokenId);
      throw new HttpForbiddenException("Token expired.");
    }
  }

  /**
   * Get the current Symfony session
   * @return Session
   */
  public function getSession()
  {
    return $this->session;
  }

  /**
   * @brief Update the session using updateSession().
   *
   * @param int    $userId User id from the JWT.
   * @param string $scope  Scope of the current token.
   * @param string $groupName  Name of the group to update session with.
   * @sa updateSession()
   */
  public function updateUserSession($userId, $scope, $groupName = null)
  {
    $authPlugin = $GLOBALS["container"]->get("helper.restHelper")->getPlugin('auth');
    $user = $this->userDao->getUserByPk($userId);
    $row = $this->userDao->getUserAndDefaultGroupByUserName($user["user_name"]);
    if ($groupName !== null) {
      $row['group_fk'] = $this->userDao->getGroupIdByName($groupName);
      $row['group_name'] = $groupName;
    }
    $authPlugin->updateSession($row);
    $this->getSession()->set('token_scope', $scope);
  }

  /**
   * Generates new JWT token.
   *
   * @param string $expire   When the token will expire ('YYYY-MM-DD')
   * @param string $created  When the token was created ('YYYY-MM-DD')
   * @param string $jti      Token id (`pat_pk.user_pk`)
   * @param string $scope    User friendly token scope
   * @param string $key      Token secret key
   * @return string New JWT token
   */
  public function generateJwtToken($expire, $created, $jti, $scope, $key)
  {
    $newJwtToken = [
      "exp" => strtotime($expire . " +1 day -1 second"),  // To allow day level granularity
      "nbf" => strtotime($created),
      "jti" => base64_encode($jti),
      "scope" => $scope
    ];
    return JWT::encode($newJwtToken, $key, 'HS256');
  }

  /**
   * Get the value for maximum API token validity from sysconfig table.
   *
   * @return integer The value stored in DB.
   * @see Fossology::UI::Api::Helper::getMaxTokenValidity()
   */
  public function getMaxTokenValidity()
  {
    return $this->dbHelper->getMaxTokenValidity();
  }

  /**
   * @brief Verify if given User Id has access to given Group name.
   *
   * @param int $userId User id from the JWT.
   * @param string $groupName Name of the group to verify access to.
   * @return void
   * @throws HttpForbiddenException If the user does not have access to group.
   */
  public function userHasGroupAccess($userId, $groupName)
  {
    $this->isGroupExisting($groupName);
    $groupMap = $this->userDao->getUserGroupMap($userId);
    $userHasGroupAccess = in_array($groupName, $groupMap, true);

    if (!$userHasGroupAccess) {
      throw new HttpForbiddenException(
        "User has no access to " . $groupName . " group");
    }
  }

  /**
   * @brief Verify if given Group name exists.
   *
   * @param string $groupName Name of the group to update session with.
   * @return void
   * @throws HttpForbiddenException If the group does not exist.
   */
  public function isGroupExisting($groupName)
  {
    if (empty($this->userDao->getGroupIdByName($groupName))) {
      throw new HttpForbiddenException(
        "Provided group:" . $groupName . " does not exist");
    }
  }

  /**
   * @brief Validate OAuth token
   *
   * Oauth tokens are majorly signed by RS256. Verify the key with library
   * against the JWKs. If valid, then fetch the user id and token scope from the
   * DB against the `client_id` stored in the token.
   *
   * @param string $jwtToken Token from header
   * @param[out] integer $userId     User ID from DB
   * @param[out] string  $tokenScope Token scope from DB
   *
   * @return void
   * @throws HttpForbiddenException If the token is expired.
   */
  private function validateOauthLogin($jwtToken, &$userId, &$tokenScope)
  {
    global $SysConf;
    $jwks = $this->loadJwks();
    try {
      try {
        $jwtTokenDecoded = JWT::decode(
          $jwtToken,
          $jwks
        );
      } catch (\Exception $e) {
        throw new \UnexpectedValueException("JWKS: " . $e->getMessage());
      }
      $clientId = $jwtTokenDecoded->{$SysConf['SYSCONFIG']['OidcClientIdClaim']};
      $tokenId = $this->dbHelper->getTokenIdFromClientId($clientId);
      $dbRows = $this->dbHelper->getTokenKey($tokenId);

      if (empty($dbRows)) {
        throw new \UnexpectedValueException("Invalid token sent.", 403);
      }
      $this->isTokenActive($dbRows, $tokenId);
      $userId = $dbRows['user_fk'];
      $tokenScope = $dbRows['token_scope'];
      if ($tokenScope == "w") {
        $tokenScope = "write";
      } elseif ($tokenScope == "r") {
        $tokenScope = "read";
      }
    } catch (\UnexpectedValueException $e) {
      throw new HttpForbiddenException($e->getMessage(), $e);
    }
  }

  /**
   * @brief Load the JWK array
   *
   * Load the JWK list from cache file (if exists), otherwise download from
   * server and cache it. The cache is stored for 24 hours.
   *
   * @return CachedKeySet JWK keys
   * @throws UnexpectedValueException Throws exception if jwk does not contain
   *                                  "keys"
   */
  public static function loadJwks()
  {
    global $SysConf;
    $cacheDir = array_key_exists('CACHEDIR', $GLOBALS) ? $GLOBALS['CACHEDIR'] : null;
    $cacheDuration = 60 * 60 * 24; // 24 hours
    $algInject = $SysConf['SYSCONFIG']['OidcJwkAlgInject'];
    if (empty($algInject)) {
      $algInject = null;
    }

    $proxy = [];

    if (
      array_key_exists('http_proxy', $SysConf['FOSSOLOGY']) &&
      !empty($SysConf['FOSSOLOGY']['http_proxy'])
    ) {
      $proxy['http'] = $SysConf['FOSSOLOGY']['http_proxy'];
    }
    if (
      array_key_exists('https_proxy', $SysConf['FOSSOLOGY']) &&
      !empty($SysConf['FOSSOLOGY']['https_proxy'])
    ) {
      $proxy['https'] = $SysConf['FOSSOLOGY']['https_proxy'];
    }
    if (
      array_key_exists('no_proxy', $SysConf['FOSSOLOGY']) &&
      !empty($SysConf['FOSSOLOGY']['no_proxy'])
    ) {
      $proxy['no'] = explode(',', $SysConf['FOSSOLOGY']['no_proxy']);
    }

    $version = $SysConf['BUILD']['VERSION'];
    $headers = ['User-Agent' => "fossology/$version"];

    $guzzleClient = new Client([
      'http_errors' => false,
      'proxy' => $proxy,
      'headers' => $headers
    ]);

    $httpFactory = new HttpFactory();

    $cacheItemPool = new FilesystemAdapter('rest', $cacheDuration, $cacheDir);

    return new CachedKeySet(
      $SysConf['SYSCONFIG']['OidcJwksURL'],
      $guzzleClient,
      $httpFactory,
      $cacheItemPool,
      $cacheDuration,
      true,
      $algInject
    );
  }

  /**
   * @brief Validate JWT token from FOSSology
   *
   * The token id is base64 encoded in JTI and the key for it will be fetched
   * from the DB to validate the token. Once valid and active, the userid and
   * scope will be taken from the DB.
   *
   * @param string $jwtToken Token from header
   * @param object $jwtTokenPayloadDecoded Decoded token
   * @param[out] integer $userId     User ID from DB
   * @param[out] string  $tokenScope Token scope from DB
   *
   * @return void
   * @throws HttpForbiddenException If the token is expired.
   */
  private function validateTokenLogin($jwtToken, $jwtTokenPayloadDecoded,
                                      &$userId, &$tokenScope)
  {
    $jwtJti = $jwtTokenPayloadDecoded->{'jti'};
    $jwtJti = base64_decode($jwtJti, true);
    list ($tokenId, $userId) = explode(".", $jwtJti);

    $dbRows = $this->dbHelper->getTokenKey($tokenId);
    if (empty($dbRows)) {
      throw new HttpForbiddenException("Invalid token sent.");
    }
    $this->isTokenActive($dbRows, $tokenId);
    try {
      $jwtTokenDecoded = JWT::decode($jwtToken,
        new Key($dbRows["token_key"], 'HS256'));
      $tokenScope = $jwtTokenDecoded->{'scope'};
    } catch (\UnexpectedValueException $e) {
      throw new HttpForbiddenException($e->getMessage(), $e);
    }
  }
}
