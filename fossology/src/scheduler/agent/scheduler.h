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

/* local includes */
#include <logging.h>

/* std library includes */
#include <errno.h>
#include <limits.h>
#include <stdio.h>
#include <stdint.h>

/* other library includes */
#include <gio/gio.h>
#include <glib.h>
#include <libpq-fe.h>
#include <signal.h>

/* fo library includes */
#include <fossconfig.h>

#define CHECKOUT_SIZE 100

#define AGENT_BINARY "%s/%s/%s/agent/%s"
#define AGENT_CONF "mods-enabled"

/* ************************************************************************** */
/* *** Scheduler structure ************************************************** */
/* ************************************************************************** */

typedef struct
{
    /* information about the scheduler process */
    gchar*   process_name;  ///< the name of the scheduler process
    gboolean s_pid;         ///< the pid of the scheduler process
    gboolean s_daemon;      ///< is the scheduler being run as a daemon
    gboolean s_startup;     ///< has the scheduler finished startup tests
    gboolean s_pause;       ///< has the scheduler been paused

    /* loaded configuration information */
    fo_conf* sysconfig;     ///< configuration information loaded from the configuration file
    gchar*   sysconfigdir;  ///< the system directory that contain fossology.conf
    gchar*   logdir;        ///< the directory to put the log file in
    gboolean logcmdline;    ///< was the log file set by the command line
    log_t*   main_log;      ///< the main log file for the scheduler

    /* used exclusively in agent.c */
    GTree*  meta_agents;    ///< List of all meta agents available to the scheduler
    GTree*  agents;         ///< List of any currently running agents

    /* used exclusively in host.c */
    GTree* host_list;       ///< List of all hosts available to the scheduler
    GList* host_queue;      ///< round-robin queue for choosing which host use next

    /* used exclusively in interface.c */
    gboolean      i_created;    ///< has the interface been created
    gboolean      i_terminate;  ///< has the interface been terminated
    uint16_t      i_port;       ///< the port that the scheduler is listening on
    GThread*      server;       ///< thread that is listening to the server socket
    GThreadPool*  workers;      ///< threads to handle incoming network communication
    GCancellable* cancel;       ///< used to stop the listening thread when it is running

    /* used exclusively in job.c */
    GTree*     job_list;    ///< List of jobs that have been created
    GSequence* job_queue;   ///< heap of jobs that still need to be started

    /* used exclusively in database.c */
    PGconn*  db_conn;         ///< The database connection
    gchar*   host_url;        ///< The url that is used to get to the FOSSology instance
    gchar*   email_subject;   ///< The subject to be used for emails
    gchar*   email_header;    ///< The beginning of the email message
    gchar*   email_footer;    ///< The end of the email message
    gchar*   email_command;   ///< The command that will sends emails, usually xmail
    gboolean default_header;  ///< Is the header the default header
    gboolean default_footer;  ///< Is the footer the default footer

    /* regular expressions */
    GRegex* parse_agent_msg;     ///< Parses messages coming from the agents
    GRegex* parse_db_email;      ///< Parses database email text
    GRegex* parse_interface_cmd; ///< Parses the commands received by the interface
} scheduler_t;

scheduler_t* scheduler_init(gchar* sysconfigdir);
void scheduler_destroy(scheduler_t* scheduler);

void scheduler_sig_handle(int signo);
void scheduler_signal(scheduler_t* scheduler);
void scheduler_update(scheduler_t* scheduler);

void g_tree_clear(GTree* tree);

extern int      verbose;
extern int      closing;
extern GThread* main_thread;

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

/* glib related functions */
gint string_is_num(gchar* str);
gint string_compare(gconstpointer a, gconstpointer b, gpointer user_data);
gint int_compare(gconstpointer a, gconstpointer b, gpointer user_data);

/* ************************************************************************** */
/* **** Scheduler Functions ************************************************* */
/* ************************************************************************** */

/* scheduler events */
void scheduler_config_event(scheduler_t* scheduler, void*);
void scheduler_close_event(scheduler_t* scheduler, void*);
void scheduler_test_agents(scheduler_t* scheduler, void*);

void scheduler_clear_config(scheduler_t* scheduler);
void scheduler_agent_config(scheduler_t* scheduler);
void scheduler_foss_config(scheduler_t* scheduler);
int  scheduler_daemonize(scheduler_t* scheduler);

void set_usr_grp(gchar* process_name, fo_conf* config);
int  kill_scheduler(int force);

#endif /* SCHEDULER_H_INCLUDE */
