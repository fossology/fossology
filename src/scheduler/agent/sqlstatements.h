/* **************************************************************
Copyright (C) 2012 Hewlett-Packard Development Company, L.P.

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
 ************************************************************** */

/**
 * @file sqlstatemnts.h
 *
 * This file declares all the sql statemtns used in the scheduler. This should
 * only be included once in the databace.c file.
 */


#ifndef SQLSTATEMENTS_H
#define SQLSTATEMENTS_H

const char* check_scheduler_tables =
    " SELECT column_name FROM information_schema.columns "
    "   WHERE table_catalog = '%s' "
    "     AND table_schema = 'public' "
    "     AND table_name = ";

const char* url_checkout =
    " SELECT conf_value FROM sysconfig "
    "   WHERE variablename = 'FOSSologyURL';";

const char* select_upload_fk =
    " SELECT job_upload_fk FROM job, jobqueue "
    "   WHERE jq_job_fk = job_pk "
    "     AND jq_pk = %d;";

const char* upload_common =
    " SELECT * FROM jobqueue "
    "   LEFT JOIN job ON jq_job_fk = job_pk"
    "   WHERE job.job_upload_fk = %d;";

const char* folder_name =
    " SELECT folder_name FROM folder "
    "   LEFT JOIN foldercontents ON folder_pk = foldercontents.parent_fk "
    "   LEFT JOIN job ON child_id = job_upload_fk "
    "   LEFT JOIN jobqueue ON jq_job_fk = job_pk "
    "   WHERE jq_pk = %d;";

const char* upload_name =
    " SELECT upload_filename FROM upload "
    "   LEFT JOIN job ON upload_pk = job_upload_fk "
    "   LEFT JOIN jobqueue ON jq_job_fk = job_pk "
    "   WHERE jq_pk = %d;";

const char* upload_pk =
    " SELECT upload_fk, uploadtree_pk FROM uploadtree "
    "   LEFT JOIN job ON upload_fk = job_upload_fk "
    "   LEFT JOIN jobqueue ON jq_job_fk = job_pk "
    "   WHERE parent IS NULL"
    "     AND jq_pk = %d;";

const char* jobsql_email =
    " SELECT user_name, user_email, email_notify FROM users, upload "
    "   WHERE user_pk = user_fk "
    "     AND upload_pk = %d;";

const char* jobsql_email_job =
    " SELECT user_name, user_email, email_notify FROM users, job, jobqueue "
    "   WHERE user_pk = job_user_fk AND job_pk = jq_job_fk "
    "     AND jq_pk = %d;";

/* job queue related sql */
const char* basic_checkout =
    " SELECT * FROM getrunnable() "
    "   LIMIT 10;";

const char* jobsql_information =
    " SELECT user_pk, job_priority, job_group_fk as group_pk FROM users "
    "   LEFT JOIN job ON job_user_fk = user_pk "
    "   WHERE job_pk = '%s';";

const char* jobsql_started =
    " UPDATE jobqueue "
    "   SET jq_starttime = now(), "
    "       jq_schedinfo ='%s.%d', "
    "       jq_endtext = 'Started' "
    "   WHERE jq_pk = '%d';";

const char* jobsql_complete =
    " UPDATE jobqueue "
    "   SET jq_endtime = now(), "
    "       jq_end_bits = jq_end_bits | 1, "
    "       jq_schedinfo = null, "
    "       jq_endtext = 'Completed' "
    "   WHERE jq_pk = '%d';";

const char* jobsql_restart =
    " UPDATE jobqueue "
    "   SET jq_endtext = 'Restarted', "
    "       jq_starttime = ( CASE "
    "         WHEN jq_starttime = CAST('9999-12-31' AS timestamp with time zone) "
    "         THEN null "
    "         ELSE jq_starttime "
    "       END ) "
    "   WHERE jq_pk = '%d';";

const char* jobsql_failed =
    " UPDATE jobqueue "
    "   SET jq_endtime = now(), "
    "       jq_end_bits = jq_end_bits | 2, "
    "       jq_schedinfo = null, "
    "       jq_endtext = '%s' "
    "   WHERE jq_pk = '%d';";

const char* jobsql_processed =
    " Update jobqueue "
    "   SET jq_itemsprocessed = %d "
    "   WHERE jq_pk = '%d';";

const char* jobsql_paused =
    " UPDATE jobqueue "
    "   SET jq_endtext = 'Paused', "
    "       jq_starttime = ( CASE "
    "         WHEN jq_starttime IS NULL "
    "         THEN CAST('9999-12-31' AS timestamp with time zone) "
    "         ELSE jq_starttime "
    "       END ) "
    "   WHERE jq_pk = '%d';";

const char* jobsql_log =
    " UPDATE jobqueue "
    "   SET jq_log = '%s' "
    "   WHERE jq_pk = '%d';";

const char* jobsql_priority =
    " UPDATE job "
    "   SET job_priority = '%d' "
    "   WHERE job_pk IN ( "
    "     SELECT jq_job_fk FROM jobqueue "
    "     WHERE jq_pk = '%d');";

const char* jobsql_anyrunnable =
    " SELECT * FROM getrunnable() "
    "   WHERE jq_job_fk = ( "
    "     SELECT jq_job_fk FROM jobqueue "
    "       WHERE jq_pk = %d "
    "   );";

const char* jobsql_jobendbits =
    " SELECT jq_pk, jq_end_bits FROM jobqueue "
    "   WHERE jq_job_fk = ( "
    "     SELECT jq_job_fk FROM jobqueue "
    "       WHERE jq_pk = %d "
    "   );";

const char* jobsql_resetqueue =
    "UPDATE jobqueue "
    "  SET jq_starttime=null, "
    "      jq_endtext=null, "
    "      jq_schedinfo=null "
    "  WHERE jq_endtime is NULL;";

#endif /* SQLSTATEMENTS_H */

