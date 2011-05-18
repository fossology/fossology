/*********************************************************************
Copyright (C) 2010 Hewlett-Packard Development Company, L.P.

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

/* for ununpack.c, start */
extern CU_TestInfo TraverseStart_testcases[]; 
extern CU_TestInfo FindCmd_testcases[];
extern CU_TestInfo UnunpackEntry_testcases[];
extern CU_TestInfo Traverse_testcases[]; 
extern CU_TestInfo RunCommand_testcases[];
extern CU_TestInfo TraverseChild_testcases[];
extern CU_TestInfo CopyFile_testcases[];
extern CU_TestInfo Prune_testcases[];

/* FindCmd */
extern int FindCmdInit();
extern int FindCmdClean();

/* TraverseStart */
extern int TraverseStartInit();
extern int TraverseStartClean();

/* Traverse */
extern int TraverseInit();
extern int TraverseClean();

/* TraverseChildInit */
extern int TraverseChildInit();

/* CopyFile */
extern int CopyFileInit();
extern int CopyFileClean();

/* Prune */
extern int PruneInit();
extern int PruneClean();

/* for ununpack.c, end */

/* for ununpack-ar.c, start */
extern CU_TestInfo ExtractAR_testcases[];
/* for ununpack-ar.c, end */

/* for ununpack-iso.c, start */
extern CU_TestInfo ExtractISO_testcases[];
/* for ununpack-iso.c, end */

/* for ununpack-disk.c, start */
extern CU_TestInfo ExtractDisk_testcases[];
extern CU_TestInfo FatDiskName_testcases[];

extern int FatDiskNameInit();
extern int FatDiskNameClean();
/* for ununpack-disk.c, end */

CU_SuiteInfo suites[] = {
        // for ununpack.c
        {"Testing the function TraverseStart:", TraverseStartInit, TraverseStartClean, TraverseStart_testcases},
        {"Testing the function FindCmd:", FindCmdInit, FindCmdClean, FindCmd_testcases},
        {"Testing the function UnunpackEntry:", NULL, NULL, UnunpackEntry_testcases},
        {"Testing the function Traverse:", TraverseInit, TraverseClean, Traverse_testcases},
        {"Testing the function RunCommand:", NULL, NULL, RunCommand_testcases},
        {"Testing the function TraverseChild:", TraverseChildInit, NULL, TraverseChild_testcases},
        {"Testing the function CopyFile:", CopyFileInit, CopyFileClean, CopyFile_testcases},
        {"Testing the function Prune:", PruneInit, PruneClean, Prune_testcases},
        // for ununpack-ar.c
        {"Testing the function ExtractAR:", NULL, NULL, ExtractAR_testcases},
        // for ununpack-iso.c
        {"Testing the function ExtractISO:", NULL, NULL, ExtractISO_testcases},
        // for ununpack-disk.c
        {"Testing the function ExtractDisk:", NULL, NULL, ExtractDisk_testcases},
        {"Testing the function FatDiskName:", FatDiskNameInit, FatDiskNameClean, FatDiskName_testcases},
        CU_SUITE_INFO_NULL
};
