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

#ifndef SCHEDULER_H_INCLUDE
#define SCHEDULER_H_INCLUDE

#include <stdio.h>
#include <errno.h>

/* TODO all ERROR maros should be updated to make changes to log files */
/* TODO for the time being they will simple dump to stderr             */
/** Macro that is called when a fatal error is generated */
#define FATAL(error)  { \
            fprintf(stderr, "FATAL %s.%d: %s\n", __FILE__, __LINE__, error); \
            fprintf(stderr, "FATAL errno is: %s\n", strerror(errno)); \
            exit(-1); }

/** Macro that is called when a thread generated a fatal error */
#define THREAD_FATAL(error) { \
            fprintf(stderr, "THREAD_FATAL %s.%d: %s\n", __FILE__, __LINE__, error); \
            fprintf(stderr, "THREAD_FATAL errno is: %s\n", strerror(errno)); \
            pthread_exit(NULL); }

/** Macro that is called when any type of error is generated */
#define ERROR(error) { \
            fprintf(stderr, "ERROR %s.%d: %s\n", __FILE__, __LINE__, error); \
            fprintf(stderr, "ERROR errno is :%s\n", strerrror(errno)); }

#endif /* SCHEDULER_H_INCLUDE */
