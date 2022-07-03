/*
 SPDX-FileCopyrightText: © 2011-2012 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

/* cunit includes */
#include <CUnit/CUnit.h>
#include "wget_agent.h"
#include "utility.h"

/**
 * \file
 * \brief testing for functions GetPosition, IsFile, TaintURL
 */

/* test functions */

/**
 * \brief Test for function IsFile()
 * \test
 * -# Create a file
 * -# Call IsFile()
 * -# Check if result is 1
 */
void testIsFileNormal_RegulerFile()
{
  int pid = system("echo 'hello world' > ./test.file");
  if (WIFEXITED(pid)) pid = WEXITSTATUS(pid);
  else pid = -1;
  char Fname[] = "./test.file";
  int isFile = IsFile(Fname, 1);
  CU_ASSERT_EQUAL(isFile, 1);
  RemoveDir(Fname);
}

/**
 * \brief Test for function IsFile()
 * a symlink
 * \test
 * -# Create a file and a symlink to the file
 * -# Call IsFile() to follow symlink
 * -# Check if result is 1
 */
void testIsFileNormal_SymLink()
{
  int pid = system("echo 'hello world' > ./test.file");
  if (WIFEXITED(pid)) pid = WEXITSTATUS(pid);
  else pid = -1;
  char Fname[] = "./test.file";
  int isFile = IsFile(Fname, 0);
  CU_ASSERT_EQUAL(isFile, 1);
  char NewFname[] = "./link.file";
  pid = symlink(Fname, NewFname);
  isFile = IsFile(NewFname, 1);
  CU_ASSERT_EQUAL(isFile, 1);
#if 0
#endif
  RemoveDir(Fname);
  RemoveDir(NewFname);
}

/**
 * \brief Test for function GetPosition()
 * \test
 * -# Create 3 URLs (http, https and ftp)
 * -# Call GetPosition() on 3 URLs
 * -# Check if correct position was returned
 */
void testGetPositionNormal()
{
  char URL[MAX_LENGTH];
  strcpy(URL, "http://fossology.org");
  int pos = GetPosition(URL);
  CU_ASSERT_EQUAL(pos, 7);
  memset(URL, 0, MAX_LENGTH);
  strcpy(URL, "https://encrypted.google.com/");
  pos = GetPosition(URL);
  CU_ASSERT_EQUAL(pos, 8);
  memset(URL, 0, MAX_LENGTH);
  strcpy(URL, "ftp://osms.chn.hp.com/pub/fossology/");
  pos = GetPosition(URL);
  CU_ASSERT_EQUAL(pos, 6);
}

/**
 * \brief Test for function TaintURL()
 * \test
 * -# Create URLs with unwanted characters
 * -# Call TaintURL()
 * -# Check if result is 1
 */
void testTaintURL()
{
  char Sin[MAX_LENGTH];
  char Sout[MAX_LENGTH];
  int SoutSize = MAX_LENGTH;
  /* the URL is failed to taint*/
  strcpy(Sin, "http://fossology.org #");
  int result = TaintURL(Sin, Sout, SoutSize);
  CU_ASSERT_EQUAL(result, 0); /* failed to taint */
  /* the URL is tainted */
  strcpy(Sin, "http://fossology.org/`debian/ 1.0.0/");
  result = TaintURL(Sin, Sout, SoutSize);
  CU_ASSERT_EQUAL(result, 1); /*  tainted */
#if 0
#endif
}

/**
 * \brief Test for function PathCheck()
 * \test
 * -# Create a path string with "%H"
 * -# Call PathCheck
 * -# Check if "%H" was replaced with HostName
 * \note free the pointer from PathCheck()
 */
void test_PathCheck()
{
  char source_path[] = "/srv/fossology/testDbRepo12704556/%H/wget";
  char des_path[1024] = {0};
  char HostName[1024] = {0};
  char *taint_path = PathCheck(source_path);
  gethostname(HostName, sizeof(HostName));
  snprintf(des_path, sizeof(des_path), "/srv/fossology/testDbRepo12704556/%s/wget", HostName);
  CU_ASSERT_STRING_EQUAL(des_path, taint_path); /*  tainted */
  free(taint_path);
}

/**
 * \brief Test for function Archivefs(), dir
 * \test
 * -# Create a directory with a file
 * -# Call Archivefs()
 * -# Check if the result is a tar archive
 */
void test_Archivefs_dir()
{
  char file_path[] = "./";
  char tar_file[] = "/tmp/Suckupfs.tar.dir/test.tar";
  char des_dir[] = "/tmp/Suckupfs.tar.dir/";
  int tar_status = -1;
  char commands[1024] = "";
  struct stat Status;
  if (stat(file_path, &Status) != 0) return; // file_path is not exist or can not access

  int res = Archivefs(file_path, tar_file, des_dir, Status);
  CU_ASSERT_EQUAL(1, res);
  tar_status = stat(file_path, &Status);
  CU_ASSERT_EQUAL(0, tar_status);
  snprintf(commands, sizeof(commands), "file %s |grep 'tar archive' >/dev/null 2>&1", tar_file);
  int rc = system(commands);
  CU_ASSERT_EQUAL(1, rc != -1 && (WEXITSTATUS(rc) == 0));
  rmdir(des_dir);
}

/**
 * \brief Test for function Archivefs(), reguler file
 * \test
 * -# Create a test file
 * -# Call Archivefs()
 * -# Check if the result is normal file
 */
void test_Archivefs_file()
{
  char file_path[] = "./Makefile";
  char tar_file[] = "/tmp/Suckupfs.tar.dir/testfile";
  char des_dir[] = "/tmp/Suckupfs.tar.dir/";
  int tar_status = -1;
  char commands[1024] = "";
  struct stat Status;
  if (stat(file_path, &Status) != 0) return; // file_path is not exist or can not access

  int res = Archivefs(file_path, tar_file, des_dir, Status);
  CU_ASSERT_EQUAL(1, res);
  tar_status = stat(file_path, &Status);
  CU_ASSERT_EQUAL(0, tar_status);
  snprintf(commands, sizeof(commands), "file %s |grep ASCII >/dev/null 2>&1", tar_file);
  int rc = system(commands);
  CU_ASSERT_EQUAL(1, rc != -1 && (WEXITSTATUS(rc) == 0));
  rmdir(des_dir);
}

/**
 * \brief testcases for function SetEnv
 */
CU_TestInfo testcases_Utiliies[] =
{
#if 0
#endif
{"Utiliies:IsFile_file", testIsFileNormal_RegulerFile},
{"Utiliies:IsFile_link", testIsFileNormal_SymLink},
{"Utiliies:GetPosition_normal", testGetPositionNormal},
{"Utiliies:TaintURL_normal", testTaintURL},
{"Utiliies:PathCheck", test_PathCheck},
{"Utiliies:Archivefs_dir", test_Archivefs_dir},
{"Utiliies:Archivefs_file", test_Archivefs_file},
  CU_TEST_INFO_NULL
};

