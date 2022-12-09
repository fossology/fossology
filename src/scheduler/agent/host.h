/*
 SPDX-FileCopyrightText: © 2011, 2012 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

#ifndef HOST_H_INCLUDE
#define HOST_H_INCLUDE

/* scheduler includes */
#include <scheduler.h>

/* std includes */
#include <stdio.h>

/* other library includes */
#include <gio/gio.h>

/* ************************************************************************** */
/* **** Data Types ********************************************************** */
/* ************************************************************************** */

/**
 * Declaration of private members for the host type.
 */
typedef struct {
  char* name;       ///< The name of the host, used to store host internally to scheduler
  char* address;    ///< The address of the host, used by ssh when starting a new agent
  char* agent_dir;  ///< The location on the host machine where the executables are
  int max;          ///< The max number of agents that can run on this host
  int running;      ///< The number of agents currently running on this host
} host_t;

/* ************************************************************************** */
/* **** Contructor Destructor *********************************************** */
/* ************************************************************************** */

host_t* host_init(char* name, char* address, char* agent_dir, int max);
void host_destroy(host_t* h);

/* ************************************************************************** */
/* **** Functions and events ************************************************ */
/* ************************************************************************** */

void host_insert(host_t* host, scheduler_t* scheduler);
void host_increase_load(host_t* host);
void host_decrease_load(host_t* host);
void host_print(host_t* host, GOutputStream* ostr);

host_t* get_host(GList** queue, uint8_t num);
void    print_host_load(GTree* host_list, GOutputStream* ostr);

#endif /* HOST_H_INCLUDE */
