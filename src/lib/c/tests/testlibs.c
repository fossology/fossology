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
