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
  File = "../test-data/testdata4unpack/FileName.tar.Z";
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
  File = "../test-data/testdata4unpack/FileName.tar.Z";
  CmdPost = "> '%s' 2>/dev/null";
  Out = "FileName.tar.Z.unpacked";
  Where = "./test-result";
  Result = RunCommand(Cmd, CmdPre, File, CmdPost, Out, Where);
  exists = file_dir_exists("./test-result/FileName.tar.Z.unpacked");
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
  File = "../test-data/testdata4unpack/israel.pdf";
  CmdPost = "> '%s' 2>/dev/null";
  Out = "israel.pdf.text";
  Where = "./test-result";
  Result = RunCommand(Cmd, CmdPre, File, CmdPost, Out, Where);
  exists = file_dir_exists("./test-result/israel.pdf.text");
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
  File = "../test-data/testdata4unpack/fossology-1.2.0-1.el5.i386.rpm";
  CmdPost = "> '%s' 2> /dev/null";
  Out = "fossology-1.2.0-1.el5.i386.rpm.unpacked";
  Where = "./test-result";
  Result = RunCommand(Cmd, CmdPre, File, CmdPost, Out, Where);
  exists = file_dir_exists("./test-result/fossology-1.2.0-1.el5.i386.rpm.unpacked");
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
  File = "./test-result/fossology-1.2.0-1.el5.i386.rpm.unpacked";
  CmdPost = ">/dev/null 2>&1";
  Out = "fossology-1.2.0-1.el5.i386.rpm.unpacked.dir";
  Where = "./test-result/fossology-1.2.0-1.el5.i386.rpm.unpacked.dir";
  Result = RunCommand(Cmd, CmdPre, File, CmdPost, Out, Where);
  exists = file_dir_exists("./test-result/fossology-1.2.0-1.el5.i386.rpm.unpacked.dir/etc/fossology/RepPath.conf");
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
