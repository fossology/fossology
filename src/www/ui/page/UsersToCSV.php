<?php
/***********************************************************
 * Copyright (C) 2016 Siemens AG
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * version 2 as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 ***********************************************************/

namespace Fossology\UI\Page;

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Plugin\DefaultPlugin;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class UsersToCSV extends DefaultPlugin
{
  const NAME = "user_to_csv";

  function __construct()
  {
    parent::__construct(self::NAME, array(
        self::TITLE => "User CSV Export",
        self::MENU_LIST => "Admin::Users::CSV Export",
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
    $usersCsvExport = new \Fossology\Lib\Application\UsersCsvExport($this->getObject('db.manager'));
    $content = $usersCsvExport->createCsv(intval($request->get('rf')));
 
    $headers = array(
        'Content-type' => 'text/csv, charset=UTF-8',
        'Content-Disposition' => 'attachment, filename=file.csv',
        'Pragma' => 'no-cache',
        'Cache-Control' => 'no-cache, must-revalidate, maxage=1, post-check=0, pre-check=0',
        'Expires' => 'Expires: Thu, 19 Nov 1981 08:52:00 GMT');

    return new Response($content, Response::HTTP_OK, $headers);
  }

}

register_plugin(new UsersToCSV());
