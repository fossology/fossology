<?php
/*
 SPDX-FileCopyrightText: © 2020 Siemens AG
 Author: Shaheem Azmal M MD

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\UI\Page;

use Fossology\Lib\Plugin\DefaultPlugin;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * \brief Third Party Licenses page on UI
 */
class ThirdPartyLicensesPage extends DefaultPlugin
{
  const NAME = "thirdPartyLicenses-FOSSology";

  const FILENAME = "NOTICES-THIRDPARTY.html";

  public function __construct()
  {
    parent::__construct(self::NAME, array(
        self::TITLE => "Third Party Licenses",
        self::MENU_LIST => "Help::Third Party Licenses",
        self::REQUIRES_LOGIN => false,
    ));
  }

  /**
   * @param Request $request
   * @return Response
   */
  protected function handle(Request $request)
  {
    $htmlFileContents = "";
    if (! file_exists(self::FILENAME)) {
      $htmlFileContents = "Cannot find <b><u>". self::FILENAME ."</u></b> file";
    } else {
      $htmlFileContents = file_get_contents(self::FILENAME, true);
      $htmlFileContents = "<div style='all:unset;'>".$htmlFileContents."</div>";
    }
    $vars = array(
      'htmlFileContents' => $htmlFileContents
    );
    return $this->render('thirdPartyLicenses.html.twig', $this->mergeWithDefault($vars));
  }
}

register_plugin(new ThirdPartyLicensesPage());
