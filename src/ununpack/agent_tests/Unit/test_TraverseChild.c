/*
 SPDX-FileCopyrightText: © 2010-2014 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/
#include "run_tests.h"
/**
 * \file
 * \brief Unit test cases for TraverseChild()
 */
extern unpackqueue Queue[MAXCHILD+1];    /* manage children */

int Index = 0;
struct stat Stat;

/**
 * @brief initialize
 */
int  TraverseChildInit()
{
  InitCmd();
  return 0;
}

/**
 * @brief ununpack iso file
 * \test
 * -# Create a ContainerInfor for an ISO file
 * -# Pass it to TraverseChild()
 * -# Check if files are unpacked
 */
void testTraverseChild4IsoFile()
{
  deleteTmpFiles("./test-result/");
  exists = file_dir_exists("./test-result/");

  Filename = "../testdata/test.iso";
  MkDirs("./test-result/test.iso.dir/");
  lstat(Filename, &Stat);
  ContainerInfo CITemp;
  memset(&CITemp,0,sizeof(ContainerInfo));
  strcpy(CITemp.Source, Filename);
  strcpy(CITemp.Partdir, NewDir);
  strcpy(CITemp.Partname, "test.iso");
  strcpy(CITemp.PartnameNew, "test.iso.dir");
  CITemp.Stat = Stat;
  CITemp.PI.Cmd = 21;
  CITemp.PI.StartTime =  1287725739;
  CITemp.PI.EndTime =  1287725739;
  CITemp.PI.ChildRecurseArtifact =  0;
  CITemp.uploadtree_pk = 0;
  CITemp.Artifact = 0;
  CITemp.IsDir = 0;
  CITemp.IsCompressed = 0;
  CITemp.uploadtree_pk = 0;
  CITemp.pfile_pk = 0;
  CITemp.ufile_mode = 0;
  strcpy(Queue[0].ChildRecurse, "./test-result/test.iso.dir");
  /* test TraverseChild */
  int Pid;
  Pid = fork();
  if (Pid == 0)
  {
    TraverseChild(Index, &CITemp, NewDir);
  } else
  {
    ParentWait();
    exists = file_dir_exists("./test-result/test.iso.dir/test1.zip.tar.dir/test1.zip");
    FO_ASSERT_EQUAL(exists, 1); // existing
  }
}

/**
 * @brief unpack debian source
 * \test
 * -# Create a ContainerInfor for a DSC file
 * -# Pass it to TraverseChild()
 * -# Check if files are unpacked
 */
void testTraverseChild4DebianSourceFile()
{
  deleteTmpFiles("./test-result/");
  exists = file_dir_exists("./test-result/");

  Filename = "../testdata/test_1-1.dsc";
  //  MkDirs("./test-result/fcitx_3.6.2-1.dsc.dir/");
  lstat(Filename, &Stat);
  ContainerInfo CITemp;
  memset(&CITemp,0,sizeof(ContainerInfo));
  strcpy(CITemp.Source, Filename);
  strcpy(CITemp.Partdir, NewDir);
  strcpy(CITemp.Partname, "test_1-1.dsc");
  strcpy(CITemp.PartnameNew, "test_1-1.dsc.dir");
  ParentInfo PITemp = {28, 1287725739, 1287725739, 0};
  CITemp.Stat = Stat;
  CITemp.PI = PITemp;
  CITemp.uploadtree_pk = 0;
  CITemp.Artifact = 0;
  CITemp.IsDir = 0;
  CITemp.IsCompressed = 0;
  CITemp.uploadtree_pk = 0;
  CITemp.pfile_pk = 0;
  CITemp.ufile_mode = 0;
  /* test TraverseChild */

  int Pid;
  Pid = fork();
  if (Pid == 0)
  {
    TraverseChild(Index, &CITemp, NewDir);
  } else
  {
    ParentWait();
    exists = file_dir_exists("./test-result/test_1-1.dsc.dir/debian/README.Debian");
    FO_ASSERT_EQUAL(exists, 1); // existing
  }
}

/**
 * @brief test the partition file
 * \test
 * -# Create a ContainerInfor for a partition files
 * -# Pass it to TraverseChild()
 * -# Check if files are unpacked
 * \todo Test file does not exists
 */
void testTraverseChild4PartitionFile()
{
  deleteTmpFiles("./test-result/");
  exists = file_dir_exists("./test-result/");

  Filename = "../testdata/vmlinuz-2.6.26-2-686";
  MkDirs("./test-result/vmlinuz-2.6.26-2-686.dir/");
  strcpy(Queue[0].ChildRecurse, "./test-result/vmlinuz-2.6.26-2-686.dir/");
  lstat(Filename, &Stat);
  ContainerInfo CITemp;
  memset(&CITemp,0,sizeof(ContainerInfo));
  strcpy(CITemp.Source, Filename);
  strcpy(CITemp.Partdir, NewDir);
  strcpy(CITemp.Partname, "vmlinuz-2.6.26-2-686");
  strcpy(CITemp.PartnameNew, "vmlinuz-2.6.26-2-686");
  ParentInfo PITemp = {27, 1287725739, 1287725739, 0};
  lstat(Filename, &Stat);
  CITemp.Stat = Stat;
  CITemp.PI = PITemp;
  int Pid;
  Pid = fork();
  if (Pid == 0)
  {
    TraverseChild(Index, &CITemp, NewDir);
  } else
  {
    ParentWait();
    exists = file_dir_exists("./test-result/vmlinuz-2.6.26-2-686.dir/Partition_0000");
    FO_ASSERT_EQUAL(exists, 1); // existing
  }
}


/* ************************************************************************** */
/* **** cunit test cases **************************************************** */
/* ************************************************************************** */

CU_TestInfo TraverseChild_testcases[] =
{
  {"TraverseChild for iso file:", testTraverseChild4IsoFile},
  {"TraverseChild for debian source file:", testTraverseChild4DebianSourceFile},
  // {"TraverseChild for departition:", testTraverseChild4PartitionFile},
  CU_TEST_INFO_NULL
};
