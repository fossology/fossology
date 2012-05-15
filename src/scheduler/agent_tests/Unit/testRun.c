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

#include <libfossology.h>
#include <testRun.h>

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
  if(log_file && fclose(log_file) != 0)
    return -1;
  if((log_file = fopen("/dev/null", "w+")) == NULL)
    return -1;
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
  if(fclose(log_file) != 0)
    return -1;

  log_file = NULL;
  return 0;
}

/* ************************************************************************** */
/* *** main and suite decl ************************************************** */
/* ************************************************************************** */

/* create test suite */
CU_SuiteInfo suites[] = {
    {"agent.c: meta",       init_suite,       clean_suite, tests_meta_agent },
    //{"agent.c:",      agent_init_suite, agent_clean_suite, tests_agent      },
    {"host.c:",             init_suite,       clean_suite, tests_host       },
    {"event.c:",            init_suite,       clean_suite, tests_event      },
    CU_SUITE_INFO_NULL
};

int main( int argc, char *argv[] )
{
  g_thread_init(NULL);

  return focunit_main(argc, argv, "scheduler_Tests", suites) ;
}
