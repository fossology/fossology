/*********************************************************************
Copyright (C) 2011-2012 Hewlett-Packard Development Company, L.P.

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

/* cunit includes */
#include <CUnit/CUnit.h>
#include "wget_agent.h"
#include "utility.h"

/**
 * \file testUtilities.c
 * \brief testing for functions GetPosition, IsFile, TaintURL
 */

/* test functions */

/**
 * \brief for function IsFile 
 * a file
 */
void testIsFileNormal_RegulerFile()
{
  system("echo 'hello world' > ./test.file");
  char Fname[] = "./test.file";
  int isFile = IsFile(Fname, 1);
  CU_ASSERT_EQUAL(isFile, 1);
  RemoveDir(Fname);
}

/**
 * \brief for function IsFile
 * a file
 */
void testIsFileNormal_SymLink()
{
  system("echo 'hello world' > ./test.file");
  char Fname[] = "./test.file";
  int isFile = IsFile(Fname, 0);
  CU_ASSERT_EQUAL(isFile, 1);
  char NewFname[] = "./link.file";
  symlink(Fname, NewFname);
  isFile = IsFile(NewFname, 1);
  CU_ASSERT_EQUAL(isFile, 1);
#if 0
#endif
  RemoveDir(Fname);
  RemoveDir(NewFname);
}

/**
 * \brief for function GetPosition
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
 * \brief for function TaintURL 
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
 * \brief for function PathCheck()
 * 
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
 * \brief for function Archivefs(), dir
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
 * \brief for function Archivefs(), reguler file
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

