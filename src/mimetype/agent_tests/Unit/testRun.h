/*
 SPDX-FileCopyrightText: © 2011 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

#ifndef RUN_TESTS_H
#define RUN_TESTS_H

#include "libfocunit.h"
#include "libfodbreposysconf.h"

/* for finder.c, start */
extern CU_TestInfo testcases_DBCheckMime[];
extern CU_TestInfo testcases_DBLoadMime[];
extern CU_TestInfo testcases_DBFindMime[];
extern CU_TestInfo testcases_CheckMimeTypes[];
extern CU_TestInfo testcases_DBCheckFileExtention[];
extern CU_TestInfo testcases_Utilities[];



/* for DBCheckMime() */
extern int DBCheckMimeInit();
extern int DBCheckMimeClean();
/* for DBLoadMime() */
extern int DBLoadMimeInit();
extern int DBLoadMimeClean();
/* for DBFindMime() */
extern int DBFindMimeInit();
extern int DBFindMimeClean();
/* for functions in testcases_DBCheckFileExtention */
extern int DBInit();
extern int DBClean();

/* for finder.c, end */

#endif
