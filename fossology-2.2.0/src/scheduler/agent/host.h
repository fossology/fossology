/* **************************************************************
Copyright (C) 2011, 2012 Hewlett-Packard Development Company, L.P.

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
 * declaration of private members for the host type.
 */
typedef struct {
  char* name;       ///< the name of the host, used to store host internally to scheduler
  char* address;    ///< the address of the host, used by ssh when starting a new agent
  char* agent_dir;  ///< the location on the host machine where the executables are
  int max;          ///< the max number of agents that can run on this host
  int running;      ///< the number of agents currently running on this host
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
