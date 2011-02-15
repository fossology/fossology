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

/**
 * Takes a format string and returns a new string that has all the correct time
 * stamps inserted into the format string. This will insert a time stamp at the
 * start of the string and after any new line characters.
 *
 * @param fmt the original formating string
 * @return the new formating string, this string needs to be freed upon completion
 */
char* insert_time_stamp(char* fmt)
{
  /* locals */
  char time_buffer[64];
  char time_stamp[64];
  char cpy[1024];
  char* ret = NULL;
  char* curr;
  time_t t = time(NULL);
  int nl_count = 1;

  /* create the time stamp */
  strftime(time_buffer, sizeof(time_buffer),"%F %T",localtime(&t));
  sprintf(time_stamp, "%s scheduler [%d] ::", time_buffer, getpid());
  memset(cpy, '\0', sizeof(cpy));
  strcpy(cpy, fmt);

  /* count the number of new lines in the string */
  for(curr = cpy; *curr; curr++)
  {
    if(*curr == '\n')
    {
      *curr = 0;
      nl_count++;
    }
  }

  /* allocate the new string and put in base time stamp */
  ret = (char*)calloc(strlen(fmt) + nl_count*strlen(time_stamp) + 1, sizeof(char));
  sprintf(ret, "%s", time_stamp);

  /* copy over the rest of the string and time stamps */
  for(curr = cpy; curr - cpy < strlen(fmt); curr++)
    if(*curr == '\n')
      sprintf(&ret[strlen(ret)], "%s\n%s", curr, time_stamp);

  return ret;
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
 * @param ... the remaining arguments
 * @return
 */
int lprintf(const char* fmt, ...)
{
  va_list args;
  int rc;

  va_start(args, fmt);
  rc = lprintf_v(fmt, args);
  va_end(args);

  return rc;
}

/**
 * TODO
 *
 * @param fmt
 * @param args
 * @return
 */
int lprintf_v(const char* fmt, va_list args)
{
  /* static used to determine if a '\n' needs to be printed */
  static int n_line = 1;

  /* locals */
  time_t t = time(NULL);
  char* tmp, * curr;
  char time_buf[64];
  int e_line;

  if(!log_file) log_open();
  if(!fmt) return 0;

  strftime(time_buf, sizeof(time_buf),"%F %T",localtime(&t));

  tmp = g_strdup_vprintf(fmt, args);
  e_line = tmp[strlen(tmp) - 1] == '\n';
  curr = strtok(tmp, "\n");
  while(curr != NULL)
  {
    if(n_line)
      if(fprintf(log_file, "%s scheduler [%d] :: ", time_buf, getpid()) == 0)
        return 0;

    if(fprintf(log_file, "%s", curr) == 0)
      return 0;

    n_line = ((curr = strtok(NULL, "\n")) != NULL);
    if(n_line)
      if(fprintf(log_file, "\n") == 0)
        return 0;
  }

  if(e_line)
  {
    n_line = 1;
    if(fprintf(log_file, "\n") == 0)
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
int lprintf_c(const char* fmt, ...)
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


