<?php
/*
 SPDX-FileCopyrightText: © 2013 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * \file
 * \brief UI component for demomod (Visible in menu)
 */
/**
 * @namespace Fossology::DemoHello
 */
namespace Fossology\DemoHello;

use Fossology\Lib\Plugin\DefaultPlugin;
use Symfony\Component\HttpFoundation\Request;
use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Dao\UserDao;

/**
 * @class DemoHello
 * @brief UI component for demomod (Visible in menu)
 */
class DemoHello extends DefaultPlugin
{
  const NAME = "demo_hello";    ///< mod name

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
