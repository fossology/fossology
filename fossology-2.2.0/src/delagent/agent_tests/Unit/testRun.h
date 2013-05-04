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
CU_SuiteInfo suites[] = {
    {"Testing the function ListFolders:", DelagentDBInit, DelagentClean, testcases_ListFolders},
    {"Testing the function DeleteFolders:", DelagentInit, DelagentClean, testcases_DeleteFolders},
    {"Testing the function ReadParameter:", DelagentDBInit, DelagentClean, testcases_ReadParameter},
    CU_SUITE_INFO_NULL
};

#endif
