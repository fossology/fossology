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
/**
 * \file
 * \brief Log related operations
 */

#ifndef LOGGING_H_INCLUDE
#define LOGGING_H_INCLUDE

/* std library includes */
#include <errno.h>
#include <stdarg.h>
#include <stdio.h>
#include <stdlib.h>
#include <stdint.h>
#include <string.h>

/* other libraries */
#include <pthread.h>
#include <libpq-fe.h>
#include <glib.h>

/* ************************************************************************** */
/* **** Log file structures ************************************************* */
/* ************************************************************************** */

/**
 * Log structure. Holds data required for logging.
 */
typedef struct
{
    gchar* log_name;  ///< The name of the log file that will be printed to
    gchar* pro_name;  ///< What should be printed as the process name
    pid_t  pro_pid;   ///< The pid of the process
    FILE*  log_file;  ///< The log file itself
} log_t;

// global log file
extern log_t* main_log;

#define SCHE_PRONAME "scheduler"

/* ************************************************************************** */
/* **** ERROR MACROS ******************************************************** */
/* ************************************************************************** */

/*
 * The following macro definitions are meant to act as their own statement in
 * the C language. To accomplish this, they needed to not only be able to be used
 * in the situation of an "if" statement with no body, but also require that
 * they are followed by a ";".
 *
 * To do this the "do {} while(0)" loop is used, the loop will not appear in
 * result flow control since it does not modify the flow of control, but it is
 * a single statement that requires a ";" at the end to be syntactically correct
 */

/** Macro that is called when the scheduler hits a fatal error */
#define FATAL(...)  do { \
            lprintf(main_log, "FATAL %s.%d: ", __FILE__, __LINE__); \
            lprintf(main_log, __VA_ARGS__); \
            lprintf(main_log, "\n"); \
            lprintf(main_log, "FATAL errno is: %s\n", strerror(errno)); \
            exit(-2); } while(0)

/** Macro that is called when a thread generated a fatal error */
#define THREAD_FATAL(file, ...) do { \
            con_printf(file, "THREAD_FATAL %s.%d: ", __FILE__, __LINE__); \
            con_printf(file, __VA_ARGS__); \
            con_printf(file, "\n"); \
            con_printf(file, "THREAD_FATAL errno is: %s\n", strerror(errno)); \
            g_thread_exit(NULL); } while(0)

/** Macro that is called when any type of error is generated */
#define ERROR(...) do { \
            lprintf(main_log, "ERROR %s.%d: ", __FILE__, __LINE__); \
            lprintf(main_log, __VA_ARGS__); \
            lprintf(main_log, "\n"); } while(0)

/** Macro that is called when any type of postgresql error is generated */
#define PQ_ERROR(pg_r, ...) { \
            lprintf(main_log, "ERROR %s.%d: ", __FILE__, __LINE__); \
            lprintf(main_log, __VA_ARGS__); \
            lprintf(main_log, "\n"); \
            lprintf(main_log, "ERROR postgresql error: %s\n", PQresultErrorMessage(pg_r)); } \
            SafePQclear(pg_r)

/** Macros that is called when a notification is generated */
#define TEST_NOTIFY verbose > 0
#define NOTIFY(...) if(TEST_NOTIFY) do { \
            lprintf(main_log, "NOTE: ");           \
            lprintf(main_log, __VA_ARGS__);        \
            lprintf(main_log, "\n"); } while(0)

/** Macros that is called when any type of warning is generated */
#define TEST_WARNING verbose > 1
#define WARNING(...) if(TEST_WARNING) do {                  \
            lprintf(main_log, "WARNING %s.%d: ", __FILE__, __LINE__); \
            lprintf(main_log, __VA_ARGS__);                           \
            lprintf(main_log, "\n"); } while(0)

/* verbose macros, if changing from greater than scheme to bit mask, just */
/* change these the the TVERBOSE# macro when a test of verbose is needed, */
/* this happens when printing from another thread. The other verbose      */
/* macro makes the syntax better everywhere else                          */
#define TVERB_JOB     (verbose & 0x8)
#define TVERB_AGENT   (verbose & 0x10)
#define TVERB_SCHED   (verbose & 0x20)
#define TVERB_EVENT   (verbose & 0x40)
#define TVERB_INTER   (verbose & 0x80)
#define TVERB_DATAB   (verbose & 0x100)
#define TVERB_HOST    (verbose & 0x200)
#define TVERB_SPECIAL (verbose & 0x400)
#define V_JOB(...)       if(TVERB_JOB)     log_printf(__VA_ARGS__)
#define V_AGENT(...)     if(TVERB_AGENT)   log_printf(__VA_ARGS__)
#define V_SCHED(...)     if(TVERB_SCHED)   log_printf(__VA_ARGS__)
#define V_EVENT(...)     if(TVERB_EVENT)   log_printf(__VA_ARGS__)
#define V_INTERFACE(...) if(TVERB_INTER)   con_printf(main_log, __VA_ARGS__)
#define V_DATABASE(...)  if(TVERB_DATAB)   log_printf(__VA_ARGS__)
#define V_HOST(...)      if(TVERB_HOST)    log_printf(__VA_ARGS__)
#define V_SPECIAL(...)   if(TVERB_SPECIAL) log_printf(__VA_ARGS__)

/* ************************************************************************** */
/* **** logging functions *************************************************** */
/* ************************************************************************** */

log_t* log_new(gchar* log_name, gchar* pro_name, pid_t pro_pid);
log_t* log_new_FILE(FILE* log_file, gchar* log_name, gchar* pro_name, pid_t pro_pid);
void log_destroy(log_t* log);

int  lprintf (log_t* log, const char* fmt, ...);
int  clprintf(log_t* log, char* s_name, uint16_t s_line, const char* fmt, ...);
int  vlprintf(log_t* log, const char* fmt, va_list args);

#define log_printf(...)       lprintf(main_log, __VA_ARGS__)
#define con_printf(log, ...) clprintf(log, __FILE__, __LINE__, __VA_ARGS__)

#endif /* LOGGING_H_INCLUDE */
