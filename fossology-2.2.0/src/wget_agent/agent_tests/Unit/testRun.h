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
