<?php
/*
 SPDX-FileCopyrightText: © 2014 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\UI\Ajax;

use Fossology\Lib\Auth\Auth;
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
        self::PERMISSION => Auth::PERM_READ
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
    $jqIds = (array) $request->get('jqIds');

    $result = array();
    foreach ($jqIds as $jq_pk) {
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
