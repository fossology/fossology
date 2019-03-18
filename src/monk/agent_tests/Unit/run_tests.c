/*
Author: Daniele Fognini, Andreas Wuerl
Copyright (C) 2013-2015, Siemens AG

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
*/

/* local includes */
#include <libfocunit.h>

/* cunit includes */
#include <libfodbreposysconf.h>

#define AGENT_DIR "../../"

fo_dbManager* dbManager;

/* ************************************************************************** */
/* **** test case sets ****************************************************** */
/* ************************************************************************** */

extern CU_TestInfo string_operations_testcases[];
extern CU_TestInfo file_operations_testcases[];
extern CU_TestInfo license_testcases[];
extern CU_TestInfo highlight_testcases[];
extern CU_TestInfo hash_testcases[];
extern CU_TestInfo diff_testcases[];
extern CU_TestInfo match_testcases[];
extern CU_TestInfo database_testcases[];
extern CU_TestInfo encoding_testcases[];
extern CU_TestInfo serialize_testcases[];

extern int license_setUpFunc();
extern int license_tearDownFunc();

extern int database_setUpFunc();
extern int database_tearDownFunc();

/* ************************************************************************** */
/* **** create test suite *************************************************** */
/* ************************************************************************** */

#if CU_VERSION_P == 213
CU_SuiteInfo suites[] = {
    {"Testing process:", NULL, NULL, NULL, NULL, string_operations_testcases},
    {"Testing monk:", NULL, NULL, NULL, NULL, file_operations_testcases},
    {"Testing license:", NULL, NULL, (CU_SetUpFunc)license_setUpFunc, (CU_TearDownFunc)license_tearDownFunc, license_testcases},
    {"Testing highlighting:", NULL, NULL, NULL, NULL, highlight_testcases},
    {"Testing hash:", NULL, NULL, NULL, NULL, hash_testcases},
    {"Testing diff:", NULL, NULL, NULL, NULL, diff_testcases},
    {"Testing match:", NULL, NULL, NULL, NULL, match_testcases},
    {"Testing database:", NULL, NULL, (CU_SetUpFunc)database_setUpFunc, (CU_TearDownFunc)database_tearDownFunc, database_testcases},
    {"Testing encoding:", NULL, NULL, NULL, NULL, encoding_testcases},
    {"Testing serialize:", NULL, NULL, NULL, NULL, serialize_testcases},
    CU_SUITE_INFO_NULL
};
#else
CU_SuiteInfo suites[] = {
    {"Testing process:", NULL, NULL, string_operations_testcases},
    {"Testing monk:", NULL, NULL, file_operations_testcases},
    {"Testing license:", license_setUpFunc, license_tearDownFunc, license_testcases},
    {"Testing highlighting:", NULL, NULL, highlight_testcases},
    {"Testing hash:", NULL, NULL, hash_testcases},
    {"Testing diff:", NULL, NULL, diff_testcases},
    {"Testing match:", NULL, NULL, match_testcases},
    {"Testing database:", database_setUpFunc, database_tearDownFunc, database_testcases},
    {"Testing encoding:", NULL, NULL, encoding_testcases},
    {"Testing serialize:", NULL, NULL, serialize_testcases},
    CU_SUITE_INFO_NULL
};
#endif

/* ************************************************************************** */
/* **** main test functions ************************************************* */
/* ************************************************************************** */

int main(int argc, char** argv) {
    dbManager = createTestEnvironment(AGENT_DIR, "monk", 0);
    const int returnValue = focunit_main(argc, argv, "monk_agent_Tests", suites);
    if (returnValue == 0) {
        dropTestEnvironment(dbManager, AGENT_DIR, "monk");
    } else {
        printf("preserving test environment in '%s'\n", get_sysconfdir());
    }
    return returnValue;
}
