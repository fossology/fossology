/*
 SPDX-FileCopyrightText: © 2011 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @dir
 * @brief Unit tests for libfossology
 */
#include <libfocunit.h>
#include <libfodbreposysconf.h>

/* ************************************************************************** */
/* * test case sets                                                         * */
/* *    when adding the tests for a library, simply add the "extern"        * */
/* *    declaration and add it to the suites array                          * */
/* ************************************************************************** */

char* dbConf;

extern CU_TestInfo fossconfig_testcases[];
extern CU_TestInfo fossscheduler_testcases[];
extern CU_TestInfo libfossdb_testcases[];
extern CU_TestInfo libfossdbmanager_testcases[];

/**
* array of every test suite. There should be at least one test suite for every
* library includes in libfossology.
* \todo Fix `fossscheduler_testcases`
*/

#if CU_VERSION_P == 213
CU_SuiteInfo suites[] =
  {
    {"Testing libfossdb", NULL, NULL, NULL, NULL, libfossdb_testcases},
    {"Testing fossconfig", NULL, NULL, NULL, NULL, fossconfig_testcases},
    {"Testing libfossdbmanger", NULL, NULL, NULL, NULL, libfossdbmanager_testcases},
    // TODO fix { "Testing fossscheduler", NULL, NULL, fossscheduler_testcases },
    CU_SUITE_INFO_NULL
  };
#else
CU_SuiteInfo suites[] =
  {
    {"Testing libfossdb", NULL, NULL, libfossdb_testcases},
    {"Testing fossconfig", NULL, NULL, fossconfig_testcases},
    {"Testing libfossdbmanger", NULL, NULL, libfossdbmanager_testcases},
    // TODO fix { "Testing fossscheduler", NULL, NULL, fossscheduler_testcases },
    CU_SUITE_INFO_NULL
  };
#endif

/* ************************************************************************** */
/* **** main function ******************************************************* */
/* ************************************************************************** */

int main(int argc, char** argv) {
  fo_dbManager* dbManager = NULL;

  if (argc > 1) {
    dbConf = argv[1];
  } else {
    dbManager = createTestEnvironment("..", NULL, 0);
    dbConf = get_dbconf();
  }

  const int returnValue = focunit_main(argc, argv, "lib c Tests", suites);

  if (dbManager)
    dropTestEnvironment(dbManager, "..", NULL);
  return returnValue;
}
