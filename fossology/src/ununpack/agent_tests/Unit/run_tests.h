/*********************************************************************
Copyright (C) 2010-2011 Hewlett-Packard Development Company, L.P.

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

#include "../agent/ununpack.h"
#include "libfocunit.h"

extern char *Filename;
extern char *NewDir;
extern int Recurse;
extern int exists;
extern magic_t MagicCookie;

/* run_tests.c */
extern int file_dir_exists(char *path_name);

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

/* for ununpack-ar.c, start */
/* for ununpack-ar.c, end */

/* for ununpack-iso.c, start */
/* for ununpack-iso.c, end */

/* for ununpack-disk.c, start */
extern int FatDiskNameInit();
extern int FatDiskNameClean();
/* for ununpack-disk.c, end */

/* InitCmd */
extern int InitCmdInit();
extern int InitCmdClean();

#endif
