/*
 SPDX-FileCopyrightText: © 2010 Hewlett-Packard Development Company, L.P.
 SPDX-FileCopyrightText: © 2015 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

#ifndef LIBFOSSSCHEDULER_H_INCLUDE
#define LIBFOSSSCHEDULER_H_INCLUDE

/* local includes */
#include <fossconfig.h>
#include <libfossdbmanager.h>

/* library includes */
#include <stdio.h>
#include <signal.h>
#include <stdlib.h>
#include <string.h>
#include <sys/file.h>
#include <unistd.h>

/* other libraries */
#include <libpq-fe.h>

#define ALARM_SECS 30


/* ************************************************************************** */
/* **** Data Types ********************************************************** */
/* ************************************************************************** */

/**
* @brief The status that a job has.
*
*  - RUNNING: all agents will continue to work normally
*  - KILLED:  all agent associated with the job will die
*  - PAUSED:  all agents will stop and wait for instructions
*/
enum job_status
{
  RUNNING, ///< RUNNING
  KILLED,  ///< KILLED
  PAUSED   ///< PAUSED
};

/**
* @brief Common verbose flags for the agents, this is used so that the scheduler can
* change the verbose level for a particular agent. All agents should use this
* flag for verbose instead of one declared within the agent. This can be set
* by the scheduler to enable different levels of verbose.
*/
extern int agent_verbose;
extern int should_connect_to_db;

extern fo_conf* sysconfig;
extern char* sysconfigdir;

/*
 * The following macro definitions are meant to act as their own statement in
 * the c language. To accomplish this, they needed to not only be able to be used
 * in the situation of an "if" statement with no body, but also require that
 * they are followed by a ";".
 *
 * To do this the "do {} while(0)" loop is used, the loop will not appear in
 * result flow control since it does not modify the flow of control, but it is
 * a single statement that requires a ";" at the end to be syntactically correct
 */

/**
 * Log fatal error
 * @param ... standard printf-style function call
 */
#define LOG_FATAL(...) { \
            fprintf(stdout, "FATAL %s.%d: ", __FILE__, __LINE__); \
            fprintf(stdout, __VA_ARGS__); \
            fprintf(stdout, "\n"); \
            fflush(stdout); }
/**
 * Log Postgres fatal error
 * @param pg_r Postgres error code
 * @param ... standard printf-style function call
 */
#define LOG_PQ_FATAL(pg_r, ...) { \
            fprintf(stdout, "FATAL %s.%d: ", __FILE__, __LINE__); \
            fprintf(stdout, __VA_ARGS__); \
            fprintf(stdout, "FATAL postgresql error: %s\n", PQresultErrorMessage(pg_r)); \
            fflush(stdout); }

/**
 * Log general error
 * @param ... standard printf-style function call
 */
#define LOG_ERROR(...) { \
            fprintf(stdout, "ERROR %s.%d: ", __FILE__, __LINE__); \
            fprintf(stdout, __VA_ARGS__); \
            fprintf(stdout, "\n"); \
            fflush(stdout); }

/**
 * Log Postgres general error
 * @param pg_r Postgres error code
 * @param ... standard printf-style function call
 */
#define LOG_PQ_ERROR(pg_r, ...) { \
            fprintf(stdout, "ERROR %s.%d: ", __FILE__, __LINE__); \
            fprintf(stdout, __VA_ARGS__); \
            fprintf(stdout, "ERROR postgresql error: %s\n", PQresultErrorMessage(pg_r)); \
            fflush(stdout); }

/**
 * Log warnings
 * @param ... standard printf-style function call
 */
#define LOG_WARNING(...) { \
            fprintf(stdout, "WARNING %s.%d: ", __FILE__, __LINE__); \
            fprintf(stdout, __VA_ARGS__); \
            fprintf(stdout, "\n"); \
            fflush(stdout); }

/**
 * Log debugging messages
 * @param ... standard printf-style function call
 */
#define LOG_DEBUG(...) { \
            fprintf(stdout, "DEBUG %s.%d: ", __FILE__, __LINE__); \
            fprintf(stdout, __VA_ARGS__); \
            fprintf(stdout, "\n"); \
            fflush(stdout); }

/**
 * Log notice messages
 * @param ... standard printf-style function call
 */
#define LOG_NOTICE(...) { \
            fprintf(stdout, "NOTICE %s.%d: ", __FILE__, __LINE__); \
            fprintf(stdout, __VA_ARGS__); \
            fprintf(stdout, "\n"); \
            fflush(stdout); }

#define TVERBOSE   agent_verbose              ///< Agent verbose set
#define TVERBOSE0 (agent_verbose & (1 << 0))  ///< Verbose level 0
#define TVERBOSE1 (agent_verbose & (1 << 1))  ///< Verbose level 1
#define TVERBOSE2 (agent_verbose & (1 << 2))  ///< Verbose level 2
#define TVERBOSE3 (agent_verbose & (1 << 3))  ///< Verbose level 3
#define TVERBOSE4 (agent_verbose & (1 << 4))  ///< Verbose level 4
#define TVERBOSE5 (agent_verbose & (1 << 5))  ///< Verbose level 5
#define TVERBOSE6 (agent_verbose & (1 << 6))  ///< Verbose level 6
#define TVERBOSE7 (agent_verbose & (1 << 7))  ///< Verbose level 7

/** @brief By using these macros the verbosity level of an agent can be changed
*         dynamically through the scheduler.
*
* For example, to print "this is a verbose test at line <line>" at verbose
* level 2 simply call:
*    `LOG_VERBOSE2("this is a verbose test at line %d", __LINE__);`
* Though you never have to put the caller's filename or line number
* in a message since they are added by LOG_NOTICE.
*/
#define LOG_VERBOSE(...)  if(TVERBOSE)  LOG_NOTICE(__VA_ARGS__);
#define LOG_VERBOSE0(...) if(TVERBOSE0) LOG_NOTICE(__VA_ARGS__);
#define LOG_VERBOSE1(...) if(TVERBOSE1) LOG_NOTICE(__VA_ARGS__);
#define LOG_VERBOSE2(...) if(TVERBOSE2) LOG_NOTICE(__VA_ARGS__);
#define LOG_VERBOSE3(...) if(TVERBOSE3) LOG_NOTICE(__VA_ARGS__);
#define LOG_VERBOSE4(...) if(TVERBOSE4) LOG_NOTICE(__VA_ARGS__);
#define LOG_VERBOSE5(...) if(TVERBOSE5) LOG_NOTICE(__VA_ARGS__);
#define LOG_VERBOSE6(...) if(TVERBOSE6) LOG_NOTICE(__VA_ARGS__);
#define LOG_VERBOSE7(...) if(TVERBOSE7) LOG_NOTICE(__VA_ARGS__);

/**
* @brief Special conditions to set for an agent execution.
*
* Possible options:
*   SPECIAL_NOKILL: instruct the scheduler not to kill the agent
*/
#define SPECIAL_NOKILL (1 << 0)

/* ************************************************************************** */
/* **** Agent api *********************************************************** */
/* ************************************************************************** */

/**
* Used to set the message that will be sent with the notification email for the
* job that this agent is working on
*
* @param ... standard printf-style function call
*/
#define NOTIFY_EMAIL(...)         \
    fprintf(stdout, "EMAIL ");    \
    fprintf(stdout, __VA_ARGS__); \
    fprintf(stdout, "\n");        \
    fflush(stdout)

void fo_scheduler_heart(int i);
void fo_scheduler_connect(int* argc, char** argv, PGconn** db_conn);
void fo_scheduler_connect_dbMan(int* argc, char** argv, fo_dbManager** dbManager);
void fo_scheduler_disconnect(int retcode);
char* fo_scheduler_next();

/* ************************************************************************** */
/* **** Accessor Functions ************************************************** */
/* ************************************************************************** */

char* fo_scheduler_current();
int fo_scheduler_userID();
int fo_scheduler_groupID();
int fo_scheduler_jobId();
void fo_scheduler_set_special(int option, int value);
int fo_scheduler_get_special(int option);
char* fo_sysconfig(const char* sectionname, const char* variablename);

#endif /* LIBFOSSSCHEDULER_H_INCLUDE */
