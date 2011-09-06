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

#include "libcunit.h"

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

/**
 * \brief all test suites for mimetype
 */
CU_SuiteInfo suites[] = {
    // for finder.c 
    {"Testing the function DBCheckMime:", DBCheckMimeInit, DBCheckMimeClean, testcases_DBCheckMime},
#if 0
#endif
    {"Testing the function DBLoadMime:", DBLoadMimeInit, DBLoadMimeClean, testcases_DBLoadMime},
    {"Testing the function DBFindMime:", DBFindMimeInit, DBFindMimeClean, testcases_DBFindMime},
    {"Testing the function CheckMimeType:", DBInit, DBClean, testcases_CheckMimeTypes},
    {"Testing the function DBCheckFileExtention:", DBInit, DBClean, testcases_DBCheckFileExtention},
    {"Testing Utilities:", NULL, NULL, testcases_Utilities},
    CU_SUITE_INFO_NULL
};

