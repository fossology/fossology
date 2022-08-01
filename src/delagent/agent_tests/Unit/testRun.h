/*
 SPDX-FileCopyrightText: © 2011 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/
#ifndef RUN_TESTS_H
#define RUN_TESTS_H

#include <stdio.h>
#include <stdlib.h>
#include <assert.h>
#include "CUnit/CUnit.h"
#include "CUnit/Automated.h"

#include "libfocunit.h"
#include "libfodbreposysconf.h"

/* for util.c, start */
extern CU_TestInfo testcases_ListFolders[];
extern CU_TestInfo testcases_DeleteFolders[];
extern CU_TestInfo testcases_ReadParameter[];

/* Database Init and Clean */
int DelagentDBInit();
int DelagentDBClean();
/* Database and RepositoryInit and Clean */
int DelagentInit();
int DelagentClean();

/* for util.c, end */

/**
 * \brief all test suites for delagent
 */

#if CU_VERSION_P == 213
CU_SuiteInfo suites[] = {
    {"Testing the function ListFolders:", NULL, NULL, (CU_SetUpFunc)DelagentDBInit, (CU_TearDownFunc)DelagentClean, testcases_ListFolders},
    {"Testing the function DeleteFolders:", NULL, NULL, (CU_SetUpFunc)DelagentInit, (CU_TearDownFunc)DelagentClean, testcases_DeleteFolders},
    CU_SUITE_INFO_NULL
};
#else
CU_SuiteInfo suites[] = {
    {"Testing the function ListFolders:", DelagentDBInit, DelagentClean, testcases_ListFolders},
    {"Testing the function DeleteFolders:", DelagentInit, DelagentClean, testcases_DeleteFolders},
    CU_SUITE_INFO_NULL
};
#endif

#endif
