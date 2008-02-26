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

/*************************************************
 Restrict usage: Every PHP file should have this
 at the very beginning.
 This prevents hacking attempts.
 *************************************************/
global $GlobalReady;
if (!isset($GlobalReady)) { exit; }

/************************************************************
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
 ************************************************************/

/************************************************************
 JobGetPriority(): Given a upload_pk and job_name, get the priority.
 Returns priority or NULL if no job.
 NOTE: In case of duplicate jobs, this returns the oldest job.
 ************************************************************/
function JobGetPriority($upload_pk,$job_name)
{
  global $DB;
  if (empty($DB)) { return; }
  $Name = str_replace("'","''",$job_name); // SQL taint string
  $Results = $DB->Action("SELECT job_priority FROM job WHERE job_upload_fk = '$upload_pk' AND job_name = '$Name' ORDER BY job_queued ASC LIMIT 1;");
  return($Results[0]['job_priority']);
} // JobGetPriority()

/************************************************************
 JobSetPriority(): Given an upload_pk and job_name, set the priority.
 NOTE: In case of duplicate jobs, this updates ALL jobs.
 ************************************************************/
function JobSetPriority($upload_pk,$job_name,$priority)
{
  global $DB;
  if (empty($DB)) { return; }
  $Name = str_replace("'","''",$job_name); // SQL taint string
  $DB->Action("UPDATE job_priority SET job_priority = '$priority' WHERE job_upload_fk = '$upload_pk' AND job_name = '$Name';");
} // JobSetPriority()

/************************************************************
 JobGetType(): Given an upload_pk and a job_name, return the
 primary key for the job (job_pk).
 NOTE: In case of duplicate jobs, this returns the oldest job.
 ************************************************************/
function JobGetType($upload_pk,$job_name)
{
  global $DB;
  if (empty($DB)) { return; }
  $Name = str_replace("'","''",$job_name); // SQL taint string
  $Results = $DB->Action("SELECT job_pk FROM job WHERE job_upload_fk = '$upload_pk' AND job_name = '$Name' ORDER BY job_queued DESC LIMIT 1;");
  return($Results[0]['job_pk']);
} // JobGetType()

/************************************************************
 JobSetType(): Given an upload_pk and a job_name, create the
 primary key for the job (job_pk) if it does not exist.
 NOTE: In case of duplicate jobs, this returns the oldest job.
 ************************************************************/
function JobSetType($upload_pk,$job_name,
		    $priority=0,
		    $job_submitter="fossy@localhost",
		    $job_email_notify="fossy@localhost")
{
  global $DB;
  if (empty($DB)) { return; }

  /* See if it exists already */
  $JobPk = JobGetType($upload_pk,$job_name);
  if (!empty($JobPk)) { return ($JobPk); }

  /* Does not exist; go ahead and create it.
  $Name = str_replace("'","''",$job_name); // SQL taint string
  $Submitter = str_replace("'","''",$job_submitter); // SQL taint string
  $Notify = str_replace("'","''",$job_email_notify); // SQL taint string
  $DB->Action("INSERT INTO job (job_upload_fk,job_name,job_priority,job_submitter,job_email_notify) VALUES ('$upload_pk','$Name','$priority','$Submitter','$Notify');");

  /* In case of duplicate inserts (race condition), go get it again */
  $JobPk = JobGetType($upload_pk,$job_name);
  return ($JobPk);
} // JobSetType()

/************************************************************
 JobQueueGetKey(): Retrieve a jobqueue_pk.
 Returns jobqueue_pk, or NULL if not found.
 ************************************************************/
function JobQueueGetKey($upload_pk,$job_name,$jobqueue_type)
{
  global $DB;
  if (empty($DB)) { return; }
  $Results = $DB->Action("SELECT jq_pk FROM jobqueue
	LEFT OUTER JOIN job ON jobqueue.jq_job_fk = job.job_pk
	WHERE job.job_upload_fk = '$upload_pk'
	AND job.job_name = '$job_name'
	AND jobqueue.jq_type = '$jobqueue_type';");
  return($Results[0]['jq_pk']);
} // JobQueueGetKey()

/************************************************************
 JobQueueAddDependency(): Add a jobqueue dependency.
 ************************************************************/
function JobQueueAddDependency($JobQueueChild, $JobQueueParent)
{
  global $DB;
  if (empty($DB)) { return; }
  $DB->Action("INSERT INTO jobdepends (jdep_jq_fk,jdep_jq_depends_fk,jdep_depends_bits) VALUES ('$JobQueueChild','$JobQueueParent',1);");
} // JobQueueAddDependency()

/************************************************************
 JobAddUpload(): Insert a new upload record.
 Returns upload_pk.
 ************************************************************/
function JobAddUpload ($job_name,$filename,$desc,$UploadMode,$FolderPk)
{
  global $DB;
  if (empty($DB)) { return; }

  $job_name = str_replace("'","''",$job_name);
  $filename = str_replace("'","''",$filename);
  $desc = str_replace("'","''",$desc);

  $DB->Action("BEGIN;");

  /* Make sure folder record exists */
  $Results = $DB->Action("SELECT folder_pk FROM folder WHERE folder_pk = '$FolderPk';");
  if (empty($Results[0]['folder_pk'])) { $DB->Action("ROLLBACK"); return; }

  /* Insert the ufile record */
  $Mode = (1<<27); // project
  $Results = $DB->Action("SELECT ufile_pk FROM ufile
	WHERE ufile_name = '$job_name' AND ufile_mode = '$Mode';");
  $ufilepk = $Results[0]['ufile_pk'];
  if (empty($ufilepk))
    {
    $DB->Action("INSERT INTO ufile (ufile_name,ufile_mode,ufile_container_fk) VALUES
	('$job_name','$Mode','$FolderPk');");
    $Results = $DB->Action("SELECT ufile_pk FROM ufile
	WHERE ufile_name = '$job_name' AND ufile_mode = '$Mode';");
    $ufilepk = $Results[0]['ufile_pk'];
    if (empty($ufilepk)) { $DB->Action("ROLLBACK"); return; }
    }
  
  /* Create the upload record */
  $Results = $DB->Action("SELECT upload_pk FROM upload
	WHERE ufile_fk = '$ufilepk'
	AND upload_filename = '$filename'
	AND upload_mode = '$UploadMode';");
  $uploadpk = $Results[0]['upload_pk'];
  if (empty($uploadpk))
    {
    $DB->Action("INSERT INTO upload
	(upload_desc,upload_filename,upload_mode,ufile_fk) VALUES
	('$desc','$filename','$UploadMode','$ufilepk');");
    $Results = $DB->Action("SELECT upload_pk FROM upload
	WHERE ufile_fk = '$ufilepk'
	AND upload_filename = '$filename'
	AND upload_mode = '$UploadMode';");
    $uploadpk = $Results[0]['upload_pk'];
    if (empty($uploadpk)) { $DB->Action("ROLLBACK"); return; }
    }

  /* Add the upload record to the folder */
  /** Mode == 2 means child_id is upload_pk **/
  $Results = $DB->Action("SELECT * FROM foldercontents
	WHERE parent_fk = '$FolderPk'
	AND foldercontents_mode = 2
	AND child_id = '$uploadpk';");
  if (empty($Results[0]['foldercontents_pk']))
    {
    $DB->Action("INSERT INTO foldercontents
	(parent_fk,foldercontents_mode,child_id) VALUES
	('$FolderPk',2,'$uploadpk');");
    $Results = $DB->Action("SELECT * FROM foldercontents
	WHERE parent_fk = '$FolderPk'
	AND foldercontents_mode = 2
	AND child_id = '$uploadpk';");
    if (empty($Results[0]['foldercontents_pk'])) { $DB->Action("ROLLBACK"); return; }
    }

  $DB->Action("COMMIT;");
  return($uploadpk);
} // JobAddUpload()

/************************************************************
 JobAddJob(): Insert a new job type (not a jobqueue item).
 NOTE: If the Job already exists, then it will not be added again.
 Returns the job_pk.
 ************************************************************/
function JobAddJob ($upload_pk, $job_name,
		    $priority=0,
		    $job_submitter="fossy@localhost",
		    $job_email_notify="fossy@localhost")
{
  global $DB;
  if (empty($DB)) { return; }

  $job_submitter = str_replace("'","''",$job_submitter);
  $job_email_notify = str_replace("'","''",$job_email_notify);
  $job_name = str_replace("'","''",$job_name);

  if (empty($upload_pk))
    {
    $SQLSelect = "SELECT job_pk FROM job WHERE job_upload_fk IS NULL AND job_name = '$job_name';";
    $SQLInsert = "INSERT INTO job
	(job_submitter,job_queued,job_priority,job_email_notify,job_name) VALUES
	('$job_submitter',now(),'$priority','$job_email_notify','$job_name');";
    }
  else
    {
    $SQLSelect = "SELECT job_pk FROM job WHERE job_upload_fk = '$upload_pk' AND job_name = '$job_name';";
    $SQLInsert = "INSERT INTO job
	(job_submitter,job_queued,job_priority,job_email_notify,job_name,job_upload_fk) VALUES
	('$job_submitter',now(),'$priority','$job_email_notify','$job_name','$upload_pk');";
    }

  $Results = $DB->Action($SQLSelect);
  $jobpk = $Results[0]['job_pk'];
  if (!empty($jobpk)) { return($jobpk); }

  $DB->Action($SQLInsert);
  $Results = $DB->Action($SQLSelect);
  $jobpk = $Results[0]['job_pk'];
  return($jobpk);
} // JobAddJob()

/************************************************************
 JobQueueAdd(): Insert a jobqueue item.
 $Depends is an ARRAY that lists one or more jobqueue_pk's.
 Returns the new jobqueue key.
 ************************************************************/
function JobQueueAdd ($job_pk, $jq_type, $jq_args, $jq_repeat, $jq_runonpfile, $Depends)
{
  global $DB;
  if (empty($DB)) { return; }

  $jq_args = str_replace("'","''",$jq_args); // protect variables
  $DB->Action("BEGIN");

  /* Make sure all dependencies exist */
  if (is_array($Depends))
    {
    foreach($Depends as $D)
	{
	if (empty($D)) { continue; }
	$Results = $DB->Action("SELECT jq_pk FROM jobqueue WHERE jq_pk = '$D';");
	if (empty($Results[0]['jq_pk'])) { $DB->Action("ROLLBACK;"); return; }
	}
    }

  /* Check if the job exists */
  $Results = $DB->Action("SELECT jq_pk FROM jobqueue
	WHERE jq_job_fk = '$job_pk' AND jq_type = '$jq_type' AND jq_args = '$jq_args';");
  $jqpk = $Results[0]['jq_pk'];
  if (empty($jqpk))
    {
    /* Add the job */
    $SQL = "INSERT INTO jobqueue ";
    $SQL .= "(jq_job_fk,jq_type,jq_args,jq_repeat,jq_runonpfile,jq_starttime,jq_endtime,jq_end_bits) VALUES ";
    $SQL .= "('$job_pk','$jq_type','$jq_args','$jq_repeat',";
    if (empty($jq_runonpfile)) { $SQL .= "NULL"; }
    else { $SQL .= "'$jq_runonpfile'"; }
    $SQL .= ",NULL,NULL,0);";
    $DB->Action($SQL);

    /* Find the job that was just added */
    $Results = $DB->Action("SELECT jq_pk FROM jobqueue
	WHERE jq_job_fk = '$job_pk' AND jq_type = '$jq_type' AND jq_args = '$jq_args';");
    $jqpk = $Results[0]['jq_pk'];
    if (empty($jqpk)) { $DB->Action("ROLLBACK;"); return; }
    }

  /* Add dependencies */
  if (is_array($Depends))
    {
    foreach($Depends as $D)
	{
	if (empty($D)) { continue; }
        $Results = $DB->Action("SELECT * FROM jobdepends
		WHERE jdep_jq_fk = '$jqpk'
		AND jdep_jq_depends_fk = '$D'
		AND jdep_depends_bits = 1;");
	if (empty($Results[0]['jdep_jq_fk']))
	  {
	  $DB->Action("INSERT INTO jobdepends
		(jdep_jq_fk,jdep_jq_depends_fk,jdep_depends_bits) VALUES
		('$jqpk','$D',1);");
          $Results = $DB->Action("SELECT * FROM jobdepends
		WHERE jdep_jq_fk = '$jqpk'
		AND jdep_jq_depends_fk = '$D'
		AND jdep_depends_bits = 1;");
	  }
	if (empty($Results[0]['jdep_jq_fk'])) { $DB->Action("ROLLBACK;"); return; }
	}
    }

  $DB->Action("COMMIT;");
  return($jqpk);
} // JobQueueAdd()

/************************************************************
 JobChangeStatus(): Mark the entire job as "reset", "fail", or
 "succeed".
 ************************************************************/

/************************************************************
 JobQueueChangeStatus(): Change the jobqueue item status.
 This can be "reset", "fail", "succeed".
 ************************************************************/

?>
