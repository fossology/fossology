/*
 Author: Daniele Fognini, Andreas Wuerl
 SPDX-FileCopyrightText: © 2013-2015 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
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
