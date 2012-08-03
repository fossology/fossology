/*********************************************************************
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
*********************************************************************/

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

/**
 * We don't want to actually generate any error messages. To do this, the log
 * file will be set to /dev/null.
 *
 * @return -1 on failure, 0 of success
 */
int init_suite(void)
{
  if(main_log)
    log_destroy(main_log);
  main_log = log_new("./founit.log", "scheduler", getpid());
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
  log_destroy(main_log);
  main_log = NULL;
  return 0;
}

/* ************************************************************************** */
/* *** main and suite decl ************************************************** */
/* ************************************************************************** */

/* create test suite */
CU_SuiteInfo suites[] =
{

    CU_SUITE_INFO_NULL
};

int main( int argc, char *argv[] )
{
  g_type_init();
  g_thread_init(NULL);

  focunit_main(argc, argv, "scheduler_Tests", suites) ;

  return 0;
}
