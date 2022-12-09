/*
 SPDX-FileCopyrightText: © 2010-2011 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/
#include "run_tests.h"
/**
 * \file
 * \brief Unit test cases for Traverse()
 */
static char *Label = "called by main";
static char *Basename ="";
static ParentInfo *PI = NULL;
static int Result = 0;

/**
 * @brief initialize
 */
int  TraverseInit()
{
  MagicCookie = magic_open(MAGIC_PRESERVE_ATIME|MAGIC_MIME);
  if (MagicCookie == NULL)
  {
    fprintf(stderr,"FATAL: Failed to initialize magic cookie\n");
    return -1;
  }

  magic_load(MagicCookie,NULL);
  return 0;
}

/**
 * @brief clean env and others
 */
int TraverseClean()
{
  magic_close(MagicCookie);
  return 0;
}

/**
 * @brief normal test for one package
 * \test
 * -# Call Traverse() on a single package
 * -# Check if the files are unpacked
 */
void testTraverseNormal4Package()
{
  Filename = "../testdata/testthree.zip";
  Basename = "testthree.zip";
  deleteTmpFiles(NewDir);
  ParentInfo PITest = {0, 1287725739, 1287725739, 0, 0};
  PI = &PITest;
  Result = Traverse(Filename,Basename,Label,NewDir,Recurse,PI);
  exists = file_dir_exists("./test-result/testthree.zip.dir/testtwo.zip.dir/test.zip.dir/ununpack");
  FO_ASSERT_EQUAL(exists, 1); // is existing
  FO_ASSERT_EQUAL(Result, 1); // Filename is one containter
}

/**
 * @brief normal test for one package another case
 * \test
 * -# Call Traverse() on a single package
 * -# Check if the files are unpacked
 */
void testTraverseNormal4Package2()
{
  Filename = "../testdata/test.ar";
  Basename = "test.ar";
  deleteTmpFiles(NewDir);
  ParentInfo PITest = {0, 1287725739, 1287725739, 0, 0};
  PI = &PITest;
  Result = Traverse(Filename,Basename,Label,NewDir,Recurse,PI);
  exists = file_dir_exists("./test-result/test.ar.dir/test.tar");
  FO_ASSERT_EQUAL(exists, 1); //  is existing
  FO_ASSERT_EQUAL(Result, 1); // Filename is one containter
}

/**
 * @brief normal test for one directory
 * \test
 * -# Call Traverse() on a directory containing packages
 * -# Check if the files are unpacked properly
 */
void testTraverseNormal4Dir()
{
  int returnval;
  Filename = "../testdata";
  Basename = NULL;
  deleteTmpFiles(NewDir);
  MkDirs("./test-result/testdata");
  char *cmdline = "/bin/cp -r ../testdata/* ./test-result/testdata/";
  returnval = system(cmdline); // cp ../testdata to ./test-result/testdata/
  if(returnval > -1)
  {
    ParentInfo PITest = {0, 1287725739, 1287725739, 0, 0};
    PI = &PITest;
    Label = "Called by dir/wait";
    Result = Traverse(Filename,Basename,Label,NewDir,Recurse,PI);
    exists = file_dir_exists("./test-result/testdata/test.jar.dir/ununpack");
    FO_ASSERT_EQUAL(exists, 1); // is existing
    FO_ASSERT_EQUAL(Result, 1); // Filename is one containter
  }
}

/**
 * @brief normal test for rpm
 * \test
 * -# Call Traverse() on a single RPM package
 * -# Check if the files are unpacked
 */
void testTraverseNormal4Rpm()
{
  Filename = "../testdata/test.rpm";
  Basename = "test.rpm";
  deleteTmpFiles(NewDir);
  ParentInfo PITest = {0, 1287725739, 1287725739, 0, 0};
  PI = &PITest;
  Result = Traverse(Filename,Basename,Label,NewDir,Recurse,PI);
  exists = file_dir_exists("./test-result/test.rpm.unpacked.dir/usr/share/fossology/bsam/VERSION");
  FO_ASSERT_EQUAL(exists, 1); // is existing
  FO_ASSERT_EQUAL(Result, 1); // Filename is one containter
}

/**
 * @brief abnormal test for null parameters
 * \test
 * -# Call Traverse() on empty strings
 * -# Check if function returns 0
 * -# Check if nothing is done by function
 */
void testTraverseNullParams()
{
  Filename = "";
  Basename = "";
  deleteTmpFiles(NewDir);
  ParentInfo PITest = {0, 1287725739, 1287725739, 0, 0};
  PI = &PITest;
  Result = Traverse(Filename,Basename,Label,NewDir,Recurse,PI);
  exists = file_dir_exists("./test-result");
  FO_ASSERT_EQUAL(exists, 0); //  not  existing
  FO_ASSERT_EQUAL(Result, 0); // Filename is not one containter
}

/* ************************************************************************** */
/* **** cunit test cases **************************************************** */
/* ************************************************************************** */

CU_TestInfo Traverse_testcases[] =
{
  {"Traverse normal package:", testTraverseNormal4Package},
  {"Traverse normal package another:", testTraverseNormal4Package2},
  {"Traverse normal directory:", testTraverseNormal4Dir},
  {"Traverse normal rpm:", testTraverseNormal4Rpm},
  {"Traverse null paramters:", testTraverseNullParams},
  CU_TEST_INFO_NULL
};
