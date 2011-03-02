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

extern int verbose;

/* ************************************************************************** */
/* **** ERROR MACROS ******************************************************** */
/* ************************************************************************** */

/** Macro that is called when the scheduler hits a fatal error */
#define FATAL(...)  { \
            lprintf("FATAL %s.%d: ", __FILE__, __LINE__); \
            lprintf(__VA_ARGS__); \
            lprintf("\n"); \
            lprintf("FATAL errno is: %s\n", strerror(errno)); \
            exit(-1); }

/** Macro that is called when a thread generated a fatal error */
#define THREAD_FATAL(...) { \
            lprintf("THREAD_FATAL %s.%d: ", __FILE__, __LINE__); \
            lprintf(__VA_ARGS__); \
            lprintf("\n"); \
            lprintf("THREAD_FATAL errno is: %s\n", strerror(errno)); \
            pthread_exit(NULL); }

/** Macro that is called when any type of error is generated */
#define ERROR(...) { \
            lprintf("ERROR %s.%d: ", __FILE__, __LINE__); \
            lprintf(__VA_ARGS__); \
            lprintf("\n"); \
            lprintf("ERROR errno is: %s\n", strerror(errno)); }

/** Macro that is called when any type of warning is generated */
#define WARNING(...) { \
            lprintf("WARNING %s.%d: ", __FILE__, __LINE__); \
            lprintf(__VA_ARGS__); \
            lprintf("\n"); \
            lprintf("WARNING errno is: %s\n", strerror(errno)); }

/* verbose macros, if changing from greater than scheme to bit mask, just */
/* change these the the TVERBOSE# macro when a test of verbose is needed, */
/* this happpens when printing from another thread. The other verbose     */
/* macro makes the syntax better everywhere else                          */
#define TVERBOSE1 verbose & 1
#define TVERBOSE2 verbose & 2
#define TVERBOSE3 verbose & 4
#define TVERBOSE4 verbose & 8
#define VERBOSE1(...) if(TVERBOSE1) lprintf(__VA_ARGS__);
#define VERBOSE2(...) if(TVERBOSE2) lprintf(__VA_ARGS__);
#define VERBOSE3(...) if(TVERBOSE3) lprintf(__VA_ARGS__);
#define VERBOSE4(...) if(TVERBOSE4) lprintf(__VA_ARGS__);

/* ************************************************************************** */
/* **** logging functions *************************************************** */
/* ************************************************************************** */

const char* lname();
void set_log(const char* name);
int  lprintf(const char* fmt, ...);
int  vlprintf(const char* fmt, va_list args);
int  clprintf(const char* fmt, ...);


#endif /* LOGGING_H_INCLUDE */
