<?php
/***********************************************************
 Copyright (C) 2011-2013 Hewlett-Packard Development Company, L.P.
 Copyright (C) 2014 Siemens AG

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

namespace Fossology\UI\Ajax;

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
        self::PERMISSION => self::PERM_WRITE
    ));

    $this->dbManager = $this->getObject('db.manager');
  }

  /**
   * @param Request $request
   * @return Response
   */
  protected function handle(Request $request)
  {
    $V = '';
    $operation = $request->get('operation');
    $vars['jobOptions'] = $this->jobListOption($operation);
    $vars['operation'] = $operation;
    $vars['priorityList'] = $this->priorityListOption();
    $content = $this->renderer->loadTemplate('ajax-admin-scheduler.html.twig')->render($vars);
    
    if ('pause' == $operation || 'restart' == $operation || 'status' == $operation || 'priority' == $operation)
    {
      $V = $content;
    }
    else if ('verbose' == $operation)
    {
      $verbose_list_option = $this->verboseListOption();
      $text2 = _("Select a verbosity level");
      $V = $content."<br>$text2: <select name='level_list' id='level_list'>$verbose_list_option</select>";
    }

    else if('agents' == $operation)
    {
      /** @var DbManager */
      $dbManager = $this->getObject('db.manager');
      $dbManager->prepare($stmt=__METHOD__.'.getAgents','SELECT MAX(agent_pk) agent_id, agent_name FROM agent WHERE agent_enabled GROUP BY agent_name');
      $res = $dbManager->execute($stmt);
      $V = '<ul>';
      while($row = $dbManager->fetchArray($res))
      {
        $V .= "<li>$row[agent_name]</li>";
      }
      $V .= '</ul>';
      $dbManager->freeResult($res);
    }
    return new Response($V, Response::HTTP_OK, array('content-type'=>'text/htm')); // not 'text/html' while console-logging
  }

  /**
   * @brief get the job list for the specified operation
   * @param string $type operation type
   * @return array job list of option elements
   **/
  function jobListOption($type)
  {
    if (empty($type))
    {
      return array();
    }
    
    $job_array = array();
    if ('status' == $type || 'verbose' == $type || 'priority' == $type)
    {
      $job_array = GetRunnableJobList();
      if ('priority' != $type)
      {
        $job_array[0] = "scheduler";
      }
    }
    if ('pause' == $type)
      $job_array = GetJobList("tart");
    if ('restart' == $type)
      $job_array = GetJobList("Paused");
    $job_options = array();
    foreach($job_array as $job_id)
    {
      $job_option[$job_id] = $job_id;
    }
    return $job_array;
  }

  /**
   * @brief get the verbose list:  if the value of verbose is 1, set verbose as 1
   * @return string
   **/
  function verboseListOption()
  {
    $verbose_list_option = "";
    $min = 1;
    $max = 3;
    for ($i = $min; $i <= $max; $i++)
    {
      $bitmask= (1<<$i) - 1;
      $verbose_list_option .= "<option value='$bitmask'>$i</option>";
    }
    return $verbose_list_option;
  }

  /**
   * @brief get the priority list for setting, -20..20
   * @return string of priority options
   **/
  function priorityListOption()
  {
    $min = -20;
    $max = 20;
    $priority_list = array();
    for ($i = $min; $i <= $max; $i++)
    {
      $priority_list[$i]=$i;
    }
    return $priority_list;
  }

}

register_plugin(new AjaxAdminScheduler());
