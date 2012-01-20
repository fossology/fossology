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
    SELECT conf_value FROM sysconfig\
      WHERE variablename = 'FOSSology_URL';";

const char* select_upload_fk =" \
    SELECT job_upload_fk FROM job, jobqueue \
      WHERE jq_job_fk = job_pk and jq_pk = %d;";

const char* upload_common = "\
    SELECT * FROM jobqueue \
      WHERE jq_job_fk in ( \
        SELECT job_pk FROM job \
          WHERE job_upload_fk = %d \
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

/* ************************************************************************** */
/* **** email notification ************************************************** */
/* ************************************************************************** */

char*  subject;
char*  header = NULL;
char*  footer = NULL;
char* mail_argv[3];
struct stat header_sb;
struct stat footer_sb;
GRegex* email_regex = NULL;

#define EMAIL_ERROR(...) {                       \
  WARNING(__VA_ARGS__);                          \
  email_notify = 0;                              \
  error = NULL; }

#define DEFAULT_HEADER "FOSSology scan complete\nmessage:\""
#define DEFAULT_FOOTER "\""
#define DEFAULT_SUBJECT "FOSSology scan complete\n"

/**
 * TODO
 */
void email_load()
{
  int email_notify, fd;
	char fname[FILENAME_MAX];
	GError* error = NULL;

	if(header && strcmp(header, DEFAULT_HEADER) != 0)
	  munmap(header, header_sb.st_size);
	if(footer && strcmp(footer, DEFAULT_FOOTER) != 0)
	  munmap(footer, footer_sb.st_size);

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
	if(email_notify && (header = mmap(NULL, header_sb.st_size, PROT_READ,
	    MAP_SHARED, fd, 0)) == MAP_FAILED)
	  EMAIL_ERROR("unable to mmap email header: %s", fname);
	if(!email_notify)
	  header = DEFAULT_HEADER;

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
	if(email_notify && (footer = mmap(NULL, footer_sb.st_size, PROT_READ,
	    MAP_SHARED, fd, 0)) == MAP_FAILED)
	  EMAIL_ERROR("unable to mmap email footer: %s", fname);
	if(!email_notify)
	  footer = DEFAULT_FOOTER;
	error = NULL;

	/* load the subject */
	subject = fo_config_get(sysconfig, "EMAILNOTIFY", "subject", &error);
	if(error)
	  subject = DEFAULT_SUBJECT;
	if(error && error->code == fo_missing_key)
	  EMAIL_ERROR("email notification setting key \"subject\" missing. Using default subject");
	if(subject[strlen(subject)] != '\n')
	  subject = g_strdup_printf("%s\n", subject);
	error = NULL;

	/* load the client */
	email_notify = 1;
	mail_argv[0] = fo_config_get(sysconfig, "EMAILNOTIFY", "client", &error);
	mail_argv[1] = NULL;
	mail_argv[2] = NULL;
	if(error)
	  mail_argv[0] = "/usr/bin/mail";
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

/**
 * TODO
 *
 * @param match
 * @param ret
 * @param j
 * @return
 */
gboolean email_replace(const GMatchInfo* match, GString* ret, job j)
{
  gchar* m_str = g_match_info_fetch(match, 1);
  gchar* sql   = NULL;
  gchar* table, * column;
  PGresult* db_result;

  if(strcmp(m_str, "BROWSELINK"))
  {
    g_string_append_printf(ret, "http://%s?mod=browse&upload=%d&show=detail",
        fossy_url, job_id(j));
  }
  else if(strcmp(m_str, "SCHEDULERLOG"))
  {
    g_string_append_printf(ret, "http://%s?mod=showjobs&show=job&job=%d",
        fossy_url, job_id(j));
  }
  else if(strcmp(m_str, "UPLOADFOLDERNAME"))
  {
    g_string_append(ret, "[NOT IMPLEMENTED]");
  }
  else if(strcmp(m_str, "JOBRESULT"))
  {
    switch(job_get_status(j))
    {
      case JB_COMPLETE: g_string_append(ret, "COMPLETE"); break;
      case JB_FAILED:   g_string_append(ret, "FAILED");   break;
      default:
        g_string_append_printf(ret, "[ERROR: illegal job status \"%s\"]",
            job_status_strings[job_get_status(j)]);
        break;
    }
  }
  else if(strcmp(m_str, "DB"))
  {
    table  = g_match_info_fetch(match, 3);
    column = g_match_info_fetch(match, 4);
    sql = g_strdup_printf("SELECT %s FROM %s;", column, table);
    db_result = PQexec(db_conn, sql);
    if(PQresultStatus(db_result) != PGRES_TUPLES_OK)
    {
      g_string_append_printf(ret, "[ERROR: unable to select %s.%s]",
          g_match_info_fetch(match, 3), g_match_info_fetch(match, 4));
      return FALSE;
    }

    g_string_append(ret, PQgetvalue(db_result, 0, 0));

    PQclear(db_result);
    g_free(sql);
    g_free(table);
    g_free(column);
  }

  g_free(m_str);
  return FALSE;
}

/**
 * TODO, unfinished function, needs the email construction
 *
 * @param job_id
 * @param failed
 */
void email_notification(job j)
{
  PGresult* db_result;
  int tuples;
  int i, j_id = job_id(j);;
  int col;
  int upload_id;
  char* val, * finished;
  char sql[1024];
  GString* email_txt;
  GError* error = NULL;
  gint mail_pid;
  gint mail_stdin;
  gint msg_len;

  if(is_special(job_type(j), SAG_NOEMAIL))
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

  tuples = PQntuples(db_result);
  col = PQfnumber(db_result, "jq_endtext");
  for(i = 0; i < tuples; i++)
  {
    val = PQgetvalue(db_result, i, col);
    if(strcmp(val, "Started") == 0 ||
       strcmp(val, "Paused")  == 0 ||
       strcmp(val, "Restart") == 0 )
    {
      PQclear(db_result);
      return;
    }
  }
  PQclear(db_result);

  sprintf(sql, jobsql_email, upload_id);
  db_result = PQexec(db_conn, sql);
  if(PQresultStatus(db_result) != PGRES_TUPLES_OK)
  {
    PQ_ERROR(db_result, "unable to access email info for job %d", j_id);
    return;
  }

  if(PQget(db_result, 0, "email_notify")[0] == 'y')
  {
    mail_argv[1] = PQget(db_result, 0, "user_email");
    g_spawn_async_with_pipes(NULL, mail_argv, NULL,
        G_SPAWN_STDOUT_TO_DEV_NULL | G_SPAWN_STDERR_TO_DEV_NULL,
        NULL, NULL, &mail_pid, &mail_stdin, NULL, NULL, &error);

    if(!error)
    {
      email_txt = g_string_new("");
      g_string_append(email_txt, header);
      g_string_append(email_txt, job_message(j));
      g_string_append(email_txt, footer);

      if(email_regex != NULL)
      {
        val = g_regex_replace_eval(email_regex, email_txt->str, email_txt->len,
            0, 0, (GRegexEvalCallback)email_replace, j, NULL);
      }
      else
        val = email_txt->str;

      finished = g_strdup_printf("%s%s \n", subject, val);
      msg_len = strlen(finished);

      // 0x03 is ascii for "End of Text"
      // this is done so that mail knows when the end of the body is
      finished[msg_len - 2] = 0x03;

      if(write(mail_stdin, finished, msg_len) == -1)
        ERROR("write of message to mailx failed");
      fsync(mail_stdin);

      if(email_regex != NULL)
        g_free(val);
      g_string_free(email_txt, TRUE);
      g_free(finished);
    }
    else
    {
      WARNING("unable to spawn mailx process: %s", error->message);
    }

    mail_argv[1] = NULL;
  }

  PQclear(db_result);
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
  if(PQresultStatus(db_result) != PGRES_TUPLES_OK && PQntuples(db_result) != 0)
    strcpy(fossy_url, PQgetvalue(db_result, 0, 0));
  PQclear(db_result);

  header = NULL;
  footer = NULL;
  subject = NULL;
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

  V_DATABASE("DB: retrieved %d entries from the job queue\n", PQntuples(db_result));
  for(i = 0; i < PQntuples(db_result); i++)
  {
    /* start by checking that the job hasn't already been grabed */
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
      lprintf("DB: got a command from job queue\n");
      // TODO handle command
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
  int j_id = job_id(j);
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
      email_notification(j);
      sql = g_strdup_printf(jobsql_complete, j_id);
      break;
    case JB_RESTART:
      sql = g_strdup_printf(jobsql_restart, j_id);
      break;
    case JB_FAILED:
      email_notification(j);
      sql = g_strdup_printf(jobsql_failed, message, j_id);
      break;
    case JB_SCH_PAUSED: case JB_CLI_PAUSED:
      sql = g_strdup_printf(jobsql_paused, j_id);
      break;
    case JB_ERROR:
      email_notification(j);
      sql = g_strdup_printf(jobsql_failed, j_id);
      break;
  }

  /* update the database job queue */
  db_result = PQexec(db_conn, sql);
  if(sql != NULL && PQresultStatus(db_result) != PGRES_COMMAND_OK)
    PQ_ERROR(db_result, "failed to update job status in job queue");

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


