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
