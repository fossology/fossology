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

/**
 * TODO
 */
typedef enum {
  JB_CHECKEDOUT = 0,    ///< JB_CHECKEDOUT
  JB_STARTED = 1,       ///< JB_STARTED
  JB_COMPLETE = 2,      ///< JB_COMPLETE
  JB_RESTART = 3,       ///< JB_RESTART
  JB_FAILED = 4,        ///< JB_FAILED
  JB_SCH_PAUSED = 5,    ///< JB_SCH_PAUSED
  JB_CLI_PAUSED = 6     ///< JB_CLI_PAUSED
} job_status;

/* ************************************************************************** */
/* **** Constructor Destructor ********************************************** */
/* ************************************************************************** */

void job_list_init();
void job_list_clean();
job job_init(char* type, int id);
void job_destroy(job j);

/* ************************************************************************** */
/* **** Functions and events ************************************************ */
/* ************************************************************************** */

void job_verbose_event(job j);
void job_status_event(void* param);

void job_add_agent(job j, void* a);
void job_remove_agent(job j, void* a);
void job_finish_agent(job j, void* a);
void job_fail_agent(job j, void* a);
void job_set_priority(job j, int pri);
void job_set_data(job j, char* data, int sql);
void job_update(job j);
void job_pause(job j, int cli);
void job_restart(job j);
int  job_id(job j);
int  job_is_paused(job j);
int  job_is_open(job j);
job  job_verbose(job j, int level);
char* job_type(job j);
char* job_next(job j);

/* ************************************************************************** */
/* **** Job list Functions ************************************************** */
/* ************************************************************************** */

job next_job();
job get_job(int id);
int num_jobs();
int active_jobs();

#endif /* JOB_H_INCLUDE */
