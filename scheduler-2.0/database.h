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
/* **** constructor destructor ********************************************** */
/* ************************************************************************** */

extern PGconn* db_conn;

void database_init();
void database_destroy();

/* ************************************************************************** */
/* **** event and functions ************************************************* */
/* ************************************************************************** */

void database_reset_queue();
void database_update_event(void* unused);
void database_update_job(int j_id, job_status status);

#endif /* DATABASE_H_INCLUDE */
