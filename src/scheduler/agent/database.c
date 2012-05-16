/* **************************************************************
Copyright (C) 2010 Hewlett-Packard Development Company, L.P.

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

/* local includes */
#include <agent.h>
#include <database.h>
#include <logging.h>
#include <scheduler.h>

/* std library includes */

/* other library includes */
#include <libfossdb.h>
#include <fcntl.h>
#include <sys/stat.h>
#include <sys/mman.h>

/* ************************************************************************** */
/* **** data and sql statements ********************************************* */
/* ************************************************************************** */

PGconn* db_conn = NULL;
char fossy_url[FILENAME_MAX];

/* email related sql */
const char* url_checkout = "\
    SELECT conf_value FROM sysconfig \
      WHERE variablename = 'FOSSologyURL';";

const char* select_upload_fk =" \
    SELECT job_upload_fk FROM job, jobqueue \
      WHERE jq_job_fk = job_pk AND jq_pk = %d;";

const char* upload_common = "\
    SELECT * FROM jobqueue \
      WHERE jq_job_fk IN ( \
        SELECT job_pk FROM job \
          WHERE job_upload_fk = %d \
      );";

const char* folder_name = "\
    SELECT folder_name FROM folder \
      WHERE folder_pk IN ( \
        SELECT parent_fk FROM foldercontents \
          WHERE foldercontents_mode = 2 AND child_id = ( \
            SELECT job_upload_fk FROM job, jobqueue \
              WHERE jq_job_fk = job_pk AND jq_pk = %d \
          ) \
      );";

const char* upload_name = "\
    SELECT upload_filename FROM upload \
      WHERE upload_pk = ( \
        SELECT job_upload_fk FROM job, jobqueue \
          WHERE jq_job_fk = job_pk AND jq_pk = %d \
      );";

const char* upload_pk = "\
    SELECT upload_pk FROM upload \
      WHERE upload_pk = ( \
        SELECT job_upload_fk FROM job, jobqueue \
          WHERE jq_job_fk = job_pk AND jq_pk = %d \
      );";

const char* jobsql_email = "\
    SELECT user_name, user_email, email_notify FROM users, upload \
      WHERE user_pk = user_fk AND upload_pk = %d;";

/* job queue related sql */
const char* basic_checkout = "\
    SELECT * FROM getrunnable()\
      LIMIT 10;";

const char* change_priority = "\
    SELECT job_priority FROM job \
      WHERE job_pk = %s;";

const char* jobsql_started = "\
    UPDATE jobqueue \
      SET jq_starttime = now(), \
          jq_schedinfo ='%s.%d', \
          jq_endtext = 'Started' \
      WHERE jq_pk = '%d';";

const char* jobsql_complete = "\
    UPDATE jobqueue \
      SET jq_endtime = now(), \
          jq_end_bits = jq_end_bits | 1, \
          jq_schedinfo = null, \
          jq_endtext = 'Completed' \
      WHERE jq_pk = '%d';";

const char* jobsql_restart = "\
    UPDATE jobqueue \
      SET jq_endtime = now(), \
          jq_schedinfo = null, \
          jq_endtext = 'Restart' \
      WHERE jq_pk = '%d';";

const char* jobsql_failed = "\
    UPDATE jobqueue \
      SET jq_endtime = now(), \
          jq_end_bits = jq_end_bits | 2, \
          jq_schedinfo = null, \
          jq_endtext = '%s' \
      WHERE jq_pk = '%d';";

const char* jobsql_processed = "\
    Update jobqueue \
      SET jq_itemsprocessed = %d \
      WHERE jq_pk = '%d';";

const char* jobsql_paused = "\
    UPDATE jobqueue \
      SET jq_endtext = 'Paused' \
      WHERE jq_pk = '%d';";

const char* jobsql_log = "\
    UPDATE jobqueue \
      SET jq_log = '%s' \
      WHERE jq_pk = '%d';";

const char* jobsql_priority = "\
    UPDATE job \
      SET job_priority = '%d' \
      WHERE job_pk IN ( \
        SELECT jq_job_fk FROM jobqueue \
        WHERE jq_pk = '%d');";

const char* jobsql_anyrunnable = "\
    SELECT * FROM getrunnable() \
      WHERE jq_job_fk = ( \
        SELECT jq_job_fk FROM jobqueue \
          WHERE jq_pk = %d \
      );";

const char* jobsql_jobendbits = "\
    SELECT jq_pk, jq_end_bits FROM jobqueue \
      WHERE jq_job_fk = ( \
        SELECT jq_job_fk FROM jobqueue \
          WHERE jq_pk = %d \
      );";

/* ************************************************************************** */
/* *** email notification                                                 *** */
/* ***                                                                    *** */
/* ***    There is no good location to put the code that performs the     *** */
/* ***    email notification. This is because it is one of the more       *** */
/* ***    database intensive actions that the scheduler performs, but     *** */
/* ***    also requires extensive network access. Since the database      *** */
/* ***    access is limited to database.c, this is where email            *** */
/* ***    notification lives.                                             *** */
/* ************************************************************************** */

static char*  email_subject = NULL;
static char*  email_header  = NULL;
static char*  email_footer  = NULL;
static char*  email_command = NULL;
static struct stat header_sb;
static struct stat footer_sb;
static GRegex* email_regex = NULL;

#define EMAIL_ERROR(...) {                       \
  WARNING(__VA_ARGS__);                          \
  email_notify = 0;                              \
  error = NULL; }

#define EMAIL_BUILD_CMD "%s -s '%s' %s"
#define DEFAULT_HEADER  "FOSSology scan complete\nmessage:\""
#define DEFAULT_FOOTER  "\""
#define DEFAULT_SUBJECT "FOSSology scan complete\n"

/**
 * Replaces the variables that are in the header and footer files. This is a
 * callback function that is passed to the glib function g_regex_replace_eval().
 * This reads what was matched by the regex and then appends the correct
 * information onto the GString that is passed to the function.
 *
 * Variables:
 *   $UPLOADNAME
 *   $BROESELINK
 *   $SHCEDULERLOG
 *   $UPLOADFOLDERNAME
 *   $JOBRESULT
 *   $DB.table.column [not implemented]
 *
 * @param match  the regex match that glib found
 * @param ret    the GString* that results should be appended to.
 * @param j      the job that this email relates to
 * @return       always FALSE so that g_regex_replace_eval() will continue
 */
static gboolean email_replace(const GMatchInfo* match, GString* ret, job j)
{
  gchar* m_str = g_match_info_fetch(match, 1);
  gchar* sql   = NULL;
  // TODO belongs to $DB if statement gchar* table, * column;
  PGresult* db_result;
  // TODO belongs to $DB if statement guint i;

  /* $UPLOADNAME
   *
   * Appends the name of the file that was uploaded and appends it to the output
   * string. This uses the job id to find the upload name.
   */
  if(strcmp(m_str, "UPLOADNAME") == 0)
  {
    sql = g_strdup_printf(upload_name, j->id);
    db_result = PQexec(db_conn, sql);

    if(PQresultStatus(db_result) != PGRES_TUPLES_OK)
    {
      g_string_append_printf(ret,
          "[ERROR: unable to select file name for upload %d]", j->id);
    }
    else
    {
      g_string_append(ret, PQgetvalue(db_result, 0, 0));
    }

    PQclear(db_result);
    g_free(sql);
  }

  /* $BROWSELINK
   *
   * Appends the url that will link to the upload in the browse menue of the user
   * interface.
   */
  else if(strcmp(m_str, "BROWSELINK") == 0)
  {
    sql = g_strdup_printf(upload_pk, j->id);
    db_result = PQexec(db_conn, sql);

    if(PQresultStatus(db_result) != PGRES_TUPLES_OK)
    {
      g_string_append_printf(ret,
          "[ERROR: unable to select file name for upload %d]", j->id);
    }
    else
    {
      g_string_append_printf(ret, "http://%s?mod=browse&upload=%s&show=detail",
          fossy_url, PQgetvalue(db_result, 0, 0));
    }

    PQclear(db_result);
    g_free(sql);
  }

  /* $JOBQUEUELINK
   *
   * Appends the url that will link to the job queue
   */
  else if(strcmp(m_str, "JOBQUEUELINK") == 0)
  {
    sql = g_strdup_printf(upload_pk, j->id);
    db_result = PQexec(db_conn, sql);

    if(PQresultStatus(db_result) != PGRES_TUPLES_OK)
    {
      g_string_append_printf(ret,
          "[ERROR: unable to select file name for upload %d]", j->id);
    }
    else
    {
      g_string_append_printf(ret, "http://%s?mod=showjobs&upload=%s",
          fossy_url, PQgetvalue(db_result, 0, 0));
    }

    PQclear(db_result);
    g_free(sql);
  }

  /* $SCHEDULERLOG
   *
   * Appends the url that will link to the log file produced by the agent.
   */
  else if(strcmp(m_str, "SCHEDULERLOG") == 0)
  {
    g_string_append_printf(ret, "http://%s?mod=showjobs&show=job&job=%d",
        fossy_url, j->id);
  }

  /* $UPLOADFOLDERNAME
   *
   * Appends the name of the folder that the upload was stored under.
   */
  else if(strcmp(m_str, "UPLOADFOLDERNAME") == 0)
  {
    sql = g_strdup_printf(folder_name, j->id);
    db_result = PQexec(db_conn, sql);

    if(PQresultStatus(db_result) != PGRES_TUPLES_OK)
    {
      g_string_append_printf(ret,
          "[ERROR: unable to select folder name for upload %d]", j->id);
    }
    else
    {
      g_string_append(ret, PQgetvalue(db_result, 0, 0));
    }

    PQclear(db_result);
    g_free(sql);
  }

  /* $JOBRESULT
   *
   * Appends if the job finished successfully or it it failed.
   */
  else if(strcmp(m_str, "JOBRESULT") == 0)
  {
    switch(j->status)
    {
      case JB_COMPLETE: g_string_append(ret, "COMPLETE"); break;
      case JB_FAILED:   g_string_append(ret, "FAILED");   break;
      default:
        g_string_append_printf(ret, "[ERROR: illegal job status \"%s\"]",
            job_status_strings[j->status]);
        break;
    }
  }

  /* $DB.table.column
   *
   * Appends a column of a table from the database to the resulting string.
   */
  else if(strcmp(m_str, "DB") == 0)
  {
    g_string_append(ret, "[NOT IMPLEMENTED]");
    /* TODO reimplement $DB variable
    table  = g_match_info_fetch(match, 3);
    column = g_match_info_fetch(match, 4);
    sql = g_strdup_printf("SELECT %s FROM %s;", column, table);
    db_result = PQexec(db_conn, sql);
    if(PQresultStatus(db_result) != PGRES_TUPLES_OK ||
        PQntuples(db_result) == 0 || PQnfields(db_result) == 0)
    {
      g_string_append_printf(ret, "[ERROR: unable to select %s.%s]", table, column);
    }
    else
    {
      g_string_append_printf(ret, "%s.%s[", table, column);
      for(i = 0; i < PQntuples(db_result); i++)
      {
        g_string_append(ret, PQgetvalue(db_result, i, 0));
        if(i != PQntuples(db_result) - 1)
          g_string_append(ret, " ");
      }
      g_string_append(ret, "]");
    }

    PQclear(db_result);
    g_free(sql);
    g_free(table);
    g_free(column);*/
  }

  g_free(m_str);
  return FALSE;
}

/**
 * @brief checks the database for the status of the job
 *
 * @param j  the job queue entry that will be checked for its job's status
 * @return 0: job is not finished, 1: job has finished, 2: job has failed
 */
static gint email_checkjobstatus(job j)
{
  gchar* sql;
  gint ret = 1;
  PGresult* db_result;
  int id, i;

  sql = g_strdup_printf(jobsql_anyrunnable, j->id);
  db_result = PQexec(db_conn, sql);
  if(PQresultStatus(db_result) != PGRES_TUPLES_OK)
  {
    PQ_ERROR(db_result, "unable to check job status for jq_pk %d", j->id);
    g_free(sql);
    PQclear(db_result);
    return 0;
  }

  /* check if all runnable jobs have been run */
  if(PQntuples(db_result) != 0)
  {
    ret = 0;
  }

  g_free(sql);
  PQclear(db_result);

  sql = g_strdup_printf(jobsql_jobendbits, j->id);
  db_result = PQexec(db_conn, sql);

  /* check for any jobs that are still running */
  for(i = 0; i < PQntuples(db_result) && ret; i++)
  {
    id = atoi(PQget(db_result, i, "jq_pk"));
    if(id != j->id && get_job(atoi(PQget(db_result, i, "jq_pk"))) != NULL)
    {
      ret = 0;
      break;
    }
  }

  /* check for any failed jobs */
  for(i = 0; i < PQntuples(db_result) && ret; i++)
  {
    if(atoi(PQget(db_result, i, "jq_end_bits")) == (1 << 1))
    {
      ret = 2;
      break;
    }
  }

  g_free(sql);
  PQclear(db_result);
  return ret;
}

/**
 * Sends an email notification that a particular job has completed correctly.
 * This compiles the email based upon the header file, footer file, and the job
 * that just completed.
 *
 * @param job  the job that just finished
 * @return void, no return
 */
static void email_notification(job j)
{
  PGresult* db_result;
  int j_id = j->id;;
  int upload_id;
  int status;
  char* val;
  char* final_cmd;
  char sql[1024];
  FILE* mail_io;
  GString* email_txt;
  job_status curr_status = j->status;

  if(is_meta_special(j->agent_type, SAG_NOEMAIL) ||
      !(status = email_checkjobstatus(j)))
    return;

  sprintf(sql, select_upload_fk, j_id);
  db_result = PQexec(db_conn, sql);
  if(PQresultStatus(db_result) != PGRES_TUPLES_OK || PQntuples(db_result) == 0)
  {
    PQ_ERROR(db_result, "unable to select the upload id for job %d", j_id);
    return;
  }

  upload_id = atoi(PQgetvalue(db_result, 0, 0));
  PQclear(db_result);

  sprintf(sql, upload_common, upload_id);
  db_result = PQexec(db_conn, sql);
  if(PQresultStatus(db_result) != PGRES_TUPLES_OK)
  {
    PQ_ERROR(db_result, "unable to check common uploads to job %d", j_id);
    return;
  }

  sprintf(sql, jobsql_email, upload_id);
  db_result = PQexec(db_conn, sql);
  if(PQresultStatus(db_result) != PGRES_TUPLES_OK || PQntuples(db_result) == 0)
  {
    PQ_ERROR(db_result, "unable to access email info for job %d", j_id);
    return;
  }

  if(PQget(db_result, 0, "email_notify")[0] == 'y')
  {
    if(status == 2)
      j->status = JB_FAILED;

    email_txt = g_string_new("");
    g_string_append(email_txt, email_header);
    g_string_append(email_txt, job_message(j));
    g_string_append(email_txt, email_footer);

    if(email_regex != NULL)
    {
      val = g_regex_replace_eval(email_regex, email_txt->str, email_txt->len,
          0, 0, (GRegexEvalCallback)email_replace, j, NULL);
    }
    else
    {
      val = email_txt->str;
    }

    final_cmd = g_strdup_printf(EMAIL_BUILD_CMD, email_command, email_subject,
        PQget(db_result, 0, "user_email"));

    if((mail_io = popen(final_cmd, "w")) != NULL)
    {
      fprintf(mail_io, "%s", val);

      putc(-1,   mail_io);
      putc('\n', mail_io);

      pclose(mail_io);
    }
    else
    {
      WARNING("Unable to spawn email notification process: '%s'.\n",
          email_command);
    }

    j->status = curr_status;
    if(email_regex != NULL)
      g_free(val);
    g_free(final_cmd);
    g_string_free(email_txt, TRUE);
  }

  PQclear(db_result);
}

/**
 * Loads information about the email that will be sent for job notifications.
 * This loads the header and footer configuration files, loads the subject and
 * client info, and compiles the regex that is used to replace variables in the
 * header and footer files.
 *
 * @return void, no return
 */
void email_load()
{
  int email_notify, fd;
	char fname[FILENAME_MAX];
	GError* error = NULL;

	if(email_header && strcmp(email_header, DEFAULT_HEADER) != 0)
	  munmap(email_header, header_sb.st_size);
	if(email_footer && strcmp(email_footer, DEFAULT_FOOTER) != 0)
	  munmap(email_footer, footer_sb.st_size);
	if(email_subject && strcmp(email_subject, DEFAULT_SUBJECT) != 0)
	  g_free(email_subject);

	/* load the header */
	email_notify = 1;
	snprintf(fname, FILENAME_MAX, "%s/%s", sysconfigdir,
	    fo_config_get(sysconfig, "EMAILNOTIFY", "header", &error));
	if(error && error->code == fo_missing_group)
	  EMAIL_ERROR("email notification setting group \"[EMAILNOTIFY]\" missing. Using defaults");
	if(error && error->code == fo_missing_key)
	  EMAIL_ERROR("email notification setting key \"header\" missing. Using default header");
	if(email_notify && (fd = open(fname, O_RDONLY)) == -1)
	  EMAIL_ERROR("unable to open file for email header: %s", fname);
	if(email_notify && fstat(fd, &header_sb) == -1)
	  EMAIL_ERROR("unable to fstat email header: %s", fname);
	if(email_notify && (email_header = mmap(NULL, header_sb.st_size, PROT_READ,
	    MAP_SHARED, fd, 0)) == MAP_FAILED)
	  EMAIL_ERROR("unable to mmap email header: %s", fname);
	if(!email_notify)
	  email_header = DEFAULT_HEADER;

	/* load the footer */
	email_notify = 1;
	snprintf(fname, FILENAME_MAX, "%s/%s", sysconfigdir,
	      fo_config_get(sysconfig, "EMAILNOTIFY", "footer", &error));
	if(error)
	  email_notify = 0;
	if(error && error->code == fo_missing_key)
	  EMAIL_ERROR("email notification setting key \"footer\" missing. Using default footer");
	if(email_notify && (fd = open(fname, O_RDONLY)) == -1)
	  EMAIL_ERROR("unable to open file for email footer: %s", fname);
	if(email_notify && fstat(fd, &footer_sb) == -1)
	  EMAIL_ERROR("unable to fstat email footer: %s", fname);
	if(email_notify && (email_footer = mmap(NULL, footer_sb.st_size, PROT_READ,
	    MAP_SHARED, fd, 0)) == MAP_FAILED)
	  EMAIL_ERROR("unable to mmap email footer: %s", fname);
	if(!email_notify)
	  email_footer = DEFAULT_FOOTER;
	error = NULL;

	/* load the email_subject */
	email_subject = fo_config_get(sysconfig, "EMAILNOTIFY", "email_subject", &error);
	if(error)
	  email_subject = DEFAULT_SUBJECT;
	if(error && error->code == fo_missing_key)
	  EMAIL_ERROR("email notification setting key \"email_subject\" missing. Using default subject");
	email_subject = g_strdup(email_subject);
	error = NULL;

	/* load the client */
	email_notify = 1;
	email_command = fo_config_get(sysconfig, "EMAILNOTIFY", "client", &error);
	if(error)
	  email_command = "/usr/bin/mailx";
	if(error && error->code == fo_missing_key)
	  EMAIL_ERROR("email notification setting key \"client\" missing. Using default client");
	error = NULL;

	/* create the regex for the email
	 * This regex should find:
	 *   1. A '$' followed by any combination of capital letters or underscore
	 *   2. A '$' followed by any combination of capital letters or underscore,
	 *      followed by a '.' followed by alphabetic characters or underscore,
	 *      followed by a '.' followed by alphabetic characters or underscore
	 *
	 * Examples:
	 *   $HELLO           -> matches
	 *   $SIMPLE_NAME     -> matches
	 *   $DB.table.column -> matches
	 *   $bad             -> does not match
	 *   $DB.table        -> does not match
	 */
	if(email_regex == NULL)
	  email_regex = g_regex_new("\\$([A-Z_]*)(\\.([a-zA-Z_]*)\\.([a-zA-Z_]*))?",
	      0, 0, &error);
	if(error)
	{
	  EMAIL_ERROR("unable to build email regular expression: %s", error->message);
	  email_regex = NULL;
	}
}

/* ************************************************************************** */
/* **** local functions ***************************************************** */
/* ************************************************************************** */

/**
 * Initializes any one-time attributes relating to the database. Currently this
 * includes creating the db connection and checking the URL of the fossology
 * instance out of the db.
 */
void database_init()
{
  PGresult* db_result;
  char *DBConfFile = NULL;  /* use default Db.conf */
  char *ErrorBuf;

  /* create the connection to the database */
  db_conn = fo_dbconnect(DBConfFile, &ErrorBuf);
  memset(fossy_url, '\0', sizeof(fossy_url));

  /* get the url for the fossology instance */
  db_result = PQexec(db_conn, url_checkout);
  if(PQresultStatus(db_result) == PGRES_TUPLES_OK && PQntuples(db_result) != 0)
  {
    strcpy(fossy_url, PQgetvalue(db_result, 0, 0));
  }
  PQclear(db_result);

  email_header = NULL;
  email_footer = NULL;
  email_subject = NULL;
  email_regex = NULL;

  email_load();
}

/**
 * close the connection to the database
 */
void database_destroy()
{
  PQfinish(db_conn);
  db_conn = NULL;
}

/* ************************************************************************** */
/* **** event and functions ************************************************* */
/* ************************************************************************** */

void database_exec_event(char* sql)
{
  PGresult* db_result = PQexec(db_conn, sql);

  if(PQresultStatus(db_result) != PGRES_COMMAND_OK)
    PQ_ERROR(db_result, "failed to perform dtabase exec: %s", sql);

  g_free(sql);
}

/**
 * Resets the any jobs in the job queue that are not completed. This is to make
 * sure that any jobs that were running with the scheduler shutdown are run correctly
 * when it starts up again.
 */
void database_reset_queue()
{
  PGresult* db_result = PQexec(db_conn, "\
      UPDATE jobqueue \
        SET jq_starttime=null, \
            jq_endtext=null, \
            jq_schedinfo=null\
        WHERE jq_endtime is NULL;");

  if(PQresultStatus(db_result) != PGRES_COMMAND_OK)
    PQ_ERROR(db_result, "failed to reset job queue");
}

/**
 * Checks the job queue for any new entries.
 *
 * @param unused
 */
void database_update_event(void* unused)
{
  /* locals */
  PGresult* db_result;
  PGresult* pri_result;
  int i, j_id;
  char sql[512];
  char* value, * type, * pfile, * parent;
  job j;

  if(closing)
  {
    WARNING("scheduler is closing, will not perform database update");
    return;
  }

  /* make the database query */

  db_result = PQexec(db_conn, basic_checkout);
  if(PQresultStatus(db_result) != PGRES_TUPLES_OK)
  {
    PQ_ERROR(db_result, "database update failed on call to PQexec");
    return;
  }

  V_SPECIAL("DB: retrieved %d entries from the job queue\n", PQntuples(db_result));
  for(i = 0; i < PQntuples(db_result); i++)
  {
    /* start by checking that the job hasn't already been grabbed */
    if(get_job(j_id = atoi(PQget(db_result, i, "jq_pk"))) != NULL)
      continue;

    /* get relevant values out of the job queue */
    parent =      PQget(db_result, i, "jq_job_fk");
    type   =      PQget(db_result, i, "jq_type");
    pfile  =      PQget(db_result, i, "jq_runonpfile");
    value  =      PQget(db_result, i, "jq_args");

    V_DATABASE("DB: jq_pk[%d] added:\n   jq_type = %s\n   jq_runonpfile = %d\n   jq_args = %s\n",
        j_id, type, (pfile != NULL && pfile[0] != '\0'), value);

    /* check if this is a command */
    if(strcmp(type, "command") == 0)
    {
      WARNING("DB: commands in the job queue not implemented, using the interface api instead");
      continue;
    }

    sprintf(sql, change_priority, parent);
    pri_result = PQexec(db_conn, sql);
    if(PQresultStatus(pri_result) != PGRES_TUPLES_OK)
    {
      PQ_ERROR(pri_result, "database update failed on call to PQexec");
      continue;
    }

    j = job_init(type, j_id, atoi(PQgetvalue(pri_result, 0, 0)));
    job_set_data(j, value, (pfile && pfile[0] != '\0'));

    PQclear(pri_result);
  }

  PQclear(db_result);
}

/**
 * Change the status of a job in the database.
 *
 * @param j_id id number of the relevant job
 * @param status the new status of the job
 */
void database_update_job(job j, job_status status)
{
  /* locals */
  gchar* sql = NULL;
  PGresult* db_result;
  int j_id = j->id;
  char* message = (j->message == NULL) ? "Failed": j->message;

  /* check how to update database */
  switch(status)
  {
    case JB_CHECKEDOUT:
      break;
    case JB_STARTED:
      sql = g_strdup_printf(jobsql_started, "localhost", getpid(), j_id);
      break;
    case JB_COMPLETE:
      sql = g_strdup_printf(jobsql_complete, j_id);
      break;
    case JB_RESTART:
      sql = g_strdup_printf(jobsql_restart, j_id);
      break;
    case JB_FAILED:
      sql = g_strdup_printf(jobsql_failed, message, j_id);
      break;
    case JB_SCH_PAUSED: case JB_CLI_PAUSED:
      sql = g_strdup_printf(jobsql_paused, j_id);
      break;
    case JB_ERROR:
      sql = g_strdup_printf(jobsql_failed, j_id);
      break;
  }

  /* update the database job queue */
  db_result = PQexec(db_conn, sql);
  if(sql != NULL && PQresultStatus(db_result) != PGRES_COMMAND_OK)
    PQ_ERROR(db_result, "failed to update job status in job queue");

  if(status == JB_COMPLETE || status == JB_FAILED || status == JB_ERROR)
    email_notification(j);

  g_free(sql);
}

/**
 * Updates teh number of items that a job queue entry has processed.
 *
 * @param j_id the id number of the job queue entry
 * @param num the number of items processed in total
 */
void database_job_processed(int j_id, int num)
{
  gchar* sql = NULL;

  sql = g_strdup_printf(jobsql_processed, num, j_id);
  event_signal(database_exec_event, sql);
}

/**
 * enters the name of the log file for a job into the database
 *
 * @param j_id the id number for the relevant job
 * @param log_name the name of the log file
 */
void database_job_log(int j_id, char* log_name)
{
  gchar* sql = NULL;

  sql = g_strdup_printf(jobsql_log, log_name, j_id);
  event_signal(database_exec_event, sql);
}

/**
 * Changes the priority of a job queue entry in the database.
 *
 * @param j         the job to change the priority for
 * @param priority  the new priority of the job
 */
void database_job_priority(job j, int priority)
{
  gchar* sql = NULL;
  PGresult* db_result;

  sql = g_strdup_printf(jobsql_priority, priority, j->id);
  db_result = PQexec(db_conn, sql);
  if(sql != NULL && PQresultStatus(db_result) != PGRES_COMMAND_OK)
    PQ_ERROR(db_result, "failed to change job queue entry priority");

  g_free(sql);
}
