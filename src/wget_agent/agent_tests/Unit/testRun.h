/*
 SPDX-FileCopyrightText: © 2011 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

#ifndef RUN_TESTS_H
#define RUN_TESTS_H

#include "libfocunit.h"

/* for wget_agent.c, start */
extern CU_TestInfo testcases_GetURL[];
extern CU_TestInfo testcases_SetEnv[];
extern CU_TestInfo testcases_Utiliies[];
extern CU_TestInfo testcases_DBLoadGold[];

/* GetURL */
extern int GetURLInit();
extern int GetURLClean();

/* SetEvn */
extern int SetEnvInit();
extern int SetEnvClean();

/* DBLoadGold */
extern int DBLoadGoldInit();
extern int DBLoadGoldClean();

/* for wget_agent.c, end */

#endif
