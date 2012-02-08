/***************************************************************
Copyright (C) 2010-2011 Hewlett-Packard Development Company, L.P.

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

***************************************************************/

/*!
 * \file libfossscheduler.c
 * \brief Scheduler API for agents.
*/

/* local includes */
#include "libfossscheduler.h"
#include "fossconfig.h"

/* unix includes */
#include <stdio.h>
#include <getopt.h>
#include <libgen.h>

/* ************************************************************************** */
/* **** Locals ************************************************************** */
/* ************************************************************************** */

int      items_processed;   ///< the number of items processed by the agent
char     buffer[2048];      ///< the last thing received from the scheduler
int      alive;
int      valid;             ///< if the information stored in buffer is valid
int      found;             ///< if the agent is even connected to the scheduler
fo_conf* sysconfig;
char* sysconfigdir;
char* module_name;

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
 * Agents should NOT call this function directly.
 *
 * This is the alarm SIGALRM function.
 * @return void
 */
void fo_heartbeat()
{
  fprintf(stdout, "HEART: %d %d\n", items_processed, alive);
  fflush(stdout);
  alarm(ALARM_SECS);
  alive = FALSE;
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
void  fo_scheduler_heart(int i)
{
  items_processed += i;
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
 * @param argc
 * @param argv
 * @returns void
 */
void fo_scheduler_connect(int* argc, char** argv)
{
  GError* error = NULL;
  GTree* keys;
  GOptionContext* parsed;
  fo_conf* version;
  char  fname[FILENAME_MAX + 1];

  GOptionEntry options[] =
  {
      {"config",          'c', 0, G_OPTION_ARG_STRING, &sysconfigdir, ""},
      {"scheduler_start",   0, 0, G_OPTION_ARG_NONE,   &found,        ""},
      {NULL}
  };

  /* initialize memory associated with agent connection */
  sysconfigdir = DEFAULT_SETUP;
  module_name = g_strdup(basename(argv[0]));
  items_processed = 0;
  memset(buffer, 0, sizeof(buffer));
  valid = 0;
  found = 0;
  agent_verbose = 0;

  /* parse command line options */
  parsed = g_option_context_new("");
  g_option_context_add_main_entries(parsed, options, NULL);
  g_option_context_set_ignore_unknown_options(parsed, TRUE);
  g_option_context_set_help_enabled(parsed, FALSE);
  g_option_context_parse(parsed, argc, &argv, NULL);
  g_option_context_free(parsed);

  /* load system configuration */
  if(sysconfigdir) {
    snprintf(fname, FILENAME_MAX, "%s/%s", sysconfigdir, "fossology.conf");
    sysconfig = fo_config_load(fname, &error);
    if(error)
    {
      fprintf(stderr, "ERROR %s.%d: unable to open system configuration: %s\n",
          __FILE__, __LINE__, error->message);
      exit(-1);
    }

    snprintf(fname, FILENAME_MAX, "%s/mods-enabled/%s/VERSION",
        sysconfigdir, module_name);

    FILE * file = fopen(fname, "r");
    if (file)
    {
      version = fo_config_load(fname, &error);
      if(error)
      {
        fprintf(stderr, "ERROR %s.%d: unable to open VERSION configuration: %s\n",
            __FILE__, __LINE__, error->message);
        exit(-1);
      }

      if((keys = g_tree_lookup(version->group_map, module_name)) != NULL)
      {
        keys = g_tree_ref(keys);
        g_tree_insert(sysconfig->group_map, module_name, keys);
      }
      fo_config_free(version);
      fclose(file);
    }
  }

  /* send "OK" to the scheduler */
  if(found)
  {
    if(fo_config_has_key(sysconfig, module_name, "VERSION"))
      fprintf(stdout, "VERSION: %s\n",
          fo_config_get(sysconfig, module_name, "VERSION", &error));
    else fprintf(stdout, "VERSION: unknown\n");
    fprintf(stdout, "\nOK\n");
    fflush(stdout);

    /* TODO check the nfs mounts for the agent */

    /* set up the heartbeat() */
    signal(SIGALRM, fo_heartbeat);
    alarm(ALARM_SECS);
  }
}

/**
 * @brief Disconnect the scheduler connection.
 *
 * Making a call to this function should be the last thing that an agent does
 * before exiting. Any error reporting to stdout or stderr will not work after
 * this function has finished execution.
 */
void fo_scheduler_disconnect(int retcode)
{
  /* send "CLOSED" to the scheduler */
  if(found) 
  {
    fo_heartbeat();
    fprintf(stdout, "\nBYE %d\n", retcode);
    fflush(stdout);

    valid = 0;
    found = 0;

    g_free(module_name);
  }
}

/**
 * @brief Get the next data to process from the scheduler.
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
  while(fgets(buffer, sizeof(buffer), stdin) != NULL)
  {
    if(agent_verbose)
      printf("\nNOTE: received %s\n", buffer);
    if(strncmp(buffer, "CLOSE", 5) == 0)
      break;
    if(strncmp(buffer, "END", 3) == 0)
    {
      fprintf(stdout, "\nOK\n");
      fflush(stdout);
      valid = 0;
      continue;
    }
    else if(strncmp(buffer, "VERBOSE", 7) == 0)
    {
      agent_verbose = atoi(&buffer[8]);
      valid = 0;
      continue;
    }
    else if(strncmp(buffer, "VERSION", 7) == 0)
    {
      if(fo_config_has_key(sysconfig, module_name, "VERSION"))
        fprintf(stdout, "VERSION: %s\n",
             fo_config_get(sysconfig, module_name, "VERSION", NULL));
      else fprintf(stdout, "VERSION: unknown\n");
      fflush(stdout);
      fflush(stdout);
      valid = 0;
      continue;
    }

    buffer[strlen(buffer) - 1] = '\0';
    valid = 1;
    return buffer;
  }

  valid = 0;
  return NULL;
}

/**
 * @brief Get the last read string from the scheduler.
 *
 * @return Returns the string buffer if it is valid.  
 * If it is not valid, return NULL
 * The buffer is not valid if the last received data from the scheduler
 * was a command, rather than data to operate on.
 */
char* fo_scheduler_current()
{
  return valid ? buffer : NULL;
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
char* fo_sysconfig(char* sectionname, char* variablename) {
  GError* error = NULL;
  char* ret;

  ret = fo_config_get(
      sysconfig,
      sectionname,
      variablename,
      &error);

  return error != NULL ? NULL : ret;
}
