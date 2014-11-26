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

namespace Fossology\UI\Ajax;

use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Plugin\DefaultPlugin;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class AjaxJobInfo extends DefaultPlugin
{
  const NAME = "jobinfo";
  /** @var DbManager */
  private $dbManager;

  function __construct()
  {
    parent::__construct(self::NAME, array(
        self::PERMISSION => self::PERM_READ
        // , 'outputtype' => 'JSON'
    ));

    $this->dbManager = $this->getObject('db.manager');
  }

  /**
   * @param Request $request
   * @return Response
   */
  protected function handle(Request $request)
  {
    $userId = $_SESSION['UserId'];
    $jqIds = (array)$request->get('jqIds');

    $result = array();
    foreach($jqIds as $jq_pk) {
      $jobInfo = $this->dbManager->getSingleRow(
        "SELECT jobqueue.jq_end_bits as end_bits FROM jobqueue INNER JOIN job ON jobqueue.jq_job_fk = job.job_pk
          WHERE jobqueue.jq_pk = $1 AND job_user_fk = $2",
          array($jq_pk, $userId)
       );
       if ($jobInfo !== false) {
         $result[$jq_pk] = array('end_bits' => $jobInfo['end_bits']);
       }
    }

    ReportCachePurgeAll();
    $status = empty($result) ? Response::HTTP_INTERNAL_SERVER_ERROR : Response::HTTP_OK;
    if (empty($result)) {
      $result = array("error" => "no info");
    }
    $response = new Response(json_encode($result),$status,array('content-type'=>'text/json'));
    return $response;
  }
  
}

register_plugin(new AjaxJobInfo());

