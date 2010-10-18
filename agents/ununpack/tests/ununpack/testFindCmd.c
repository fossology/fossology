/*********************************************************************
Copyright (C) 2010 Hewlett-Packard Development Company, L.P.

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
#include "ununpack.h"

extern int	FindCmd	(char *Filename);
extern magic_t MagicCookie;
/* global variables. This is used to avoid needing to initialize a   */


/* ************************************************************************** */
/* **** local declarations **************************************** */
/* ************************************************************************** */


void testFindCmdNormal()
{
  #if 0
  char *Filename = "../test-data/testdata4unpack.7z";
  int result = 0;
  MagicCookie = (magic_t)0xb04e028;
  result = FindCmd(Filename);
  CU_ASSERT_EQUAL(result, 19);
  #endif
}

CU_TestInfo FindCmd_testcases[] =
{
    {"Testing FindCmd normal:", testFindCmdNormal},
    CU_TEST_INFO_NULL
};
