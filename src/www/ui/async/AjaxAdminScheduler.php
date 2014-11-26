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
    $job_list_option = $this->jobListOption($operation);
    if ('pause' == $operation || 'restart' == $operation)
    {
      $text = _("Select a job");
      $V.= "$text: <select name='job_list' id='job_list'>$job_list_option</select>";
    }
    else if ('verbose' == $operation)
    {
      $verbose_list_option = $this->verboseListOption();
      $text1 = _("Select the scheduler or a job");
      $text2 = _("Select a verbosity level");
      $V.= "$text1: <select name='job_list' id='job_list'>$job_list_option</select><br>$text2: <select name='level_list' id='level_list'>$verbose_list_option</select>";
    }
    else if ('status' == $operation)
    {
      $text = _("Select the scheduler or a job");
      $V.= "$text: <select name='job_list' id='job_list'>$job_list_option</select><br></select>";
    }
    else if ('priority' == $operation)
    {
      $priority_list_option = $this->priorityListOption();
      $text1 = _("Select a job");
      $text2 = _("Select a priority level");
      $V.= "$text1: <select name='job_list' id='job_list'>$job_list_option </select> <br>$text2: <select name='priority_list' id='priority_list'>$priority_list_option</select>";
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
   * @return string job list of option elements
   **/
  function jobListOption($type)
  {
    if (empty($type))
    {
      return '';
    }
    
    $job_list_option = "";
    $job_array = array();
    if ('status' == $type || 'verbose' == $type || 'priority' == $type)
    {
      /* you can select scheduler besides jobs for 'status' and 'verbose',
       for 'priority', only jobs to select */
      if ('priority' != $type)
      {
        $job_list_option .= "<option value='0'>scheduler</option>";
      }
      $job_array = GetRunnableJobList();
    }
    /* get job list from the table jobqueque */
    if ('pause' == $type)  $job_array = GetJobList("tart");
    if ('restart' == $type)  $job_array = GetJobList("Paused");

    for($i = 0; $i < count($job_array); $i++)
    {
      $job_id = $job_array[$i];
      $job_list_option .= "<option value='$job_id'>$job_id</option>";
    }
    return $job_list_option;
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
   * @brief get the priority list for setting, -20-20
   * @return string of priority options
   **/
  function priorityListOption()
  {
    $priority_list_option = "";
    $min = -20;
    $max = 20;
    for ($i = $min; $i <= $max; $i++)
    {
      $selected = (0 == $i) ? ' selected="selected"' : '';
      $priority_list_option .= "<option $selected value=\"$i\">$i</option>";
    }
    return $priority_list_option;
  }

}

register_plugin(new AjaxAdminScheduler());
