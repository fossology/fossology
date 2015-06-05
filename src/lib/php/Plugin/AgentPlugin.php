<?php
/***********************************************************
 * Copyright (C) 2015, Siemens AG
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

namespace Fossology\Lib\Plugin;

use Fossology\Lib\Util\Object;

abstract class AgentPlugin extends Object implements Plugin
{
  const PRE_JOB_QUEUE = 'preJq';
  
  public $AgentName;
  public $Name = "agent_abstract";
  public $Dependency = array();
  public $Title = 'how to show checkbox';
  public $PluginLevel = 10;
  public $State = PLUGIN_STATE_READY;
  public $DBaccess = PLUGIN_DB_WRITE;

  function __construct(){}
  function execute(){}
  function postInstall(){}

  function preInstall()
  {
    menu_insert("Agents::" . $this->Title, 0, $this->Name);
  }

  function unInstall(){}

  /**
   * @return string
   */
  function getName()
  {
    return $this->Name;
  }

  /**
   * @param int $uploadId
   * @return int
   * * 0 = no or this agent can be re run multiple times
   * * 1 = yes, from latest agent version
   * * 2 = yes, from older agent version
   **/
  public function AgentHasResults($uploadId=0)
  {
    return 0;
  }

  /**
   * @param int $jobId
   * @param int $uploadId
   * @param &string $errorMsg - error message on failure
   * @param array $dependencies - array of plugin names representing dependencies.
   * @param mixed $arguments (ignored if not a string)
   * @returns int
   * * jqId  Successfully queued
   * *   0   Not queued, latest version of agent has previously run successfully
   * *  -1   Not queued, error, error string in $ErrorMsg
   **/
  public function AgentAdd($jobId, $uploadId, &$errorMsg, $dependencies=array(), $arguments=null)
  {
    $dependencies[] = "agent_adj2nest";
    if ($this->AgentHasResults($uploadId) == 1)
    {
      return 0;
    }

    $jobQueueId = \IsAlreadyScheduled($jobId, $this->AgentName, $uploadId);
    if ($jobQueueId != 0)
    {
      return $jobQueueId;
    }

    $args = is_array($arguments) ? '' : $arguments;
    return $this->doAgentAdd($jobId, $uploadId, $errorMsg, $dependencies, $uploadId, $args);
  }

  /**
   * @param int $jobId
   * @param int $uploadId
   * @param &string $errorMsg - error message on failure
   * @param array $dependencies
   * @param string|null $jqargs (optional) jobqueue.jq_args
   * @return
   * * jqId  Successfully queued
   * *   0   Not queued, latest version of agent has previously run successfully
   * *  -1   Not queued, error, error string in $ErrorMsg
   **/
  protected function doAgentAdd($jobId, $uploadId, &$errorMsg, $dependencies, $jqargs = "", $jq_cmd_args = null)
  {
    $deps = array();
    foreach ($dependencies as $dependency)
    {
      $dep = $this->implicitAgentAdd($jobId, $uploadId, $errorMsg, $dependency);
      if ($dep == -1)
      {
        return -1;
      }
      $deps[] = $dep;
    }

    if (empty($jqargs))
    {
      $jqargs = $uploadId;
    }
    $jobQueueId = \JobQueueAdd($jobId, $this->AgentName, $jqargs, "", $deps, NULL, $jq_cmd_args);
    if (empty($jobQueueId))
    {
      $errorMsg = "Failed to insert agent $this->AgentName into job queue. jqargs: $jqargs";
      return -1;
    }
    $success = \fo_communicate_with_scheduler("database", $output, $errorMsg);
    if (!$success)
    {
      $errorMsg .= "\n" . $output;
    }

    return $jobQueueId;
  }

  /**
   * @param int $jobId
   * @param int $uploadId
   * @param &string $errorMsg
   * @param mixed $dependency
   * @return int
   */
  protected function implicitAgentAdd($jobId, $uploadId, &$errorMsg, $dependency)
  {
    if (is_array($dependency))
    {
      $pluginName = $dependency['name'];
      $depArgs = $dependency['args'];
      $preJq = array_key_exists(self::PRE_JOB_QUEUE, $dependency) ? $dependency[self::PRE_JOB_QUEUE] : 0;
    }
    else
    {
      $pluginName = $dependency;
      $depArgs = null;
      $preJq = 0;
    }
    $depPlugin = plugin_find($pluginName);
    if (!$depPlugin)
    {
      $errorMsg = "Invalid plugin name: $pluginName, (implicitAgentAdd())";
      return -1;
    }
    $jqId = $depPlugin->AgentAdd($jobId, $uploadId, $errorMsg, array(), $depArgs);
    if($preJq>0 && $jqId>0)
    {
      $dbManager = $GLOBALS['container']->get('db.manager');
      $dbManager->insertTableRow('jobdepends',array('jdep_jq_fk'=>$jqId,'jdep_jq_depends_fk'=>$preJq));
    }
    return $jqId;
  }

  function __toString()
  {
    return getStringRepresentation(get_object_vars($this), get_class($this));
  }
}
