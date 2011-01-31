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

extern FILE* log_file;

/* ************************************************************************** */
/* **** ERROR MACROS ******************************************************** */
/* ************************************************************************** */

/** Macro that is called when the scheduler hits a fatal error */
#define FATAL(...)  { \
            lprintf_n("FATAL %s.%d: ", __FILE__, __LINE__); \
            fprintf(log_file, __VA_ARGS__); \
            fprintf(log_file, "\n"); \
            lprintf("FATAL errno is: %s\n", strerror(errno)); \
            exit(-1); }

/** Macro that is called when a thread generated a fatal error */
#define THREAD_FATAL(...) { \
            lprintf_n("THREAD_FATAL %s.%d: ", __FILE__, __LINE__); \
            fprintf(log_file, __VA_ARGS__); \
            fprintf(log_file, "\n"); \
            lprintf("THREAD_FATAL errno is: %s\n", strerror(errno)); \
            pthread_exit(NULL); }

/** Macro that is called when any type of error is generated */
#define ERROR(...) { \
            lprintf_n("ERROR %s.%d: ", __FILE__, __LINE__); \
            fprintf(log_file, __VA_ARGS__); \
            fprintf(log_file, "\n"); \
            lprintf("ERROR errno is: %s\n", strerror(errno)); }

/** Macro that is called when any type of warning is generated */
#define WARNING(...) { \
            lprintf_n("WARNING %s.%d: ", __FILE__, __LINE__); \
            fprintf(log_file, __VA_ARGS__); \
            fprintf(log_file, "\n"); \
            lprintf("WARNING errno is: %s\n", strerror(errno)); }

/* verbose macros, if changing from greater than scheme to bit mask, just change these */
#define VERBOSE1 verbose & 1  ///< level 1
#define VERBOSE2 verbose & 2  ///< level 2
#define VERBOSE3 verbose & 4  ///< level 3
#define VERBOSE4 verbose & 8  ///< level 4

/* ************************************************************************** */
/* **** logging functions *************************************************** */
/* ************************************************************************** */

const char* lname();
void set_log(const char* name);
int  lprintf(const char* fmt, ...);
int  lprintf_n(const char* fmt, ...);
int  lprintf_v(const char* fmt, va_list args);
int  lprintf_c(const char* fmt, ...);


#endif /* LOGGING_H_INCLUDE */
