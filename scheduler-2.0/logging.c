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
#include <event.h>
#include <logging.h>

/* std library includes */
#include <time.h>

/* unix includes */
#include <sys/stat.h>
#include <sys/types.h>
#include <unistd.h>

/* glib includes */
#include <glib.h>

FILE* log_file = NULL;
char  log_name[FILENAME_MAX];
int   log_name_set = 0;

#ifndef LOG_DIR
#define LOG_DIR "/var/log/fossology/fossology.log"
#endif

/* ************************************************************************** */
/* **** local functions ***************************************************** */
/* ************************************************************************** */

/**
 * Utility function that will open the log file using whatever name is stored
 * in log_name. If the log name hasn't been set when this method is called
 * it will attempt to open the default that is stored in LOG_DIR.
 */
void log_open()
{
  if(!log_name_set)
  {
    set_log(LOG_DIR);
  }

  if((log_file = fopen(log_name, "a")) == NULL)
  {
    log_file = stderr;
    sprintf(log_name, "%s", LOG_DIR);
    if((log_file = fopen(log_name, "a")) == NULL)
    {
      log_file = stderr;
      FATAL("could not open %s for logging and %s failed", log_name, LOG_DIR)
      log_file = NULL;
    }
    else
      ERROR("cout not open %s for logging, using default", log_name)
  }

  lprintf("log openned\n");
}

/**
 * Event used to pass log messages to the main thread for processing instead of
 * processing them in a side thread. This is used so that prints will happen
 * in the correct order instead of intermixed.
 *
 * @param str the string that will be printed to the log file
 */
void log_event(char* str)
{
  lprintf("%s", str);
  g_free(str);
}

/* ************************************************************************** */
/* **** logging functions *************************************************** */
/* ************************************************************************** */

/**
 * Changes the name of the file that will be logged to. If a log file is
 * already open when this gets called, this will close the old log file.
 * This forces the logging function to attempt to open a new one before it
 * logs again.
 *
 * @param name the new name of the log file
 */
void set_log(const char* name)
{
  struct stat stats;

  /* make sure that the log is closed before openning a new one */
  if(log_file != NULL && log_file != stdout && log_file != stderr)
    fclose(log_file);
  log_file = NULL;

  memset(log_name, '\0', sizeof(log_name));
  if ((stat(name,&stats) == 0) && S_ISDIR(stats.st_mode))
    sprintf(log_name, "%s/fossology.log", name);
  else
    sprintf(log_name, "%s", name);

  /* make sure that the name provided is valid */
  if(log_name[0] == '\0')
  {
    log_file = stderr;
    errno = EINVAL;
    ERROR("invalid file name provided to set_log(), using default: %s", LOG_DIR);
    sprintf(log_name, "%s", LOG_DIR);
    log_file = NULL;
  }

  /* check special cases */
  if(strcmp(log_name, "stderr") == 0) { log_file = stderr; return; }
  if(strcmp(log_name, "stdout") == 0) { log_file = stdout; return; }

  log_name_set = 1;
}

/**
 * Gets the name of the file that will be logged to. The return of this
 * function is const since set_log should be used if the log name is to
 * change.
 *
 * @return the name of the log file
 */
const char* lname()
{
  return log_name;
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
int lprintf(const char* fmt, ...)
{
  va_list args;
  int rc;

  if(!fmt) return 0;
  if(!log_file) log_open();

  va_start(args, fmt);
  rc = vlprintf(log_file, fmt, args);
  va_end(args);

  return rc;
}

/**
 * agent logging function. Since the agents will log to a different location
 * this takes a file to print the log to. Other than that this will work exactly
 * like lprintf in the all line will be prepended by a time stamp.
 *
 * @param dst the destination file
 * @param fmt the formating string
 * @return 1 on success, o otherwise
 */
int alprintf(FILE* dst, const char* fmt, ...)
{
  va_list args;
  int rc;

  if(!fmt) return 0;
  if(!dst) return 0;

  va_start(args, fmt);
  rc = vlprintf(dst, fmt, args);
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
int vlprintf(FILE* dst, const char* fmt, va_list args)
{
  /* static used to determine if a '\n' needs to be printed */
  static int n_line = 1;

  /* locals */
  time_t t = time(NULL);
  char* tmp, * curr;
  char time_buf[64];
  int e_line;

  if(!dst) return 0;
  if(!fmt) return 0;

  strftime(time_buf, sizeof(time_buf),"%F %T",localtime(&t));

  tmp = g_strdup_vprintf(fmt, args);
  e_line = tmp[strlen(tmp) - 1] == '\n';
  curr = strtok(tmp, "\n");
  while(curr != NULL)
  {
    if(n_line && fprintf(dst, "%s scheduler [%d] :: ", time_buf, getpid()) == 0)
        return 0;

    if(fprintf(dst, "%s", curr) == 0)
      return 0;

    n_line = ((curr = strtok(NULL, "\n")) != NULL);
    if(n_line && fprintf(dst, "\n") == 0)
        return 0;
  }

  if(e_line)
  {
    n_line = 1;
    if(fprintf(dst, "\n") == 0)
      return 0;
  }

  g_free(tmp);
  return 1;
}

/**
 * TODO
 *
 * @param fmt
 * @return
 */
int clprintf(const char* fmt, ...)
{
  va_list args;
  char* buf;

  if(!log_file) log_open();
  if(!fmt) return 0;

  va_start(args, fmt);
  buf = g_strdup_vprintf(fmt, args);
  va_end(args);

  if(buf == NULL)
    return 0;

  event_signal(log_event, buf);
  return 1;
}


