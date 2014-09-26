<?php
/***********************************************************
 Copyright (C) 2011-2013 Hewlett-Packard Development Company, L.P.

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

/**
 * \file ajax_admin_scheduler
 * \brief ajax operations for admin-scheduler.php
 **/

define("TITLE_ajax_admin_scheduler", _("URL"));

/**
 * \class ajax_admin_scheduler
 **/
class ajax_admin_scheduler extends FO_Plugin
{
  function __construct()
  {
    $this->Name = "ajax_admin_scheduler";
    $this->Title = TITLE_ajax_admin_scheduler;
    $this->DBaccess = PLUGIN_DB_WRITE;
    $this->LoginFlag = 0;
    parent::__construct();
  }


  /**
   * \brief get the job list for the specified operation
   * \param $type operation type, the job list is different 
   *        according to the type of the operation
   * \return job list as array of <option> elements
   **/
  function JobListOption($type)
  {
    $job_list_option = "";
    $job_array = array();

    if (empty($type))
    {
      return '';
    }
    else if ('status' == $type || 'verbose' == $type || 'priority' == $type)
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

    for($i = 0; $i < sizeof($job_array); $i++)
    {
      $job_id = $job_array[$i];
      $job_list_option .= "<option value='$job_id'>$job_id</option>";
    }
    return $job_list_option;
  }

  /**
   * \brief get the verbose list
   *        if the value of verbose is 1, set verbose as 1
   * \return array of verbosity values as <option> elements
   **/
  function VerboseListOption()
  {
    $verbose_list_option = "";
    $min = 1;
    $max = 3;
    for ($i = $min; $i <= $max; $i++)
    {
      $bitmask= pow(2, $i) - 1;
      $verbose_list_option .= "<option value='$bitmask'>$i</option>";
    }
    return $verbose_list_option;
  }

  /**
   * \brief get the priority list for setting, -20-20
   * \return array of priority levels from -20 ($min) to +20 ($max) as 
   *         <option> elements
   **/
  function PriorityListOption()
  {
    $priority_list_option = "";
    $min = -20;
    $max = 20;
    for ($i = $min; $i <= $max; $i++)
    {
      if (0 == $i)
        $priority_list_option .= "<option SELECTED value='$i'>$i</option>";
      else
        $priority_list_option .= "<option value='$i'>$i</option>";
    }
    return $priority_list_option ;
  }


  /**
   * \brief Generate the output for this plugin
   */
  function Output()
  {
    if ($this->State != PLUGIN_STATE_READY)
    {
      return 0;
    }
    $output = '';
    if ($this->OutputType=='HTML')
    {
      $output = $this->htmlContent();
    }
    if (!$this->OutputToStdout)
    {
      $this->vars['content'] = $output;
      return $output." ";
    }
    print $output;
  }
  
  
  protected function htmlContent()
  {
    $V = '';
    $operation = GetParm('operation', PARM_TEXT);
    $job_list_option = $this->JobListOption($operation);
    if ('pause' == $operation || 'restart' == $operation)
    {
      $text = _("Select a job");
      $V.= "$text: <select name='job_list' id='job_list'>$job_list_option</select>";
    }
    else if ('verbose'  == $operation)
    {
      $verbose_list_option = $this->VerboseListOption();
      $text1 = _("Select the scheduler or a job");
      $text2 = _("Select a verbosity level");
      $V.= "$text1: <select name='job_list' id='job_list'>$job_list_option</select><br>$text2: <select name='level_list' id='level_list'>$verbose_list_option</select>";
    }
    else if ('status'  == $operation)
    {
      $text = _("Select the scheduler or a job");
      $V.= "$text: <select name='job_list' id='job_list'>$job_list_option</select><br></select>";
    }
    else if ('priority'  == $operation)
    {
      $priority_list_option = $this->PriorityListOption();
      $text1 = _("Select a job");
      $text2 = _("Select a priority level");
      $V.= "$text1: <select name='job_list' id='job_list'>$job_list_option </select> <br>$text2: <select name='priority_list' id='priority_list'>$priority_list_option</select>";
    }
    return $V;
  }
}
$NewPlugin = new ajax_admin_scheduler();
