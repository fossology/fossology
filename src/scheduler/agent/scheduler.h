/*
 SPDX-FileCopyrightText: © 2010, 2011, 2012 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * \dir
 * \brief Source for scheduler
 * \file
 * \brief Header file for the scheduler
 * \page scheduler FOSSology Scheduler
 * \tableofcontents
 * \section schedulerabout About scheduler
 * The FOSSology job scheduler is a daemon that periodically checks the job
 * queue and spawns agents to run scans. Multiple jobs can be run simultaneously
 * as defined in fossology.conf.
 * \section schedulerterminology Terminology
 * \subsection jobstreamterm Job stream
 *     A list of all the job steps queued at the same time for an upload.
 *     For example, all the job steps (programs) necessary to get a particular
 *     upload from unpack through all of its scans.
 * \subsection jobterm Job
 *     A specific step in the process of working on an upload. Each job would
 *     be associated with a different type of agent. For example if a person
 *     uploaded a .tar file and wanted license and copyright analysis run on the
 *     file, there would be a job for the unpack step, a job for the license step
 *     and a job for the copyright step.
 * \subsection agentterm Agent
 *     The actual process that the scheduler will spawn and be communicating
 *     with. An instance of the copyright scanner would be an example of an
 *     agent. A job is a scheduler construct used to run an agent process.
 * \section schedulerarchitecture Scheduler Architecture
 * Scheduler use a classic client server communication style for both the agent
 * communication and the UI communication. Every time a new communication
 * channel is needed, the scheduler will create a new thread to manage the
 * communications with that channel. This makes the communication logic much simpler.
 *
 * If the communication thread receives anything that would involve changing a
 * data structure internal to the scheduler, it passes the information off to
 * the main thread instead of changing it personally. The communication threads send
 * information to the main thread using events. The communication thread will package
 * the function and arguments and pass it to a concurrent queue that the main thread
 * is waiting on.
 *
 * When a new job stream is found in the job queue, the scheduler will create only
 * the jobs for that job stream that have all the preconditions fulfilled. Once
 * created the jobs will have agents allocated to them. Allocated agents belong
 * to the job and will remain in the scheduler's data structures until the job
 * is removed from the system. Jobs are responsible for cleaning up any agents
 * allocated to them.
 *
 * Within a job, when an agent is ready for data, it will inform the main thread
 * that it is waiting. The main thread will then take a chunk of data from the
 * job that the agent belongs to and allocate it to the agent. The communication
 * thread will then be responsible for sending the data to the corresponding
 * process. It is important to note that the communication threads are using
 * blocking IO on the pipe from the corresponding process. As a result, any
 * string that starts with "@" is reserved as a communication from the scheduler
 * instead of the corresponding process. Writing anything that starts with "@"
 * to stdout within an agent will result in undefined behavior.
 *
 * Here are some other properties of the scheduler:
 * - Scheduler can take advantage of multiple processors on whatever machine it is running on.
 * - Simplified code for communicating with agents since all IO would be blocking instead of non-blocking.
 * - Master thread can not get swamped with communications between the agents and can concentrate on managing new jobs.
 * - Uses GLib
 * - Job queue is implemented in db tables:
 *     - **job** - the job stream
 *     - **jobqueue** - individual jobs
 *     - **jobdepends** - job dependencies (for example, you can't run buckets until the license scan is done)
 *
 * [More info on scheduler](https://github.com/fossology/fossology/wiki/Job-Scheduler)
 * \section scheduleractions Communicating with scheduler
 * You can send commands to a running scheduler. The scheduler listens on the
 * port specified in fossology.conf. After a command is sent, the scheduler will
 * respond with "received". You can send the following commands:
 *
 * | Command | Definition |
 * | ---: | :--- |
 * | close   | Close the connection to the scheduler. |
 * | stop    | Scheduler will gracefully shutdown after the currently running jobs have finished. |
 * | pause <job_id> | The job id (job_pk) will pause. All agents associated with the job will be put to sleep. |
 * | agents  | Respond with a space separated list of agents configured to run. |
 * | reload  | Reload the configuration information for the agents and hosts. |
 * | status  | Respond on the same socket with all of the status information. |
 * | status <job_id> | Respond with a detailed status for the specific job. |
 * | restart <job_id> | Restart the job specified. |
 * | verbose | The verbosity level for the scheduler will be changed to match level. |
 * | verbose <job_id> | The verbosity level for all of the agents owned by the specified job will be changed to level. |
 * | priority | Changes the priority of a particular job within the scheduler. |
 * | database | Check the database job queue for new jobs. |
 *
 * \section schedulersource Agent source
 *   - \link src/scheduler/agent \endlink
 *   - Test agents \link src/scheduler/agent_tests/agents \endlink
 *   - Functional test cases \link src/scheduler/agent_tests/Functional \endlink
 *   - Unit test cases \link src/scheduler/agent_tests/Unit \endlink
 */
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

#define AGENT_BINARY "%s/%s/%s/agent/%s"  ///< Format to get agent binary
#define AGENT_CONF "mods-enabled"         ///< Agent conf location

/**
 * Check if PGresult is not NULL. Then call PQclear and set result as NULL
 * to prevent multiple calls.
 */
#define SafePQclear(pgres)  if (pgres) {PQclear(pgres); pgres = NULL;}

/* ************************************************************************** */
/* *** Scheduler structure ************************************************** */
/* ************************************************************************** */

/**
 * The main scheduler structure. It holds all the information about the current
 * scheduler process and is used by several different operations.
 */
typedef struct
{
    /* information about the scheduler process */
    gchar*   process_name;  ///< The name of the scheduler process
    gboolean s_pid;         ///< The pid of the scheduler process
    gboolean s_daemon;      ///< Is the scheduler being run as a daemon
    gboolean s_startup;     ///< Has the scheduler finished startup tests
    gboolean s_pause;       ///< Has the scheduler been paused

    /* loaded configuration information */
    fo_conf* sysconfig;     ///< Configuration information loaded from the configuration file
    gchar*   sysconfigdir;  ///< The system directory that contain fossology.conf
    gchar*   logdir;        ///< The directory to put the log file in
    gboolean logcmdline;    ///< Was the log file set by the command line
    log_t*   main_log;      ///< The main log file for the scheduler

    /* used exclusively in agent.c */
    GTree*  meta_agents;    ///< List of all meta agents available to the scheduler
    GTree*  agents;         ///< List of any currently running agents

    /* used exclusively in host.c */
    GTree* host_list;       ///< List of all hosts available to the scheduler
    GList* host_queue;      ///< Round-robin queue for choosing which host use next

    /* used exclusively in interface.c */
    gboolean      i_created;    ///< Has the interface been created
    gboolean      i_terminate;  ///< Has the interface been terminated
    uint16_t      i_port;       ///< The port that the scheduler is listening on
    GThread*      server;       ///< Thread that is listening to the server socket
    GThreadPool*  workers;      ///< Threads to handle incoming network communication
    GCancellable* cancel;       ///< Used to stop the listening thread when it is running

    /* used exclusively in job.c */
    GTree*     job_list;    ///< List of jobs that have been created
    GSequence* job_queue;   ///< heap of jobs that still need to be started

    /* used exclusively in database.c */
    PGconn*  db_conn;         ///< The database connection
    gchar*   host_url;        ///< The url that is used to get to the FOSSology instance
    gchar*   email_subject;   ///< The subject to be used for emails
    gchar*   email_header;    ///< The beginning of the email message
    gchar*   email_footer;    ///< The end of the email message
    gchar*   email_command;   ///< The command that will sends emails, usually mailx
    gboolean default_header;  ///< Is the header the default header
    gboolean default_footer;  ///< Is the footer the default footer

    /* regular expressions */
    GRegex* parse_agent_msg;     ///< Parses messages coming from the agents
    GRegex* parse_db_email;      ///< Parses database email text
    GRegex* parse_interface_cmd; ///< Parses the commands received by the interface
} scheduler_t;

scheduler_t* scheduler_init(gchar* sysconfigdir, log_t* log);
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
