/* **************************************************************
Copyright (C) 2011 Hewlett-Packard Development Company, L.P.

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

/* std includes */
#include <stdio.h>

/* ************************************************************************** */
/* **** Data Types ********************************************************** */
/* ************************************************************************** */

/**
 * TODO
 */
typedef struct host_internal* host;

/* ************************************************************************** */
/* **** Contructor Destructor *********************************************** */
/* ************************************************************************** */

void host_list_clean();

void host_init(char* name, char* address, char* agent_dir, int max);
void host_destroy(host h);

/* ************************************************************************** */
/* **** Functions and events ************************************************ */
/* ************************************************************************** */

char* host_name(host h);
char* host_address(host h);
char* host_agent_dir(host h);
void host_increase_load(host h);
void host_decrease_load(host h);

host get_host(int num);
host name_host(char* name);
void for_each_host(void(*callback)(host));
int  num_hosts();

#endif /* HOST_H_INCLUDE */
