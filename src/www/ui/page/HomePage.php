<?php
/*
Copyright (C) 2014-2016, Siemens AG

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
version 2 as published by the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License along
with this program; if not, write to the Free Software Foundation, Inc.,
51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/

namespace Fossology\UI\Page;

use Fossology\Lib\Plugin\DefaultPlugin;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Omines\OAuth2\Client\Provider\Gitlab;

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
    $vars = array('isSecure' => $request->isSecure());
    if (array_key_exists('User', $_SESSION) && $_SESSION['User']=="Default User" && plugin_find_id("auth")>=0)
    {
      if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != "off")
      {
        $vars['protocol'] = "HTTPS";
      }
      else
      {
        $vars['protocol'] = preg_replace("@/.*@", "", @$_SERVER['SERVER_PROTOCOL']);
      }

      $vars['referrer'] = "?mod=browse";
      $vars['authUrl'] = "?mod=auth";
    }
    $vars['getEmail'] = "";
    $vars['getOAuthClient'] = false;

    global $SysConf;
    if(isset($_GET['code'])) {
      $domainOauth = "https://gitlab.com";
      if(!empty($SysConf['SYSCONFIG']['GitlabDomainURL'])){
        $domainOauth = $SysConf['SYSCONFIG']['GitlabDomainURL'];
      }
      $provider = new Gitlab([
        "clientId"                => $SysConf['SYSCONFIG']['GitlabAppIdOauth'],
        "clientSecret"            => $SysConf['SYSCONFIG']['GitlabSecretOauth'],
        "redirectUri"             => $SysConf['SYSCONFIG']['RedirectOauthURL'],
        "domain"                  => $domainOauth
      ]);
      try {
        $accessToken = $provider->getAccessToken('authorization_code', [
          'code' => $_GET['code']
        ]);
        $resourceOwner = $provider->getResourceOwner($accessToken);
        if(!empty($resourceOwner->getEmail())){
          $vars['getEmail'] = $resourceOwner->getEmail();
          $vars['getOAuthClient'] = true;
        }
      } 
      catch (Exception $e) {
          exit($e->getMessage());
      }
    }

    if(!empty($SysConf['SYSCONFIG']['GitlabAppIdOauth'])){
      $vars['providerExist'] = "Gitlab";
    }else{
      $vars['providerExist'] = 0;
    }
    return $this->render("home.html.twig", $this->mergeWithDefault($vars));
  }
}

register_plugin(new HomePage());
