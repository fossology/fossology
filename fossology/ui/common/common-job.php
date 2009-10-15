<?php
/***********************************************************
 Copyright (C) 2008 Hewlett-Packard Development Company, L.P.

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
 * common-job
 * \brief library of functions used by the ui to manage 'jobs'
 * 
 * @version "$Id: $"
 */
/*
 Restrict usage: Every PHP file should have this
 at the very beginning.
 This prevents hacking attempts.
 */
global $GlobalReady;
if (!isset($GlobalReady)) {
  exit;
}

/*
 Terminology:
 Scheduled jobs are divided into a specific heirarchy.
 
 "Job"
 This is the highest level and assigns a name to the class of
 tasks that need to be performed.
 
 "JobQueue"
 Each job may require multiple "JobQueue" tasks.
 JobQueue tasks may have dependencies upon the completion of
 other JobQueue tasks.
 
 "Tasks"
 A single JobQueue items may define hundreds of individual operations.
 The scheduler handles the splitting of JobQueue items into tasks
 for processing.
 The Task may be a single operation, or a multi-SQL query (MSQ).
 (See the documentation for managing the jobqueue for the scheduler.
 
 As an example:
 The "license" job requires three jobqueue items:
 - Filter_License: For each file, pre-process the file contents.
 - License: For each pre-processed file, determine the licenses.
 - Filter_Clean: For each pre-processed file, remove the cache file.
 These jobqueue items depend on each other:
 - Filter_Clean depends on the completion of License.
 - License depends on the completion of Filter_License.
 - Filter_License depends on a non-"license" job named "unpack".
 The "unpack" jobqueue item is part of the "unpack" job.
 And the "unpack" jobqueue item MAY have a dependency on another
 job, called "wget".

 The jobqueue tasks for a specific job may vary based on the
 item being processed.
 */

/*
 * funcion: JobSetPriority
 *
 * Given an upload_pk and job_name, set the priority.
 * NOTE: In case of duplicate jobs, this updates ALL jobs.
 *
 * @param int $jobpk the upload_pk (why isn't the parameter called that?)
 * @param string
 *
 * Huh: the code does not match the comments, does this even work?
 * chat with neal...
 */
/************************************************************
 JobSetPriority(): Given an upload_pk and job_name, set the priority.
 NOTE: In case of duplicate jobs, this updates ALL jobs.
 ************************************************************/
function JobSetPriority($jobpk, $priority) {
  global $DB;
  if (empty($DB)) {
    return;
  }
  if (empty($jobpk)) {
    return;
  }
  if (empty($priority)) {
    $priority = 0;
  }
  $DB->Action("UPDATE job SET job_priority = '$priority' WHERE job_pk = '$jobpk';");
} // JobSetPriority()
/************************************************************
 JobGetType(): Given an upload_pk and a job_name, return the
 primary key for the job (job_pk).
 NOTE: In case of duplicate jobs, this returns the oldest job.
 ************************************************************/
function JobGetType($upload_pk, $job_name) {
  global $DB;
  if (empty($DB)) {
    return;
  }
  $Name = str_replace("'", "''", $job_name); // SQL taint string
  $Results = $DB->Action("SELECT job_pk FROM job WHERE job_upload_fk = '$upload_pk' AND job_name = '$Name' ORDER BY job_queued DESC LIMIT 1;");
  return ($Results[0]['job_pk']);
} // JobGetType()

/**
 *************************************************************
 * function: JobSetType()
 *
 * Given an upload_pk and a job_name, create the
 * primary key for the job (job_pk) if it does not exist.
 *
 * @param int $upload_pk the upload primary key
 * @param string $job_name name of the job
 * @param string $job_user_fk, default NULL.  The user_pk from users table, if not set
 *                             then use the user_pk from fossy.
 * @param string $job_email_notify the email address to use for noticifaction. defaults to "fossy@localhost"
 *
 * @return job_pk, NOTE: In case of duplicate jobs, this returns the oldest job.
 *************************************************************
 */
function JobSetType($upload_pk, $job_name, $priority = 0, $job_user_fk = NULL, $job_email_notify = NULL) {
  global $DB;
  if (empty($DB)) {
    return;
  }
  /* See if it exists already */
  $JobPk = JobGetType($upload_pk, $job_name);
  if (!empty($JobPk)) {
    return ($JobPk);
  }
  /* Set to the job_user_fk to the session UserId or fossy's */
  if (empty($job_user_fk)) {
    if (!empty($_SESSION['UserEmail'])) {
      $job_user_fk = $_SESSION['UserId'];
    }
    else {
      $Sql = "SELECT user_pk FROM users WHERE user_name = 'fossy';";
      $Results = $DB->Action($Sql);
      $job_user_fk = $Results[0]['user_pk'];
    }
  }
  if (empty($job_email_notify)) {
    if (!empty($_SESSION['UserEmail'])) {
      $job_email_notify = $_SESSION['UserEmail'];
    } else {
      $job_email_notify = 'fossy@localhost';
    }
  }
  /* Does not exist; go ahead and create it. */
  $Name = str_replace("'", "''", $job_name); // SQL taint string
  $Notify = str_replace("'", "''", $job_email_notify); // SQL taint string
  $DB->Action("INSERT INTO job (job_upload_fk,job_name,job_priority,job_user_fk, " .
               "job_email_notify) VALUES " .
               "('$upload_pk','$Name','$priority','$job_user_fk','$Notify');");
  /* In case of duplicate inserts (race condition), go get it again */
  $JobPk = JobGetType($upload_pk, $job_name);
  return ($JobPk);
} // JobSetType()
/************************************************************
 JobQueueGetKey(): Retrieve a jobqueue_pk.
 Returns jobqueue_pk, or NULL if not found.
 ************************************************************/
function JobQueueGetKey($upload_pk, $job_name, $jobqueue_type) {
  global $DB;
  if (empty($DB)) {
    return;
  }
  $Results = $DB->Action("SELECT jq_pk FROM jobqueue
  	LEFT OUTER JOIN job ON jobqueue.jq_job_fk = job.job_pk
  	WHERE job.job_upload_fk = '$upload_pk'
  	AND job.job_name = '$job_name'
  	AND jobqueue.jq_type = '$jobqueue_type';");
  return ($Results[0]['jq_pk']);
} // JobQueueGetKey()
/************************************************************
 JobQueueAddDependency(): Add a jobqueue dependency.
 ************************************************************/
function JobQueueAddDependency($JobQueueChild, $JobQueueParent) {
  global $DB;
  if (empty($DB)) {
    return;
  }
  /* See if the dependency exists */
  $Results = $DB->Action("SELECT * FROM jobdepends
  	WHERE jdep_jq_fk = '$JobQueueChild'
  	AND jdep_jq_depends_fk = '$JobQueueParent';");
  if (!empty($Results[0]['jdep_jq_fk'])) {
    return;
  } // Already exists
  /* Add it since it is missing */
  $DB->Action("INSERT INTO jobdepends
  	(jdep_jq_fk,jdep_jq_depends_fk)
  	VALUES
  	('$JobQueueChild','$JobQueueParent');");
} // JobQueueAddDependency()

/**
 * function:  JobAddUpload
 *
 * Insert a new upload record, and update foldercontents table
 *
 * @param string $job_name   the name to associate with the job
 * NOTE: $job_name is not used in this function!
 * @param string $filename   the path the to file to upload
 * @param string $desc       A meaningful description
 * @param int    $UploadMode e.g. 1<<2 = URL, 1<<3 = file upload, 1<<4 = filesystem
 * @param int    $FolderPk   The folder primary key
 *
 * @return upload_pk the upload primary key or null (failure)
 *
 * @todo fix the name of this function, it has nothing to do with scheduling
 * a job.
 */
function JobAddUpload($job_name, $filename, $desc, $UploadMode, $FolderPk) {
  global $DB;
  if (empty($DB)) {
    return;
  }
  $job_name = str_replace("'", "''", $job_name);
  $filename = str_replace("'", "''", $filename);
  $desc = str_replace("'", "''", $desc);
  $DB->Action("BEGIN;");
  /* Make sure folder record exists */
  $Results = $DB->Action("SELECT folder_pk FROM folder WHERE folder_pk = '$FolderPk';");
  if (empty($Results[0]['folder_pk'])) {
    $DB->Action("ROLLBACK");
    return;
  }
  /* check the user_pk (user_id), make sure it exists, if so, use it */
  $UserId = $_SESSION['UserId'];
  if (!empty($UserId)) {
    $DB->Action("INSERT INTO upload
      (upload_desc,upload_filename,upload_userid,upload_mode,upload_origin) VALUES
      ('$desc','$job_name','$UserId','$UploadMode','$filename');");
    $Results = $DB->Action("SELECT currval('upload_upload_pk_seq') as upload_pk FROM upload;");
    $uploadpk = $Results[0]['upload_pk'];
    if (empty($uploadpk)) {
      $DB->Action("ROLLBACK");
      return;
    }
  }
  else {
    /* Create the upload record WITHOUT upload_userid */
    $DB->Action("INSERT INTO upload
      (upload_desc,upload_filename,upload_mode,upload_origin) VALUES
      ('$desc','$job_name','$UploadMode','$filename');");
    $Results = $DB->Action("SELECT currval('upload_upload_pk_seq') as upload_pk FROM upload;");
    $uploadpk = $Results[0]['upload_pk'];
    if (empty($uploadpk)) {
      $DB->Action("ROLLBACK");
      return;
    }
  }
  /* Add the upload record to the folder */
  /** Mode == 2 means child_id is upload_pk **/
  $Results = $DB->Action("SELECT * FROM foldercontents
  	WHERE parent_fk = '$FolderPk'
  	AND foldercontents_mode = 2
  	AND child_id = '$uploadpk';");
  if (empty($Results[0]['foldercontents_pk'])) {
    $DB->Action("INSERT INTO foldercontents
    	(parent_fk,foldercontents_mode,child_id) VALUES
    	('$FolderPk',2,'$uploadpk');");
    $Results = $DB->Action("SELECT * FROM foldercontents
    	WHERE parent_fk = '$FolderPk'
    	AND foldercontents_mode = 2
    	AND child_id = '$uploadpk';");
    if (empty($Results[0]['foldercontents_pk'])) {
      $DB->Action("ROLLBACK");
      return;
    }
  }
  $DB->Action("COMMIT;");
  return ($uploadpk);
} // JobAddUpload()

/************************************************************
 JobQueueFindKey(): Given a job_pk and a jobqueue name, returns
 the jq_pk, or -1 if it does not exist.
 If you don't have a JobPk, use JobFindKey().
 ************************************************************/
function JobQueueFindKey($JobPk, $Name) {
  global $DB;
  if (empty($DB)) {
    return;
  }
  if (empty($JobPk)) {
    return (-1);
  }
  $Name = str_replace("'", "''", $Name);
  $SQL = "SELECT jq_pk FROM jobqueue WHERE jq_job_fk = '$JobPk' AND jq_type = '$Name';";
  $Results = $DB->Action($SQL);
  $jqpk = $Results[0]['jq_pk'];
  if (!empty($jqpk)) {
    return ($jqpk);
  }
  return (-1);
} // JobQueueFindKey()
/************************************************************
 JobFindKey(): Given an upload_pk and a job name, returns
 the job_pk, or -1 if it does not exist.
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
  $Status['active'] = 0;
  $Status['failed'] = 0;
  $SQL = "SELECT job_pk,jq_starttime,jq_endtime,jq_end_bits FROM jobqueue
  	INNER JOIN job ON jq_job_fk = job_pk
  	AND job_upload_fk = '$upload_pk'
  	ORDER BY job_pk;";
  $Results = $DB->Action($SQL);
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
    if(count($Results) > 1) {
      $i++;
    }
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
  return ($Status);
} // JobListSummary()

?>
