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
#include "libfodbreposysconf.h"

/**
 * \file testGetURL.c
 * \brief testing for the function GetURL()
 * int GetURL(char *TempFile, char *URL, char *TempFileDir)
 * char *TempFile - used when upload from URL by the scheduler, the downloaded file(directory) will be archived as this file
 *               when running from command, this parameter is null, e.g. /var/local/lib/fossology/agents/wget.32732
 * char *URL - the url you want to download
 * char *TempFileDir - where you want to store your downloaded file(directory)
 *
 * return int, 0 on success, non-zero on failure.
 */

static char TempFile[MAX_LENGTH];
static char URL[MAX_LENGTH];
static char TempFileDir[MAX_LENGTH];

/**
 * \brief initialize
 */
int  GetURLInit()
{
  return 0;
}
/**
 * \brief clean the env
 */
int GetURLClean()
{
  if (file_dir_existed(TempFileDir))
  {
    RemoveDir(TempFileDir);
  }

  return 0;
}

/* test functions */

/**
 * \brief the URL is one file 
 * TempFileDir is ./test_result
 * TempFile is empty
 */
void testGetURLNormal_URLIsOneFile()
{
  strcpy(URL, "http://www.fossology.org/testdata/wgetagent/mkpackages");
  strcpy(TempFileDir, "./test_result");
  GetURL(TempFile, URL, TempFileDir); /* download the file mkpackages into ./test_result/www.fossology.org/testdata/wgetagent/ */
  int existed = file_dir_existed("./test_result/www.fossology.org/testdata/wgetagent/mkpackages");
  CU_ASSERT_EQUAL(existed, 1); /* the file downloaded? */
}

/**
 * \brief the URL is one dir 
 * TempFileDir is ./test_result
 * TempFile is not empty
 */
void testGetURLAbnormal_URLIsOneDir()
{
  strcpy(GlobalParam, "-l 1 -A gz -R fosso*,index.html*");
  strcpy(URL, "http://www.fossology.org/testdata/wgetagent/debian/2.0.0/");
  strcpy(TempFileDir, "./test_result/");
  strcpy(TempFile, "./test_result/wget.tar");
  GetURL(TempFile, URL, TempFileDir); 
  int existed = file_dir_existed("./test_result/wget.tar");
  CU_ASSERT_EQUAL(existed, 1); /* the file downloaded? */
}

/**
 * \brief testcases for function GetURL
 */
CU_TestInfo testcases_GetURL[] =
{
#if 0
#endif
  {"GetURL:File", testGetURLNormal_URLIsOneFile},
  {"GetURL:Dir", testGetURLAbnormal_URLIsOneDir},
  CU_TEST_INFO_NULL
};

