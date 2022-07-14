/*
 SPDX-FileCopyrightText: © 2011-2012 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

/* cunit includes */
#include <CUnit/CUnit.h>
#include "wget_agent.h"
#include "utility.h"
#include "libfodbreposysconf.h"

/**
 * \file
 * \brief testing for the function GetURL()
 *
 * int GetURL(char *TempFile, char *URL, char *TempFileDir)
 * char *TempFile - used when upload from URL by the scheduler, the downloaded file(directory) will be archived as this file
 *               when running from command, this parameter is null, e.g. /var/local/lib/fossology/agents/wget.32732
 *
 * char *URL - the url you want to download
 *
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
 * \brief The URL is one file
 *
 * TempFileDir is ./test_result,
 * TempFile is empty
 * \test
 * -# Load a single file URL
 * -# Set the TempFileDir
 * -# Call GetURL()
 * -# Check if the file got downloaded
 */
void testGetURLNormal_URLIsOneFile()
{
  strcpy(URL, "https://mirrors.kernel.org/fossology/releases/3.0.0/ubuntu/14.04/fossology.sources.list");
  strcpy(TempFileDir, "./test_result");
  GetURL(TempFile, URL, TempFileDir); /* download the file mkpackages into ./test_result/ */
  int existed = file_dir_existed("./test_result/mirrors.kernel.org/fossology/releases/3.0.0/ubuntu/14.04/fossology.sources.list");
  CU_ASSERT_EQUAL(existed, 1); /* the file downloaded? */
}

/**
 * \brief the URL is one dir
 *
 * TempFileDir is ./test_result
 * TempFile is not empty
 * \test
 * -# Set wget parameters to include several files
 * -# Load a URL for a file
 * -# Set the TempFileDir and TempFile
 * -# Call GetURL()
 * -# Check if the files were downloaded
 */
void testGetURLAbnormal_URLIsOneDir()
{
  strcpy(GlobalParam, "-l 1 -A *.list -R *.deb");
  strcpy(URL, "https://mirrors.kernel.org/fossology/releases/3.0.0/ubuntu/14.04/");
  strcpy(TempFileDir, "./test_result/");
  strcpy(TempFile, "./test_result/fossology.sources.list");
  GetURL(TempFile, URL, TempFileDir);
  int existed = file_dir_existed("./test_result/fossology.sources.list");
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

