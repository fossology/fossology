/*
 SPDX-FileCopyrightText: © 2010-2011 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/
#ifndef RUN_TESTS_H
#define RUN_TESTS_H

#include <stdio.h>
#include <stdlib.h>
#include <assert.h>
#include "CUnit/CUnit.h"
#include "CUnit/Automated.h"

#include "../agent/ununpack.h"
#include "libfocunit.h"
#include "libfodbreposysconf.h"

extern char *Filename;
extern char *NewDir;
extern int Recurse;
extern int exists;
extern magic_t MagicCookie;

/* run_tests.c */
extern int file_dir_exists(char *path_name);

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

/* DBInsert */
extern int DBInsertInit();
extern int DBInsertClean();
#endif
