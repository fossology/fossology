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

void job_verbose_event(job j);

void job_add_agent(job j, void* a);
void job_remove_agent(job j, void* a);
void job_finish_agent(job j, void* a);
void job_fail_agent(job j, void* a);
void job_set_priority(job j, int pri);
void job_update(job j);
void job_pause(job j);
void job_restart(job j);
int  job_id(job j);
int  job_is_paused(job j);
job  job_verbose(job j, int level);
char** job_next(job j);

/* ************************************************************************** */
/* **** Job list Functions ************************************************** */
/* ************************************************************************** */

void job_list_clean();
void add_job(job j);
job  get_job(int id);

#endif /* JOB_H_INCLUDE */
