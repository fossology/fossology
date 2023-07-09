<?php
/*
 SPDX-FileCopyrightText: © 2011-2013 Hewlett-Packard Development Company, L.P.
 SPDX-FileCopyrightText: © 2014 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\UI\Ajax;

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Plugin\DefaultPlugin;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class AjaxAdminScheduler extends DefaultPlugin
{
  const NAME = "ajax_admin_scheduler";
  /** @var DbManager */
  private $dbManager;

  function __construct()
  {
    parent::__construct(self::NAME, array(
        self::TITLE => _("URL"),
        self::PERMISSION => Auth::PERM_WRITE
    ));

    $this->dbManager = $this->getObject('db.manager');
  }

  /**
   * @param Request $request
   * @return Response | array
   */
  public function handle(Request $request)
  {
    $V = '';
    $operation = $request->get('operation');
    $vars['jobOptions'] = $this->jobListOption($operation);
    $vars['operation'] = $operation;
    $vars['priorityList'] = $this->priorityListOption($request->get('fromRest'));
    $content = $this->renderer->load('ajax-admin-scheduler.html.twig')->render($vars);
    $restRes = [
      'jobList' => $vars['jobOptions'] ?? [],
      'priorityList' => $vars['priorityList'] ?? [],
      'verboseList' => [],
      'agentList' => [],
    ];

    if ('pause' == $operation || 'restart' == $operation ||
      'status' == $operation || 'priority' == $operation) {
      $V = $content;
    } else if ('verbose' == $operation) {
      $verbose_list_option = $this->verboseListOption($request->get('fromRest'));
      $text2 = _("Select a verbosity level");
      $V = $content .
        "<br>$text2: <select name='level_list' id='level_list'>$verbose_list_option</select>";
      $restRes['verboseList'] = $verbose_list_option;
    } else if ('agents' == $operation) {
      /** @var DbManager */
      $dbManager = $this->getObject('db.manager');
      $dbManager->prepare($stmt = __METHOD__ . '.getAgents',
        'SELECT MAX(agent_pk) agent_id, agent_name FROM agent WHERE agent_enabled GROUP BY agent_name');
      $res = $dbManager->execute($stmt);
      $V = '<ul>';
      while ($row = $dbManager->fetchArray($res)) {
        $restRes['agentList'][] = $row['agent_name'];
        $V .= "<li>$row[agent_name]</li>";
      }
      $V .= '</ul>';
      $dbManager->freeResult($res);
    }

    if ($request->get('fromRest')) {
      return $restRes;
    }
    return new Response($V, Response::HTTP_OK, array('content-type'=>'text/htm')); // not 'text/html' while console-logging
  }

  /**
   * @brief get the job list for the specified operation
   * @param string $type operation type
   * @return array job list of option elements
   */
  function jobListOption($type)
  {
    if (empty($type)) {
      return array();
    }

    $job_array = array();
    if ('status' == $type || 'verbose' == $type || 'priority' == $type) {
      $job_array = GetRunnableJobList();
      if ('priority' != $type) {
        $job_array[0] = "scheduler";
      }
    }
    if ('pause' == $type) {
      $job_array = GetJobList("Started");
    }
    if ('restart' == $type) {
      $job_array = GetJobList("Paused");
    }
    return $job_array;
  }

  /**
   * @brief get the verbose list:  if the value of verbose is 1, set verbose as 1
   * @return string | array
   **/
  function verboseListOption($fromRest = false)
  {
    $verbose_list_option = "";
    $min = 1;
    $max = 3;
    $restRes = [];
    for ($i = $min; $i <= $max; $i++) {
      $bitmask= (1<<$i) - 1;
      $verbose_list_option .= "<option value='$bitmask'>$i</option>";
      $restRes[] = $bitmask;
    }
    if ($fromRest) {
      return $restRes;
    }
    return $verbose_list_option;
  }

  /**
   * @brief get the priority list for setting, -20..20
   * @return string | array of priority options
   **/
  function priorityListOption($fromRest = false)
  {
    $min = -20;
    $max = 20;
    $resRes = [];
    $priority_list = array();
    for ($i = $min; $i <= $max; $i++) {
      $priority_list[$i]=$i;
      $resRes[] = $i;
    }
    if ($fromRest) {
      return $resRes;
    }
    return $priority_list;
  }
}

register_plugin(new AjaxAdminScheduler());
