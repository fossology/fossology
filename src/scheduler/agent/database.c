/*
 SPDX-FileCopyrightText: © 2010, 2011, 2012 Hewlett-Packard Development Company, L.P.
 SPDX-FileCopyrightText: © 2015, 2018 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * \file
 * \brief Database related operations
 */
/* local includes */
#include <agent.h>
#include <database.h>
#include <logging.h>
#include <emailformatter.h>

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

#define EMAIL_ERROR(...) { \
  WARNING(__VA_ARGS__);    \
  email_notify = 0;        \
  error = NULL; }

#define EMAIL_BUILD_CMD "%s %s -s '%s' %s"    ///< Email command format
#define DEFAULT_HEADER  "FOSSology scan complete\nmessage:\"" ///< Default email header
#define DEFAULT_FOOTER  "\""                  ///< Default email footer
#define DEFAULT_SUBJECT "FOSSology scan complete\n" ///< Default email subject
#define DEFAULT_COMMAND "/usr/bin/mailx"      ///< Default email command to use

#define min(x, y) (x < y ? x : y)     ///< Return the minimum of x, y

/**
 * We need to pass both a job_t* and the fossology url string to the
 * email_replace() function. This structure allows both of these to be passed.
 */
typedef struct {
    scheduler_t* scheduler; ///< Current scheduler reference
    gchar* foss_url;        ///< Fossology URL string
    job_t* job;             ///< Current job structure
} email_replace_args;

/**
 * @brief Replaces the variables that are in the header and footer files.
 *
 * This is a
 * callback function that is passed to the glib function g_regex_replace_eval().
 * This reads what was matched by the regex and then appends the correct
 * information onto the GString that is passed to the function.
 *
 * Variables:\n
 *   $UPLOADNAME\n
 *   $BROWSELINK\n
 *   $SCHEDULERLOG\n
 *   $UPLOADFOLDERNAME\n
 *   $JOBRESULT\n
 *   $JOBQUEUELINK\n
 *   $AGENTSTATUS\n
 *   $DB.table.column [not implemented]
 *
 * @param match  the regex match that glib found
 * @param ret    the GString* that results should be appended to.
 * @param args   The email replace arguments with foss url and job reference
 * @return       always FALSE so that g_regex_replace_eval() will continue
 * @todo needs implementation of $DB.table.column
 */
static gboolean email_replace(const GMatchInfo* match, GString* ret,
    email_replace_args* args)
{
  gchar* m_str = g_match_info_fetch(match, 1);
  gchar* sql   = NULL;
  gchar* fossy_url = args->foss_url;
  job_t* job       = args->job;
  GPtrArray* rows  = NULL;
  /* TODO belongs to $DB if statement => gchar* table, * column; */
  PGresult* db_result;
  guint i;

  /* $UPLOADNAME
   *
   * Appends the name of the file that was uploaded and appends it to the output
   * string. This uses the job id to find the upload name.
   */
  if(strcmp(m_str, "UPLOADNAME") == 0)
  {
    sql = g_strdup_printf(upload_name, job->id);
    db_result = database_exec(args->scheduler, sql);

    if(PQresultStatus(db_result) != PGRES_TUPLES_OK)
    {
      g_string_append_printf(ret,
          "[ERROR: unable to select upload file name for job %d]", job->id);
    }
    else if(PQntuples(db_result) == 0)
    {
      if(strcmp(job->agent_type, "delagent") == 0)
        g_string_append_printf(ret,
          "[File has been deleted by job %d]", job->id);
      else
        g_string_append_printf(ret,
          "[ERROR: file has not been uploaded or unpacked yet for job %d]", job->id);
    }
    else
    {
      g_string_append(ret, PQgetvalue(db_result, 0, 0));
    }

    SafePQclear(db_result);
    g_free(sql);
  }

  /* $BROWSELINK
   *
   * Appends the URL that will link to the upload in the browse menu of the user
   * interface.
   */
  else if(strcmp(m_str, "BROWSELINK") == 0)
  {
    sql = g_strdup_printf(upload_pk, job->id);
    db_result = database_exec(args->scheduler, sql);

    if(PQresultStatus(db_result) != PGRES_TUPLES_OK)
    {
      g_string_append_printf(ret,
          "[ERROR: unable to select upload primary key for job %d]", job->id);
    }
    else if(PQntuples(db_result) == 0)
    {
      if(strcmp(job->agent_type, "delagent") == 0)
        g_string_append_printf(ret,
          "[File has been deleted by job %d]", job->id);
      else
        g_string_append_printf(ret,
          "[ERROR: file has not been uploaded or unpacked yet for job %d]", job->id);
    }
    else
    {
      g_string_append_printf(ret,
          "http://%s?mod=browse&upload=%s&item=%s&show=detail",
          fossy_url, PQgetvalue(db_result, 0, 0), PQgetvalue(db_result, 0, 1));
    }

    SafePQclear(db_result);
    g_free(sql);
  }

  /* $JOBQUEUELINK
   *
   * Appends the URL that will link to the job queue
   */
  else if(strcmp(m_str, "JOBQUEUELINK") == 0)
  {
    sql = g_strdup_printf(upload_pk, job->id);
    db_result = database_exec(args->scheduler, sql);

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

    SafePQclear(db_result);
    g_free(sql);
  }

  /* $SCHEDULERLOG
   *
   * Appends the URL that will link to the log file produced by the agent.
   */
  else if(strcmp(m_str, "SCHEDULERLOG") == 0)
  {
    g_string_append_printf(ret, "http://%s?mod=showjobs&job=%d",
        fossy_url, job->id);
  }

  /* $UPLOADFOLDERNAME
   *
   * Appends the name of the folder that the upload was stored under.
   */
  else if(strcmp(m_str, "UPLOADFOLDERNAME") == 0)
  {
    sql = g_strdup_printf(folder_name, job->id);
    db_result = database_exec(args->scheduler, sql);

    if(PQresultStatus(db_result) != PGRES_TUPLES_OK || PQntuples(db_result) == 0)
    {
      g_string_append_printf(ret,
          "[NOTICE: unable to select folder name for upload %d]", job->id);
    }
    else
    {
      rows = g_ptr_array_new();
      GString *foldername = g_string_new(PQgetvalue(db_result, 0, 0));
      guint folder_pk = atoi(PQget(db_result, 0, "folder_pk"));
      g_ptr_array_add(rows, foldername);
      SafePQclear(db_result);
      g_free(sql);
      sql = g_strdup_printf(parent_folder_name, folder_pk);
      db_result = database_exec(args->scheduler, sql);
      /*
       * Get the current folder name and traverse back till the root folder.
       * Add the folder names found in an array.
       * array[0] => Curr_Folder
       * array[1] => Par_Folder
       * array[2] => Root_Folder
       */
      while(PQresultStatus(db_result) == PGRES_TUPLES_OK && PQntuples(db_result) == 1)
      {
        GString *foldername = g_string_new(PQgetvalue(db_result, 0, 0));
        guint folder_pk = atoi(PQget(db_result, 0, "folder_pk"));
        g_ptr_array_add(rows, foldername);
        SafePQclear(db_result);
        g_free(sql);
        sql = g_strdup_printf(parent_folder_name, folder_pk);
        db_result = database_exec(args->scheduler, sql);
      }
      /*
       * Traverse the folder name array from behind and append the names with a
       * `/` as a separator between the names.
       * Result => Root_Folder / Par_Folder / Curr_folder
       */
      for(i = rows->len - 1; i > 0; i--)
      {
        GString *folder = g_ptr_array_index(rows, i);
        g_string_append(ret, folder->str);
        g_string_append(ret, " / ");
      }
      GString *folder = g_ptr_array_index(rows, 0);
      g_string_append(ret, folder->str);
      g_ptr_array_free(rows, TRUE);
    }

    SafePQclear(db_result);
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

  /* $AGENTSTATUS
   *
   * Appends the list of agents run with their status.
   */
  else if(strcmp(m_str, "AGENTSTATUS") == 0)
  {
    sql = g_strdup_printf(jobsql_jobinfo, job->id);
    db_result = database_exec(args->scheduler, sql);
    if(PQresultStatus(db_result) != PGRES_TUPLES_OK)
    {
      g_string_append_printf(ret,
                "[ERROR: unable to select agent info for job %d]", job->id);
    }
    else
    {
      rows = g_ptr_array_sized_new(PQntuples(db_result));
      /*
       * Find all the agents for the current job and attach their jq_pk,
       * their name and their status (true=>pass, false=>fail)
       */
      for(i = 0; i < PQntuples(db_result) && ret; i++)
      {
        agent_info *data = (agent_info *)calloc(1, sizeof(agent_info));
        data->id = atoi(PQget(db_result, i, "jq_pk"));
        data->agent = g_string_new(PQget(db_result, i, "jq_type"));
        if(atoi(PQget(db_result, i, "jq_end_bits")) == 1)
        {
          data->status = TRUE;
        }
        else
        {
          data->status = FALSE;
        }
        g_ptr_array_add(rows, data);
      }
      /* Pass the agent data to email_formating function to convert in desired format */
      g_string_append(ret, email_format_text(rows, fossy_url));
      g_ptr_array_free(rows, TRUE);
    }
    SafePQclear(db_result);
    g_free(sql);
  }

  /* $DB.table.column
   *
   * Appends a column of a table from the database to the resulting string.
   */
  else if(strcmp(m_str, "DB") == 0)
  {
    g_string_append(ret, "[DB. syntax is NOT IMPLEMENTED]");
    /* TODO reimplement $DB variable
    table  = g_match_info_fetch(match, 3);
    column = g_match_info_fetch(match, 4);
    sql = g_strdup_printf("SELECT %s FROM %s;", column, table);
    db_result = database_exec(scheduler, sql);
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

    SafePQclear(db_result);
    g_free(sql);
    g_free(table);
    g_free(column);*/
  }

  g_free(m_str);
  return FALSE;
}

/**
 * @brief Checks the database for the status of the job
 *
 * @param scheduler Current scheduler reference
 * @param job       The job to check for the job status on
 * @return 0: job is not finished, 1: job has finished, 2: job has failed
 */
static gint email_checkjobstatus(scheduler_t* scheduler, job_t* job)
{
  gchar* sql;
  gint ret = 1;
  PGresult* db_result;
  int id, i;

  sql = g_strdup_printf(jobsql_anyrunnable, job->id);
  db_result = database_exec(scheduler, sql);
  if(PQresultStatus(db_result) != PGRES_TUPLES_OK)
  {
    PQ_ERROR(db_result, "unable to check job status for jq_pk %d", job->id);
    g_free(sql);
    SafePQclear(db_result);
    return 0;
  }

  /* check if all runnable jobs have been run */
  if(PQntuples(db_result) != 0)
  {
    ret = 0;
  }

  g_free(sql);
  SafePQclear(db_result);

  sql = g_strdup_printf(jobsql_jobendbits, job->id);
  db_result = database_exec(scheduler, sql);

  /* check for any jobs that are still running */
  for(i = 0; i < PQntuples(db_result) && ret; i++)
  {
    id = atoi(PQget(db_result, i, "jq_pk"));
    if(id != job->id && g_tree_lookup(scheduler->job_list, &id) != NULL)
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
  SafePQclear(db_result);
  return ret;
}

/**
 * Sends an email notification that a particular job has completed correctly.
 * This compiles the email based upon the header file, footer file, and the job
 * that just completed.
 *
 * @param scheduler Current scheduler reference
 * @param job       The job that just finished
 * @return void, no return
 */
static void email_notification(scheduler_t* scheduler, job_t* job)
{
  PGresult* db_result;
  int j_id = job->id;
  int upload_id;
  int status;
  int retcode;
  char* val;
  char* final_cmd = NULL;
  char sql[1024];
  FILE* mail_io;
  GString* email_txt;
  job_status curr_status = job->status;
  email_replace_args args;

  if(is_meta_special(g_tree_lookup(scheduler->meta_agents, job->agent_type), SAG_NOEMAIL) ||
      !(status = email_checkjobstatus(scheduler, job)))
    return;

  sprintf(sql, select_upload_fk, j_id);
  db_result = database_exec(scheduler, sql);
  if(PQresultStatus(db_result) != PGRES_TUPLES_OK || PQntuples(db_result) == 0)
  {
    PQ_ERROR(db_result, "unable to select the upload id for job %d", j_id);
    return;
  }

  upload_id = atoi(PQgetvalue(db_result, 0, 0));
  SafePQclear(db_result);

  sprintf(sql, upload_common, upload_id);
  db_result = database_exec(scheduler, sql);
  if(PQresultStatus(db_result) != PGRES_TUPLES_OK)
  {
    PQ_ERROR(db_result, "unable to check common uploads to job %d", j_id);
    return;
  }
  SafePQclear(db_result);

  sprintf(sql, jobsql_email, upload_id);
  db_result = database_exec(scheduler, sql);
  if(PQresultStatus(db_result) != PGRES_TUPLES_OK)
  {
    PQ_ERROR(db_result, "unable to access email info for job %d", j_id);
    return;
  }

  /* special for delagent, upload records have been deleted.
   * So can't get the user info from upload table.
   * So get the user info from job table */
  if(PQntuples(db_result) == 0)
  {
    SafePQclear(db_result);
    sprintf(sql, jobsql_email_job, j_id);
    db_result = database_exec(scheduler, sql);
    if(PQresultStatus(db_result) != PGRES_TUPLES_OK || PQntuples(db_result) == 0)
    {
      PQ_ERROR(db_result, "unable to access email info for job %d", j_id);
      return;
    }
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
      args.foss_url   = scheduler->host_url;
      args.job        = job;
      args.scheduler  = scheduler;
      val = g_regex_replace_eval(scheduler->parse_db_email, email_txt->str,
            email_txt->len, 0, 0, (GRegexEvalCallback)email_replace, &args, NULL);
    }
    else
    {
      val = email_txt->str;
    }

    final_cmd = get_email_command(scheduler, PQget(db_result, 0, "user_email"));
    if(final_cmd == NULL)
    {
      if(scheduler->parse_db_email != NULL)
        g_free(val);
      g_string_free(email_txt, TRUE);
      return;
    }
    if((mail_io = popen(final_cmd, "w")) != NULL)
    {
      fprintf(mail_io, "%s", val);
      fflush(mail_io);
      retcode = WEXITSTATUS(pclose(mail_io));
      if(retcode != 0)
      {
        ERROR("Received error code %d from '%s'", retcode, scheduler->email_command);
      }
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
  SafePQclear(db_result);
}

/**
 * @brief Loads information about the email that will be sent for job notifications.
 *
 * This loads the header and footer configuration files, loads the subject and
 * client info, and compiles the regex that is used to replace variables in the
 * header and footer files.
 *
 * @param[in,out] scheduler Current scheduler to init
 * @return void, no return
 */
void email_init(scheduler_t* scheduler)
{
  int email_notify, fd;
  struct stat header_sb = {};
  struct stat footer_sb = {};
	gchar* fname;
	GError* error = NULL;

	if(scheduler->email_header && !scheduler->default_header)
	  munmap(scheduler->email_header, header_sb.st_size);
	if(scheduler->email_footer && !scheduler->default_footer)
	  munmap(scheduler->email_footer, footer_sb.st_size);
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
	if((scheduler->default_header = !email_notify))
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
	if((scheduler->default_footer = !email_notify))
	  scheduler->email_footer = DEFAULT_FOOTER;
	error = NULL;

	/* load the email_subject */
	scheduler->email_subject = fo_config_get(scheduler->sysconfig, "EMAILNOTIFY",
	    "subject", &error);
	if(error)
	  scheduler->email_subject = DEFAULT_SUBJECT;
	if(error && error->code == fo_missing_key)
	  EMAIL_ERROR("email notification setting key \"subject\" missing. Using default subject");
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
 *
 * @param scheduler the scheduler_t* that holds the connection
 */
static void check_tables(scheduler_t* scheduler)
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

  /* 1st iterate across every require table and column */
  sprintf(sqltmp, check_scheduler_tables, PQdb(scheduler->db_conn));
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
    g_string_append(sql, ") ORDER BY column_name");

    /* execute the sql */
    db_result = database_exec(scheduler, sql->str);
    if(PQresultStatus(db_result) != PGRES_TUPLES_OK)
    {
      passed = FALSE;
      PQ_ERROR(db_result, "could not check database tables");
      break;
    }

    /* check that the correct number of columns was returned yr */
    if(PQntuples(db_result) != curr->ncols)
    {
      /* we have failed the database check */
      passed = FALSE;

      /* print the columns that do not exist */
      for(i = 0, curr_row = 0; i < curr->ncols; i++)
      {
        if(curr_row>=PQntuples(db_result) || strcmp(PQgetvalue(db_result, curr_row, 0), curr->columns[i]) != 0)
        {
          ERROR("Column %s.%s does not exist", curr->table, curr->columns[i]);
        }
        else
        {
          curr_row++;
        }
      }
    }

    SafePQclear(db_result);
    g_string_free(sql, TRUE);
  }

  if(!passed)
  {
    log_printf("FATAL %s.%d: Scheduler did not pass database check\n", __FILE__, __LINE__);
    log_printf("FATAL %s.%d: Running fo_postinstall should fix the database schema\n", __FILE__, __LINE__);
    exit(230);
  }
}

/**
 * @brief Creates and performs error checking for a new database connection
 *
 * @param configdir  the location of the Db.conf file
 * @return  a new database connection
 */
static PGconn* database_connect(gchar* configdir)
{
  PGconn* ret = NULL;
  gchar* dbconf = NULL;
  char* error  = NULL;

  dbconf = g_strdup_printf("%s/Db.conf", configdir);
  ret = fo_dbconnect(dbconf, &error);

  if(error || PQstatus(ret) != CONNECTION_OK)
    FATAL("Unable to connect to the database: \"%s\"", error);

  g_free(dbconf);
  return ret;
}

/**
 * Initializes any one-time attributes relating to the database. Currently this
 * includes creating the db connection and checking the URL of the FOSSology
 * instance out of the db.
 */
void database_init(scheduler_t* scheduler)
{
  PGresult* db_result;

  /* create the connection to the database */
  scheduler->db_conn = database_connect(scheduler->sysconfigdir);

  /* get the url for the fossology instance */
  db_result = database_exec(scheduler, url_checkout);
  if(PQresultStatus(db_result) == PGRES_TUPLES_OK && PQntuples(db_result) != 0)
    scheduler->host_url = g_strdup(PQgetvalue(db_result, 0, 0));
  SafePQclear(db_result);

  /* check that relevant database fields exist */
  check_tables(scheduler);
}

/* ************************************************************************** */
/* **** event and functions ************************************************* */
/* ************************************************************************** */

/**
 * @brief Executes an sql statement for the scheduler
 *
 * This is used in case the database connection is lost. The scheduler requires
 * a connection to the database to correctly operate. So if the connection is
 * ever lost we automatically try to reconnect and if we are unable to, the
 * scheduler will die.
 *
 * @param scheduler  the scheduler_t* that holds the connection
 * @param sql        the sql that will be performed
 * @return           the PGresult struct that is returned by PQexec
 */
PGresult* database_exec(scheduler_t* scheduler, const char* sql)
{
  PGresult* ret = NULL;

  V_SPECIAL("DATABASE: exec \"%s\"\n", sql);

  ret = PQexec(scheduler->db_conn, sql);
  if(ret == NULL || PQstatus(scheduler->db_conn) != CONNECTION_OK)
  {
    PQfinish(scheduler->db_conn);
    scheduler->db_conn = database_connect(scheduler->sysconfigdir);

    ret = PQexec(scheduler->db_conn, sql);
  }

  return ret;
}

/**
 * @todo
 *
 * @param scheduler
 * @param sql
 */
void database_exec_event(scheduler_t* scheduler, char* sql)
{
  PGresult* db_result = database_exec(scheduler, sql);
  if(PQresultStatus(db_result) != PGRES_COMMAND_OK)
    PQ_ERROR(db_result, "failed to perform database exec: %s", sql);
  g_free(sql);
}

/**
 * @brief Checks the job queue for any new entries.
 *
 * @param scheduler The scheduler_t* that holds the connection
 * @param unused
 */
void database_update_event(scheduler_t* scheduler, void* unused)
{
  /* locals */
  PGresult* db_result;
  PGresult* pri_result;
  int i, j_id;
  char sql[512];
  char* value, * type, * host, * pfile, * parent, *jq_cmd_args;
  job_t* job;

  if(closing)
  {
    WARNING("scheduler is closing, will not check the job queue");
    return;
  }

  /* make the database query */
  db_result = database_exec(scheduler, basic_checkout);
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
    jq_cmd_args  =PQget(db_result, i, "jq_cmd_args");

    if(host != NULL)
      host = (strlen(host) == 0) ? NULL : host;
    if(jq_cmd_args != NULL)
      jq_cmd_args = (strlen(jq_cmd_args) == 0) ? NULL : jq_cmd_args;

    V_DATABASE("DB: jq_pk[%d] added:\n   jq_type = %s\n   jq_host = %s\n   "
        "jq_runonpfile = %d\n   jq_args = %s\n  jq_cmd_args = %s\n",
        j_id, type, host, (pfile != NULL && pfile[0] != '\0'), value, jq_cmd_args);

    /* check if this is a command */
    if(strcmp(type, "command") == 0)
    {
      WARNING("DB: commands in the job queue not implemented,"
          " using the interface api instead");
      continue;
    }

    sprintf(sql, jobsql_information, parent);
    pri_result = database_exec(scheduler, sql);
    if(PQresultStatus(pri_result) != PGRES_TUPLES_OK)
    {
      PQ_ERROR(pri_result, "database update failed on call to PQexec");
      continue;
    }
    if(PQntuples(pri_result)==0)
    {
      WARNING("can not find the user information of job_pk %s\n", parent);
      SafePQclear(pri_result);
      continue;
    }
    job = job_init(scheduler->job_list, scheduler->job_queue, type, host, j_id,
        atoi(parent),
        atoi(PQget(pri_result, 0, "user_pk")),
        atoi(PQget(pri_result, 0, "group_pk")),
        atoi(PQget(pri_result, 0, "job_priority")), jq_cmd_args);
    job_set_data(scheduler, job,  value, (pfile && pfile[0] != '\0'));

    SafePQclear(pri_result);
  }

  SafePQclear(db_result);
}

/**
 * @brief Resets any jobs in the job queue that are not completed.
 *
 * This is to make sure that any jobs that were running with the scheduler
 * shutdown are run correctly when it starts up again.
 */
void database_reset_queue(scheduler_t* scheduler)
{
  PGresult* db_result = database_exec(scheduler, jobsql_resetqueue);
  if(PQresultStatus(db_result) != PGRES_COMMAND_OK)
    PQ_ERROR(db_result, "failed to reset job queue");
}

/**
 * @brief Change the status of a job in the database.
 *
 * @param scheduler The scheduler_t* that holds the connection
 * @param job    job_t* object for the job
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
  db_result = database_exec(scheduler, sql);
  if(sql != NULL && PQresultStatus(db_result) != PGRES_COMMAND_OK)
    PQ_ERROR(db_result, "failed to update job status in job queue");

  if(status == JB_COMPLETE || status == JB_FAILED)
    email_notification(scheduler, job);

  g_free(sql);
}

/**
 * @brief Updates the number of items that a job queue entry has processed.
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
 * @brief Enters the name of the log file for a job into the database
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
 * @brief Changes the priority of a job queue entry in the database.
 *
 * @param scheduler The scheduler_t* that holds the connection
 * @param job       the job to change the priority for
 * @param priority  the new priority of the job
 */
void database_job_priority(scheduler_t* scheduler, job_t* job, int priority)
{
  gchar* sql = NULL;
  PGresult* db_result;

  sql = g_strdup_printf(jobsql_priority, priority, job->id);
  db_result = database_exec(scheduler, sql);
  if(sql != NULL && PQresultStatus(db_result) != PGRES_COMMAND_OK)
    PQ_ERROR(db_result, "failed to change job queue entry priority");

  g_free(sql);
}

/**
 * @brief Find s-nail version to check if mta is supported 
 *
 * \return 1 if mta is supported, 0 if not
 */
/**
 * @brief Find s-nail version to check if mta is supported 
 *
 * \return 1 if mta is supported, 0 if not
 */
int check_mta_support()
{
  char cmd[] = "mailx -V 2>&1 | grep -i 'v' | awk '{print$2}' | cut -c -4";
  char version_str[128] = {0};
  char buf[128];
  float version_float = 0.0;
  FILE *fp;

  fp = popen(cmd, "r");
  if(!fp)
  {
    WARNING("Unable to run the command '%s'.\n", cmd);
    return 0;
  }
  if(fgets(buf, sizeof(buf), fp) != NULL)
  {
    strncpy(version_str, buf, sizeof(version_str) - 1);
  }
  pclose(fp);

  if (version_str[0] == '\0') {
    return 0;
  }

  sscanf(version_str, "%f", &version_float);

  if(version_float - 14.8 > 0.0001)
  {
    return 1;
  }
  return 0;
}

/**
 * \brief Build command to run to send email
 * \param scheduler  Current scheduler object
 * \param user_email Email id to send mail to
 * \return The command to run
 */
char* get_email_command(scheduler_t* scheduler, char* user_email)
{
  PGresult* db_result_smtp;
  int i;
  GString* client_cmd;
  GHashTable* smtpvariables;
  char* temp_smtpvariable;
  char* final_command;

  db_result_smtp = database_exec(scheduler, smtp_values);
  if(PQresultStatus(db_result_smtp) != PGRES_TUPLES_OK || PQntuples(db_result_smtp) == 0)
  {
    PQ_ERROR(db_result_smtp, "unable to get conf variables for SMTP from sysconfig");
    return NULL;
  }
  client_cmd = g_string_new("");
  smtpvariables = g_hash_table_new_full(g_str_hash, g_str_equal, g_free, g_free);
  for(i = 0; i < PQntuples(db_result_smtp); i++)
  {
    if(PQget(db_result_smtp, i, "conf_value")[0])  //Not empty
    {
      g_hash_table_insert(smtpvariables, g_strdup(PQget(db_result_smtp, i, "variablename")),
                          g_strdup(PQget(db_result_smtp, i, "conf_value")));
    }
  }
  SafePQclear(db_result_smtp);
  if(g_hash_table_contains(smtpvariables, "SMTPHostName") && g_hash_table_contains(smtpvariables, "SMTPPort"))
  {
    if(g_hash_table_contains(smtpvariables, "SMTPStartTls"))
    {
      temp_smtpvariable = (char *)g_hash_table_lookup(smtpvariables, "SMTPStartTls");
      if(g_strcmp0(temp_smtpvariable, "1") == 0)
      {
        g_string_append_printf(client_cmd, " -S smtp-use-starttls");
      }
    }
    if(g_hash_table_contains(smtpvariables, "SMTPAuth"))
    {
      temp_smtpvariable = (char *)g_hash_table_lookup(smtpvariables, "SMTPAuth");
      if(g_strcmp0(temp_smtpvariable, "L") == 0)
      {
        g_string_append_printf(client_cmd, " -S smtp-auth=login");
      }
      else if(g_strcmp0(temp_smtpvariable, "P") == 0)
      {
        g_string_append_printf(client_cmd, " -S smtp-auth=plain");
      }
      else if(g_strcmp0(temp_smtpvariable, "N") == 0)
      {
        g_string_append_printf(client_cmd, " -S smtp-auth=none");
      }
    }
    if(g_hash_table_contains(smtpvariables, "SMTPFrom"))
    {
      g_string_append_printf(client_cmd, " -S from=\"%s\"",
          (char *)g_hash_table_lookup(smtpvariables, "SMTPFrom"));
    }
    if(g_hash_table_contains(smtpvariables, "SMTPSslVerify"))
    {
      temp_smtpvariable = (char *)g_hash_table_lookup(smtpvariables, "SMTPSslVerify");
      g_string_append(client_cmd, " -S ssl-verify=");
      if(g_strcmp0(temp_smtpvariable, "I") == 0)
      {
        g_string_append(client_cmd, "ignore");
      }
      else if(g_strcmp0(temp_smtpvariable, "S") == 0)
      {
        g_string_append(client_cmd, "strict");
      }
      else if(g_strcmp0(temp_smtpvariable, "W") == 0)
      {
        g_string_append(client_cmd, "warn");
      }
    }
    g_string_append_printf(client_cmd, " -S v15-compat");
    if(!check_mta_support())
    {
      g_string_append_printf(client_cmd, " -S smtp=\"");
    }
    else
    {
      g_string_append_printf(client_cmd, " -S mta=\"");
    }
    /* use smtps only if port is not 25 or SMTPStartTls is provided */
    if((g_strcmp0((char *)g_hash_table_lookup(smtpvariables, "SMTPPort"), "25") !=  0) 
        || g_strcmp0((char *)g_hash_table_lookup(smtpvariables, "SMTPStartTls"), "1") == 0)
    {
      g_string_append_printf(client_cmd, "smtps://");
    }
    else
    {
      g_string_append_printf(client_cmd, "smtp://");
    }
    if(g_hash_table_contains(smtpvariables, "SMTPAuthUser"))
    {
      temp_smtpvariable = g_hash_table_lookup(smtpvariables, "SMTPAuthUser");
      g_string_append_uri_escaped(client_cmd, temp_smtpvariable, NULL, TRUE);
      if(g_hash_table_lookup(smtpvariables, "SMTPAuthPasswd"))
      {
        g_string_append_printf(client_cmd, ":");
        temp_smtpvariable = g_hash_table_lookup(smtpvariables, "SMTPAuthPasswd");
        g_string_append_uri_escaped(client_cmd, temp_smtpvariable, NULL, TRUE);
      }
      g_string_append_printf(client_cmd, "@");
      g_string_append_printf(client_cmd, "%s:%s\"", (char *)g_hash_table_lookup(smtpvariables, "SMTPHostName"),
          (char *)g_hash_table_lookup(smtpvariables, "SMTPPort"));
    }
    temp_smtpvariable = NULL;
    final_command = g_strdup_printf(EMAIL_BUILD_CMD, scheduler->email_command,
                  client_cmd->str, scheduler->email_subject, user_email);
  }
  else
  {
    NOTIFY("Unable to send email. SMTP host or port not found in the configuration.\n"
        "Please check Configuration Variables.");
    final_command = NULL;
  }
  g_hash_table_destroy(smtpvariables);
  g_string_free(client_cmd, TRUE);
  return final_command;
}