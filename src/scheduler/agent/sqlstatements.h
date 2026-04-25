/*
 SPDX-FileCopyrightText: © 2012 Hewlett-Packard Development Company, L.P.
 SPDX-FileCopyrightText: © 2015, 2018 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * @file
 *
 * This file declares all the sql statements used in the scheduler. This should
 * only be included once in the databace.c file.
 */


#ifndef SQLSTATEMENTS_H
#define SQLSTATEMENTS_H

/**
 * Check column names for a given table
 */
const char* check_scheduler_tables =
    " SELECT column_name FROM information_schema.columns "
    "   WHERE table_catalog = '%s' "
    "     AND table_schema = 'public' "
    "     AND table_name = ";

/**
 * Get the FOSSologyURL from sysconfig table
 */
const char* url_checkout =
    " SELECT conf_value FROM sysconfig "
    "   WHERE variablename = 'FOSSologyURL';";

/**
 * For a given job queue id, get the upload id
 */
const char* select_upload_fk =
    " SELECT j.job_upload_fk FROM job j"
    "   INNER JOIN jobqueue jq ON jq.jq_job_fk = j.job_pk"
    "   WHERE jq.jq_pk = %d;";

/**
 * For a given upload id, get job and job queue
 */
const char* upload_common =
    " SELECT * FROM jobqueue "
    "   LEFT JOIN job ON jq_job_fk = job_pk"
    "   WHERE job.job_upload_fk = %d;";

/**
 * For a given job id, get the folder name and folder id
 */
const char* folder_name =
    " SELECT folder_name, folder_pk FROM folder "
    "   LEFT JOIN foldercontents ON folder_pk = foldercontents.parent_fk "
    "   LEFT JOIN job ON child_id = job_upload_fk "
    "   LEFT JOIN jobqueue ON jq_job_fk = job_pk "
    "   WHERE jq_pk = %d;";

/**
 * For a given folder id, get the folder name and folder id of the immediate
 * parent
 */
const char* parent_folder_name =
    " SELECT folder_name, folder_pk FROM folder "
    "   INNER JOIN foldercontents ON folder_pk=foldercontents.parent_fk "
    "   WHERE child_id = %d AND foldercontents_mode = 1;";

/**
 * For a given job id, get the upload file name
 */
const char* upload_name =
    " SELECT upload_filename FROM upload "
    "   LEFT JOIN job ON upload_pk = job_upload_fk "
    "   LEFT JOIN jobqueue ON jq_job_fk = job_pk "
    "   WHERE jq_pk = %d;";

/**
 * For a given job id, get the upload id and upload tree
 */
const char* upload_pk =
    " SELECT upload_fk, uploadtree_pk FROM uploadtree "
    "   LEFT JOIN job ON upload_fk = job_upload_fk "
    "   LEFT JOIN jobqueue ON jq_job_fk = job_pk "
    "   WHERE parent IS NULL"
    "     AND jq_pk = %d;";

/**
 * For a given upload id, get the user's name, email and email preference
 */
const char* jobsql_email =
    " SELECT user_name, user_email, email_notify FROM users, upload "
    "   WHERE user_pk = user_fk "
    "     AND upload_pk = %d;";

/**
 * For a given job id, get the user's name, email and email preference
 */
const char* jobsql_email_job =
    " SELECT user_name, user_email, email_notify FROM users, job, jobqueue "
    "   WHERE user_pk = job_user_fk AND job_pk = jq_job_fk "
    "     AND jq_pk = %d;";

/* job queue related sql */
/**
 * Fetch the next batch of schedulable jobs with user and priority in one round trip.
 * INNER JOIN on users drops orphan jobs (no matching user). NOT EXISTS uses explicit
 * INNER JOIN on jobdepends index so the planner avoids a full cross-join scan.
 */
const char* basic_checkout =
    " SELECT jq.jq_pk, jq.jq_job_fk, jq.jq_type, jq.jq_host,"
    "        jq.jq_runonpfile, jq.jq_args, jq.jq_cmd_args,"
    "        u.user_pk, j.job_priority, j.job_group_fk AS group_pk"
    " FROM jobqueue jq"
    " INNER JOIN job j     ON j.job_pk  = jq.jq_job_fk"
    " INNER JOIN users u   ON u.user_pk = j.job_user_fk"
    " WHERE jq.jq_starttime IS NULL AND jq.jq_end_bits < 2"
    "   AND NOT EXISTS("
    "     SELECT 1 FROM jobdepends jd"
    "     INNER JOIN jobqueue dep ON dep.jq_pk = jd.jdep_jq_depends_fk"
    "     WHERE jd.jdep_jq_fk = jq.jq_pk"
    "       AND NOT (dep.jq_endtime IS NOT NULL AND dep.jq_end_bits < 2)"
    "   )"
    " ORDER BY j.job_priority DESC"
    " LIMIT 10;";

/**
 * Mark the given job id as started
 */
const char* jobsql_started =
    " UPDATE jobqueue "
    "   SET jq_starttime = now(), "
    "       jq_schedinfo ='%s.%d', "
    "       jq_endtext = 'Started' "
    "   WHERE jq_pk = %d;";

/**
 * Mark the given job id as completed
 */
const char* jobsql_complete =
    " UPDATE jobqueue "
    "   SET jq_endtime = now(), "
    "       jq_end_bits = jq_end_bits | 1, "
    "       jq_schedinfo = null, "
    "       jq_endtext = 'Completed' "
    "   WHERE jq_pk = %d;";

/**
 * Mark the given job id as restarted
 */
const char* jobsql_restart =
    " UPDATE jobqueue "
    "   SET jq_endtext = 'Restarted', "
    "       jq_starttime = ( CASE "
    "         WHEN jq_starttime = CAST('9999-12-31' AS timestamp with time zone) "
    "         THEN null "
    "         ELSE jq_starttime "
    "       END ) "
    "   WHERE jq_pk = %d;";

/**
 * Mark the given job id as failed
 */
const char* jobsql_failed =
    " UPDATE jobqueue "
    "   SET jq_endtime = now(), "
    "       jq_end_bits = jq_end_bits | 2, "
    "       jq_schedinfo = null, "
    "       jq_endtext = '%s' "
    "   WHERE jq_pk = %d;";

/**
 * Update the items processed for the given job id
 */
const char* jobsql_processed =
    " Update jobqueue "
    "   SET jq_itemsprocessed = %d "
    "   WHERE jq_pk = %d;";

/**
 * Mark the given job id as paused
 */
const char* jobsql_paused =
    " UPDATE jobqueue "
    "   SET jq_endtext = 'Paused', "
    "       jq_starttime = ( CASE "
    "         WHEN jq_starttime IS NULL "
    "         THEN CAST('9999-12-31' AS timestamp with time zone) "
    "         ELSE jq_starttime "
    "       END ) "
    "   WHERE jq_pk = %d;";

/**
 * Get the log location for the given job id
 */
const char* jobsql_log =
    " UPDATE jobqueue "
    "   SET jq_log = '%s' "
    "   WHERE jq_pk = %d;";

/**
 * Change the priority of the given job id
 */
const char* jobsql_priority =
    " UPDATE job "
    "   SET job_priority = %d "
    "   WHERE job_pk IN ( "
    "     SELECT jq_job_fk FROM jobqueue "
    "     WHERE jq_pk = %d);";

/**
 * Check whether any runnable queue entries remain for the job containing jq_pk.
 * LIMIT 1 short-circuits after the first match; SELECT 1 avoids projecting columns.
 */
const char* jobsql_anyrunnable =
    " SELECT 1 FROM jobqueue jq"
    " WHERE jq.jq_starttime IS NULL AND jq.jq_end_bits < 2"
    "   AND NOT EXISTS("
    "     SELECT 1 FROM jobdepends jd"
    "     INNER JOIN jobqueue dep ON dep.jq_pk = jd.jdep_jq_depends_fk"
    "     WHERE jd.jdep_jq_fk = jq.jq_pk"
    "       AND NOT (dep.jq_endtime IS NOT NULL AND dep.jq_end_bits < 2)"
    "   )"
    "   AND jq.jq_job_fk = (SELECT jq_job_fk FROM jobqueue WHERE jq_pk = %d)"
    " LIMIT 1;";

/**
 * Get jq_pk and jq_end_bits for all queue entries sharing the same parent job as jq_pk.
 * Self-join lets the planner use the jq_job_fk index on both sides.
 */
const char* jobsql_jobendbits =
    " SELECT jq2.jq_pk, jq2.jq_end_bits"
    " FROM jobqueue jq1"
    " INNER JOIN jobqueue jq2 ON jq2.jq_job_fk = jq1.jq_job_fk"
    " WHERE jq1.jq_pk = %d;";

/**
 * Reset the job queue for jobs with end time as NULL
 */
const char* jobsql_resetqueue =
    "UPDATE jobqueue "
    "  SET jq_starttime=null, "
    "      jq_endtext=null, "
    "      jq_schedinfo=null "
    "  WHERE jq_endtime is NULL;";

/**
 * Get all queue entries sharing the same parent job as jq_pk (self-join on jq_job_fk).
 */
const char* jobsql_jobinfo =
    " SELECT jq2.*"
    " FROM jobqueue jq1"
    " INNER JOIN jobqueue jq2 ON jq2.jq_job_fk = jq1.jq_job_fk"
    " WHERE jq1.jq_pk = %d;";

/**
 * Get the SMTP (email) values for the sysconfig table
 */
const char* smtp_values =
    " SELECT conf_value, variablename FROM sysconfig "
    "   WHERE variablename LIKE 'SMTP%';";

#endif /* SQLSTATEMENTS_H */
