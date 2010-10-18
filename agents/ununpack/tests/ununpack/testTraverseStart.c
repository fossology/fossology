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

extern void	TraverseStart	(char *Filename, char *Label, char *NewDir, int Recurse);

/* global variables. This is used to avoid needing to initialize a   */


/* ************************************************************************** */
/* **** local declarations **************************************** */
/* ************************************************************************** */


void testTraverseStartNormal()
{
  #if 0
  char *Filename = "../testdata4unpack.7z";
  char *Label = "call by main";
  char *NewDir = "../test-result";
  #endif
  int Recurse = -1;
  TraverseStart("", "", "", Recurse);
}

CU_TestInfo TraverseStart_testcases[] =
{
    {"Testing TraverseStart normal:", testTraverseStartNormal},
    CU_TEST_INFO_NULL
};
