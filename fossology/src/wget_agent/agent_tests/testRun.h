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
#include "CUnit/CUnit.h"
#include "CUnit/Automated.h"

/* for wget_agent.c, start */
extern CU_TestInfo testcases_GetURL[];
extern CU_TestInfo testcases_SetEnv[];

/* GetURL */
extern int GetURLInit();
extern int GetURLClean();

/* for wget_agent.c, end */

/**
 * \brief all test suites for wget agent
 */
CU_SuiteInfo suites[] = {
    // for wget_agent.c
    {"Testing the function GetURL:", GetURLInit, GetURLClean, testcases_GetURL},
    {"Testing the function SetEnv:", NULL, NULL, testcases_SetEnv},
    CU_SUITE_INFO_NULL
};

