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

#if 0
/**
 * @brief when unpack end, do not delete all the unpacked files
 * quiet (generate no output).
 * force continue when unpack tool fails.
 * recursively unpack
 */
void testUnunpackEntryNormal()
{
  int argc = 5;
  char *argv[] = {"../ununpack", "-qCR", "./test-data/testdata4unpack.7z", "-d", "./test-result/"};
  deleteTmpFiles("./test-result/");
  existed = file_dir_existed("./test-result/");
  CU_ASSERT_EQUAL(existed, 0); // ./test-result/ is not existing
  UnunpackEntry(argc, argv);
  existed = file_dir_existed("./test-result/testdata4unpack.7z.dir/testdata4unpack/libfossagent.a.dir/libfossagent.o");
  CU_ASSERT_EQUAL(existed, 1); // ./test-result/ is existing
}
#endif

/**
 * @brief when unpack end, delete all the unpacked files
 */
void testUnunpackEntryNormalDeleteResult()
{
  int argc = 5;

  char *argv[] = {"../ununpack", "-qCRLx", "./test-data/testdata4unpack.7z", "-d", "./test-result/"};
  deleteTmpFiles("./test-result/");
  existed = file_dir_existed("./test-result/");
  CU_ASSERT_EQUAL(existed, 0); // ./test-result/ is not existing
  UnunpackEntry(argc, argv);
  existed = file_dir_existed("./test-result/");
  CU_ASSERT_EQUAL(existed, 0); // ./test-result/ is not existing
}

#if 0
/**
 * @brief when unpack end, do not delete all the unpacked files
 * the package is ext3 type
 * multy process
 */
void testUnunpackEntryNormalMultyProcess1()
{
  int argc = 7;
  char *argv[] = {"../ununpack", "-qCR", "-m", "5", "./test-data/testdata4unpack/ext3test-image", "-d", "./test-result/"};  
  deleteTmpFiles("./test-result/");
  existed = file_dir_existed("./test-result/");
  CU_ASSERT_EQUAL(existed, 0); // ./test-result/ is not existing
  UnunpackEntry(argc, argv);
  existed = file_dir_existed("./test-result/libfossagent.a.dir/");
  CU_ASSERT_EQUAL(existed, 1); // existing
  printf("one start\n");
}
#endif

/** only one multy process, otherwise will fail, please notice **/

/**
 * @brief when unpack end, do not delete all the unpacked files
 * the package is xx.tar/xxx.rpm type
 * multy process
 */
void testUnunpackEntryNormalMultyProcess()
{
  int argc = 5;
  char *argv[] = {"../ununpack", "-qCR", "./test-data/testdata4unpack/rpm.tar", "-d", "./test-result/"}; 
  deleteTmpFiles("./test-result/");
  existed = file_dir_existed("./test-result/");
  CU_ASSERT_EQUAL(existed, 0); // ./test-result/ is not existing
  UnunpackEntry(argc, argv);
  existed = file_dir_existed("./test-result/rpm.tar.dir/yast2-trans-bn.rpm.unpacked.dir/yast2-trans-bn.rpm.dir/usr/share/doc/packages/yast2-trans-bn/status.txt");
  CU_ASSERT_EQUAL(existed, 1); // existing
}

/**
 * @brief the option is invalid
 */
void testUnunpackEntryOptionInvalid()
{
  int argc = 5;
  char *argv[] = {"../ununpack", "-qCRs",
         "./test-data/testdata4unpack/rpm.tar", "-d", "./test-result/"}; // option H is invalid
  deleteTmpFiles("./test-result/");
  existed = file_dir_existed("./test-result/");
  CU_ASSERT_EQUAL(existed, 0); // ./test-result/ is not existing
  
  int Pid = fork();
  if (Pid == 0)
  {
    UnunpackEntry(argc, argv);
  } else
  {
    int status;
    waitpid(Pid, &status, 0);
    int code = WEXITSTATUS(status);
    existed = file_dir_existed("./test-result/rpm.tar.dir/yast2-trans-bn.rpm.unpacked.dir/yast2-trans-bn.rpm.dir/usr/share/doc/packages/yast2-trans-bn/status.txt");
    CU_ASSERT_EQUAL(existed, 0); // not existing
    CU_ASSERT_EQUAL(code, 25); 
  }
}

/**
 * @brief the option Q, Using database queue system.
 */
void testUnunpackEntryUsingDB()
{
  int argc = 5;
  char *argv[] = {"../ununpack", "-qCRQi",
         "./test-data/testdata4unpack/rpm.tar", "-d", "./test-result/"}; // option H is invalid
  deleteTmpFiles("./test-result/");

  int Pid = fork();
  if (Pid == 0)
  {
    UnunpackEntry(argc, argv);
  } else
  {
    int status;
    waitpid(Pid, &status, 0);
    int code = WEXITSTATUS(status);
    existed = file_dir_existed("./test-result/rpm.tar.dir/yast2-trans-bn.rpm.unpacked.dir/yast2-trans-bn.rpm.dir/usr/share/doc/packages/yast2-trans-bn/status.txt");
    CU_ASSERT_EQUAL(existed, 0); // not existing
    CU_ASSERT_EQUAL(code, 21); 
  }
}

CU_TestInfo UnunpackEntry_testcases[] =
{
    {"Testing the function UnunpackEntry, option is invalid:", testUnunpackEntryOptionInvalid},
    {"Testing the function UnunpackEntry, using database:", testUnunpackEntryUsingDB},
    {"Testing the function UnunpackEntry, multy process :", testUnunpackEntryNormalMultyProcess},
//    {"Testing testUnunpackEntryNormal:", testUnunpackEntryNormal},
    {"Testing the function UnunpackEntry, delete unpack result:", testUnunpackEntryNormalDeleteResult},
    CU_TEST_INFO_NULL
};

