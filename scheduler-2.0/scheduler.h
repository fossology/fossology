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

#define CHECKOUT_SIZE 100

/* ************************************************************************** */
/* **** Logging Macros ****************************************************** */
/* ************************************************************************** */

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
            fprintf(stderr, "ERROR errno is :%s\n", strerror(errno)); }

/* ************************************************************************** */
/* **** SQL strings ********************************************************* */
/* ************************************************************************** */

/**
 *
 */
char* check_queue = "\
SELECT * \
  FROM getrunnable() \
  LIMIT 10;";

/**
 *
 */
char* check_extended = "\
SELECT DISTINCT(jobqueue.*), job.* \
  FROM jobqueue \
    LEFT JOIN jobdepends ON jobqueue.jq_pk = jobdepends.jdep_jq_fk \
    LEFT JOIN jobqueue AS depends ON depends.jq_pk = jobdepends.jdep_jq_depends_fk \
    LEFT JOIN job ON jobqueue.jq_job_fk = job.job_pk WHERE jobqueue.jq_starttime IS NULL AND ( (depends.jq_endtime IS NOT NULL AND depends.jq_end_bits < 2 ) OR jobdepends.jdep_jq_depends_fk IS NULL) ORDER BY job.job_priority DESC,job.job_queued ASC LIMIT 6;";

#endif /* SCHEDULER_H_INCLUDE */
