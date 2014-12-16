<?php
/***********************************************************
 * Copyright (C) 2014 Siemens AG
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

use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Plugin\DefaultPlugin;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminLicenseToCSV extends DefaultPlugin
{
  const NAME = "admin_license_to_csv";

  function __construct()
  {
    parent::__construct(self::NAME, array(
        self::TITLE => "Admin License CSV Export",
        self::MENU_LIST => "Admin::License Admin::CSV Export",
        self::REQUIRES_LOGIN => true,
        self::DEPENDENCIES => array(\ui_menu::NAME),
        self::PERMISSION => self::PERM_ADMIN
    ));
  }

  /**
   * @
   * @param Request $request
   * @throws \Exception
   * @return Response
   */
  protected function handle(Request $request)
  {
    $rf = intval($request->get('rf'));
    /** @var DbManager $dbManager */
    $dbManager = $this->getObject('db.manager');
    $sql = "SELECT rf.rf_shortname,rf.rf_fullname,rf.rf_text,p.rf_shortname parent_shortname,rf.rf_url,rf.rf_notes,rf.rf_source
            FROM license_ref rf LEFT JOIN license_map ON rf_pk=rf_fk LEFT JOIN license_ref p on rf_parent=p.rf_pk
            WHERE rf.rf_detector_type=$1";
    $param = array($userDetected=1);
    if ($rf>0)
    {
      $param[] = $rf;
      $sql .= ' AND rf.rf_pk=$'.count($param);
      $row = $dbManager->getSingleRow($sql,$param);
      $vars = $row ? array( $row ) : array();
    }
    else
    {
      $stmt = __METHOD__;
      $dbManager->prepare($stmt,$sql);
      $res = $dbManager->execute($stmt,$param);
      $vars = $dbManager->fetchAll( $res );
      $dbManager->freeResult($res);
    }
    
    $out = fopen('php://output', 'w');
    ob_start();
    fputcsv($out, array('shortname','fullname','text','parent_shortname','url','notes','source'));
    foreach($vars as $row)
    {
      fputcsv($out, $row);
    }
    $content = ob_get_contents();
    ob_end_clean();
    
    // Output CSV-specific headers
    $headers = array(
        'Content-type' => 'text/csv',
        'Pragma' => 'no-cache',
        'Cache-Control' => 'no-cache, must-revalidate, maxage=1, post-check=0, pre-check=0',
        'Expires' => 'Expires: Thu, 19 Nov 1981 08:52:00 GMT');

    return new Response(
        $content,
        Response::HTTP_OK,
        $headers
    );
  }

}

register_plugin(new AdminLicenseToCSV());
