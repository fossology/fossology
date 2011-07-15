<?php
/*
 Copyright (C) 2011 Hewlett-Packard Development Company, L.P.

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

global $GlobalReady;
if (!isset($GlobalReady)) {
  exit;
}

/**
 * ajax-urlUpload
 * \brief display to upload from url form
 *
 * @version "$Id: ajax-urlUpload.php 3942 2011-03-17 23:24:33Z rrando $"
 * Created on Feb 14, 2011 by Mark Donohoe
 */

define("TITLE_ajax_admin_scheduler", _("URL"));

class ajax_admin_scheduler extends FO_Plugin
{
  public $Name = "ajax_admin_scheduler";
  public $Title = TITLE_ajax_admin_scheduler;
  public $Version = "1.0";
  public $Dependency = array();
  public $DBaccess = PLUGIN_DB_UPLOAD;
  public $NoHTML     = 1; /* This plugin needs no HTML content help */
  public $LoginFlag = 0;

  function JobListOption($type)
  {
    if (empty($type))
    {
      return '';
    }
    else if ('status' == $type)
    {
      $job_list_option .= "<option value='0'>ALL</option>";
    }
    else if ('verbose' == $type)
    {
      $job_list_option .= "<option value='0'>scheduler</option>";
    }
    $job_list_option .= "<option value='1'>1</option>";
    $job_list_option .= "<option value='2'>2</option>";
    $job_list_option .= "<option value='3'>3</option>";
    return $job_list_option;
  }

  function VerboseListOption()
  {
    $min = 1;
    $max = 4;
    for ($i = $min; $i <= $max; $i++)
    {
      if ($i == $max)
      {
        $bitmask= pow(2, $i - 1) - 1;
        $verbose_list_option .= "<option value='$bitmask'>$i</option>";
      }
      else
      {
        $bitmask = pow(2, $i -1);
        $verbose_list_option .= "<option value='$bitmask'>$i</option>";
      }
    }
    return $verbose_list_option;
  }

  function PriorityListOption()
  {
    $min = -20;
    $max = 20;
    for ($i = $min; $i <= $max; $i++) 
    {
      if (0 == $i)
      {
        $priority_list_option .= "<option SELECTED value='$i'>$i</option>";
      } 
      else
      {
        $priority_list_option .= "<option value='$i'>$i</option>";
      }
    }
    return $priority_list_option ;
  }


  /*
   Output(): Generate the text for this plugin.
   */
  function Output()
  {
    if ($this->State != PLUGIN_STATE_READY) {
      return;
    }
    $V = "";
    switch ($this->OutputType)
    {
      case "XML":
        break;
      case "HTML":
       $operation = GetParm('operation', PARM_TEXT);
       $job_list_option = $this->JobListOption($operation);
       if ('pause' == $operation || 'restart' == $operation || 'status'  == $operation)
       {
         $V.= "Please select one job:<select name='job_list' id='job_list'>$job_list_option</select>";
       }
       else if ('verbose'  == $operation) 
       {
         $verbose_list_option = $this->VerboseListOption();
         $V.= "Please select one job:<select name='job_list' id='job_list'>$job_list_option</select><br>Please one verbosity level:<select name='level_list' id='level_list'>$verbose_list_option</select>";
       }
       else if ('priority'  == $operation)
       {
         $priority_list_option = $this->PriorityListOption();
         $V.= "Please select one job:<select name='job_list' id='job_list'>$job_list_option </select> <br>Set the priority for the specified job:<select name='priority_list' id='priority_list'>$priority_list_option</select>";
       }
        break;
      case "Text":
        break;
      default:
        break;
    }
    if (!$this->OutputToStdout) {
      return ($V);
    }
    print ("$V");
    return;
  } // Output()
};
$NewPlugin = new ajax_admin_scheduler();
?>
