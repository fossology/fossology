/*********************************************************************
Copyright (C) 2010-2014 Hewlett-Packard Development Company, L.P.

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
 */
void testTraverseChild4IsoFile()
{
  deleteTmpFiles("./test-result/");
  exists = file_dir_exists("./test-result/");
  
  Filename = "../test-data/testdata4unpack/imagefile.iso";
  MkDirs("./test-result/imagefile.iso.dir/");
  lstat(Filename, &Stat);
  ContainerInfo CITemp;
  memset(&CITemp,0,sizeof(ContainerInfo));
  strcpy(CITemp.Source, Filename);
  strcpy(CITemp.Partdir, NewDir);
  strcpy(CITemp.Partname, "imagefile.iso");
  strcpy(CITemp.PartnameNew, "imagefile.iso.dir");
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
  strcpy(Queue[0].ChildRecurse, "./test-result/imagefile.iso.dir");
  /* test TraverseChild */
  int Pid;
  Pid = fork();
  if (Pid == 0)
  {
    TraverseChild(Index, &CITemp, NewDir);
  } else
  {
    ParentWait();
    int rc = 0;
    char commands[250];
    sprintf(commands, "isoinfo -f -R -i '%s' | grep ';1' > /dev/null ", Filename);
    rc = system(commands);
    if (0 != rc)
    {
      exists = file_dir_exists("./test-result/imagefile.iso.dir/test.jar");
      FO_ASSERT_EQUAL(exists, 1); // existing  
      exists = file_dir_exists("./test-result/imagefile.iso.dir/test.jar.dir");
      FO_ASSERT_EQUAL(exists, 0); // not existing
    }
    else
    {
      exists = file_dir_exists("./test-result/imagefile.iso.dir/TEST.JAR;1");
      FO_ASSERT_EQUAL(exists, 1); // existing  
    }
  }
}

/**
 * @brief unpack debian source
 */
void testTraverseChild4DebianSourceFile()
{
  deleteTmpFiles("./test-result/");
  exists = file_dir_exists("./test-result/");

  Filename = "../test-data/testdata4unpack/fcitx_3.6.2-1.dsc";
  //  MkDirs("./test-result/fcitx_3.6.2-1.dsc.dir/");
  lstat(Filename, &Stat);
  ContainerInfo CITemp;
  memset(&CITemp,0,sizeof(ContainerInfo));
  strcpy(CITemp.Source, Filename);
  strcpy(CITemp.Partdir, NewDir);
  strcpy(CITemp.Partname, "fcitx_3.6.2-1.dsc");
  strcpy(CITemp.PartnameNew, "fcitx_3.6.2-1.dsc.dir");
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
    exists = file_dir_exists("./test-result/fcitx_3.6.2-1.dsc.dir/debian/README.Debian");
    FO_ASSERT_EQUAL(exists, 1); // existing
  }
}

/**
 * @brief test the partition file 
 */
void testTraverseChild4PartitionFile()
{
  deleteTmpFiles("./test-result/");
  exists = file_dir_exists("./test-result/");

  Filename = "../test-data/testdata4unpack/vmlinuz-2.6.26-2-686";
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
  {"TraverseChild for departition:", testTraverseChild4PartitionFile},
  CU_TEST_INFO_NULL
};
