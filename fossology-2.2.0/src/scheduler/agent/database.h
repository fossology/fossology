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

#ifndef DATABASE_H_INCLUDE
#define DATABASE_H_INCLUDE

/* local includes */
#include <job.h>
#include <scheduler.h>

/* ************************************************************************** */
/* **** utility ************************************************************* */
/* ************************************************************************** */

#define PQget(db_result, row, col) \
  PQgetvalue(db_result, row, PQfnumber(db_result, col))

extern const char* jobsql_failed;

/* ************************************************************************** */
/* **** constructor destructor ********************************************** */
/* ************************************************************************** */

void database_init(scheduler_t* scheduler);
void email_init(scheduler_t* scheduler);

/* ************************************************************************** */
/* **** event and functions ************************************************* */
/* ************************************************************************** */

PGresult* database_exec(scheduler_t* scheduler, const char* sql);
void database_exec_event(scheduler_t* scheduler, char* sql);
void database_update_event(scheduler_t* scheduler, void* unused);

void database_reset_queue(scheduler_t* scheduler);
void database_update_job(scheduler_t* db_conn, job_t* j, job_status status);
void database_job_processed(int j_id, int number);
void database_job_log(int j_id, char* log_name);
void database_job_priority(scheduler_t* scheduler, job_t* job, int priority);

#endif /* DATABASE_H_INCLUDE */
