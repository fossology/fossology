<?php
/***********************************************************
 Copyright (C) 2013 Hewlett-Packard Development Company, L.P.

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

namespace Fossology\DemoHello;

use Fossology\Lib\Plugin\DefaultPlugin;
use Symfony\Component\HttpFoundation\Request;
use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Dao\UserDao;

class DemoHello extends DefaultPlugin
{
  const NAME = "demo_hello";

  public function __construct()
  {
    parent::__construct(self::NAME, array(
        self::TITLE => _("Demo Hello World"),
        self::MENU_LIST => "Help::Demo::Hello World",
        self::PERMISSION => Auth::PERM_READ
    ));
  }

  /**
   * \brief Find out who I am from my user record.
   * \returns user name
   */
  protected function WhoAmI()
  {
    $user_pk = Auth::getUserId();

    if (empty($user_pk))
    {
      return _("You are not logged in");
    }

    $userDao = $this->getObject('dao.user');
    return $userDao->getUserName($user_pk);
  }

  /**
   * \brief Generate response.
   */
  protected function handle(Request $request)
  {
    $UserName = $this->WhoAmI();
    $Hello = _("Hello");
    $OutBuf = "<h2>$Hello $UserName </h2>";
    $OutBuf .= _("Wasn't that easy?");

    return $this->render('include/base.html.twig', $this->mergeWithDefault(array('message' => $OutBuf)));
  }

}

register_plugin(new DemoHello());
