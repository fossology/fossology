<?php
/*
Copyright (C) 2014, Siemens AG

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

/**
 * @brief about page on UI
 */
class HomePage extends DefaultPlugin
{
  const NAME = "home";

  function __construct()
  {
    parent::__construct(self::NAME, array(
        self::TITLE => "Welcome to FOSSology",
        self::MENU_LIST => "Home",
        self::REQUIRES_LOGIN => false,
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
    if ($_SESSION['User']=="Default User" && plugin_find_id("auth")>=0)
    {
      $vars['protocol'] = preg_replace("@/.*@", "", @$_SERVER['SERVER_PROTOCOL']);
      $vars['referrer'] = "?mod=browse";
      $vars['authUrl'] = "?mod=auth";
    }
    return $this->render("home.html.twig", $this->mergeWithDefault($vars));
  }
}

register_plugin(new HomePage());
