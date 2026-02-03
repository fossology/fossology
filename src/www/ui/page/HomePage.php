<?php
/*
 SPDX-FileCopyrightText: © 2014-2016, 2021 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\UI\Page;

use Firebase\JWT\JWT;
use Fossology\Lib\Plugin\DefaultPlugin;
use Fossology\UI\Api\Helper\AuthHelper;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Provider\GenericProvider;
use League\OAuth2\Client\Token\AccessToken;
use League\OAuth2\Client\Token\AccessTokenInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @brief about page on UI
 */
class HomePage extends DefaultPlugin
{
  const NAME = "home";

  function __construct()
  {
    parent::__construct(self::NAME, array(
        self::TITLE =>  _("Getting Started with FOSSology"),
        self::REQUIRES_LOGIN => false,
        self::MENU_LIST => "Home",
        self::MENU_ORDER => 100
    ));
  }

  /**
   * @param Request $request
   * @return Response
   */
  protected function handle(Request $request)
  {
    global $SysConf;

    $vars = array('isSecure' => $request->isSecure());
    $vars['loginProvider'] = "password";
    if (array_key_exists('AUTHENTICATION', $SysConf) &&
      array_key_exists('provider', $SysConf['AUTHENTICATION'])) {
        $vars['loginProvider'] = $SysConf['AUTHENTICATION']['provider'];
    }
    // Protocol detection: prefer X-Forwarded-Proto header
    $vars['protocol'] = strtoupper(getProtocolScheme());

    if (array_key_exists('User', $_SESSION) && $_SESSION['User'] ==
      "Default User" && plugin_find_id("auth") >= 0) {
      $vars['protocol'] = strtoupper(getProtocolScheme());

      $vars['referrer'] = "?mod=browse";
      $vars['authUrl'] = "?mod=auth";
    }
    $vars['getEmail'] = "";
    $vars['getOauth'] = false;

    $email = null;
    if (! empty(GetParm("code", PARM_TEXT))) {
      try {
        $email = $this->getEmailFromOAuth();
      } catch (IdentityProviderException $e) {
        $vars['message'] = $e->getMessage();
      } catch (\UnexpectedValueException $e) {
        $vars['message'] = $e->getMessage();
      }
    }
    if (! empty(GetParm("error", PARM_TEXT))) {
      $vars['message'] = GetParm("error_description", PARM_TEXT);
    }

    if ($email !== null) {
      $_SESSION['oauthemail'] = $email;
      $vars['getOauth'] = true;
      if (array_key_exists('HTTP_REFERER', $_SESSION)) {
        $vars['referrer'] = $_SESSION['HTTP_REFERER'];
      }
    }

    if (!empty($SysConf['SYSCONFIG']['OidcAppName'])) {
      $vars['providerExist'] = $SysConf['SYSCONFIG']['OidcAppName'];
    } else {
      $vars['providerExist'] = 0;
    }
    return $this->render("home.html.twig", $this->mergeWithDefault($vars));
  }

  /**
   * Get the email from the OAuth2 server.
   *
   * @throws \UnexpectedValueException If the state is invalid or access token
   *                                   is invalid
   * @throws IdentityProviderException If something goes wrong while
   *                                   authenticating
   * @return NULL|string Email from OAuth if success
   */
  private function getEmailFromOAuth()
  {
    global $SysConf;
    if (empty(GetParm("state", PARM_TEXT)) ||
        (isset($_SESSION['oauth2state']) &&
        GetParm("state", PARM_TEXT) !== $_SESSION['oauth2state'])) {
      // Check given state against previously stored one to mitigate CSRF attack
      if (isset($_SESSION['oauth2state'])) {
        unset($_SESSION['oauth2state']);
      }
      throw new \UnexpectedValueException('Invalid state');
    }
    $proxy = "";
    if (array_key_exists('http_proxy', $SysConf['FOSSOLOGY']) &&
        ! empty($SysConf['FOSSOLOGY']['http_proxy'])) {
      $proxy = $SysConf['FOSSOLOGY']['http_proxy'];
    }
    if (array_key_exists('https_proxy', $SysConf['FOSSOLOGY']) &&
        ! empty($SysConf['FOSSOLOGY']['https_proxy'])) {
      $proxy = $SysConf['FOSSOLOGY']['https_proxy'];
    }

    $provider = new GenericProvider([
      "clientId"                => $SysConf['SYSCONFIG']['OidcAppId'],
      "clientSecret"            => $SysConf['SYSCONFIG']['OidcSecret'],
      "redirectUri"             => $SysConf['SYSCONFIG']['OidcRedirectURL'],
      "urlAuthorize"            => $SysConf['SYSCONFIG']['OidcAuthorizeURL'],
      "urlAccessToken"          => $SysConf['SYSCONFIG']['OidcAccessTokenURL'],
      "urlResourceOwnerDetails" => $SysConf['SYSCONFIG']['OidcResourceURL'],
      "responseResourceOwnerId" => $SysConf['SYSCONFIG']['OidcResourceOwnerId'],
      "proxy"                   => $proxy
    ]);
    $accessToken = $provider->getAccessToken('authorization_code',
      ['code' => GetParm("code", PARM_TEXT)]);

    $this->validateAccessToken($accessToken);

    return $this->getEmailFromResource($provider, $accessToken);
  }

  /**
   * Validate JWT access token from OIDC
   *
   * @param AccessTokenInterface $accessToken Access token from provider
   * @return bool True on success, throw exception if invalid.
   *
   * @throws \UnexpectedValueException Exception in case token is invalid
   */
  private function validateAccessToken($accessToken)
  {
    global $SysConf;
    /**
     * @var AuthHelper $authHelper
     * Auth helper to load JWKS
     */
    $authHelper = $this->container->get('helper.authHelper');
    $jwks = $authHelper::loadJwks();
    $jwtToken = null;
    if ($SysConf['SYSCONFIG']['OidcTokenType'] === "A") {
      $jwtToken = $accessToken->getToken();
    } elseif ($SysConf['SYSCONFIG']['OidcTokenType'] === "I") {
      $jwtToken = $accessToken->getValues()['id_token'];
    }
    if (empty($jwtToken)) {
      throw new \UnexpectedValueException("Unable to get identity from OIDC token. " .
        "Please check 'Token to use from provider' field in config.");
    }
    try {
      $jwtTokenDecoded = JWT::decode(
        $jwtToken,
        $jwks
      );
    } catch (\Exception $e) {
      throw new \UnexpectedValueException("JWKS: " . $e->getMessage());
    }
    if (property_exists($jwtTokenDecoded, 'iss') &&
        $jwtTokenDecoded->{'iss'} == $SysConf['SYSCONFIG']['OidcIssuer']) {
      return true;
    }
    throw new \UnexpectedValueException("Invalid issuer of token.");
  }

  /**
   * Get the email information from the resource server.
   *
   * @param GenericProvider $provider
   * @param AccessToken $accessToken
   * @return string|NULL
   */
  private function getEmailFromResource($provider, $accessToken)
  {
    $resourceOwner = $provider->getResourceOwner($accessToken);
    if (!empty($resourceOwner->getId())) {
      return $resourceOwner->getId();
    }
    return null;
  }
}

register_plugin(new HomePage());
