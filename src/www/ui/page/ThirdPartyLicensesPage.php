<?php
/*
Copyright (C) 2020, Siemens AG
Author: Shaheem Azmal M MD

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
