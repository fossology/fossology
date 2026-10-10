/*
 SPDX-FileCopyrightText: © 2026 anshika-006

 SPDX-License-Identifier: GPL-2.0-only
*/

/* cunit includes */
#include <CUnit/CUnit.h>
#include "wget_agent.h"

/**
 * \file
 * \brief testing for the git credential handling:
 * UrlEncodeUserinfo() and replace_url_with_auth()
 */

/**
 * \brief Reset the globals used by replace_url_with_auth()
 */
static void resetGlobals()
{
  memset(GlobalURL, 0, URLMAX);
  memset(GlobalParam, 0, STRMAX);
}

/**
 * \brief Encode plain and special characters
 * \test
 * -# Call UrlEncodeUserinfo() with unreserved and reserved characters
 * -# Check that only the reserved ones are percent-encoded
 */
void testUrlEncodeUserinfo()
{
  char out[64];

  CU_ASSERT_EQUAL(UrlEncodeUserinfo("s3cret-._~", out, sizeof(out)), 0);
  CU_ASSERT_STRING_EQUAL(out, "s3cret-._~");

  CU_ASSERT_EQUAL(UrlEncodeUserinfo("pa#ss", out, sizeof(out)), 0);
  CU_ASSERT_STRING_EQUAL(out, "pa%23ss");

  CU_ASSERT_EQUAL(UrlEncodeUserinfo("p@ss:/?%", out, sizeof(out)), 0);
  CU_ASSERT_STRING_EQUAL(out, "p%40ss%3A%2F%3F%25");

  CU_ASSERT_EQUAL(UrlEncodeUserinfo("", out, sizeof(out)), 0);
  CU_ASSERT_STRING_EQUAL(out, "");
}

/**
 * \brief Output buffer too small
 * \test
 * -# Call UrlEncodeUserinfo() with a buffer that cannot hold the result
 * -# Check that it fails instead of writing past the buffer
 */
void testUrlEncodeUserinfoBufferTooSmall()
{
  char out[4];

  CU_ASSERT_EQUAL(UrlEncodeUserinfo("abcd", out, sizeof(out)), -1);
  CU_ASSERT_EQUAL(UrlEncodeUserinfo("##", out, sizeof(out)), -1);
}

/**
 * \brief Credentials without special characters
 * \test
 * -# Set the git URL and the username/password parameters
 * -# Call replace_url_with_auth()
 * -# Check that the credentials are in the URL and the parameters are consumed
 */
void testReplaceUrlWithAuthPlain()
{
  resetGlobals();
  strcpy(GlobalURL, "https://github.com/org/repo.git");
  strcpy(GlobalParam, "--username alice --password s3cret ");

  replace_url_with_auth();

  CU_ASSERT_STRING_EQUAL(GlobalURL, "https://alice:s3cret@github.com/org/repo.git");
  CU_ASSERT_STRING_EQUAL(GlobalParam, "");
}

/**
 * \brief Credentials with characters that are special in a URL
 * \test
 * -# Use a password containing '#' and a username containing '@'
 * -# Call replace_url_with_auth()
 * -# Check that both are percent-encoded in the URL
 */
void testReplaceUrlWithAuthSpecialCharacters()
{
  resetGlobals();
  strcpy(GlobalURL, "https://github.com/org/repo.git");
  strcpy(GlobalParam, "--username bob@example.com --password pa#ss ");

  replace_url_with_auth();

  CU_ASSERT_STRING_EQUAL(GlobalURL,
    "https://bob%40example.com:pa%23ss@github.com/org/repo.git");
  CU_ASSERT_STRING_EQUAL(GlobalParam, "");
}

/**
 * \brief Additional git parameters after the credentials are kept
 * \test
 * -# Add a branch parameter behind the credentials
 * -# Call replace_url_with_auth()
 * -# Check that the branch parameter stays in GlobalParam
 */
void testReplaceUrlWithAuthKeepsOtherParameters()
{
  resetGlobals();
  strcpy(GlobalURL, "http://127.0.0.1:8099/proj.git");
  strcpy(GlobalParam, "--username alice --password p@ss --single-branch --branch 'main'");

  replace_url_with_auth();

  CU_ASSERT_STRING_EQUAL(GlobalURL, "http://alice:p%40ss@127.0.0.1:8099/proj.git");
  CU_ASSERT_STRING_EQUAL(GlobalParam, "--single-branch --branch 'main'");
}

/**
 * \brief No credentials given
 * \test
 * -# Call replace_url_with_auth() without username and password
 * -# Check that the URL and the parameters stay untouched
 */
void testReplaceUrlWithAuthWithoutCredentials()
{
  resetGlobals();
  strcpy(GlobalURL, "https://github.com/org/repo.git");
  strcpy(GlobalParam, "--single-branch --branch 'main'");

  replace_url_with_auth();

  CU_ASSERT_STRING_EQUAL(GlobalURL, "https://github.com/org/repo.git");
  CU_ASSERT_STRING_EQUAL(GlobalParam, "--single-branch --branch 'main'");
}

/**
 * \brief Remove the shell escaping added by the web UI
 * \test
 * -# Call UnescapeShellEscaping() with backslashes in front of \, ", ` and $
 * -# Check that they are removed
 * -# Check that other backslashes and a trailing backslash are kept
 */
void testUnescapeShellEscaping()
{
  char str[64];

  strcpy(str, "d\\\"ouble");
  UnescapeShellEscaping(str);
  CU_ASSERT_STRING_EQUAL(str, "d\"ouble");

  strcpy(str, "back\\\\slash");
  UnescapeShellEscaping(str);
  CU_ASSERT_STRING_EQUAL(str, "back\\slash");

  strcpy(str, "do\\$llar\\`tick");
  UnescapeShellEscaping(str);
  CU_ASSERT_STRING_EQUAL(str, "do$llar`tick");

  strcpy(str, "lone\\x");
  UnescapeShellEscaping(str);
  CU_ASSERT_STRING_EQUAL(str, "lone\\x");

  strcpy(str, "end\\");
  UnescapeShellEscaping(str);
  CU_ASSERT_STRING_EQUAL(str, "end\\");

  strcpy(str, "plain");
  UnescapeShellEscaping(str);
  CU_ASSERT_STRING_EQUAL(str, "plain");
}

/**
 * \brief Passwords that the web UI shell-escapes (", $, backslash)
 * \test
 * -# Use the parameters the way the web UI writes them, with a backslash
 *    in front of the special character
 * -# Call replace_url_with_auth()
 * -# Check that the real password is percent-encoded, not the escaped text
 */
void testReplaceUrlWithAuthShellEscapedPassword()
{
  resetGlobals();
  strcpy(GlobalURL, "https://github.com/org/repo.git");
  strcpy(GlobalParam, "--username u --password d\\\"ouble ");
  replace_url_with_auth();
  CU_ASSERT_STRING_EQUAL(GlobalURL, "https://u:d%22ouble@github.com/org/repo.git");

  resetGlobals();
  strcpy(GlobalURL, "https://github.com/org/repo.git");
  strcpy(GlobalParam, "--username u --password do\\$llar --single-branch --branch 'main'");
  replace_url_with_auth();
  CU_ASSERT_STRING_EQUAL(GlobalURL, "https://u:do%24llar@github.com/org/repo.git");
  CU_ASSERT_STRING_EQUAL(GlobalParam, "--single-branch --branch 'main'");

  resetGlobals();
  strcpy(GlobalURL, "https://github.com/org/repo.git");
  strcpy(GlobalParam, "--username u --password back\\\\slash ");
  replace_url_with_auth();
  CU_ASSERT_STRING_EQUAL(GlobalURL, "https://u:back%5Cslash@github.com/org/repo.git");
}

/**
 * \brief Parameters that mention username and password but give no password
 * \test
 * -# Use a username that contains the word "password" and give no password
 * -# Call replace_url_with_auth()
 * -# Check that it does not crash and leaves the URL untouched
 */
void testReplaceUrlWithAuthMissingPassword()
{
  resetGlobals();
  strcpy(GlobalURL, "https://github.com/org/repo.git");
  strcpy(GlobalParam, "--username mypassword");

  replace_url_with_auth();

  CU_ASSERT_STRING_EQUAL(GlobalURL, "https://github.com/org/repo.git");
}

/**
 * \brief testcases for the git credential handling
 */
CU_TestInfo testcases_GitAuth[] =
{
  {"UrlEncodeUserinfo", testUrlEncodeUserinfo},
  {"UrlEncodeUserinfo buffer too small", testUrlEncodeUserinfoBufferTooSmall},
  {"replace_url_with_auth plain", testReplaceUrlWithAuthPlain},
  {"replace_url_with_auth special characters", testReplaceUrlWithAuthSpecialCharacters},
  {"replace_url_with_auth keeps other parameters", testReplaceUrlWithAuthKeepsOtherParameters},
  {"replace_url_with_auth without credentials", testReplaceUrlWithAuthWithoutCredentials},
  {"replace_url_with_auth missing password", testReplaceUrlWithAuthMissingPassword},
  {"UnescapeShellEscaping", testUnescapeShellEscaping},
  {"replace_url_with_auth shell-escaped password", testReplaceUrlWithAuthShellEscapedPassword},
  CU_TEST_INFO_NULL
};
