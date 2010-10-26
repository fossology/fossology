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
#include "utility.h"

/* used funtions */
int     UnunpackEntry(int argc, char *argv[]);

/* test functions */
void testUnunpackEntryNormal()
{
  int argc = 5;
  char *argv[] = {"../../ununpack", "-qCR", "../test-data/testdata4unpack.7z", "-d", "../test-result/"};
  deleteTmpFiles("../test-result/");
  existed = file_dir_existed("../test-result/");
  CU_ASSERT_EQUAL(existed, 0); // ../test-result/ is not existing
  UnunpackEntry(argc, argv);
  existed = file_dir_existed("../test-result/");
  CU_ASSERT_EQUAL(existed, 1); // ../test-result/ is existing
}

void testUnunpackEntryNormalDeleteResult()
{
  int argc = 5;

  char *argv[] = {"../../ununpack", "-qCRx", "../test-data/testdata4unpack.7z", "-d", "../test-result/"};
  deleteTmpFiles("../test-result/");
  existed = file_dir_existed("../test-result/");
  CU_ASSERT_EQUAL(existed, 0); // ../test-result/ is not existing
  UnunpackEntry(argc, argv);
  existed = file_dir_existed("../test-result/");
  CU_ASSERT_EQUAL(existed, 0); // ../test-result/ is not existing
}
CU_TestInfo UnunpackEntry_testcases[] =
{
    {"Testing testUnunpackEntryNormal:", testUnunpackEntryNormal},
    {"Testing testUnunpackEntryNormalDeleteResult:", testUnunpackEntryNormalDeleteResult},
    CU_TEST_INFO_NULL
};
