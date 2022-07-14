/*
 SPDX-FileCopyrightText: © 2011 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

#include "testRun.h"

/**
 * \file
 * \brief main function for in this testing module
 */

/**
 * \brief all test suites for wget agent
 */

#if CU_VERSION_P == 213
CU_SuiteInfo suites[] = {
    // for wget_agent.c
#if 0
#endif
    {"GetURL", NULL, NULL, (CU_SetUpFunc)GetURLInit, (CU_TearDownFunc)GetURLClean, testcases_GetURL},
    {"SetEnv", NULL, NULL, (CU_SetUpFunc)SetEnvInit, (CU_TearDownFunc)SetEnvClean, testcases_SetEnv},
    {"Utiliies", NULL, NULL, NULL, NULL, testcases_Utiliies},
    {"DBLoadGold", NULL, NULL, (CU_SetUpFunc)DBLoadGoldInit, (CU_TearDownFunc)DBLoadGoldClean, testcases_DBLoadGold},
    CU_SUITE_INFO_NULL
};
#else
CU_SuiteInfo suites[] = {
    // for wget_agent.c
#if 0
#endif
    {"GetURL", GetURLInit, GetURLClean, testcases_GetURL},
    {"SetEnv", SetEnvInit, SetEnvClean, testcases_SetEnv},
    {"Utiliies", NULL, NULL, testcases_Utiliies},
    {"DBLoadGold", DBLoadGoldInit, DBLoadGoldClean, testcases_DBLoadGold},
    CU_SUITE_INFO_NULL
};
#endif

/*
 * \brief  main test function
 */
int main( int argc, char *argv[] )
{
  return focunit_main(argc, argv, "wget_agent_Tests", suites) ;
}

