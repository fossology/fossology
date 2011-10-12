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
 * The status that a job has.
 *
 * RUNNING: all agents will continue to work normally
 * KILLED:  all agent associated with the job will die
 * PAUSED:  all agents will stop and wait for instructions
 */
enum job_status {
  RUNNING, ///< RUNNING
  KILLED,  ///< KILLED
  PAUSED   ///< PAUSED
};

/**
 * Common verbose flags for the agents, this is used so that the scheduler can
 * change the verbose level for a particular agent. All agents should use this
 * flag for verbose instead of one declared within the agent. This can be set
 * by the scheduler to enable different levels of verbose.
 */
extern int verbose;

/* ************************************************************************** */
/* **** Error and Verbose Functions ***************************************** */
/* ************************************************************************** */

#define FATAL(...) { \
            fprintf(stderr, "FATAL %s.%d: ", __FILE__, __LINE__); \
            fprintf(stderr, __VA_ARGS__); \
            fprintf(stderr, "\n"); }

#define PQ_FATAL(pg_r, ...) { \
            fprintf(stderr, "FATAL %s.%d: ", __FILE__, __LINE__); \
            fprintf(stderr, __VA_ARGS__); \
            fprintf(stderr, "FATAL postgresql error: %s\n", PQresultErrorMessage(pg_r)); }

#define ERROR(...) { \
            fprintf(stderr, "ERROR %s.%d: ", __FILE__, __LINE__); \
            fprintf(stderr, __VA_ARGS__); \
            fprintf(stderr, "\n"); }

#define PQ_ERROR(pg_r, ...) { \
            fprintf(stderr, "ERROR %s.%d: ", __FILE__, __LINE__); \
            fprintf(stderr, __VA_ARGS__); \
            fprintf(stderr, "ERROR postgresql error: %s\n", PQresultErrorMessage(pg_r)); }

#define WARNING(...) { \
            fprintf(stderr, "WARNING %s.%d: ", __FILE__, __LINE__); \
            fprintf(stderr, __VA_ARGS__); \
            fprintf(stderr, "\n"); }

#define DEBUG(...) { \
            fprintf(stderr, "DEBUG %s.%d: ", __FILE__, __LINE__); \
            fprintf(stderr, __VA_ARGS__); \
            fprintf(stderr, "\n"); }

#define NOTICE(...) { \
            fprintf(stderr, "NOTICE %s.%d: ", __FILE__, __LINE__); \
            fprintf(stderr, __VA_ARGS__); \
            fprintf(stderr, "\n"); }

/* a set of verbose macros that can be used to tso auto testing on verbose   */
/* for example, to print "this is a verbose test at line <line>" at verbose */
/* level 2 simply call:                                                     */
/*    VERBOSE2("this is a verbose test at line %d", __LINE__);              */
#define TVERBOSE1 verbose & 1
#define TVERBOSE2 verbose & 2
#define TVERBOSE3 verbose & 4
#define TVERBOSE4 verbose & 8
#define VERBOSE1(...) if(TVERBOSE1) fprintf(stdout, __VA_ARGS__);
#define VERBOSE2(...) if(TVERBOSE2) fprintf(stdout, __VA_ARGS__);
#define VERBOSE3(...) if(TVERBOSE3) fprintf(stdout, __VA_ARGS__);
#define VERBOSE4(...) if(TVERBOSE4) fprintf(stdout, __VA_ARGS__);

/* ************************************************************************** */
/* **** Agent api *********************************************************** */
/* ************************************************************************** */

void  fo_scheduler_heart(int i);
void  fo_scheduler_connect(int* argc, char** argv);
void  fo_scheduler_disconnect(int retcode);
char* fo_scheduler_next();

/* ************************************************************************** */
/* **** Accessor Functions ************************************************** */
/* ************************************************************************** */

char* fo_scheduler_current();
char* fo_sysconfig(char* sectionname, char* variablename);

#endif /* LIBFOSSSCHEDULER_H_INCLUDE */
