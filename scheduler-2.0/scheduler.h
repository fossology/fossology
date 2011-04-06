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

#ifndef gsize
#define gsize unsigned long
#endif
#ifndef G_MAXULONG
#define G_MAXULONG ULONG_MAX
#endif
#ifndef G_MAXSIZE
#define G_MAXSIZE G_MAXULONG
#endif

#define CHECKOUT_SIZE 100

extern int verbose;
extern int closing;

/* ************************************************************************** */
/* **** Utility Functions *************************************************** */
/* ************************************************************************** */

/* scheduler utility functions */
void load_config();
void scheduler_close_event(void*);

/* glib related functions */
gint string_compare(gconstpointer a, gconstpointer b, gpointer user_data);
gint int_compare(gconstpointer a, gconstpointer b, gpointer user_data);

/* ************************************************************************** */
/* **** SQL strings ********************************************************* */
/* ************************************************************************** */

#endif /* SCHEDULER_H_INCLUDE */
