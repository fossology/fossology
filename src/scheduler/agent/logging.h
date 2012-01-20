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

#ifndef LOGGING_H_INCLUDE
#define LOGGING_H_INCLUDE

/* std library includes */
#include <errno.h>
#include <stdarg.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>

/* unix library includes */
#include <pthread.h>

extern int   verbose;
extern FILE* log_file;
extern char  log_name[FILENAME_MAX];

/* ************************************************************************** */
/* **** ERROR MACROS ******************************************************** */
/* ************************************************************************** */

/*
 * The following macro definitions are meant to act as their own statement in
 * the c language. To acomplish this, they needed to not only be able to be used
 * in the situation of an "if" statement with no body, but also require that
 * they are followed by a ";".
 *
 * To do this the "do {} while(0)" loop is used, the loop will not appear in
 * result flow control since it does not modify the flow of control, but it is
 * a single statement that requires a ";" at the end to be syntatically correct
 */

/** Macro that is called when the scheduler hits a fatal error */
#define FATAL(...)  do { \
            lprintf("FATAL %s.%d: ", __FILE__, __LINE__); \
            lprintf(__VA_ARGS__); \
            lprintf("\n"); \
            lprintf("FATAL errno is: %s\n", strerror(errno)); \
            exit(-1); } while(0)

/** Macro that is called when a thread generated a fatal error */
#define THREAD_FATAL(file, ...) do { \
            alprintf(file, "THREAD_FATAL %s.%d: ", __FILE__, __LINE__); \
            alprintf(file, __VA_ARGS__); \
            alprintf(file, "\n"); \
            alprintf(file, "THREAD_FATAL errno is: %s\n", strerror(errno)); \
            g_thread_exit(NULL); } while(0)

/** Macro that is called when any type of error is generated */
#define ERROR(...) do { \
            lprintf("ERROR %s.%d: ", __FILE__, __LINE__); \
            lprintf(__VA_ARGS__); \
            lprintf("\n"); } while(0)

/** Macro that is called when any type of postgresql error is generated */
#define PQ_ERROR(pg_r, ...) { \
            lprintf("ERROR %s.%d: ", __FILE__, __LINE__); \
            lprintf(__VA_ARGS__); \
            lprintf("\n"); \
            lprintf("ERROR postgresql error: %s\n", PQresultErrorMessage(pg_r)); } \
            PQclear(pg_r)

/** Macro that is called when a notification is generated */
#define NOTIFY(...) if(verbose > 0) do { \
            lprintf("NOTIFY: "); \
            lprintf(__VA_ARGS__); \
            lprintf("\n"); } while(0)

/** Macro that is called when any type of warning is generated */
#define WARNING(...) if(verbose > 1) do { \
            lprintf("WARNING %s.%d: ", __FILE__, __LINE__); \
            lprintf(__VA_ARGS__); \
            lprintf("\n"); } while(0)

/* verbose macros, if changing from greater than scheme to bit mask, just */
/* change these the the TVERBOSE# macro when a test of verbose is needed, */
/* this happpens when printing from another thread. The other verbose     */
/* macro makes the syntax better everywhere else                          */
#define TVERB_JOB   (verbose & 0x8)
#define TVERB_AGENT (verbose & 0x10)
#define TVERB_SCHED (verbose & 0x20)
#define TVERB_EVENT (verbose & 0x40)
#define TVERB_INTER (verbose & 0x80)
#define TVERB_DATAB (verbose & 0x100)
#define TVERB_HOST  (verbose & 0x200)
#define V_JOB(...)       if(TVERB_JOB)    lprintf(__VA_ARGS__);
#define V_AGENT(...)     if(TVERB_AGENT)  lprintf(__VA_ARGS__);
#define V_SCHED(...)     if(TVERB_SCHED)  lprintf(__VA_ARGS__);
#define V_EVENT(...)     if(TVERB_EVENT)  lprintf(__VA_ARGS__);
#define V_INTERFACE(...) if(TVERB_INTER) clprintf(__VA_ARGS__);
#define V_DATABASE(...)  if(TVERB_DATAB)  lprintf(__VA_ARGS__);
#define V_HOST(...)      if(TVERB_HOST)   lprintf(__VA_ARGS__);

/* ************************************************************************** */
/* **** logging functions *************************************************** */
/* ************************************************************************** */

const char* lname();
void set_log(const char* name);
int  lprintf(const char* fmt, ...);
int  alprintf(FILE* dst, const char* fmt, ...);
int  vlprintf(FILE* dst, const char* fmt, va_list args);
int  clprintf(const char* fmt, ...);


#endif /* LOGGING_H_INCLUDE */
