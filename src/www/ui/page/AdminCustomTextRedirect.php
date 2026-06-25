<?php
/*
 SPDX-FileCopyrightText: © 2025 Harshit Gandhi <gandhiharshit716@gmail.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\UI\Page;

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Plugin\DefaultPlugin;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Redirect plugin for Text Management parent menu.
 * Redirects to the List page when user clicks on "Text Management".
 */
class AdminCustomTextRedirect extends DefaultPlugin
{
  const NAME = "admin_custom_text_redirect";

  function __construct()
  {
    parent::__construct(self::NAME, array(
        self::TITLE => "Text Management",
        self::MENU_LIST => "Admin::Text Management",
        self::REQUIRES_LOGIN => true,
        self::PERMISSION => Auth::PERM_ADMIN
    ));
  }

  /**
   * @param Request $request
   * @return Response
   */
  protected function handle(Request $request)
  {
    // Redirect to the list page
    $redirectUrl = Traceback_uri() . '?mod=admin_custom_text_list';
    return new RedirectResponse($redirectUrl);
  }
}

register_plugin(new AdminCustomTextRedirect());
