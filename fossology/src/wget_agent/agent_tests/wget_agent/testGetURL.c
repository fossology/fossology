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
 * \file testGetURL.c
 * \brief testing for the function GetURL()
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
 * \brief 
 * the URL is one file 
 */
void testGetURLNormal_URLIsOneFile()
{
	strcpy(URL, "http://fossology.org/debian/mkpackages");
	strcpy(TempFileDir, "./test_result");
	GetURL(TempFile, URL, TempFileDir); /* download the file mkpackages into ./test_result/fossology.org/debian/ */
	int existed = file_dir_existed("./test_result/fossology.org/debian/mkpackages");
	CU_ASSERT_EQUAL(existed, 1); /* the file downloaded? */
}

/**
 * \brief testcases for function GetURL
 */
CU_TestInfo testcases_GetURL[] =
{
  {"Testing the function GetURL, the URL is a normal file:", testGetURLNormal_URLIsOneFile},
  CU_TEST_INFO_NULL
};

