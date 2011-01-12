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

/* std library includes */
#include <stdio.h>
#include <errno.h>

/* other library includes */
#include <glib.h>

#define CHECKOUT_SIZE 100

extern int verbose;
extern int runtest;

/* ************************************************************************** */
/* **** Utility Functions *************************************************** */
/* ************************************************************************** */

/* scheduler utility functions */
void load_config();

/* glib related functions */
gint string_compare(gconstpointer a, gconstpointer b, gpointer user_data);
gint int_compare(gconstpointer a, gconstpointer b, gpointer user_data);

/* ************************************************************************** */
/* **** SQL strings ********************************************************* */
/* ************************************************************************** */

/**
 *
 */
/*char* check_queue = "\
SELECT * \
  FROM getrunnable() \
  LIMIT 10;";*/

/**
 *
 */
/*char* check_extended = "\
SELECT DISTINCT(jobqueue.*), job.* \
  FROM jobqueue \
    LEFT JOIN jobdepends ON jobqueue.jq_pk = jobdepends.jdep_jq_fk \
    LEFT JOIN jobqueue AS depends ON depends.jq_pk = jobdepends.jdep_jq_depends_fk \
    LEFT JOIN job ON jobqueue.jq_job_fk = job.job_pk WHERE jobqueue.jq_starttime IS NULL AND ( (depends.jq_endtime IS NOT NULL AND depends.jq_end_bits < 2 ) OR jobdepends.jdep_jq_depends_fk IS NULL) ORDER BY job.job_priority DESC,job.job_queued ASC LIMIT 6;";*/

#endif /* SCHEDULER_H_INCLUDE */
