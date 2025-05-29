/*
 SPDX-FileCopyrightText: © 2010-2011 Hewlett-Packard Development Company, L.P.
 SPDX-FileCopyrightText: © 2015 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

/*!
 * \file
 * \brief Scheduler API for agents.
*/

/* local includes */
#include "libfossscheduler.h"
#include "libfossdb.h"
#include "fossconfig.h"

/* unix includes */
#include <stdio.h>
#include <getopt.h>
#include <libgen.h>
#include <glib.h>

/* ************************************************************************** */
/* **** Locals ************************************************************** */
/* ************************************************************************** */

volatile gint items_processed; ///< The number of items processed by the agent
volatile int alive;            ///< If the agent has updated with a hearbeat
char buffer[2048];   ///< The last thing received from the scheduler
int valid;           ///< If the information stored in buffer is valid
int sscheduler;      ///< Whether the agent was started by the scheduler
int userID;          ///< The id of the user that created the job
int groupID;         ///< The id of the group of the user that created the job
int jobId;           ///< The id of the job
char* module_name = NULL;   ///< The name of the agent
int should_connect_to_db = 1; /// Default: connect to DB unless explicitly disabled

/** Check for an agent in DB */
const static char* sql_check = "\
  SELECT * FROM agent \
    WHERE agent_name = '%s' AND agent_rev='%s.%s'";

/** Insert new agent in DB */
const static char* sql_insert = "\
  INSERT INTO agent (agent_name, agent_rev, agent_desc) \
    VALUES ('%s', '%s.%s', '%s')";

/** System configuration settings */
fo_conf* sysconfig = NULL;
/** System configuration directory */
char* sysconfigdir = NULL;

/* these will be freed in fo_scheduler_disconnect */
extern GRegex* fo_conf_parse;
extern GRegex* fo_conf_replace;

/**
* Global verbose flags that agents should use instead of specific verbose
* flags. This is used by the scheduler to turn verbose on a particular agent
* on during run time. When the verbose flag is turned on by the scheduler
* the on_verbose function will be called. If nothing needs to be done when
* verbose is turned on, simply pass NULL to scheduler_connect
*/
int agent_verbose;

/**
* @brief Internal function to send a heartbeat to the
* scheduler along with the number of items processed.
 *
 * \note Agents should NOT call this function directly.
 * \note This is the alarm SIGALRM function.
* @return void
 * @todo These functions are not safe for a signal handler
*/
void fo_heartbeat()
{
  int processed = g_atomic_int_get(&items_processed);
  // TODO these functions are not safe for a signal handler
  fprintf(stdout, "HEART: %d %d\n", processed, alive);
  fflush(stdout);
  fflush(stderr);
  alarm(ALARM_SECS);
  alive = FALSE;
}

/**
* @brief Checks that the agent is already in the agent table.
*
* This uses the VERSION and COMMIT_HASH in the system configuration information to
* determine if a new agent record needs to be created for this agent in the
* database.
*/
void fo_check_agentdb(PGconn* db_conn)
{
  PGresult* db_result = NULL;
  char* db_sql = NULL;

  db_sql = g_strdup_printf(sql_check, module_name,
    fo_config_get(sysconfig, module_name, "VERSION", NULL),
    fo_config_get(sysconfig, module_name, "COMMIT_HASH", NULL));
  db_result = PQexec(db_conn, db_sql);
  if (PQresultStatus(db_result) != PGRES_TUPLES_OK)
  {
    fprintf(stderr, "FATAL %s.%d: unable to check agent table: %s",
      __FILE__, __LINE__, PQresultErrorMessage(db_result));
    fflush(stderr);
    PQfinish(db_conn);
    PQclear(db_result);
    g_free(db_sql);
    exit(252);
  }

  if (PQntuples(db_result) != 1)
  {
    g_free(db_sql);
    PQclear(db_result);

    db_sql = g_strdup_printf(sql_insert, module_name,
      fo_config_get(sysconfig, module_name, "VERSION", NULL),
      fo_config_get(sysconfig, module_name, "COMMIT_HASH", NULL),
      fo_config_get(sysconfig, module_name, "DESCRIPTION", NULL));
    db_result = PQexec(db_conn, db_sql);
    if (PQresultStatus(db_result) != PGRES_COMMAND_OK)
    {
      fprintf(stderr, "FATAL %s.%d: unable to insert into agent table: %s",
        __FILE__, __LINE__, PQresultErrorMessage(db_result));
      fflush(stderr);
      PQfinish(db_conn);
      PQclear(db_result);
      g_free(db_sql);
      exit(251);
    }
  }

  g_free(db_sql);
  PQclear(db_result);
}

/* ************************************************************************** */
/* **** Global Functions **************************************************** */
/* ************************************************************************** */

/**
* @brief This function must be called by agents to let the scheduler know they
* are alive and how many items they have processed.
*
* @param i   This is the number of itmes processed since the last call to
* fo_scheduler_heart()
*
* @return void
*/
void fo_scheduler_heart(int i)
{
  g_atomic_int_add(&items_processed, i);
  alive = TRUE;

  fflush(stdout);
  fflush(stderr);
}

/**
 * @brief Establish a connection between an agent and the scheduler.
 * @param[in] argc     Command line agrument count
 * @param[in] argv     Command line agrument vector
 * @param[out] db_conn DB Connection
 * @param[out] db_conf DB conf file
 * @sa fo_scheduler_connect()
 */
void fo_scheduler_connect_conf(int* argc, char** argv, PGconn** db_conn, char** db_conf)
{
  /* locals */
  GError* error = NULL;
  GOptionContext* parsed;
  fo_conf* version;
  char fname[FILENAME_MAX + 1];

  char* db_config = NULL;
  char* db_error = NULL;

  /* command line options */
  GOptionEntry options[] =
    {
      {"config", 'c', 0, G_OPTION_ARG_STRING, &sysconfigdir, ""},
      {"userID", 0, 0, G_OPTION_ARG_INT, &userID, ""},
      {"groupID", 0, 0, G_OPTION_ARG_INT, &groupID, ""},
      {"scheduler_start", 0, 0, G_OPTION_ARG_NONE, &sscheduler, ""},
      {"jobId", 0, 0, G_OPTION_ARG_INT, &jobId, ""},
      {NULL}
    };

  /* initialize memory associated with agent connection */
  module_name = g_strdup(basename(argv[0]));
  sysconfigdir = DEFAULT_SETUP;
  items_processed = 0;
  valid = 0;
  sscheduler = 0;
  userID = -1;
  groupID = -1;
  agent_verbose = 0;
  memset(buffer, 0, sizeof(buffer));

  if (sysconfig != NULL)
  {
    LOG_WARNING("fo_scheduler_connect() has already been called.");
    sscheduler = 1;
    return;
  }

  if (getenv("FO_SYSCONFDIR"))
    sysconfigdir = getenv("FO_SYSCONFDIR");

  /* parse command line options */
  parsed = g_option_context_new("");
  g_option_context_add_main_entries(parsed, options, NULL);
  g_option_context_set_ignore_unknown_options(parsed, TRUE);
  g_option_context_set_help_enabled(parsed, FALSE);
  g_option_context_parse(parsed, argc, &argv, NULL);
  g_option_context_free(parsed);

  /* load system configuration */
  if (sysconfigdir)
  {
    snprintf(fname, FILENAME_MAX, "%s/%s", sysconfigdir, "fossology.conf");
    sysconfig = fo_config_load(fname, &error);
    if (error)
    {
      fprintf(stderr, "FATAL %s.%d: unable to open system configuration: %s\n",
        __FILE__, __LINE__, error->message);
      exit(255);
    }

    snprintf(fname, FILENAME_MAX, "%s/mods-enabled/%s/VERSION",
      sysconfigdir, module_name);

    version = fo_config_load(fname, &error);
    if (error)
    {
      fprintf(stderr, "FATAL %s.%d: unable to open VERSION configuration: %s\n",
        __FILE__, __LINE__, error->message);
      exit(254);
    }

    fo_config_join(sysconfig, version, &error);
    if (error)
    {
      fprintf(stderr, "FATAL %s.%d: unable to join configuration files: %s\n",
        __FILE__, __LINE__, error->message);
      exit(250);
    }


    fo_config_free(version);
  }

  /* create the database connection only if needed */
  if (db_conn && should_connect_to_db)  // Add a condition
  {
    db_config = g_strdup_printf("%s/Db.conf", sysconfigdir);
    (*db_conn) = fo_dbconnect(db_config, &db_error);
    if (db_conf)
      *db_conf = db_config;
    else
      g_free(db_config);
    if (db_error)
    {
      fprintf(stderr, "FATAL %s.%d: unable to open database connection: %s\n",
        __FILE__, __LINE__, db_error);
      fflush(stderr);
      exit(253);
    }
  }

  /* send "OK" to the scheduler */
  if (sscheduler)
  {
    /* check that the agent record exists */
    if (db_conn)
      fo_check_agentdb(*db_conn);

    if (fo_config_has_key(sysconfig, module_name, "VERSION"))
      fprintf(stdout, "VERSION: %s\n",
        fo_config_get(sysconfig, module_name, "VERSION", &error));
    else fprintf(stdout, "VERSION: unknown\n");
    fprintf(stdout, "\nOK\n");
    fflush(stdout);

    /* set up the heartbeat() */
    signal(SIGALRM, fo_heartbeat);
    alarm(ALARM_SECS);
  }

  fflush(stdout);
  fflush(stderr);

  alive = TRUE;
}

/**
* @brief Establish a connection between an agent and the scheduler.
*
* Steps taken by this function:
*   - initialize memory associated with agent connection
*   - send "SPAWNED" to the scheduler
*   - receive the number of items between notifications
*   - check the nfs mounts for the agent
*   - set up the heartbeat()
*
* Making a call to this function should be the first thing that an agent does
* after parsing its command line arguments.
*
* If the database connection passed is NULL, then this will not return a
* database connection, and will not check the agent's database record.
*
* @param argc     pointer to the number of arguments passed to the agent
* @param argv     the command line arguments for the agent
* @param db_conn  pointer to the location for the database connection
* @return void
*/
void fo_scheduler_connect(int* argc, char** argv, PGconn** db_conn)
{
  fo_scheduler_connect_conf(argc, argv, db_conn, NULL);
}

/**
 * @brief Make a connection from an agent to the scheduler and create a DB
 * manager as well.
 * @param[out] dbManager New DB manager
 */
void fo_scheduler_connect_dbMan(int* argc, char** argv, fo_dbManager** dbManager)
{
  char* dbConf;
  PGconn* dbConnection;
  fo_scheduler_connect_conf(argc, argv, &dbConnection, &dbConf);
  *dbManager = fo_dbManager_new_withConf(dbConnection, dbConf);
  free(dbConf);
}

/**
* @brief Disconnect the scheduler connection.
*
* Making a call to this function should be the last thing that an agent does
* before exiting. Any error reporting to stdout or stderr will not work after
* this function has finished execution.
* @param retcode Return code to the scheduler
*/
void fo_scheduler_disconnect(int retcode)
{
  if (module_name != NULL) {
    /* send "CLOSED" to the scheduler */
    if (sscheduler)
    {
      fo_heartbeat();
      fprintf(stdout, "\nBYE %d\n", retcode);
      fflush(stdout);

      valid = 0;
      sscheduler = 0;

      g_free(module_name);
    }

    if (strcmp(sysconfigdir, DEFAULT_SETUP))
      g_free(sysconfigdir);

    fo_config_free(sysconfig);
  }
  g_regex_unref(fo_conf_parse);
  g_regex_unref(fo_conf_replace);

  sysconfigdir = NULL;
  sysconfig = NULL;
  fo_conf_parse = NULL;
  fo_conf_replace = NULL;
  module_name = NULL;

  fflush(stdout);
  fflush(stderr);
}

/**
* @brief Get the next data to process from the scheduler.
*
* It is the job of the agent to decide how this string is
* interpreted.
*
* Steps taken by this function:
*   - get the next line from the scheduler
*     - if the scheduler has paused this agent this will block till unpaused
*   - check for "CLOSE" from scheduler, return NULL if received
*   - check for "VERBOSE" from scheduler
*     - if this is received turn the verbose flag to whatever is specified
*     - a new line must be received, perform same task (i.e. recursive call)
*   - check for "END" from scheduler, if received print OK and recurse
*     - this is used to simplify communications within the scheduler
*   - return whatever has been received
*
* @return char* for the next thing to analyze, NULL if there is nothing
*          left in this job, in which case the agent should close
*/
char* fo_scheduler_next()
{
  fflush(stdout);

  /* get the next line from the scheduler and possibly WAIT */
  while (fgets(buffer, sizeof(buffer), stdin) != NULL)
  {
    if (agent_verbose)
      printf("\nNOTE: received %s\n", buffer);
    if (strncmp(buffer, "CLOSE", 5) == 0)
      break;
    if (strncmp(buffer, "END", 3) == 0)
    {
      fprintf(stdout, "\nOK\n");
      fflush(stdout);
      fflush(stderr);
      valid = 0;
      continue;
    }
    else if (strncmp(buffer, "VERBOSE", 7) == 0)
    {
      agent_verbose = atoi(&buffer[8]);
      valid = 0;
      continue;
    }
    else if (strncmp(buffer, "VERSION", 7) == 0)
    {
      if (fo_config_has_key(sysconfig, module_name, "VERSION"))
        fprintf(stdout, "VERSION: %s\n",
          fo_config_get(sysconfig, module_name, "VERSION", NULL));
      else fprintf(stdout, "VERSION: unknown\n");
      fflush(stdout);
      fflush(stderr);
      valid = 0;
      continue;
    }

    buffer[strlen(buffer) - 1] = '\0';
    valid = 1;
    return buffer;
  }

  valid = 0;

  fflush(stdout);
  fflush(stderr);

  return NULL;
}

/**
* @brief Get the last read string from the scheduler.
*
* @return Returns the string buffer if it is valid.
* If it is not valid, return NULL.
* The buffer is not valid if the last received data from the scheduler
* was a command, rather than data to operate on.
*/
char* fo_scheduler_current()
{
  return valid ? buffer : NULL;
}

/**
* @brief Sets something special about the agent within the scheduler.
*
* Possible Options:
*   `SPECIAL_NOKILL`: instruct the scheduler to not kill the agent
*
* @param option  the option to set
* @param value   whether to set the option to true or false
*/
void fo_scheduler_set_special(int option, int value)
{
  fprintf(stdout, "SPECIAL: %d %d\n", option, value);
  fflush(stdout);
}

/**
* @brief Gets if a particular special attribute is set in the scheduler.
*
* Possible Options:
* - `SPECIAL_NOKILL`   : the agent will not be killed if it stops updating status
* - `SPECIAL_EXCLUSIVE`: the agent cannot run simultaneously with any other agent
* - `SPECIAL_NOEMAIL`  : the scheduler will not send notification emails for this agent
* - `SPECIAL_LOCAL`    : the agent is required to run on the same machine as the scheduler
*
* @param option  the relevant option to the get the value of
* @return  if the value of the special option was true
*/
int fo_scheduler_get_special(int option)
{
  int value;

  fprintf(stdout, "GETSPECIAL: %d\n", option);
  fflush(stdout);

  if (fscanf(stdin, "VALUE: %d\n", &value) != 1)
    return 0;
  return value;
}

/**
* @brief Gets the id of the job that the agent is running
*
* @return the job id
*/
int fo_scheduler_jobId()
{
  return jobId;
}

/**
* @brief Gets the id of the user that created the job that the agent is running
*
* @return the user id
*/
int fo_scheduler_userID()
{
  return userID;
}

/**
* @brief Gets the id of the group that created the job that the agent is running
*
* @return the group id
*/
int fo_scheduler_groupID()
{
  return groupID;
}

/**
* @brief gets a system configuration variable from the configuration data.
*
* This function should be called after fo_scheduler_connect has been called.
* This is because the configuration data it not loaded until after that.
*
* @param sectionname the group of the variable
* @param variablename the name of the variable
* @return the value of the variable
*/
char* fo_sysconfig(const char* sectionname, const char* variablename)
{
  GError* error = NULL;
  char* ret;

  ret = fo_config_get(
    sysconfig,
    sectionname,
    variablename,
    &error);

  fflush(stdout);
  fflush(stderr);

  if (error)
  {
    g_clear_error(&error);
    ret = NULL;
  }

  return ret;
}