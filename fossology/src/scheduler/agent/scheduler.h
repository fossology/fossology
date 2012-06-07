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
#include <errno.h>
#include <limits.h>
#include <stdio.h>
#include <stdint.h>

/* other library includes */
#include <glib.h>

/* fo library includes */
#include <fossconfig.h>

#define CHECKOUT_SIZE 100

#define AGENT_BINARY "%s/%s/%s/agent/%s"
#define AGENT_CONF "mods-enabled"

extern int verbose;
extern int closing;
extern int s_pid;
extern int s_daemon;
extern int s_port;
extern char* sysconfigdir;
extern fo_conf* sysconfig;
extern char* logdir;
extern char* process_name;

/* ************************************************************************** */
/* *** CONFIGURATION                                                      *** */
/* ***   There are a set of variables that can be defined in the          *** */
/* ***   Configuration file. These are defined used X-Macros so that      *** */
/* ***   adding a new variable can be accomplished by simply changing     *** */
/* ***   just the CONF_VARIABLE_TYPES Macro.                              *** */
/* ************************************************************************** */

/**
 * If no operation needs to be applied to a configuration variable when it is
 * loaded from the configuration file, use this macro as the operation.
 *
 * example appearing in the CONF_VARIABLES_TYPES macro:
 *   apply(char*, some_variable, NOOP, "some value")
 */
#define NOOP(val) val

/**
 * X-Macro used to define variables that can be loaded from the configuration
 * file. To add a new configuration variable, simply add it to this macro and
 * use it in the code.
 *
 * Current variables:
 *   fork_backoff_time     => The max time to back off when a call to fork() fails
 *   agent_death_timer     => The amount of time to wait before killing an agent
 *   agent_update_interval => The time between each SIGALRM for the scheduler
 *   agent_update_number   => The number of updates before killing an agent
 *   interface_nthreads    => The number of threads available to the interface
 *
 * For the operation that will be taken when a variable is loaded from the
 * configuration file. You should provide a function or macro that takes a
 * c-string and returns the correct type for assignment to the variable. For
 * any integer types, just provide one of the atoi family of functions. for a
 * string, use the CONF_NOOP macro.
 *
 * @param apply  A macro that is passed to this function. Apply should take 3
 *               arguments. The type of the variable, name of the variable,
 *               the operation to apply when loading from the file, the
 *               operation to perform when writing the variable to the log
 *               file, and the default value.
 */
#define CONF_VARIABLES_TYPES(apply)                               \
  apply(uint32_t, fork_backoff_time,     atoi, %d, 5)             \
  apply(uint32_t, agent_death_timer,     atoi, %d, 180)           \
  apply(uint32_t, agent_update_interval, atoi, %d, 120)           \
  apply(uint32_t, agent_update_number,   atoi, %d, 5)             \
  apply(gint,     interface_nthreads,    atoi, %d, 10)

/** The extern declaractions of configuration varaibles */
#define SELECT_DECLS(type, name, l_op, w_op, val) extern type CONF_##name;
CONF_VARIABLES_TYPES(SELECT_DECLS)
#undef SELECT_DECLS

/** turns the input into a string literal */
#define MK_STRING_LIT(passed) #passed

/* ************************************************************************** */
/* **** Utility Functions *************************************************** */
/* ************************************************************************** */

/* scheduler utility functions */
void load_config(void*);
void scheduler_close_event(void*);

/* glib related functions */
gint string_is_num(gchar* str);
gint string_compare(gconstpointer a, gconstpointer b, gpointer user_data);
gint int_compare(gconstpointer a, gconstpointer b, gpointer user_data);

/* ************************************************************************** */
/* **** Scheduler Functions ************************************************* */
/* ************************************************************************** */

void sig_handle(int signo);
void update_scheduler();
void signal_scheduler();
void set_usr_grp();
int  kill_scheduler(int force);
void load_agent_config();
void load_foss_config();
int  close_scheduler();
int  kill_scheduler(int force);

#endif /* SCHEDULER_H_INCLUDE */
