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

int	RunCommand	(char *Cmd, char *CmdPre, char *File, char *CmdPost,
			 char *Out, char *Where);

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
  deleteTmpFiles("../test-result/");
  Cmd = "zcat";
  CmdPre = "-q -l";
  File = "../test-data/testdata4unpack/FileName.tar.Z";
  CmdPost = ">/dev/null 2>&1";
  Out = 0x0;
  Where = 0x0;
  Result = RunCommand(Cmd, CmdPre, File, CmdPost, Out, Where);
  existed = file_dir_existed("../test-result/");
  CU_ASSERT_EQUAL(existed, 0); // ../test-result/ is not existing
  CU_ASSERT_EQUAL(Result, 0); // command could run
}

/**
 * @brief test the command zcat, unpack via zcat 
 */
void testRunCommand4Zcat()
{
  deleteTmpFiles("../test-result/");
  Cmd = "zcat";
  CmdPre = "";
  File = "../test-data/testdata4unpack/FileName.tar.Z";
  CmdPost = "> '%s' 2>/dev/null";
  Out = "FileName.tar.Z.unpacked";
  Where = "../test-result";
  Result = RunCommand(Cmd, CmdPre, File, CmdPost, Out, Where);
  existed = file_dir_existed("../test-result/FileName.tar.Z.unpacked");
  CU_ASSERT_EQUAL(existed, 1); // the file is existing
  CU_ASSERT_EQUAL(Result, 0); // command could run
}

/**
 * @brief test the command pdftotext
 */
void testRunCommand4Pdf()
{
  deleteTmpFiles("../test-result/");
  Cmd = "pdftotext";
  CmdPre = "-htmlmeta";
  File = "../test-data/testdata4unpack/israel.pdf";
  CmdPost = "> '%s' 2>/dev/null";
  Out = "israel.pdf.text";
  Where = "../test-result";
  Result = RunCommand(Cmd, CmdPre, File, CmdPost, Out, Where);
  existed = file_dir_existed("../test-result/israel.pdf.text");
  CU_ASSERT_EQUAL(existed, 1); // the file is existing
  CU_ASSERT_EQUAL(Result, 0); // command could run
}

/**
 * @brief test rpm file, the command is rmp2cpio
 */
void testRunCommand4Rpm1()
{
  deleteTmpFiles("../test-result/");
  Cmd = "rpm2cpio";
  CmdPre = "";
  File = "../test-data/testdata4unpack/fossology-1.2.0-1.el5.i386.rpm";
  CmdPost = "> '%s' 2> /dev/null";
  Out = "fossology-1.2.0-1.el5.i386.rpm.unpacked";
  Where = "../test-result";
  Result = RunCommand(Cmd, CmdPre, File, CmdPost, Out, Where);
  existed = file_dir_existed("../test-result/fossology-1.2.0-1.el5.i386.rpm.unpacked");
  CU_ASSERT_EQUAL(existed, 1); //  existing
  CU_ASSERT_EQUAL(Result, 0); // command could run
}

/**
 * @brief test rpm file, the command is cpio
 */
void testRunCommand4Rpm2()
{
  deleteTmpFiles("../test-result/");
  testRunCommand4Rpm1();
  Cmd = "cpio";
  CmdPre = "--no-absolute-filenames -i -d <";
  File = "../test-result/fossology-1.2.0-1.el5.i386.rpm.unpacked";
  CmdPost = ">/dev/null 2>&1";
  Out = "fossology-1.2.0-1.el5.i386.rpm.unpacked.dir";
  Where = "../test-result/fossology-1.2.0-1.el5.i386.rpm.unpacked.dir";
  Result = RunCommand(Cmd, CmdPre, File, CmdPost, Out, Where);
  existed = file_dir_existed("../test-result/fossology-1.2.0-1.el5.i386.rpm.unpacked.dir/etc/fossology/RepPath.conf");
  CU_ASSERT_EQUAL(existed, 1); //  existing
  CU_ASSERT_EQUAL(Result, 0); // command could run
}

CU_TestInfo RunCommand_testcases[] =
{
    {"Testing RunCommand for Zcat, just testing if the command can run:", testRunCommand4ZcatTesting},
    {"Testing RunCommand for Zcat:", testRunCommand4Zcat},
    {"Testing RunCommand for pdf:", testRunCommand4Pdf},
    {"Testing RunCommand for rpm file, the command rpm2cpio", testRunCommand4Rpm1},
    {"Testing RunCommand for rpm file, the command cpio", testRunCommand4Rpm2},
    CU_TEST_INFO_NULL
};
