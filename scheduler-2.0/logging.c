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
#include <logging.h>

/* std library includes */
#include <time.h>

/* unix includes */
#include <sys/stat.h>
#include <sys/types.h>
#include <unistd.h>

FILE* log_file = NULL;
char  log_name[FILENAME_MAX];
int   log_name_set = 0;

#ifndef LOG_DIR
#define LOG_DIR "/var/log/fossology/fossology.log"
#endif

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
  char time_buffer[64];
  va_list args;
  time_t t;
  int rc;

  if(!log_file) log_open();
  if(!fmt) return 0;

  t = time(NULL);
  strftime(time_buffer, sizeof(time_buffer),"%F %T",localtime(&t));

  va_start(args, fmt);
  fprintf(log_file, "%s scheduler [%d] :: ", time_buffer, getpid());
  rc = vfprintf(log_file, fmt, args);
  fflush(log_file);
  va_end(args);

  return rc;
}

/**
 * TODO
 *
 * @param fmt
 * @param ...
 * @return
 */
int lprintf_t(const char* fmt, ...)
{
  va_list args;
  int rc;

  if(!log_file) log_open();
  if(!fmt) return 0;

  va_start(args, fmt);
  rc = vfprintf(log_file, fmt, args);
  fflush(log_file);
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
  char time_buffer[64];
  time_t t;
  int rc;

  if(!log_file) log_open();
  if(!fmt) return 0;

  t = time(NULL);
  strftime(time_buffer, sizeof(time_buffer),"%F %T",localtime(&t));

  fprintf(log_file, "%s scheduler [%d] :: ", time_buffer, getpid());
  rc = vfprintf(log_file, fmt, args);
  fflush(log_file);

  return rc;
}


