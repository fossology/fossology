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

#ifndef DATABASE_H_INCLUDE
#define DATABASE_H_INCLUDE

/* local includes */
#include <job.h>

/* postgresql library */
#include <libpq-fe.h>

/* ************************************************************************** */
/* **** useful macros ******************************************************* */
/* ************************************************************************** */

#define PQget(db_result, row, col) \
  PQgetvalue(db_result, row, PQfnumber(db_result, col))

/* ************************************************************************** */
/* **** constructor destructor ********************************************** */
/* ************************************************************************** */

extern PGconn* db_conn;

void database_init();
void database_destroy();

void email_load();

/* ************************************************************************** */
/* **** event and functions ************************************************* */
/* ************************************************************************** */

void database_exec_event(char* sql);
void database_reset_queue();
void database_update_event(void* unused);
void database_update_job(job j, job_status status);
void database_job_processed(int j_id, int number);
void database_job_log(int j_id, char* log_name);
void database_job_priority(job j, int priority);

#endif /* DATABASE_H_INCLUDE */
