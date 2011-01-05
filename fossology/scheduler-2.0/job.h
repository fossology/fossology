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

#ifndef JOB_H_INCLUDE
#define JOB_H_INCLUDE

/* local includes */
#include <agent.h>

/* ************************************************************************** */
/* **** Data Types ********************************************************** */
/* ************************************************************************** */

/**
 * TODO
 */
typedef struct job_internal* job;

/* ************************************************************************** */
/* **** Constructor Destructor ********************************************** */
/* ************************************************************************** */

job job_init(char* type, char** data, int data_size);
void job_destroy(job j);

/* ************************************************************************** */
/* **** Functions and events ************************************************ */
/* ************************************************************************** */

void job_add_agent(job j, agent a);
void job_set_priority(job j, int pri);
void job_update(job j);
void job_pause(job j);
void job_restart(job j);
char** job_next(job j);

#endif /* JOB_H_INCLUDE */
