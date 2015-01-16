<?php
/***********************************************************
 Copyright (C) 2008-2013 Hewlett-Packard Development Company, L.P.
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
 ***********************************************************/


namespace Fossology\UI\Page;

use Fossology\Lib\Plugin\DefaultPlugin;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;


class GettingStartedPage extends DefaultPlugin
{
  const NAME = 'Getting Started';
  
  function __construct()
  {
    parent::__construct(self::NAME, array(
        self::TITLE =>  _("Getting Started with FOSSology"),
        self::REQUIRES_LOGIN => false,
        self::MENU_LIST => "Help::Getting Started",
    ));
  }

  /**
   * \brief Customize submenus.
   */
  function RegisterMenus()
  {
    $topMenuList = "Main::" . "Help::Getting Started";
    $menuOrder = 0;
    menu_insert($topMenuList.'::Overview', $menuOrder-10, $this->getName()."&show=welcome");
    menu_insert($topMenuList.'::License Browser', $menuOrder, $this->getName()."&show=licensebrowser");
  }
  
  /**
   * @param Request $request
   * @return Response
   */
  protected function handle(Request $request)
  {
    $show = $request->get('show');
    if ($show=='licensebrowser'){
      return $this->render("getting_started_licensebrowser.html.twig");
    }
    $login = _("Login");
    if (empty($_SESSION['User']) && (plugin_find_id("auth") >= 0))
    {
      $login = "<a href='".Traceback_uri()."?mod=auth'>$login</a>";
    }
    $vars = array('login'=>$login, 'SiteURI'=> Traceback_uri());

    return $this->render('getting_started.html.twig', $this->mergeWithDefault($vars));
  }
}

register_plugin(new GettingStartedPage());