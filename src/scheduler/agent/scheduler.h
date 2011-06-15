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

/* other library includes */
#include <glib.h>

#define CHECKOUT_SIZE 100

extern int verbose;
extern int closing;
extern int s_pid;
extern int s_daemon;
extern int s_port;

/* ************************************************************************** */
/* **** Utility Functions *************************************************** */
/* ************************************************************************** */

/* scheduler utility functions */
void load_config(void*);
void scheduler_close_event(void*);

/* glib related functions */
gint string_compare(gconstpointer a, gconstpointer b, gpointer user_data);
gint int_compare(gconstpointer a, gconstpointer b, gpointer user_data);

/* ************************************************************************** */
/* **** SQL strings ********************************************************* */
/* ************************************************************************** */

#endif /* SCHEDULER_H_INCLUDE */
