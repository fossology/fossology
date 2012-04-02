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

#ifndef LIBFOSSSCHEDULER_H_INCLUDE
#define LIBFOSSSCHEDULER_H_INCLUDE

/* local includes */
#include <fossconfig.h>

/* library includes */
#include <stdio.h>
#include <signal.h>
#include <stdlib.h>
#include <string.h>
#include <sys/file.h>
#include <unistd.h>

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
enum job_status {
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

extern fo_conf* sysconfig;
extern char*    sysconfigdir;

/*
 * The following macro definitions are meant to act as their own statement in
 * the c language. To acomplish this, they needed to not only be able to be used
 * in the situation of an "if" statement with no body, but also require that
 * they are followed by a ";".
 *
 * To do this the "do {} while(0)" loop is used, the loop will not appear in
 * result flow control since it does not modify the flow of control, but it is
 * a single statement that requires a ";" at the end to be syntatically correct
 */

/** @brief Logging functions
 */
#define LOG_FATAL(...) { \
            fprintf(stderr, "FATAL %s.%d: ", __FILE__, __LINE__); \
            fprintf(stderr, __VA_ARGS__); \
            fprintf(stderr, "\n"); \
            fflush(stderr); }

#define LOG_PQ_FATAL(pg_r, ...) { \
            fprintf(stderr, "FATAL %s.%d: ", __FILE__, __LINE__); \
            fprintf(stderr, __VA_ARGS__); \
            fprintf(stderr, "FATAL postgresql error: %s\n", PQresultErrorMessage(pg_r)); \
            fflush(stderr); }

#define LOG_ERROR(...) { \
            fprintf(stderr, "ERROR %s.%d: ", __FILE__, __LINE__); \
            fprintf(stderr, __VA_ARGS__); \
            fprintf(stderr, "\n"); \
            fflush(stderr); }

#define LOG_PQ_ERROR(pg_r, ...) { \
            fprintf(stderr, "ERROR %s.%d: ", __FILE__, __LINE__); \
            fprintf(stderr, __VA_ARGS__); \
            fprintf(stderr, "ERROR postgresql error: %s\n", PQresultErrorMessage(pg_r)); \
            fflush(stderr); }

#define LOG_WARNING(...) { \
            fprintf(stderr, "WARNING %s.%d: ", __FILE__, __LINE__); \
            fprintf(stderr, __VA_ARGS__); \
            fprintf(stderr, "\n"); \
            fflush(stderr); }

#define LOG_DEBUG(...) { \
            fprintf(stderr, "DEBUG %s.%d: ", __FILE__, __LINE__); \
            fprintf(stderr, __VA_ARGS__); \
            fprintf(stderr, "\n"); \
            fflush(stderr); }

#define LOG_NOTICE(...) { \
            fprintf(stderr, "NOTICE %s.%d: ", __FILE__, __LINE__); \
            fprintf(stderr, __VA_ARGS__); \
            fprintf(stderr, "\n"); \
            fflush(stderr); }

#define TVERBOSE   agent_verbose
#define TVERBOSE0 (agent_verbose & (1 << 0))
#define TVERBOSE1 (agent_verbose & (1 << 1))
#define TVERBOSE2 (agent_verbose & (1 << 2))
#define TVERBOSE3 (agent_verbose & (1 << 3))
#define TVERBOSE4 (agent_verbose & (1 << 4))
#define TVERBOSE5 (agent_verbose & (1 << 5))
#define TVERBOSE6 (agent_verbose & (1 << 6))
#define TVERBOSE7 (agent_verbose & (1 << 7))

/** @brief By using these macros the verbosity level of an agent can be changed   
 *         dynamically through the scheduler.
 *
 * For example, to print "this is a verbose test at line <line>" at verbose 
 * level 2 simply call:
 *    LOG_VERBOSE2("this is a verbose test at line %d", __LINE__);
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

void  fo_scheduler_heart(int i);
void  fo_scheduler_connect(int* argc, char** argv);
void  fo_scheduler_disconnect(int retcode);
char* fo_scheduler_next();

/* ************************************************************************** */
/* **** Accessor Functions ************************************************** */
/* ************************************************************************** */

char* fo_scheduler_current();
void  fo_scheduler_set_special(int option, int value);
char* fo_sysconfig(char* sectionname, char* variablename);

#endif /* LIBFOSSSCHEDULER_H_INCLUDE */
