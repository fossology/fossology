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
            lprintf("FATAL %s.%d: ", __FILE__, __LINE__); \
            lprintf_t(__VA_ARGS__); \
            lprintf_t("\n"); \
            lprintf("FATAL errno is: %s\n", strerror(errno)); \
            exit(-1); }

/** Macro that is called when a thread generated a fatal error */
#define THREAD_FATAL(...) { \
            lprintf("THREAD_FATAL %s.%d: ", __FILE__, __LINE__); \
            lprintf_t(__VA_ARGS__); \
            lprintf_t("\n"); \
            lprintf("THREAD_FATAL errno is: %s\n", strerror(errno)); \
            pthread_exit(NULL); }

/** Macro that is called when any type of error is generated */
#define ERROR(...) { \
            lprintf("ERROR %s.%d: ", __FILE__, __LINE__); \
            lprintf_t(__VA_ARGS__); \
            lprintf_t("\n"); \
            lprintf("ERROR errno is :%s\n", strerror(errno)); }

/** Macro that is called when any type of warning is generated */
#define WARNING(...) { \
            lprintf("WARNING %s.%d: ", __FILE__, __LINE__); \
            lprintf_t(__VA_ARGS__); \
            lprintf_t("\n"); \
            lprintf("WARNING errno is :%s\n", strerror(errno)); }

/* ************************************************************************** */
/* **** logging functions *************************************************** */
/* ************************************************************************** */

const char* lname();
void set_log(const char* name);
int  lprintf(const char* fmt, ...);
int  lprintf_t(const char* fmt, ...);
int  lprintf_v(const char* fmd, va_list args);

#endif /* LOGGING_H_INCLUDE */
