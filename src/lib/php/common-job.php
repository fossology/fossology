<?php
/***********************************************************
 Copyright (C) 2008-2011 Hewlett-Packard Development Company, L.P.

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
 * \file common-job.php
 * \brief library of functions used by the ui to manage jobs.
 *        Jobs information is stored in the jobs, jobdepends,
 *        and jobqueue tables.
 * 
 * Terminology:
 * Scheduled jobs are divided into a specific heirarchy.
 * 
 * "Job"
 * This is the highest level and assigns a name to the type of
 * tasks that need to be performed.  For example, a copyright
 * job would only run the copyright agent.  But an unpack job
 * would run both ununpack and adj2nest agents since they are
 * both needed to complete the unpack task.
 * 
 * "JobQueue"
 * Each job may require multiple "JobQueue" tasks.
 * In the above example, copyright, ununpack, and adj2nest
 * are all separate enteries in the jobqueue table.
 * The copyright jobqueue would be under the copyright job.
 * The ununpack and adj2nest are the two jobqueue entries
 * under the unpack job.
 * 
 * JobQueue tasks may have dependencies upon the completion of
 * other JobQueue tasks.  The jobdepends tables keep those
 * parent child relationships.
 * 
 **/

/**
 * \brief  Set a job priority.
 *
 * \param int $jobpk
 * \param int $priority - Numeric job priority.  May be negative.
 *
 * \return True if priority was updated, else False
 */
function JobSetPriority($jobpk, $priority) {
  global $PG_CONN;

  if (empty($jobpk) or empty($priority)) return false;

  $sql = "UPDATE job SET job_priority = '$priority' WHERE job_pk = '$jobpk'";
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  pg_free_result($result);
  return true;
} // JobSetPriority()


/**
 * \brief Insert a new upload record, and update foldercontents table
 *
 * \param string $job_name   Job name
 * \param string $filename   For upload from URL, this is the URL
 *                           For uplaod from file, this is the filename
 *                           For upload from server, this is the file path
 * \param string $desc       Optional user file description.
 * \param int    $UploadMode e.g. 1<<2 = URL, 1<<3 = file upload, 1<<4 = filesystem
 * \param int    $FolderPk   The folder to contain this upload
 *
 * \return upload_pk or null (failure)
 */
function JobAddUpload($job_name, $filename, $desc, $UploadMode, $FolderPk) 
{
  global $PG_CONN;
  global $SysConf;

  $UserId = $_SESSION['UserId'];
  if (empty($UserId)) $UserId = $SysConf['auth']['UserId'];

  /* check all required inputs */
  if (empty($UserId) or empty($job_name) or empty($filename) or 
      empty($UploadMode) or empty($FolderPk)) return;

  $job_name = str_replace("'", "''", $job_name);
  $filename = str_replace("'", "''", $filename);
  $desc = str_replace("'", "''", $desc);

  $sql = "INSERT INTO upload
      (upload_desc,upload_filename,user_fk,upload_mode,upload_origin) VALUES
      ('$desc','$job_name','$UserId','$UploadMode','$filename')";
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  pg_free_result($result);

  /* get upload_pk of just added upload */
  $sql = "SELECT currval('upload_upload_pk_seq') as upload_pk FROM upload";
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  $row = pg_fetch_assoc($result);
  $uploadpk = $row['upload_pk'];
  pg_free_result($result);

  /* Add the upload record to the folder */
  /** Mode == 2 means child_id is upload_pk **/
  $sql = "INSERT INTO foldercontents (parent_fk,foldercontents_mode,child_id) 
               VALUES ('$FolderPk',2,'$uploadpk')";
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  pg_free_result($result);

  return ($uploadpk);
} // JobAddUpload()

/**
 * \brief Find the job_pk given the upload_pk and job_name
 *
 * \param int    $UploadPk (optional)
 * \param string $JobName
 *
 * \return the last job_pk, or -1 if it does not exist.
 ************************************************************/
function JobFindKey($UploadPk, $JobName) 
{
  global $PG_CONN;
  $JobName = str_replace("'", "''", $JobName);

  if (empty($UploadPk)) {
    $sql = "SELECT max(job_pk) as job_pk FROM job WHERE job_upload_fk IS NULL AND job_name = '$JobName'";
  } else {
    $sql = "SELECT max(job_pk) as job_pk FROM job WHERE job_upload_fk = '$UploadPk' AND job_name = '$JobName'";
  }
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);

  if (pg_num_rows($result) == 0)
    $job_pk = -1;
  else
  {
    $row = pg_fetch_assoc($result);
    $job_pk = $row['job_pk'];
  }
  pg_free_result($result);

  return($job_pk);
} // JobFindKey()


/**
 * @brief Insert a new job record.
 *
 * @param int $upload_pk (optional)
 * @param string $job_name
 * @param int $priority the job priority, default 0
 *
 * @return int $jobpk the job primary key, or -1 on error
 */
function JobAddJob($upload_pk, $job_name, $priority = 0) 
{
  global $PG_CONN;
  global $SysConf;

  $job_user_fk = GetArrayVal("UserId", $_SESSION);
  if (empty($job_user_fk)) $job_user_fk = $SysConf['auth']['UserId'];
  if (empty($job_user_fk)) return -1;

  $Job_name = str_replace("'", "''", $job_name);
  if (empty($upload_pk))
    $upload_pk_val = "null";
  else
    $upload_pk_val = $upload_pk;

  $sql = "INSERT INTO job
    	(job_user_fk,job_queued,job_priority,job_name,job_upload_fk) VALUES
     	('$job_user_fk',now(),'$priority','$Job_name',$upload_pk_val)";
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  pg_free_result($result);

  $jobpk = JobFindKey($upload_pk, $job_name);
  return ($jobpk);
} // JobAddJob()

/**
 * @brief Insert a jobqueue + jobdepends records.
 *
 * @param int    $job_pk the job primary key (returned by JobAddJob)
 * @param string $jq_type name of agent (should match the name in agent.conf
 * @param string $jq_args arguments to pass to the agent in the form of
 * $jq_args="folder_pk='$Folder' name='$Name' description='$Desc' ...";
 * @param string $jq_repeat obsolete
 * @param string $jq_runonpfile column name
 * @param array  $Depends lists on or more jobqueue_pk's this job is
 * dependent on.
 * @param int    $Reschedule, default 0, 1 to reschedule.
 *
 * @return new jobqueue key (jobqueue.jq_pk), or null on failure
 *
 */
function JobQueueAdd($job_pk, $jq_type, $jq_args, $jq_repeat, $jq_runonpfile, $Depends, $Reschedule = 0) 
{
  global $PG_CONN;
  $jq_args = str_replace("'", "''", $jq_args); // protect variables

  /* Make sure all dependencies exist */
  if (is_array($Depends)) 
  {
    foreach($Depends as $D) 
    {
      if (empty($D)) continue;

      $sql = "SELECT jq_pk FROM jobqueue WHERE jq_pk = '$D'";
      $result = pg_query($PG_CONN, $sql);
      DBCheckResult($result, $sql, __FILE__, __LINE__);
      $MissingDep =  (pg_num_rows($result) == 0) ? true : false;
      pg_free_result($result);

      if ($MissingDep) return;
    }
  }

  $sql = "BEGIN";
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  pg_free_result($result);

  /* Add the job */
  $sql = "INSERT INTO jobqueue ";
  $sql.= "(jq_job_fk,jq_type,jq_args,jq_runonpfile,jq_starttime,jq_endtime,jq_end_bits) VALUES ";
  $sql.= "('$job_pk','$jq_type','$jq_args',";
  if (empty($jq_runonpfile))
    $sql.= "NULL";
  else 
    $sql.= "'$jq_runonpfile'";
  $sql.= ",NULL,NULL,0);";

  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  pg_free_result($result);
   
  /* Find the job that was just added */
  $sql = "SELECT currval('jobqueue_jq_pk_seq') as jq_pk FROM jobqueue";
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  $row = pg_fetch_assoc($result);
  pg_free_result($result);

  $jq_pk = $row['jq_pk'];
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
    foreach($Depends as $D) 
    {
      if (empty($D)) continue;

      $sql = "INSERT INTO jobdepends
        		(jdep_jq_fk,jdep_jq_depends_fk) VALUES
        		('$jq_pk','$D')";
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

  if ($Reschedule) 
  {
    JobQueueChangeStatus($jq_pk, "reset");
  }
  return $jq_pk;
} // JobQueueAdd()


/**
 * @brief Change Job Status
 *
 * @param int    $job_pk (job.job_pk)
 * @param string $Status
 *   - reset
 *   - fail
 *   - succeed
 *   - delete
 * @return 0 on success, non-0 on failure.
 ************************************************************/
function JobChangeStatus($jobpk, $Status) 
{
  global $PG_CONN;

  if (empty($jobpk) || ($jobpk < 0)) return (-1);

  switch ($Status) 
  {
    case "reset":
      $sql = "UPDATE jobqueue
      	SET jq_starttime=NULL,jq_endtime=NULL,jq_end_bits=0, jq_endtext=null
      		WHERE jq_job_fk = '$jobpk'";
      break;
    case "fail":
      $sql = "UPDATE jobqueue
      		SET jq_starttime=now(),jq_endtime=now(),jq_end_bits=2
      		WHERE jq_job_fk = '$jobpk'
      		AND jq_starttime IS NULL;
      		UPDATE jobqueue
      		SET jq_starttime=now(),jq_endtime=now(),jq_end_bits=2
      		WHERE jq_job_fk = '$jobpk' AND jq_starttime IS NOT NULL
      		AND jq_endtime IS NULL;";
      break;
    case "succeed":
      $sql = "UPDATE jobqueue
      		SET jq_starttime=now(),jq_endtime=now(),jq_end_bits=1
      		WHERE jq_job_fk = '$jobpk'
      		AND jq_starttime IS NULL;
      		UPDATE jobqueue
      		SET jq_starttime=now(),jq_endtime=now(),jq_end_bits=1
      		WHERE jq_job_fk = '$jobpk'
      		AND jq_starttime IS NOT NULL
      		AND jq_endtime IS NULL;";
      break;
    case "delete":
      /* Blow away the jobqueue items and the job */
      $sql = "DELETE FROM jobdepends WHERE
      		jdep_jq_fk IN (SELECT jq_pk FROM jobqueue WHERE jq_job_fk = '$jobpk')
      		OR
      		jdep_jq_depends_fk IN (SELECT jq_pk FROM jobqueue WHERE jq_job_fk = '$jobpk');
      		DELETE FROM jobqueue WHERE jq_job_fk = '$jobpk';
      		DELETE FROM job WHERE job_pk = '$jobpk'; ";
      break;
    default:
      return (-1);
  }

  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  pg_free_result($result);
  return (0);
} // JobChangeStatus()


/**
 * @brief Change the jobqueue item status.
 *
 * @param int $jqpk the job Queue primary key
 * @param string $Status the new status
 *  - reset
 *  - reset_completed
 *  - fail
 *  - succeed
 *
 *  There is no "Delete" for a jobqueue item.
 *  "Why not?"
 *  The jobdepends table creates complexity.  If a deleted job was
 *  a dependency, then all intermediate dependencies need to be
 *  deleted or rewritten.  In general, jobqueue dependencies are
 *  there for a reason.  Deleting a middle step destroys the flow
 *  and will likely lead to a failed analysis.
 *  If you need to delete, use delete job.
 *
 * @return 0 on success, non-0 on failure.
 */
function JobQueueChangeStatus($jqpk, $Status) 
{
  global $PG_CONN;

  if (empty($jqpk) || ($jqpk < 0)) return (-1);
 
  switch ($Status) 
  {
    case "reset":
      $sql = "UPDATE jobqueue
      		SET jq_starttime=NULL,jq_endtime=NULL,jq_end_bits=0
      		WHERE jq_pk = '$jqpk'";
      break;
    case "reset_completed": /* reset the job if it is done */
      $sql = "UPDATE jobqueue
      		SET jq_starttime=NULL,jq_endtime=NULL,jq_end_bits=0
      		WHERE jq_pk = '$jqpk' AND jq_endtime IS NOT NULL";
      break;
    case "fail":
      $sql = "UPDATE jobqueue
      		SET jq_starttime=now(),jq_endtime=now(),jq_end_bits=2
      		WHERE jq_pk = '$jqpk' AND jq_starttime IS NULL;
      		UPDATE jobqueue
      		SET jq_starttime=now(),jq_endtime=now(),jq_end_bits=2
      		WHERE jq_pk = '$jqpk'
      		AND jq_starttime IS NOT NULL
      		AND jq_endtime IS NULL;";
      break;
    case "succeed":
      $sql = "UPDATE jobqueue
      		SET jq_starttime=now(),jq_endtime=now(),jq_end_bits=1
      		WHERE jq_pk = '$jqpk'
      		AND jq_starttime IS NULL;
      		UPDATE jobqueue
      		SET jq_starttime=now(),jq_endtime=now(),jq_end_bits=1
      		WHERE jq_pk = '$jqpk'
      		AND jq_starttime IS NOT NULL
      		AND jq_endtime IS NULL;";
      break;
    default:
      return (-1);
  }
  
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  pg_free_result($result);
  return (0);
} // JobQueueChangeStatus()


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
	if (empty($status)) return;
  $sql = "SELECT jq_pk FROM jobqueue WHERE jq_endtext like '%$status%' order by jq_pk;";
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  $job_array = array();
  $job_array =	pg_fetch_all_columns($result, 0);

	pg_free_result($result);
	return $job_array;
}

/**
 * \brief scheduling agent tasks on upload ids
 *
 * \param $upload_pk_list -  upload ids, The string can be a comma-separated list of upload ids.
 * Or, use 'ALL' to specify all upload ids.
 * \param $agent_list - agent list, specify agent to schedule (default is everything from fossjobs -a)
 * The string can be a comma-separated list of agent tasks, 
 * \param $Verbose - verbose output, not empty: output, empty: does not output
 * \param $Priority - priority for the jobs (higher = more important, default:0)
 */
function QueueUploadsOnAgents($upload_pk_list, &$agent_list, $Verbose, $Priority=0)
{
  global $Plugins;
  global $PG_CONN;
 
  if (!empty($upload_pk_list)) {
    $reg_agents = array();
    $results = array();
    // Schedule them
    $agent_count = count($agent_list);
    foreach(explode(",", $upload_pk_list) as $upload_pk) {
      if (empty($upload_pk)) {
        continue;
      }
      // don't exit on AgentAdd failure, or all the agents requested will
      // not get scheduled.
      for ($ac = 0;$ac < $agent_count;$ac++) {
        $agentname = $agent_list[$ac]->URI;
        if (!empty($agentname)) {
          $Agent = & $Plugins[plugin_find_id($agentname) ];
          $results = $Agent->AgentAdd($upload_pk, NULL, $Priority);
          if (!empty($results)) {
            echo "ERROR: Scheduling failed for Agent $agentname\n";
            echo "ERROR message: $results\n";
          } else if ($Verbose) {
            $SQL = "SELECT upload_filename FROM upload where upload_pk = $upload_pk;";
            $result = pg_query($PG_CONN, $SQL);
            DBCheckResult($result, $SQL, __FILE__, __LINE__);
            $row = pg_fetch_assoc($result);
            pg_free_result($result);
            print "$agentname is queued to run on $upload_pk:$row[upload_filename].\n";
          }
        }
      } /* for $ac */
    } /* for each $upload_pk */
  } // if $upload_pk is defined
}
?>
