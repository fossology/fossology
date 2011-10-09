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
 * 
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
  CU_TEST_INFO_NULL
};

