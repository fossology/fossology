/*
 SPDX-FileCopyrightText: © 2010-2011 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/
#include "run_tests.h"
/**
 * \file
 * \brief Unit test cases for RunCommand()
 */
static char *Cmd = "";
static char *CmdPre = "";
static char *File = "";
static char *CmdPost = "";
static char *Out = "";
static char *Where = "";
static int Result = 0;

/**
 * @brief test the command zcat, just testing if the commmand can run
 */
void testRunCommand4ZcatTesting()
{
  deleteTmpFiles("./test-result/");
  Cmd = "zcat";
  CmdPre = "-q -l";
  File = "../testdata/test.tar.Z";
  CmdPost = ">/dev/null 2>&1";
  Out = 0x0;
  Where = 0x0;
  Result = RunCommand(Cmd, CmdPre, File, CmdPost, Out, Where);
  exists = file_dir_exists("./test-result/");
  FO_ASSERT_EQUAL(exists, 0); // ./test-result/ is not existing
  FO_ASSERT_EQUAL(Result, 0); // command could run
}

/**
 * @brief test the command zcat, unpack via zcat
 */
void testRunCommand4Zcat()
{
  deleteTmpFiles("./test-result/");
  Cmd = "zcat";
  CmdPre = "";
  File = "../testdata/test.tar.Z";
  CmdPost = "> '%s' 2>/dev/null";
  Out = "test.tar.Z.unpacked";
  Where = "./test-result";
  Result = RunCommand(Cmd, CmdPre, File, CmdPost, Out, Where);
  exists = file_dir_exists("./test-result/test.tar.Z.unpacked");
  FO_ASSERT_EQUAL(exists, 1); // the file is existing
  FO_ASSERT_EQUAL(Result, 0); // command could run
}

/**
 * @brief test the command pdftotext
 */
void testRunCommand4Pdf()
{
  deleteTmpFiles("./test-result/");
  Cmd = "pdftotext";
  CmdPre = "-htmlmeta";
  File = "../testdata/test.pdf";
  CmdPost = "> '%s' 2>/dev/null";
  Out = "test.pdf.text";
  Where = "./test-result";
  Result = RunCommand(Cmd, CmdPre, File, CmdPost, Out, Where);
  exists = file_dir_exists("./test-result/test.pdf.text");
  FO_ASSERT_EQUAL(exists, 1); // the file is existing
  FO_ASSERT_EQUAL(Result, 0); // command could run
}

/**
 * @brief test rpm file, the command is rmp2cpio
 */
void testRunCommand4Rpm1()
{
  deleteTmpFiles("./test-result/");
  Cmd = "rpm2cpio";
  CmdPre = "";
  File = "../testdata/test.rpm";
  CmdPost = "> '%s' 2> /dev/null";
  Out = "test.rpm.unpacked";
  Where = "./test-result";
  Result = RunCommand(Cmd, CmdPre, File, CmdPost, Out, Where);
  exists = file_dir_exists("./test-result/test.rpm.unpacked");
  FO_ASSERT_EQUAL(exists, 1); //  existing
  FO_ASSERT_EQUAL(Result, 0); // command could run
}

/**
 * @brief test rpm file, the command is cpio
 */
void testRunCommand4Rpm2()
{
  deleteTmpFiles("./test-result/");
  testRunCommand4Rpm1();
  Cmd = "cpio";
  CmdPre = "--no-absolute-filenames -i -d <";
  File = "./test-result/test.rpm.unpacked";
  CmdPost = ">/dev/null 2>&1";
  Out = "test.rpm.unpacked.dir";
  Where = "./test-result/test.rpm.unpacked.dir";
  Result = RunCommand(Cmd, CmdPre, File, CmdPost, Out, Where);
  exists = file_dir_exists("./test-result/test.rpm.unpacked.dir/usr/share/fossology/bsam/VERSION");
  FO_ASSERT_EQUAL(exists, 1); //  existing
  FO_ASSERT_EQUAL(Result, 0); // command could run
}


/* ************************************************************************** */
/* **** cunit test cases **************************************************** */
/* ************************************************************************** */

CU_TestInfo RunCommand_testcases[] =
{
  {"RunCommand: Zcat, test if the command can run:", testRunCommand4ZcatTesting},
  {"RunCommand: Zcat:", testRunCommand4Zcat},
  {"RunCommand: pdf:", testRunCommand4Pdf},
  {"RunCommand: rpm file with rpm2cpio", testRunCommand4Rpm1},
  {"RunCommand: rpm file with cpio", testRunCommand4Rpm2},
  CU_TEST_INFO_NULL
};
