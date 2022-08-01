/*
 SPDX-FileCopyrightText: © 2011, 2012 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * \dir
 * \brief Unit tests for scheduler
 * \file
 * \brief Unit tests for scheduler
 */
#include <stdio.h>
#include <stdlib.h>
#include <assert.h>
#include <CUnit/CUnit.h>
#include <CUnit/Automated.h>
#include <gio/gio.h>
#include <glib.h>

#include <libfossology.h>
#include <testRun.h>

#include <agent.h>
#include <database.h>
#include <event.h>
#include <host.h>
#include <interface.h>
#include <logging.h>
#include <scheduler.h>

/* ************************************************************************** */
/* **** suite initializations *********************************************** */
/* ************************************************************************** */

char* testdb = NULL;

/**
 * Check if main log is not NULL, destroy it else create a new log on
 * ./founit.log
 *
 * @return -1 on failure, 0 of success
 */
int init_suite(void)
{
  if(main_log)
    log_destroy(main_log);
  main_log = log_new("./founit.log", "UNIT_TESTS", getpid());

  if(!testdb && (testdb = getenv("FOSSOLOGY_TESTCONFIG")) == NULL)
  {
    printf("ERROR: scheduler unit tests require a test database");
    exit(1);
  }

  return 0;
}

/**
 * Since we changed the log file in the initializations, we need to close it
 * and set the pointer to NULL so that the logging system can reset it to the
 * correct value.
 *
 * @return -1 of failure, 0 on success
 */
int clean_suite(void)
{
  if(main_log)
    log_destroy(main_log);

  main_log = NULL;
  return 0;
}

/* ************************************************************************** */
/* *** main and suite decl ************************************************** */
/* ************************************************************************** */

/* create test suite */
#if CU_VERSION_P == 213
/** \todo tests_job is not running */
CU_SuiteInfo suites[] =
{
    {"Host",            NULL, NULL, (CU_SetUpFunc)init_suite, (CU_TearDownFunc)clean_suite, tests_host             },
    {"Interface",       NULL, NULL, (CU_SetUpFunc)init_suite, (CU_TearDownFunc)clean_suite, tests_interface        },
    {"InterfaceThread", NULL, NULL, (CU_SetUpFunc)init_suite, (CU_TearDownFunc)clean_suite, tests_interface_thread },
    {"Database",        NULL, NULL, (CU_SetUpFunc)init_suite, (CU_TearDownFunc)clean_suite, tests_database },
    {"Email",           NULL, NULL, (CU_SetUpFunc)init_suite, (CU_TearDownFunc)clean_suite, tests_email },
  //  {"Job"  NULL, NULL, (CU_SetUpFunc)init_suite, (CU_TearDownFunc)clean_suite, tests_job },
    {"Scheduler",       NULL, NULL, (CU_SetUpFunc)init_suite, (CU_TearDownFunc)clean_suite, tests_scheduler },
    {"MetaAgent",       NULL, NULL, (CU_SetUpFunc)init_suite, (CU_TearDownFunc)clean_suite, tests_meta_agent },
    {"Agent",           NULL, NULL, (CU_SetUpFunc)init_suite, (CU_TearDownFunc)clean_suite, tests_agent },
    {"Event",           NULL, NULL, (CU_SetUpFunc)init_suite, (CU_TearDownFunc)clean_suite, tests_event },
    CU_SUITE_INFO_NULL
};
#else
CU_SuiteInfo suites[] =
{
    {"Host",            init_suite, clean_suite, tests_host             },
    {"Interface",       init_suite, clean_suite, tests_interface        },
    {"InterfaceThread", init_suite, clean_suite, tests_interface_thread },
    {"Database",init_suite,clean_suite, tests_database },
    {"Email",init_suite,clean_suite, tests_email },
  //  {"Job",init_suite,clean_suite, tests_job },
    {"Scheduler", init_suite,clean_suite, tests_scheduler },
    {"MetaAgent", init_suite, clean_suite, tests_meta_agent },
    {"Agent", init_suite, clean_suite, tests_agent },
    {"Event",init_suite,clean_suite, tests_event },
    CU_SUITE_INFO_NULL
};
#endif

int main( int argc, char *argv[] )
{
#if !GLIB_CHECK_VERSION(2,35,0)
  g_type_init ();
  g_thread_init(NULL);
#endif

  return focunit_main(argc, argv, "scheduler_Tests", suites);
}
