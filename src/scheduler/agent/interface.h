/*
 SPDX-FileCopyrightText: © 2010, 2011, 2012 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

#ifndef INTERFACE_H_INCLUDE
#define INTERFACE_H_INCLUDE

/* local includes */
#include <scheduler.h>

/* glib include */
#include <glib.h>

/* ************************************************************************** */
/* **** Constructor Destructor ********************************************** */
/* ************************************************************************** */

void interface_init(scheduler_t* scheduler);
void interface_destroy(scheduler_t* scheduler);

#endif /* INTERFACE_H_INCLUDE */
