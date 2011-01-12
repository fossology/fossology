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

/* ************************************************************************** */
/* **** Agent api *********************************************************** */
/* ************************************************************************** */

void  scheduler_heart(int i);
void  scheduler_connect();
void  scheduler_disconnect();
char* scheduler_next();

/* ************************************************************************** */
/* **** Accessor Functions ************************************************** */
/* ************************************************************************** */

char* scheduler_current();

#endif /* LIBFOSSSCHEDULER_H_INCLUDE */
