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
Notes for creating common-jobs

To add a job:

JobInsert($upload_pk,$job_name,$depends=-1,$priority=0,$job_email_notify='fossy@localhost')
  $depends = $jobpk
  This will set job_depends if $depends != -1
  Also need all of the values for jobqueue table:
	jq_type,jq_args,jq_runonpfile

 ************************************************************/

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
  $Name = preg_replace("/'/","''",$job_name); // SQL taint string
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
  $Name = preg_replace("/'/","''",$job_name); // SQL taint string
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
  $Name = preg_replace("/'/","''",$job_name); // SQL taint string
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
  $Name = preg_replace("/'/","''",$job_name); // SQL taint string
  $Submitter = preg_replace("/'/","''",$job_submitter); // SQL taint string
  $Notify = preg_replace("/'/","''",$job_email_notify); // SQL taint string
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
 JobQueueAdd(): Insert a jobqueue item.
 $Depends is an ARRAY that lists one or more jobqueue_pk's.
 Returns the new jobqueue key.
 ************************************************************/
function JobQueueAdd ($upload_pk, $job_name,
		      $jq_type, $jq_args, $jq_repeat, $jq_runonpfile, $Depends)
{
  global $DB;
  if (empty($DB)) { return; }

  /* Find the job key */
  $Results = $DB->Action("SELECT job_pk FROM job
	WHERE job_name = '$job_name'
	AND job_upload_fk = '$upload_pk';");
  $job_pk = $Results[0]['job_pk'];
  if (empty($job_pk)) { return; }

  /* Make sure all dependencies exist */
  if (is_array($Depends))
    {
    foreach($Depends as $D)
	{
	if (empty($D)) { continue; }
	$Results = $DB->Action("SELECT jq_pk FROM jobqueue WHERE jq_pk = '$D';");
	if (empty($Results[0]['jq_pk'])) { return; }
	}
    }

  /* Add the job */
  $jq_args = preg_replace("/'/","''",$jq_args); // protect variables
  $DB->Action("INSERT INTO jobqueue
	(jq_job_fk,jq_type,jq_args,jq_repeat,jq_runonpfile) VALUES
	('$job_pk','$jq_type','$jq_args','$jq_repeat','$jq_runonpfile');");

  /* Find the job that was just added */
  $Results = $DB->Action("SELECT jq_pk FROM jobqueue
	WHERE jq_job_fk = '$job_pk' AND jq_type = 'jq_type';");
  $jqpk = $Results[0]['jq_pk'];
  if (empty($jqpk)) { return; }

  /* Add dependencies */
  if (is_array($Depends))
    {
    foreach($Depends as $D)
	{
	if (empty($D)) { continue; }
	$DB->Action("INSERT INTO jobdepends
		(jdep_jq_fk,jdep_jq_depends_fk,jdep_depends_bits) VALUES
		('$jqpk','$D',1);");
	if (empty($Results[0]['jq_pk'])) { return; }
	}
    }

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
