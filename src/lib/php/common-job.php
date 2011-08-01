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

  $UserId = $_SESSION['UserId'];

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
 * \param int    $UploadPk
 * \parm  string $JobName
 *
 * \return  the job_pk, or -1 if it does not exist.
 * \todo this might return multiple records in v2.0
 ************************************************************/
function JobFindKey($UploadPk, $JobName) {
  global $DB;
  if (empty($DB)) {
    return;
  }
  $JobName = str_replace("'", "''", $JobName);
  if (empty($UploadPk)) {
    $SQL = "SELECT job_pk FROM job WHERE job_upload_fk IS NULL AND job_name = '$JobName';";
  } else {
    $SQL = "SELECT job_pk FROM job WHERE job_upload_fk = '$UploadPk' AND job_name = '$JobName';";
  }
  $Results = $DB->Action($SQL);
  if(empty($Results)) {
    return(-1);
  }
  else {
    return($Results[0]['job_pk']);
  }
} // JobFindKey()


/**
 * function: JobAddJob
 *
 * Insert a new job type (not a jobqueue item). NOTE: If the Job already
 * exists, then it will not be added again.
 *
 * @param int $upload_pk upload record primary key, see JobAddUpload
 * @param string $job_name
 * @param int $priority the job priority, default 0
 *
 * @return int $jobpk the job primary key
 *
 */
function JobAddJob($upload_pk, $job_name, $priority = 0) {
  global $DB;
  if (empty($DB)) {
    return;
  }
  if (!empty($_SESSION['UserEmail'])) {
    $job_user_fk = $_SESSION['UserId'];
  }
  else {
    $Sql = "SELECT user_pk FROM users WHERE user_name = 'fossy';";
    $Results = $DB->Action($Sql);
    $job_user_fk = $Results[0]['user_pk'];
  }

  if (!empty($_SESSION['UserEmail'])) {
    $job_email_notify = $_SESSION['UserEmail'];
  } else {
    $job_email_notify = 'fossy@localhost';
  }
  $Job_email_notify = str_replace("'", "''", $job_email_notify);
  $Job_name = str_replace("'", "''", $job_name);
  if (empty($upload_pk)) {
    $SQLInsert = "INSERT INTO job
    	(job_user_fk,job_queued,job_priority,job_email_notify,job_name) VALUES
    	('$job_user_fk',now(),'$priority','$Job_email_notify','$Job_name');";
  }
  else {
    $SQLInsert = "INSERT INTO job
    	(job_user_fk,job_queued,job_priority,job_email_notify,job_name,job_upload_fk) VALUES
     	('$job_user_fk',now(),'$priority','$Job_email_notify','$Job_name','$upload_pk');";
  }
  $jobpk = JobFindKey($upload_pk, $Job_name);
  /*
     If the job already exists, just return the jobpk, don't insert
   */
  if ($jobpk >= 0) {
    return ($jobpk);
  }
  $DB->Action($SQLInsert);
  $jobpk = JobFindKey($upload_pk, $job_name);
  return ($jobpk);
} // JobAddJob()

/**
 * function: JobQueueAdd
 *
 * Insert a jobqueue item.
 *
 * @param int    $job_pk the job primary key (returned by JobAddJob)
 * @param string $jq_type name of agent (should match a string in Scheduler.conf
 * @param string $jq_args arguments to pass to the agent in the form of
 * $jq_args="folder_pk='$Folder' name='$Name' description='$Desc' ...";
 * @param string $jq_repeat values: yes or no
 * @param string $jq_runonpfile the column name
 * @param array  $Depends lists on or more jobqueue_pk's this job is
 * dependent on.
 * @param int    $Reschedule, default 0, 1 to reschedule.
 *
 * @return new jobqueue key ($jqpk)
 *
 */
function JobQueueAdd($job_pk, $jq_type, $jq_args, $jq_repeat, $jq_runonpfile, $Depends, $Reschedule = 0) {
  global $DB;
  if (empty($DB)) {
    return;
  }
  $jq_args = str_replace("'", "''", $jq_args); // protect variables
  $DB->Action("BEGIN");
  /* Make sure all dependencies exist */
  if (is_array($Depends)) {
    foreach($Depends as $D) {
      if (empty($D)) {
        continue;
      }
      $Results = $DB->Action("SELECT jq_pk FROM jobqueue WHERE jq_pk = '$D';");
      if (empty($Results[0]['jq_pk'])) {
        $DB->Action("ROLLBACK;");
        return;
      }
    }
  }
  /* Check if the job exists */
  $Results = $DB->Action("SELECT jq_pk FROM jobqueue
  	WHERE jq_job_fk = '$job_pk' AND jq_type = '$jq_type' AND jq_args = '$jq_args';");
  if (!empty($Results)) {
    $jqpk = $Results[0]['jq_pk'];
  }
  else if (empty($Results)) {
    /* Add the job */
    $SQL = "INSERT INTO jobqueue ";
    $SQL.= "(jq_job_fk,jq_type,jq_args,jq_repeat,jq_runonpfile,jq_starttime,jq_endtime,jq_end_bits) VALUES ";
    $SQL.= "('$job_pk','$jq_type','$jq_args','$jq_repeat',";
    if (empty($jq_runonpfile)) {
      $SQL.= "NULL";
    } else {
      $SQL.= "'$jq_runonpfile'";
    }
    $SQL.= ",NULL,NULL,0);";
    $DB->Action($SQL);
    /* Find the job that was just added */
    $Results = $DB->Action("SELECT jq_pk FROM jobqueue
    	WHERE jq_job_fk = '$job_pk' AND jq_type = '$jq_type' AND jq_args = '$jq_args';");
    $jqpk = $Results[0]['jq_pk'];
    if (empty($jqpk)) {
      $DB->Action("ROLLBACK;");
      return;
    }
  }
  /* Add dependencies */
  if (is_array($Depends)) {
    foreach($Depends as $D) {
      if (empty($D)) {
        continue;
      }
      $Results = $DB->Action("SELECT * FROM jobdepends
      		WHERE jdep_jq_fk = '$jqpk'
      		AND jdep_jq_depends_fk = '$D'");
      if (empty($Results[0]['jdep_jq_fk'])) {
        $DB->Action("INSERT INTO jobdepends
        		(jdep_jq_fk,jdep_jq_depends_fk) VALUES
        		('$jqpk','$D');");
        $Results = $DB->Action("SELECT * FROM jobdepends
        		WHERE jdep_jq_fk = '$jqpk'
        		AND jdep_jq_depends_fk = '$D';");
      }
      if (empty($Results[0]['jdep_jq_fk'])) {
        $DB->Action("ROLLBACK;");
        return;
      }
    }
  }
  $DB->Action("COMMIT;");
  if ($Reschedule) {
    JobQueueChangeStatus($jqpk, "reset");
  }
  return ($jqpk);
} // JobQueueAdd()
/************************************************************
 JobChangeStatus(): Mark the entire job with a state.
 Returns 0 on success, non-0 on failure.
 ************************************************************/
function JobChangeStatus($jobpk, $Status) {
  if (empty($jobpk) || ($jobpk < 0)) {
    return (-1);
  }
  global $DB;
  switch ($Status) {
    case "reset":
      $SQL = "UPDATE jobqueue
      	SET jq_starttime=NULL,jq_endtime=NULL,jq_end_bits=0
      		WHERE jq_job_fk = '$jobpk'";
      break;
    case "fail":
      $SQL = "UPDATE jobqueue
      		SET jq_starttime=now(),jq_endtime=now(),jq_end_bits=2
      		WHERE jq_job_fk = '$jobpk'
      		AND jq_starttime IS NULL;
      		UPDATE jobqueue
      		SET jq_starttime=now(),jq_endtime=now(),jq_end_bits=2
      		WHERE jq_job_fk = '$jobpk' AND jq_starttime IS NOT NULL
      		AND jq_endtime IS NULL;";
      break;
    case "succeed":
      $SQL = "UPDATE jobqueue
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
      $SQL = "DELETE FROM jobdepends WHERE
      		jdep_jq_fk IN (SELECT jq_pk FROM jobqueue WHERE jq_job_fk = '$jobpk')
      		OR
      		jdep_jq_depends_fk IN (SELECT jq_pk FROM jobqueue WHERE jq_job_fk = '$jobpk');
      		DELETE FROM jobqueue WHERE jq_job_fk = '$jobpk';
      		DELETE FROM job WHERE job_pk = '$jobpk'; ";
      break;
    default:
      return (-1);
  }
  $DB->Action($SQL);
  return (0);
} // JobChangeStatus()

/**
 * function: JobQueueChangeStatus
 *
 * Change the jobqueue item status.
 *
 * @param int $jqpk the job Queue primary key
 * @param string $Status the new status
 *        Valid status key words are:
 *          reset
 *          fail
 *          succeed
 *          delete (no real delete for job Q item)
 *
 * @return 0 on success, non-0 on failure.
 */
function JobQueueChangeStatus($jqpk, $Status) {
  if (empty($jqpk) || ($jqpk < 0)) {
    return (-1);
  }
  global $DB;
  switch ($Status) {
    case "reset":
      $SQL = "UPDATE jobqueue
      		SET jq_starttime=NULL,jq_endtime=NULL,jq_end_bits=0
      		WHERE jq_pk = '$jqpk'";
      break;
    case "reset_completed": /* reset the job if it is done */
      $SQL = "UPDATE jobqueue
      		SET jq_starttime=NULL,jq_endtime=NULL,jq_end_bits=0
      		WHERE jq_pk = '$jqpk' AND jq_endtime IS NOT NULL";
      break;
    case "fail":
      $SQL = "UPDATE jobqueue
      		SET jq_starttime=now(),jq_endtime=now(),jq_end_bits=2
      		WHERE jq_pk = '$jqpk' AND jq_starttime IS NULL;
      		UPDATE jobqueue
      		SET jq_starttime=now(),jq_endtime=now(),jq_end_bits=2
      		WHERE jq_pk = '$jqpk'
      		AND jq_starttime IS NOT NULL
      		AND jq_endtime IS NULL;";
      break;
    case "succeed":
      $SQL = "UPDATE jobqueue
      		SET jq_starttime=now(),jq_endtime=now(),jq_end_bits=1
      		WHERE jq_pk = '$jqpk'
      		AND jq_starttime IS NULL;
      		UPDATE jobqueue
      		SET jq_starttime=now(),jq_endtime=now(),jq_end_bits=1
      		WHERE jq_pk = '$jqpk'
      		AND jq_starttime IS NOT NULL
      		AND jq_endtime IS NULL;";
      break;
    case "delete":
      /****
       There is no "Delete" for a jobqueue item.
       "Why not?"
       The jobdepends table creates complexity.  If a deleted job was
       a dependency, then all intermediate dependencies need to be
       deleted or rewritten.  In general, jobqueue dependencies are
       there for a reason.  Deleting a middle step destroys the flow
       and will likely lead to a failed analysis.
       If you need to delete, use delete job.
       ****/
    default:
      return (-1);
  }
  $R = $DB->Action($SQL);
  return (0);
} // JobQueueChangeStatus()
/************************************************************
 JobListSummary(): Given an upload_pk, return a list of
 the number of items in the jobqueue.
 The returned array:
 ['failed'] = 0x01 number of failed jobs (not jobqueue items)
 ['completed'] = 0x02 number of completed jobs (not jobqueue items)
 ['pending'] = 0x04 number of pending (not active) jobs (not jobqueue items)
 ['active'] = 0x08 number of active jobs (not jobqueue items)
 ['total'] = number of total jobs (not jobqueue items)
 ************************************************************/
function JobListSummary($upload_pk) {
  global $DB;
  if (empty($DB)) {
    return;
  }
  $Status = array();
  $Status['total'] = 0;
  $Status['completed'] = 0;
  $Status['pending'] = 0;
  $Status['active'] = 0;
  $Status['failed'] = 0;
  $SQL = "SELECT job_pk,jq_starttime,jq_endtime,jq_end_bits FROM jobqueue
  	INNER JOIN job ON jq_job_fk = job_pk
  	AND job_upload_fk = '$upload_pk'
  	ORDER BY job_pk;";
  $Results = $DB->Action($SQL);
  if (empty($Results)) return $Status;
  /* Scan and track the results */
  $JobId = $Results[0]['job_pk'];
  $i = 0;
  $State = 0;
  while (!empty($Results[$i]['job_pk'])) {
    if ($Results[$i]['jq_end_bits'] == 2) {
      $State|= 0x01;
    }
    if (!empty($Results[$i]['jq_starttime'])) {
      if (!empty($Results[$i]['jq_endtime'])) {
        $State|= 0x02;
      } else {
        $State|= 0x08;
      }
    } else {
      $State|= 0x04;
    }
    if(count($Results) >= 1) {
      $i++;
    }
    if (array_key_exists($i, $Results) && array_key_exists('job_pk',$Results[$i]))
    {
    if ($Results[$i]['job_pk'] != $JobId) {
      $Status['total']++;
      if ($State & 0x01) {
        $Status['failed']++;
      } else if ($State & 0x08) {
        $Status['active']++;
      } else if ($State & 0x04) {
        $Status['pending']++;
      } else if ($State & 0x02) {
        $Status['completed']++;
      }
      $State = 0;
      $JobId = $Results[$i]['job_pk'];
    }
    }
  }
  return ($Status);
} // JobListSummary()

/**
 * \brief  Get job list according the status

 * \param string $status - the status might be:
          Started, Completed, Restart, Failed, Paused, etc
          the status 'Started' and 'Restart', you can call them as running status
          to get all the running job list, you can set the $status as 'tart'

 * \return job list related to the jobstatus,
           the result is like: Array1, 2, 3, .., i), sorted
 */
function GetJobList($status)
{

  /* get the job list according to the status */
  global $DB;
  if (empty($DB)) {
    return;
  }
  $SQL = "SELECT jq_pk FROM jobqueue WHERE jq_endtext like '%$status%' order by jq_pk;";
  $Results = $DB->Action($SQL);
	$job_array = array();
  foreach ($Results as $key => $value)
  {
    array_push($job_array, $value['jq_pk']);
	}

  sort($job_array, SORT_NUMERIC);
	return $job_array;
}

?>
