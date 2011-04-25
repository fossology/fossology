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

void	TraverseStart	(char *Filename, char *Label, char *NewDir, int Recurse);

static char *Label = "";

/**
 * @brief initialize
 */
int  TraverseStartInit()
{
  MagicCookie = magic_open(MAGIC_PRESERVE_ATIME|MAGIC_MIME);
  if (MagicCookie == NULL)
  {
    fprintf(stderr,"FATAL: Failed to initialize magic cookie\n");
    return -1;
  }

  magic_load(MagicCookie,NULL);
  return 0;
}

/**
 * @brief clean env and others
 */
int TraverseStartClean()
{
  magic_close(MagicCookie);
  return 0;
}

void testTraverseStartNormal()
{
  Filename = "./test-data/testdata4unpack/threezip.zip";
  deleteTmpFiles("./test-result/");
  existed = file_dir_existed("./test-result/threezip.zip.dir/twozip.zip.dir/Desktop.zip.dir/record.txt");
  CU_ASSERT_EQUAL(existed, 0); // ./test-result/ is not existing
  TraverseStart(Filename, Label, NewDir, Recurse);
  existed = file_dir_existed("./test-result/threezip.zip.dir/twozip.zip.dir/Desktop.zip.dir/record.txt");
  CU_ASSERT_EQUAL(existed, 1); // ./test-result/ is existing
}

CU_TestInfo TraverseStart_testcases[] =
{
    {"Testing TraverseStart normal:", testTraverseStartNormal},
    CU_TEST_INFO_NULL
};
