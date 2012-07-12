/*********************************************************************
Copyright (C) 2012 Hewlett-Packard Development Company, L.P.

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
#include "run_tests.h"

static int Result = 0;

/**
 * @brief function IsDebianSourceFile(char *Filename)
 */
void testIsDebianSourceFile()
{
  char *Filename = "../test-data/testdata4unpack/fcitx_3.6.2-1.dsc";
  Result = IsDebianSourceFile(Filename);
  FO_ASSERT_EQUAL(Result, 1);
}
/**
 * @brief function IsDebianSourceFile(char *Filename)
 */
void testIsNotDebianSourceFile()
{
  char *Filename = "../test-data/testdata4unpack/tx_3.6.2.orig.tar.gz";
  Result = IsDebianSourceFile(Filename);
  FO_ASSERT_EQUAL(Result, 0);
}

/* ************************************************************************** */
/* **** cunit test cases **************************************************** */
/* ************************************************************************** */

CU_TestInfo IsFunctions_testcases[] =
{
  {"IsDebianSourceFile:", testIsDebianSourceFile},
  {"IsNotDebianSourceFile:", testIsNotDebianSourceFile},
  CU_TEST_INFO_NULL
};
