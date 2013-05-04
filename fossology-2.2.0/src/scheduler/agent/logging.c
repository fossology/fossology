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
#include <event.h>
#include <logging.h>
#include <scheduler.h>

/* std library includes */
#define _GNU_SOURCE
#include <stdio.h>
#include <time.h>

/* unix includes */
#include <sys/stat.h>
#include <sys/types.h>
#include <unistd.h>

/* glib includes */
#include <glib.h>

/**
 * The main log, this is global because every function within the scheduler
 * hould have access to the main log.
 */
log_t* main_log = NULL;

/* ************************************************************************** */
/* **** local functions ***************************************************** */
/* ************************************************************************** */

/**
 * Since events take a single parameter, we need to create a structure when more
 * than one parameter is necessary.
 */
typedef struct
{
    log_t* log;
    gchar* msg;
} log_event_args;

/**
 * Event used to pass log messages to the main thread for processing instead of
 * processing them in a side thread. This is used so that prints will happen
 * in the correct order instead of intermixed.
 *
 * @param pass  the arguments for the event
 */
static void log_event(scheduler_t* scheduler, log_event_args* pass)
{
  lprintf(pass->log, "%s", pass->msg);
  g_free(pass->msg);
  g_free(pass);
}

/* ************************************************************************** */
/* **** logging functions *************************************************** */
/* ************************************************************************** */

/**
 * @brief Creates a new log
 *
 * This will open and set the parameters of a log_t type. This checks the name
 * given and checks if it is a directory. If it is a directory, it will try to
 * open a file named fossology.log inside the directory instead. If the file
 * cannot be openned, this will return NULL.
 *
 * @param log_name  the name or directory of the log file
 * @param pro_name  the name of the process printed to the log file, can be NULL
 * @param pro_pid   the pid of the process that this log file belongs to
 * @return          a new log_t stucture
 */
log_t* log_new(gchar* log_name, gchar* pro_name, pid_t pro_pid)
{
  struct stat stats;
  log_t* ret = g_new0(log_t, 1);

  /* set the process name */
  if(pro_name == NULL)
    ret->pro_name = g_strdup(SCHE_PRONAME);
  else
    ret->pro_name = g_strdup(pro_name);
  ret->pro_pid = pro_pid;

  /* set the logs name */
  if(strcmp(log_name, "stderr") != 0 && strcmp(log_name, "stdout") != 0 &&
      (stat(log_name, &stats) == 0) && S_ISDIR(stats.st_mode))
    ret->log_name = g_strdup_printf("%s/fossology.log", log_name);
  else
    ret->log_name = g_strdup(log_name);

  /* open the log file */
  if     (strcmp(ret->log_name, "stderr") == 0) { ret->log_file = stderr; }
  else if(strcmp(ret->log_name, "stdout") == 0) { ret->log_file = stdout; }
  else { ret->log_file = fopen(ret->log_name, "a"); }

  /* make sure that everything is valid */
  if(ret->log_file == NULL)
  {
    ERROR("could not open log file \"%s\"", ret->log_name);
    g_free(ret->pro_name);
    g_free(ret->log_name);
    return NULL;
  }

  return ret;
}

/**
 * @brief Creates a log file structure based on an already created FILE*
 *
 * @param log_file  the already existing FILE*
 * @param pro_name  the name of the process to write to the log file
 * @param pro_pid   the PID of the process to write to the log file
 * @return          a new log_t instance that can be used to write to
 */
log_t* log_new_FILE(FILE* log_file, gchar* log_name, gchar* pro_name, pid_t pro_pid)
{
  log_t* ret = g_new0(log_t, 1);

  if(pro_name == NULL)
    ret->pro_name = g_strdup(SCHE_PRONAME);
  else
    ret->pro_name = g_strdup(pro_name);
  ret->pro_pid = pro_pid;

  ret->log_name = g_strdup(log_name);
  ret->log_file = log_file;

  V_JOB("NEW_LOG: log_name: \"%s\", pro_name: \"%s\", pro_pid: %d, log_file: %p\n",
      ret->log_name, ret->pro_name, ret->pro_pid, ret->log_file);

  return ret;
}

/**
 * @brief Free memory associated with the log file.
 *
 * @param log  the log file to close
 */
void log_destroy(log_t* log)
{
  if(log->pro_name) g_free(log->pro_name);
  if(log->log_name) g_free(log->log_name);

  if(log->log_file && log->log_file != stdout && log->log_file != stderr)
    fclose(log->log_file);

  log->pro_name = NULL;
  log->log_name = NULL;

  if(log->log_file != stdout && log->log_file != stderr)
    log->log_file = NULL;

  g_free(log);
}

/**
 * main logging function. This will print the time stamp for the log and the
 * scheduler's pid, followed by whatever is to be printed to the log. This
 * function will also make sure that the log is open, and if it isn't open
 * it using whatever the log_name is currently set to. This should be used
 * almost identically to a normal printf
 *
 * @param fmt the format for the printed data
 * @return 1 on success, 0 otherwise
 */
int lprintf(log_t* log, const char* fmt, ...)
{
  va_list args;
  int rc;

  if(!fmt) return 0;

  va_start(args, fmt);
  if(log == NULL || log->log_file == NULL)
  {
    rc = vlprintf(main_log, fmt, args);
  }
  else
  {
    rc = vlprintf(log, fmt, args);
  }
  va_end(args);

  return rc;
}

/**
 * The provides the same functionality for lprintf as vprintf does for printf.
 * If somebody wanted to create a custom logging function, they could simply
 * use this function within a va_start va_end pair.
 *
 * @param fmt the formatting string for the print
 * @param args the arguemtn for the print in and form of a va_list
 * @return 1 on success, 0 otherwise
 */
int vlprintf(log_t* log, const char* fmt, va_list args)
{
  /* static used to determine if a '\n' needs to be printed */
  static int n_line = 1;

  /* locals */
  time_t t = time(NULL);
  char* tmp, * curr;
  char time_buf[64];
  int e_line;

  if(!fmt) return 0;
  if(!log) return 0;

  strftime(time_buf, sizeof(time_buf),"%F %T",localtime(&t));

  tmp = g_strdup_vprintf(fmt, args);
  e_line = tmp[strlen(tmp) - 1] == '\n';
  curr = strtok(tmp, "\n");
  while(curr != NULL)
  {
    if(n_line && fprintf(log->log_file, "%s %s [%d] :: ", time_buf,
        log->pro_name, log->pro_pid) == 0)
      return 0;

    if(fprintf(log->log_file, "%s", curr) == 0)
      return 0;

    n_line = ((curr = strtok(NULL, "\n")) != NULL);
    if(n_line && fprintf(log->log_file, "\n") == 0)
        return 0;
  }

  if(e_line)
  {
    n_line = 1;
    if(fprintf(log->log_file, "\n") == 0)
      return 0;
  }

  fflush(log->log_file);
  g_free(tmp);
  return 1;
}

/**
 * Function that allows for printing to the log file concurrently. This will
 * create an event that prints the log file instead of printing it itself. This
 * does have the disadvantage that two call of clprintf right next to each other
 * will not necessarily fall next to each other in the log.
 *
 * @param fmt  the format string like any normal printf function
 * @return  if the printf was successful.
 */
int clprintf(log_t* log, char* s_name, uint16_t s_line, const char* fmt, ...)
{
  va_list args;
  int ret = 1;
  log_event_args* pass;

  if(!fmt) return 0;
  if(!log) return 0;

  va_start(args, fmt);
  if(g_thread_self() != main_thread)
  {
    pass = g_new0(log_event_args, 1);
    pass->log = log;
    pass->msg = g_strdup_vprintf(fmt, args);
    event_signal_ext(log_event, pass, "log_event", s_name, s_line);
  }
  else
  {
    ret = vlprintf(log, fmt, args);
  }
  va_end(args);

  return ret;
}


