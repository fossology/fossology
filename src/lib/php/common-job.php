<?php

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Dao\FolderDao;
use Fossology\Lib\Db\DbManager;
/***********************************************************
 Copyright (C) 2008-2015 Hewlett-Packard Development Company, L.P.
 Copyright (C) 2015 Siemens AG

 This library is free software; you can redistribute it and/or
 modify it under the terms of the GNU Lesser General Public
 License version 2.1 as published by the Free Software Foundation.

 This library is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 Lesser General Public License for more details.

 You should have received a copy of the GNU Lesser General Public License
 along with this library; if not, write to the Free Software Foundation, Inc.0
 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 ***********************************************************/
/**
 * \file common-job.php
 * \brief library of functions used by the ui to manage jobs.
 *        Jobs information is stored in the jobs, jobdepends,
 *        and jobqueue tables.
 * 
 * Terminology:
 * Scheduled jobs are divided into a specific heirarchy.
 * 
 * "Job"
 * This is the Job container and is saved in a database
 * job record.  
 *
 * "JobQueue"
 * There may be several tasks to perform for a job.  
 * For example, a job may be composed of
 * an unpack task, an adj2nest task, and a nomos task.
 * Each job task is specified in a database jobqueue record.
 * 
 * JobQueue tasks may have dependencies upon the completion of
 * other JobQueue tasks.  The jobdepends tables keep those
 * parent child relationships.
 * 
 **/


/**
 * \brief Insert a new upload record, and update the foldercontents table.
 *
 * \param $userId
 * \param $groupId
 * \param $job_name   Job name
 * \param $filename   For upload from URL, this is the URL.\n
 *                    For upload from file, this is the filename.\n
 *                    For upload from server, this is the file path.\n
 * \param $desc       Optional user file description.
 * \param $UploadMode 1<<2=URL, 1<<3=upload from server or file
 * \param $folder_pk   The folder to contain this upload
 * \param $public_perm The public permission on this upload
 *
 * \return upload_pk or null (failure)
 *         On failure, error is written to stdout
 */
function JobAddUpload($userId, $groupId, $job_name, $filename, $desc, $UploadMode, $folder_pk, $public_perm=Auth::PERM_NONE)
{
  global $container;

  $dbManager = $container->get('db.manager');
  /* check all required inputs */
  if (empty($userId) or empty($job_name) or empty($filename) or
      empty($UploadMode) or empty($folder_pk)) return;

  $row = $dbManager->getSingleRow("INSERT INTO upload
      (upload_desc,upload_filename,user_fk,upload_mode,upload_origin, public_perm) VALUES ($1,$2,$3,$4,$5,$6) RETURNING upload_pk",
      array($desc,$job_name,$userId,$UploadMode,$filename, $public_perm),__METHOD__.'.insert.upload');
  $uploadId = $row['upload_pk'];

  $dbManager->getSingleRow("INSERT INTO foldercontents (parent_fk,foldercontents_mode,child_id) VALUES ($1,$2,$3)",
               array($folder_pk,FolderDao::MODE_UPLOAD,$uploadId),'insert.foldercontents');

  /****  Add user permission to perm_upload *****/
  if (empty($groupId))
  {
    $usersRow = $dbManager->getSingleRow('SELECT * FROM users WHERE user_pk=$1',array($userId),__METHOD__.'.select.user');
    $groupId = $usersRow['group_fk'];
  }
  $perm_admin = Auth::PERM_ADMIN;

  $dbManager->getSingleRow("INSERT INTO perm_upload (perm, upload_fk, group_fk) VALUES ($1,$2,$3)",
               array($perm_admin, $uploadId, $groupId),'insert.perm_upload');

  return ($uploadId);
}


/**
 * @brief Insert a new job record.
 *
 * @param $userId
 * @param $groupId
 * @param $job_name
 * @param $upload_pk (optional)
 * @param $priority  (optional default 0)
 *
 * @return int $job_pk the job primary key
 */
function JobAddJob($userId, $groupId, $job_name, $upload_pk=0, $priority=0)
{
  global $container;

  /** @var DbManager $dbManager */
  $dbManager = $container->get('db.manager');

  $upload_pk_val = empty($upload_pk) ? null : $upload_pk;

  $params = array($userId, $priority, $job_name, $upload_pk_val);
  $stmtName = __METHOD__;
  if (empty($groupId))
  {
    $stmtName .= "noGrp";
    $groupPkVal = "(SELECT group_fk FROM users WHERE user_pk = $1)";
  }
  else
  {
    $params[] = $groupId;
    $groupPkVal = "$". count($params);
  }

  $row = $dbManager->getSingleRow(
    "INSERT INTO job
    (job_user_fk,job_group_fk,job_queued,job_priority,job_name,job_upload_fk) VALUES
    ($1,$groupPkVal,now(),$2,$3,$4) RETURNING job_pk",
    $params,
    $stmtName
  );

  return ($row['job_pk']);
} // JobAddJob()


/**
 * @brief Insert a jobqueue + jobdepends records.
 *
 * @param int    $job_pk the job primary key (returned by JobAddJob)
 * @param string $jq_type name of agent (should match the name in agent.conf
 * @param string $jq_args arguments to pass to the agent in the form of
 * $jq_args="folder_pk='$Folder' name='$Name' description='$Desc' ...";
 * @param string $jq_runonpfile column name
 * @param array  $Depends array of jq_pk's this jobqueue is dependent on.
 * @param string $jq_cmd_args  command line arguments
 *
 * @return new jobqueue key (jobqueue.jq_pk), or null on failure
 *
 */
function JobQueueAdd($job_pk, $jq_type, $jq_args, $jq_runonpfile, $Depends, $host = NULL, $jq_cmd_args=NULL)
{
  global $PG_CONN;
  $jq_args = pg_escape_string($jq_args);
  $jq_cmd_args = pg_escape_string($jq_cmd_args);

  /* Make sure all dependencies exist */
  if (is_array($Depends)) 
  {
    foreach($Depends as $Dependency) 
    {
      if (empty($Dependency)) continue;

      $sql = "SELECT jq_pk FROM jobqueue WHERE jq_pk = '$Dependency'";
      $result = pg_query($PG_CONN, $sql);
      DBCheckResult($result, $sql, __FILE__, __LINE__);
      $MissingDep =  (pg_num_rows($result) == 0);
      pg_free_result($result);

      if ($MissingDep) return;
    }
  }

  $sqlBegin = "BEGIN";
  $result = pg_query($PG_CONN, $sqlBegin);
  DBCheckResult($result, $sqlBegin, __FILE__, __LINE__);
  pg_free_result($result);

  /* Add the job */
  $sql = "INSERT INTO jobqueue ";
  $sql.= "(jq_job_fk,jq_type,jq_args,jq_runonpfile,jq_starttime,jq_endtime,jq_end_bits,jq_host,jq_cmd_args) VALUES ";
  $sql.= "('$job_pk','$jq_type','$jq_args',";
  $sql .= (empty($jq_runonpfile)) ? "NULL" : "'$jq_runonpfile'";
  $sql.= ",NULL,NULL,0,";
  $sql .= $host ? "'$host'," : "NULL,";
  $sql .= $jq_cmd_args ? "'$jq_cmd_args')" : "NULL)";

  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  pg_free_result($result);
   
  /* Find the jobqueue that was just added */
  $jq_pk = GetLastSeq("jobqueue_jq_pk_seq", "jobqueue");
  if (empty($jq_pk))
  {
    $sql = "ROLLBACK";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    pg_free_result($result);
    return;
  }

  /* Add dependencies */
  if (is_array($Depends)) 
  {
    foreach($Depends as $Dependency) 
    {
      if (empty($Dependency))
      {
        continue;
      }
      $sql = "INSERT INTO jobdepends (jdep_jq_fk,jdep_jq_depends_fk) VALUES ('$jq_pk','$Dependency')";
      $result = pg_query($PG_CONN, $sql);
      DBCheckResult($result, $sql, __FILE__, __LINE__);
      pg_free_result($result);
    }
  }

  /* Commit the jobqueue and jobdepends changes */
  $sql = "COMMIT";
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  pg_free_result($result);

  return $jq_pk;
} // JobQueueAdd()


/**
 * \brief Gets the list of jobqueue records with the requested $status 
 *
 * \param string $status - the status might be:
 *        Started, Completed, Restart, Failed, Paused, etc
 *        the status 'Started' and 'Restart', you can call them as running status
 *        to get all the running job list, you can set the $status as 'tart'
 *
 * \return job list related to the jobstatus,
 *         the result is like: Array(1, 2, 3, .., i), sorted
 **/
function GetJobList($status)
{
  /* Gets the list of jobqueue records with the requested $status */
  global $PG_CONN;
  if (empty($status))
  {
    return;
  }
  $sql = "SELECT jq_pk FROM jobqueue WHERE jq_endtext like '%$status%' order by jq_pk;";
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  $job_array = pg_fetch_all_columns($result, 0);
  pg_free_result($result);
  return $job_array;
}

/**
 * \brief Schedule agent tasks on upload ids
 *
 * \param $upload_pk_list -  upload ids, The string can be a comma-separated list of upload ids.
 * Or, use 'ALL' to specify all upload ids.
 * \param $agent_list - array of agent plugin objects to schedule.
 * \param $Verbose - verbose output, not empty: output, empty: does not output
 */
function QueueUploadsOnAgents($upload_pk_list, $agent_list, $Verbose)
{
  global $Plugins;
  global $PG_CONN;

  /* Get the users.default_bucketpool_fk */
  $user_pk = Auth::getUserId();
  $group_pk = Auth::getGroupId();

  if (empty($upload_pk_list)) 
  {
    return;
  }
  // Schedule them
  $agent_count = count($agent_list);
  foreach(explode(",", $upload_pk_list) as $upload_pk) 
  {
    if (empty($upload_pk))  continue;

    // Create a job for the upload
    $where = "where upload_pk ='$upload_pk'";
    $UploadRec = GetSingleRec("upload", $where);
    if (empty($UploadRec))
    {
      echo "ERROR: unknown upload_pk: $upload_pk\n";
      continue;
    }

    $ShortName = $UploadRec['upload_filename'];

    /* Create Job */
    $job_pk = JobAddJob($user_pk, $group_pk, $ShortName, $upload_pk);

    // don't exit on AgentAdd failure, or all the agents requested will
    // not get scheduled.
    for ($ac = 0;$ac < $agent_count;$ac++) 
    {
      $agentname = $agent_list[$ac]->URI;
      if (!empty($agentname)) 
      {
        $Agent = & $Plugins[plugin_find_id($agentname) ];
        $Dependencies = "";
        $ErrorMsg = "already queued!";
        $agent_jq_pk = $Agent->AgentAdd($job_pk, $upload_pk, $ErrorMsg, $Dependencies);
        if ($agent_jq_pk <= 0) 
        {
          echo "WARNING: Scheduling failed for Agent $agentname, upload_pk is: $upload_pk, job_pk is:$job_pk\n";
          echo "WARNING message: $ErrorMsg\n";
        } 
        else if ($Verbose) 
        {
          $SQL = "SELECT upload_filename FROM upload where upload_pk = $upload_pk";
          $result = pg_query($PG_CONN, $SQL);
          DBCheckResult($result, $SQL, __FILE__, __LINE__);
          $row = pg_fetch_assoc($result);
          pg_free_result($result);
          print "$agentname is queued to run on $upload_pk:$row[upload_filename].\n";
        }
      }
    } /* for $ac */
  } /* for each $upload_pk */
} /* QueueUploadsOnAgents() */

/**
 * \brief Schedule delagent on upload ids
 *
 * \param $upload_pk_list -  upload ids, The string can be a comma-separated list of upload ids.
 * Or, use 'ALL' to specify all upload ids.
 */
function QueueUploadsOnDelagents($upload_pk_list)
{
  /* Get the users.default_bucketpool_fk */
  $user_pk = Auth::getUserId();
  $group_pk = Auth::getGroupId();

  if (!empty($upload_pk_list))
  {
    foreach(explode(",", $upload_pk_list) as $upload_pk)
    {
      if (empty($upload_pk))  continue;

      // Create a job for the upload
      $jobpk = JobAddJob($user_pk, $group_pk, "Delete", $upload_pk);
      if (empty($jobpk) || ($jobpk < 0)) {
        echo "WARNING: Failed to schedule Delagent for Upload $upload_pk";
      }
      $jqargs = "DELETE UPLOAD $upload_pk";
      $jobqueuepk = JobQueueAdd($jobpk, "delagent", $jqargs, NULL, NULL);
      if (empty($jobqueuepk)) {
        echo "WARNING: Failed to schedule Delagent for Upload $upload_pk";
      }
      print "Delagent is queued to run on Upload: $upload_pk.\n";
    } /* for each $upload_pk */
  } // if $upload_pk is defined
  /* Tell the scheduler to check the queue. */
  $success  = fo_communicate_with_scheduler("database", $output, $error_msg);
  if (!$success) echo $error_msg . "\n" . $output;
}

/**
 * \brief Check if an agent is already scheduled in a job.
 * This is used to make sure dependencies, like unpack
 * don't get scheduled multiple times within a single job.
 *
 * \param $job_pk    - the job to be checked
 * \param $AgentName - the agent name (from agent.agent_name)
 *
 * \return 
 * jq_pk of scheduled jobqueue
 * or 0 = not scheduled
 */
function IsAlreadyScheduled($job_pk, $AgentName, $upload_pk)
{
  global $PG_CONN;

  $jq_pk = 0;

  /* check if the upload_pk is currently in the job queue being processed when agent name is ununpack or adj2nest */
  /* it is unneccessary to reschedule ununpack and adj2nest, one time is enough */
  if ($AgentName == "ununpack" || $AgentName == "adj2nest")
  {
    $sql = "SELECT jq_pk FROM jobqueue, job where job_pk=jq_job_fk AND jq_type='$AgentName' and job_upload_fk = $upload_pk";
  }
  else
  {
    /* check if the upload_pk is currently in the job queue being processed */
    $sql = "SELECT jq_pk FROM jobqueue, job where job_pk=jq_job_fk AND jq_type='$AgentName' and job_pk=$job_pk";
  }
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  if (pg_num_rows($result) > 0)
  {
    $row = pg_fetch_assoc($result);
    $jq_pk = $row["jq_pk"];
  }
  pg_free_result($result);
  return $jq_pk;
} // IsAlreadyScheduled()


/**
 * \brief Queue an agent.  This is a simple version of AgentAdd() that can be
 *  used by multiple plugins that only use upload_pk as jqargs.
 *  Before queuing, check if agent needs to be queued.  It doesn't need to be queued if:
 *  - It is already queued
 *  - It has already been run by the latest agent version
 *
 * \param $plugin caller plugin object
 * \param $job_pk
 * \param $upload_pk
 * \param $ErrorMsg - error message on failure
 * \param $Dependencies - array of named dependencies. Each array element is the plugin name.
 *         For example,  array(agent_adj2nest, agent_pkgagent).  
 *         Typically, this will just be array(agent_adj2nest).
 * \param $jqargs (optional) jobqueue.jq_args
 *
 * \returns
 * - jq_pk Successfully queued
 * -   0   Not queued, latest version of agent has previously run successfully
 * -  -1   Not queued, error, error string in $ErrorMsg
 **/
function CommonAgentAdd($plugin, $job_pk, $upload_pk, &$ErrorMsg, $Dependencies, $jqargs = "", $jq_cmd_args = NULL)
{
  global $Plugins;
  $Deps = array();
  $DependsEmpty = array();

  /* check if the latest agent has already been run */
  if ($plugin->AgentHasResults($upload_pk) == 1)
    return 0;

  /* if it is already scheduled, then return success */
  if (($jq_pk = IsAlreadyScheduled($job_pk, $plugin->AgentName, $upload_pk)) != 0)
    return $jq_pk;

  /* queue up dependencies */
  foreach ($Dependencies as $Dependency)
  {
    if (is_array($Dependency))
    {
      $PluginName = $Dependency['name'];
      $DepArgs = $Dependency['args'];
    } else
    {
      $PluginName = $Dependency;
      $DepArgs = null;
    }
    $DepPlugin = plugin_find($PluginName);
    if ($DepPlugin === null)
    {
      $ErrorMsg = "Invalid plugin name: $PluginName, (CommonAgentAdd())";
      return -1;
    }
    if (($Deps[] = $DepPlugin->AgentAdd($job_pk, $upload_pk, $ErrorMsg, $DependsEmpty, $DepArgs)) == -1)
      return -1;
  }
  /* schedule AgentName */
  if (empty($jqargs))
  {
    $jqargs = $upload_pk;
  }
  $jq_pk = JobQueueAdd($job_pk, $plugin->AgentName, $jqargs, "", $Deps, NULL, $jq_cmd_args);
  if (empty($jq_pk))
  {
    $ErrorMsg = _("Failed to insert agent $plugin->AgentName into job queue. jqargs: $jqargs");
    return (-1);
  }
  /* Tell the scheduler to check the queue. */
  $success = fo_communicate_with_scheduler("database", $output, $error_msg);
  if (!$success)
  {
    $ErrorMsg = $error_msg . "\n" . $output;
  }

  return ($jq_pk);
}
