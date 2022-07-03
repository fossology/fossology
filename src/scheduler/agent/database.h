/*
 SPDX-FileCopyrightText: © 2010, 2011, 2012 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

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
char* get_email_command(scheduler_t* scheduler, char* user_email);

#endif /* DATABASE_H_INCLUDE */
