/*********************************************************************
Copyright (C) 2010-2011 Hewlett-Packard Development Company, L.P.

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

/**
 * @brief when unpack end, do not delete the unpacked files
 */
void testUnunpackEntryNormal()
{
  int Pid = fork();
  if (Pid < 0)
  {
    fprintf(stderr,"Fork error!\n");
    exit(-1);
  }
  else  if (Pid == 0)
  {
    int argc = 5;
    char *argv[] = {"../ununpack", "-qCR", "./test-data/testdata4unpack.7z", "-d", "./test-result/"};
    deleteTmpFiles("./test-result/");
    existed = file_dir_existed("./test-result/");
    CU_ASSERT_EQUAL(existed, 0); // ./test-result/ does not exist
    UnunpackEntry(argc, argv);
    exit(0);
  } else
  {
    int status;
    waitpid(Pid, &status, 0);
    existed = file_dir_existed("./test-result/testdata4unpack.7z.dir/testdata4unpack/libfossagent.a.dir/libfossagent.o");
    CU_ASSERT_EQUAL(existed, 1); // the file above exists 
  }
}


/**
 * @brief when unpack end, delete all the unpacked files
 */
void testUnunpackEntryNormalDeleteResult()
{
  int Pid = fork();
  if (Pid < 0)
  {
    fprintf(stderr,"Fork error!\n");
    exit(-1);
  }
  else  if (Pid == 0)
  {
    int argc = 5;
    char *argv[] = {"../ununpack", "-qCRx", "./test-data/testdata4unpack.7z", "-d", "./test-result/"};
    deleteTmpFiles("./test-result/");
    existed = file_dir_existed("./test-result/");
    CU_ASSERT_EQUAL(existed, 0); // ./test-result/ does not exist
    int rc = system("mkdir ./test-result; cp ./test-data/testdata4unpack/ununpack.c.Z ./test-result");
    if (rc != 0) exit(-1);
    UnunpackEntry(argc, argv);
    exit(0);
  } else
  {
    int status;
    waitpid(Pid, &status, 0);
    existed = file_dir_existed("./test-result/testdata4unpack.7z.dir/testdata4unpack/libfossagent.a.dir/libfossagent.o");
    CU_ASSERT_EQUAL(existed, 0); // the file above does not exist
    existed = file_dir_existed("./test-result/");
    CU_ASSERT_EQUAL(existed, 0); // ./test-result/ does not exist 
  }
}


/**
 * @brief when unpack end, do not delete all the unpacked files
 * the package is xx.tar/xxx.rpm type
 * multy process
 */
void testUnunpackEntryNormalMultyProcess()
{
  int Pid = fork();
  if (Pid < 0)
  {
    fprintf(stderr,"Fork error!\n");
    exit(-1);
  }
  else  if (Pid == 0)
  {
    int argc = 7;
    char *argv[] = {"../ununpack", "-qCR", "-m", "5", "./test-data/testdata4unpack/rpm.tar", "-d", "./test-result/"}; 
    deleteTmpFiles("./test-result/");
    existed = file_dir_existed("./test-result/");
    CU_ASSERT_EQUAL(existed, 0); // ./test-result/ does not exist
    UnunpackEntry(argc, argv);
    exit(0);
  } else
  {
    int status;
    waitpid(Pid, &status, 0);
    existed = file_dir_existed("./test-result/rpm.tar.dir/yast2-trans-bn.rpm.unpacked.dir/yast2-trans-bn.rpm.dir/usr/share/doc/packages/yast2-trans-bn/status.txt");
    CU_ASSERT_EQUAL(existed, 1); // the file above exists 
  }
}

/**
 * @brief the option is invalid
 */
void testUnunpackEntryOptionInvalid()
{
  int Pid = fork();
  if (Pid < 0)
  {     
    fprintf(stderr,"Fork error!\n");    
    exit(-1);     
  }    
  else if (Pid == 0)
  {
    int argc = 5;
    char *argv[] = {"../ununpack", "-qCRs",
         "./test-data/testdata4unpack/rpm.tar", "-d", "./test-result/"}; // option H is invalid
    deleteTmpFiles("./test-result/");
    existed = file_dir_existed("./test-result/");
    CU_ASSERT_EQUAL(existed, 0); // ./test-result/ does not exist
    UnunpackEntry(argc, argv);
    exit(0);
  } else
  {
    int status;
    waitpid(Pid, &status, 0);
    int code = WEXITSTATUS(status);
    existed = file_dir_existed("./test-result/rpm.tar.dir/yast2-trans-bn.rpm.unpacked.dir/yast2-trans-bn.rpm.dir/usr/share/doc/packages/yast2-trans-bn/status.txt");
    CU_ASSERT_EQUAL(existed, 0); // the file above does not exist 
    CU_ASSERT_EQUAL(code, 25); // the exit code of unpack agent is 25
  }
}

CU_TestInfo UnunpackEntry_testcases[] =
{
    {"Testing the function UnunpackEntry, option is invalid:", testUnunpackEntryOptionInvalid},
    {"Testing the function UnunpackEntry, multy process :", testUnunpackEntryNormalMultyProcess},
    {"Testing testUnunpackEntryNormal:", testUnunpackEntryNormal},
    {"Testing the function UnunpackEntry, delete unpack result:", testUnunpackEntryNormalDeleteResult},
    CU_TEST_INFO_NULL
};

