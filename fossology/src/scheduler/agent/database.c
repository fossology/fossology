/* **************************************************************
Copyright (C) 2010, 2011, 2012 Hewlett-Packard Development Company, L.P.

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

/* std library includes */

/* other library includes */
#include <libfossdb.h>
#include <fcntl.h>
#include <sys/stat.h>
#include <sys/mman.h>

/* all of the sql statements used in the database */
#include <sqlstatements.h>

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

#define EMAIL_ERROR(...) {                       \
  WARNING(__VA_ARGS__);                          \
  email_notify = 0;                              \
  error = NULL; }

#define EMAIL_BUILD_CMD "%s -s '%s' %s"
#define DEFAULT_HEADER  "FOSSology scan complete\nmessage:\""
#define DEFAULT_FOOTER  "\""
#define DEFAULT_SUBJECT "FOSSology scan complete\n"
#define DEFAULT_COMMAND "/usr/bin/mailx"

#define min(x, y) (x < y ? x : y)

/**
 * We need to pass both a job_t* and the fossology url string to the
 * email_replace() function. This structure allows both of these to be passed.
 */
typedef struct {
    gchar* foss_url;
    job_t* job;
    PGconn* db_conn;
} email_replace_args;

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
static gboolean email_replace(const GMatchInfo* match, GString* ret,
    email_replace_args* args)
{
  gchar* m_str = g_match_info_fetch(match, 1);
  gchar* sql   = NULL;
  gchar* fossy_url = args->foss_url;
  job_t* job       = args->job;
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
    sql = g_strdup_printf(upload_name, job->id);
    db_result = PQexec(args->db_conn, sql);

    if(PQresultStatus(db_result) != PGRES_TUPLES_OK)
    {
      g_string_append_printf(ret,
          "[ERROR: unable to select file name for upload %d]", job->id);
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
    sql = g_strdup_printf(upload_pk, job->id);
    db_result = PQexec(args->db_conn, sql);

    if(PQresultStatus(db_result) != PGRES_TUPLES_OK)
    {
      g_string_append_printf(ret,
          "[ERROR: unable to select file name for upload %d]", job->id);
    }
    else
    {
      g_string_append_printf(ret,
          "http://%s?mod=browse&upload=%s&item=%s&show=detail",
          fossy_url, PQgetvalue(db_result, 0, 0), PQgetvalue(db_result, 0, 1));
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
    sql = g_strdup_printf(upload_pk, job->id);
    db_result = PQexec(args->db_conn, sql);

    if(PQresultStatus(db_result) != PGRES_TUPLES_OK)
    {
      g_string_append_printf(ret,
          "[ERROR: unable to select file name for upload %d]", job->id);
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
        fossy_url, job->id);
  }

  /* $UPLOADFOLDERNAME
   *
   * Appends the name of the folder that the upload was stored under.
   */
  else if(strcmp(m_str, "UPLOADFOLDERNAME") == 0)
  {
    sql = g_strdup_printf(folder_name, job->id);
    db_result = PQexec(args->db_conn, sql);

    if(PQresultStatus(db_result) != PGRES_TUPLES_OK)
    {
      g_string_append_printf(ret,
          "[ERROR: unable to select folder name for upload %d]", job->id);
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
    switch(job->status)
    {
      case JB_COMPLETE: g_string_append(ret, "COMPLETE"); break;
      case JB_FAILED:   g_string_append(ret, "FAILED");   break;
      default:
        g_string_append_printf(ret, "[ERROR: illegal job status \"%s\"]",
            job_status_strings[job->status]);
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
 * @param db_conn   database connection for getting list of related jobs
 * @param job_list  the list of all currently running jobs
 * @param job       the job to check for the job status on
 * @return 0: job is not finished, 1: job has finished, 2: job has failed
 */
static gint email_checkjobstatus(PGconn* db_conn, GTree* job_list, job_t* job)
{
  gchar* sql;
  gint ret = 1;
  PGresult* db_result;
  int id, i;

  sql = g_strdup_printf(jobsql_anyrunnable, job->id);
  db_result = PQexec(db_conn, sql);
  if(PQresultStatus(db_result) != PGRES_TUPLES_OK)
  {
    PQ_ERROR(db_result, "unable to check job status for jq_pk %d", job->id);
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

  sql = g_strdup_printf(jobsql_jobendbits, job->id);
  db_result = PQexec(db_conn, sql);

  /* check for any jobs that are still running */
  for(i = 0; i < PQntuples(db_result) && ret; i++)
  {
    id = atoi(PQget(db_result, i, "jq_pk"));
    if(id != job->id && g_tree_lookup(job_list, &id) != NULL)
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
static void email_notification(scheduler_t* scheduler, job_t* job)
{
  PGresult* db_result;
  int j_id = job->id;;
  int upload_id;
  int status;
  char* val;
  char* final_cmd;
  char sql[1024];
  FILE* mail_io;
  GString* email_txt;
  job_status curr_status = job->status;
  email_replace_args args;

  if(is_meta_special(g_tree_lookup(scheduler->meta_agents, job->agent_type), SAG_NOEMAIL) ||
      !(status = email_checkjobstatus(scheduler->db_conn, scheduler->job_list, job)))
    return;

  sprintf(sql, select_upload_fk, j_id);
  db_result = PQexec(scheduler->db_conn, sql);
  if(PQresultStatus(db_result) != PGRES_TUPLES_OK || PQntuples(db_result) == 0)
  {
    PQ_ERROR(db_result, "unable to select the upload id for job %d", j_id);
    return;
  }

  upload_id = atoi(PQgetvalue(db_result, 0, 0));
  PQclear(db_result);

  sprintf(sql, upload_common, upload_id);
  db_result = PQexec(scheduler->db_conn, sql);
  if(PQresultStatus(db_result) != PGRES_TUPLES_OK)
  {
    PQ_ERROR(db_result, "unable to check common uploads to job %d", j_id);
    return;
  }

  sprintf(sql, jobsql_email, upload_id);
  db_result = PQexec(scheduler->db_conn, sql);
  if(PQresultStatus(db_result) != PGRES_TUPLES_OK || PQntuples(db_result) == 0)
  {
    PQ_ERROR(db_result, "unable to access email info for job %d", j_id);
    return;
  }

  if(PQget(db_result, 0, "email_notify")[0] == 'y')
  {
    if(status == 2)
      job->status = JB_FAILED;

    email_txt = g_string_new("");
    g_string_append(email_txt, scheduler->email_header);
    g_string_append(email_txt, job->message == NULL ? "" : job->message);
    g_string_append(email_txt, scheduler->email_footer);


    if(scheduler->parse_db_email != NULL)
    {
      args.foss_url = scheduler->host_url;
      args.job      = job;
      args.db_conn  = scheduler->db_conn;
      val = g_regex_replace_eval(scheduler->parse_db_email, email_txt->str,
          email_txt->len, 0, 0, (GRegexEvalCallback)email_replace, &args, NULL);
    }
    else
    {
      val = email_txt->str;
    }

    final_cmd = g_strdup_printf(EMAIL_BUILD_CMD, scheduler->email_command,
        scheduler->email_subject, PQget(db_result, 0, "user_email"));

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
          scheduler->email_command);
    }

    job->status = curr_status;
    if(scheduler->parse_db_email != NULL)
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
void email_init(scheduler_t* scheduler)
{
  int email_notify, fd;
  struct stat header_sb;
  struct stat footer_sb;
	gchar* fname;
	GError* error = NULL;

	if(scheduler->email_header && strcmp(scheduler->email_header, DEFAULT_HEADER) != 0)
	  munmap(scheduler->email_header, header_sb.st_size);
	if(scheduler->email_footer && strcmp(scheduler->email_footer, DEFAULT_FOOTER) != 0)
	  munmap(scheduler->email_footer, footer_sb.st_size);
	if(scheduler->email_subject && strcmp(scheduler->email_subject, DEFAULT_SUBJECT) != 0)
	  g_free(scheduler->email_subject);

	/* load the header */
	email_notify = 1;
	fname = g_strdup_printf("%s/%s", scheduler->sysconfigdir,
	    fo_config_get(scheduler->sysconfig, "EMAILNOTIFY", "header", &error));
	if(error && error->code == fo_missing_group)
	  EMAIL_ERROR("email notification setting group \"[EMAILNOTIFY]\" missing. Using defaults");
	if(error && error->code == fo_missing_key)
	  EMAIL_ERROR("email notification setting key \"header\" missing. Using default header");
	if(email_notify && (fd = open(fname, O_RDONLY)) == -1)
	  EMAIL_ERROR("unable to open file for email header: %s", fname);
	if(email_notify && fstat(fd, &header_sb) == -1)
	  EMAIL_ERROR("unable to fstat email header: %s", fname);
	if(email_notify && (scheduler->email_header = mmap(NULL, header_sb.st_size, PROT_READ,
	    MAP_SHARED, fd, 0)) == MAP_FAILED)
	  EMAIL_ERROR("unable to mmap email header: %s", fname);
	if(!email_notify)
	  scheduler->email_header = DEFAULT_HEADER;

	/* load the footer */
	email_notify = 1;
	fname = g_strdup_printf("%s/%s", scheduler->sysconfigdir,
	      fo_config_get(scheduler->sysconfig, "EMAILNOTIFY", "footer", &error));
	if(error)
	  email_notify = 0;
	if(error && error->code == fo_missing_key)
	  EMAIL_ERROR("email notification setting key \"footer\" missing. Using default footer");
	if(email_notify && (fd = open(fname, O_RDONLY)) == -1)
	  EMAIL_ERROR("unable to open file for email footer: %s", fname);
	if(email_notify && fstat(fd, &footer_sb) == -1)
	  EMAIL_ERROR("unable to fstat email footer: %s", fname);
	if(email_notify && (scheduler->email_footer = mmap(NULL, footer_sb.st_size, PROT_READ,
	    MAP_SHARED, fd, 0)) == MAP_FAILED)
	  EMAIL_ERROR("unable to mmap email footer: %s", fname);
	if(!email_notify)
	  scheduler->email_footer = DEFAULT_FOOTER;
	error = NULL;

	/* load the email_subject */
	scheduler->email_subject = fo_config_get(scheduler->sysconfig, "EMAILNOTIFY",
	    "email_subject", &error);
	if(error)
	  scheduler->email_subject = DEFAULT_SUBJECT;
	if(error && error->code == fo_missing_key)
	  EMAIL_ERROR("email notification setting key \"email_subject\" missing. Using default subject");
	scheduler->email_subject = g_strdup(scheduler->email_subject);
	error = NULL;

	/* load the client */
	email_notify = 1;
	scheduler->email_command = fo_config_get(scheduler->sysconfig, "EMAILNOTIFY",
	    "client", &error);
	if(error)
	  scheduler->email_command = DEFAULT_COMMAND;
	if(error && error->code == fo_missing_key)
	  EMAIL_ERROR("email notification setting key \"client\" missing. Using default client");
	scheduler->email_command = g_strdup(scheduler->email_command);
	error = NULL;
}

/* ************************************************************************** */
/* **** local functions ***************************************************** */
/* ************************************************************************** */

/**
 * @brief Data type used to check if the database is correct.
 *
 * This will be statically initialized in the check_tables() function.
 */
typedef struct
{
    char* table;        ///< The name of the table to check columns in
    uint8_t ncols;      ///< The number of columns in the table that the scheduler uses
    char* columns[13];  ///< The columns that the scheduler uses for this table
} reqcols;

/**
 * @brief Checks that any part of the database used by the scheduler is correct
 *
 * This has a static list of all tables and the associated columns used by the
 * scheduler. If any changes that affect the tables and columns used by the
 * scheduler are made, this static list should be updated.
 */
static void check_tables(PGconn* db_conn)
{
  /* locals */
  PGresult* db_result;
  GString* sql;
  reqcols* curr;
  uint32_t i;
  uint32_t curr_row;
  int passed = TRUE;
  char sqltmp[1024] = {0};

  /* All of the tables and columns that the scheduler uses
   *
   * Note: the columns should be listed in alphabetical order, if they are not
   *       then the error messages that result will be erroneous
   */
  reqcols cols[] =
  {
      {"jobqueue",  13, {
          "jq_args", "jq_end_bits", "jq_endtext", "jq_endtime", "jq_host",
          "jq_itemsprocessed", "jq_job_fk", "jq_log", "jq_pk", "jq_runonpfile",
          "jq_schedinfo", "jq_starttime", "jq_type"                                  }},
      {"sysconfig",      2, {"conf_value", "variablename"                            }},
      {"job",            2, {"job_pk",  "job_upload_fk"                              }},
      {"folder",         2, {"folder_name",  "folder_pk"                             }},
      {"foldercontents", 3, {"child_id",  "foldercontents_mode",  "parent_fk"        }},
      {"upload",         2, {"upload_filename",  "upload_pk"                         }},
      {"uploadtree",     3, {"parent",  "upload_fk",  "uploadtree_pk"                }},
      {"users",          4, {"email_notify",  "user_email",  "user_name",  "user_pk" }},
      { NULL }
  };

  /* iterate accros every require table and column */
  sprintf(sqltmp, check_scheduler_tables, PQdb(db_conn));
  for(curr = cols; curr->table; curr++)
  {
    /* build the sql statement */
    sql = g_string_new(sqltmp);
    g_string_append_printf(sql, "'%s' AND column_name IN (", curr->table);
    for(i = 0; i < curr->ncols; i++)
    {
      g_string_append_printf(sql, "'%s'", curr->columns[i]);
      if(i != curr->ncols - 1)
        g_string_append(sql, ", ");
    }
    g_string_append(sql, ") ORDER BY column_name;");

    /* execute the sql */
    db_result = PQexec(db_conn, sql->str);
    if(PQresultStatus(db_result) != PGRES_TUPLES_OK)
    {
      passed = FALSE;
      PQ_ERROR(db_result, "could not check database tables");
      break;
    }

    /* check that the correct number of columns was returned */
    if(PQntuples(db_result) != curr->ncols)
    {
      /* we have failed the database check */
      passed = FALSE;

      /* print the columns that do not exist */
      for(i = 0, curr_row = 0; i < min(PQntuples(db_result), curr->ncols); i++)
      {
        if(strcmp(PQgetvalue(db_result, curr_row, 0), curr->columns[i]) != 0)
          ERROR("Column %s.%s does not exist", curr->table, curr->columns[i]);
        else
          curr_row++;
      }
    }

    PQclear(db_result);
    g_string_free(sql, TRUE);
  }

  if(!passed)
  {
    log_printf("FATAL %s.%d: Scheduler did not pass database check\n", __FILE__, __LINE__);
    log_printf("FATAL %s.%d: Running fo_postinstall should fix these issues\n", __FILE__, __LINE__);
    exit(230);
  }
}

/**
 * Initializes any one-time attributes relating to the database. Currently this
 * includes creating the db connection and checking the URL of the FOSSology
 * instance out of the db.
 */
void database_init(scheduler_t* scheduler)
{
  PGresult* db_result;
  gchar* dbConf = NULL;
  char* ErrorBuf = NULL;

  /* create the connection to the database */
  dbConf = g_strdup_printf("%s/Db.conf", scheduler->sysconfigdir);
  scheduler->db_conn = fo_dbconnect(dbConf, &ErrorBuf);
  if(ErrorBuf || PQstatus(scheduler->db_conn) != CONNECTION_OK)
    FATAL("Unable to connect to the database: \"%s\"", ErrorBuf);

  /* get the url for the fossology instance */
  db_result = PQexec(scheduler->db_conn, url_checkout);
  if(PQresultStatus(db_result) == PGRES_TUPLES_OK && PQntuples(db_result) != 0)
    scheduler->host_url = g_strdup(PQgetvalue(db_result, 0, 0));
  PQclear(db_result);

  /* check that relevant database fields exist */
  check_tables(scheduler->db_conn);
}

/* ************************************************************************** */
/* **** event and functions ************************************************* */
/* ************************************************************************** */

/**
 * TODO
 *
 * @param scheduler
 * @param sql
 */
void database_exec_event(scheduler_t* scheduler, char* sql)
{
  PGresult* db_result = PQexec(scheduler->db_conn, sql);
  if(PQresultStatus(db_result) != PGRES_COMMAND_OK)
    PQ_ERROR(db_result, "failed to perform database exec: %s", sql);
  g_free(sql);
}

/**
 * Checks the job queue for any new entries.
 *
 * @param unused
 */
void database_update_event(scheduler_t* scheduler, void* unused)
{
  /* locals */
  PGresult* db_result;
  PGresult* pri_result;
  int i, j_id;
  char sql[512];
  char* value, * type, * host, * pfile, * parent;
  job_t* job;

  if(closing)
  {
    WARNING("scheduler is closing, will not perform database update");
    return;
  }

  /* make the database query */

  db_result = PQexec(scheduler->db_conn, basic_checkout);
  if(PQresultStatus(db_result) != PGRES_TUPLES_OK)
  {
    PQ_ERROR(db_result, "database update failed on call to PQexec");
    return;
  }

  V_SPECIAL("DB: retrieved %d entries from the job queue\n",
      PQntuples(db_result));
  for(i = 0; i < PQntuples(db_result); i++)
  {
    /* start by checking that the job hasn't already been grabbed */
    j_id = atoi(PQget(db_result, i, "jq_pk"));
    if(g_tree_lookup(scheduler->job_list, &j_id) != NULL)
      continue;

    /* get relevant values out of the job queue */
    parent =      PQget(db_result, i, "jq_job_fk");
    host   =      PQget(db_result, i, "jq_host");
    type   =      PQget(db_result, i, "jq_type");
    pfile  =      PQget(db_result, i, "jq_runonpfile");
    value  =      PQget(db_result, i, "jq_args");

    if(host != NULL)
      host = (strlen(host) == 0) ? NULL : host;

    V_DATABASE("DB: jq_pk[%d] added:\n   jq_type = %s\n   jq_host = %s\n   "
        "jq_runonpfile = %d\n   jq_args = %s\n",
        j_id, type, host, (pfile != NULL && pfile[0] != '\0'), value);

    /* check if this is a command */
    if(strcmp(type, "command") == 0)
    {
      WARNING("DB: commands in the job queue not implemented,"
          " using the interface api instead");
      continue;
    }

    sprintf(sql, change_priority, parent);
    pri_result = PQexec(scheduler->db_conn, sql);
    if(PQresultStatus(pri_result) != PGRES_TUPLES_OK)
    {
      PQ_ERROR(pri_result, "database update failed on call to PQexec");
      continue;
    }

    // TODO change for new scheduler
    job = job_init(scheduler->job_list, scheduler->job_queue, type, host, j_id,
        atoi(PQgetvalue(pri_result, 0, 0)));
    job_set_data(job, scheduler->db_conn,  value, (pfile && pfile[0] != '\0'));

    PQclear(pri_result);
  }

  PQclear(db_result);
}

/**
 * Resets the any jobs in the job queue that are not completed. This is to make
 * sure that any jobs that were running with the scheduler shutdown are run correctly
 * when it starts up again.
 */
void database_reset_queue(PGconn* db_conn)
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
 * Change the status of a job in the database.
 *
 * @param j_id id number of the relevant job
 * @param status the new status of the job
 */
void database_update_job(scheduler_t* scheduler, job_t* job, job_status status)
{
  /* locals */
  gchar* sql = NULL;
  PGresult* db_result;
  int j_id = job->id;
  char* message = (job->message == NULL) ? "Failed": job->message;

  /* check how to update database */
  switch(status)
  {
    case JB_NOT_AVAILABLE: case JB_CHECKEDOUT:
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
    case JB_PAUSED:
      sql = g_strdup_printf(jobsql_paused, j_id);
      break;
  }

  /* update the database job queue */
  db_result = PQexec(scheduler->db_conn, sql);
  if(sql != NULL && PQresultStatus(db_result) != PGRES_COMMAND_OK)
    PQ_ERROR(db_result, "failed to update job status in job queue");

  if(status == JB_COMPLETE || status == JB_FAILED)
    email_notification(scheduler, job);

  g_free(sql);
}

/**
 * Updates the number of items that a job queue entry has processed.
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
 * @param db_conn   the connection to the database
 * @param job       the job to change the priority for
 * @param priority  the new priority of the job
 */
void database_job_priority(PGconn* db_conn, job_t* job, int priority)
{
  gchar* sql = NULL;
  PGresult* db_result;

  sql = g_strdup_printf(jobsql_priority, priority, job->id);
  db_result = PQexec(db_conn, sql);
  if(sql != NULL && PQresultStatus(db_result) != PGRES_COMMAND_OK)
    PQ_ERROR(db_result, "failed to change job queue entry priority");

  g_free(sql);
}
