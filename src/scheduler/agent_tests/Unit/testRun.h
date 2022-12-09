/*
 SPDX-FileCopyrightText: © 2011, 2012 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

#pragma once

/* local includes */
#include <agent.h>
#include <database.h>
#include <event.h>
#include <host.h>
#include <interface.h>
#include <job.h>
#include <logging.h>
#include <scheduler.h>

/* libraries */
#include <libfocunit.h>
#include <glib.h>

/* suite init and clean */
int init_suite(void);
int clean_suite(void);

extern char* testdb;

/* agent suite init and clean */
/* int agent_init_suite(void);
int agent_clean_suite(void); */

/* test case sets */
extern CU_TestInfo tests_host[];
extern CU_TestInfo tests_interface[];
extern CU_TestInfo tests_interface_thread[];

extern CU_TestInfo tests_meta_agent[];
extern CU_TestInfo tests_agent[];

extern CU_TestInfo tests_event[];

extern CU_TestInfo tests_database[];
extern CU_TestInfo tests_email[];

extern CU_TestInfo tests_job[];

extern CU_TestInfo tests_scheduler[];
/* scheduler private declarations */
event_loop_t* event_loop_get();
