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

struct ParentInfo
  {
  int Cmd;      /* index into command table used to run this */
  time_t StartTime;     /* time when command started */
  time_t EndTime;       /* time when command ended */
  int ChildRecurseArtifact; /* child is an artifact -- don't log to XML */
  long uploadtree_pk;	/* if DB is enabled, this is the parent */
  };
typedef struct ParentInfo ParentInfo;

struct ContainerInfo
  {
  char Source[FILENAME_MAX];  /* Full source filename */
  char Partdir[FILENAME_MAX];  /* directory name */
  char Partname[FILENAME_MAX];  /* filename without directory */
  char PartnameNew[FILENAME_MAX];  /* new filename without directory */
  int TopContainer;	/* flag: 1=yes (so Stat is meaningless), 0=no */
  int HasChild;	/* Can this a container have children? (include directories) */
  int Pruned;	/* no longer exists due to pruning */
  int Corrupt;	/* is this container/file known to be corrupted? */
  stat_t Stat;
  ParentInfo PI;
  int Artifact; /* this container is an artifact -- don't log to XML */
  int IsDir; /* this container is a directory */
  int IsCompressed; /* this container is compressed */
  long uploadtree_pk;	/* uploadtree of this item */
  long pfile_pk;	/* pfile of this item */
  long ufile_mode;	/* ufile_mode of this item */
  };
typedef struct ContainerInfo ContainerInfo;

struct unpackqueue
  {
  int ChildPid; /* set to 0 if this record is not in use */
  char ChildRecurse[FILENAME_MAX+1]; /* file (or directory) to recurse on */
  int ChildStatus;	/* return code from child */
  int ChildCorrupt;	/* return status from child */
  int ChildEnd;	/* flag: 0=recurse, 1=don't recurse */
  int ChildHasChild;	/* is the child likely to have children? */
  stat_t ChildStat;
  ParentInfo PI;
  };
typedef struct unpackqueue unpackqueue;
#define MAXCHILD        4096
extern unpackqueue Queue[MAXCHILD+1];    /* manage children */

void	TraverseChild	(int Index, ContainerInfo *CI, char *NewDir);
void InitCmd();
static int Index = 0;
static int Result = 0;
static stat_t Stat;

/**
 * @brief initialize
 */
int  TraverseChildInit()
{
  InitCmd();
}

/**
 * @brief ununpack iso file
 */
void testTraverseChild4IsoFile()
{
  deleteTmpFiles("../test-result/");
  existed = file_dir_existed("../test-result/");
  
  Filename = "../test-data/testdata4unpack/imagefile.iso";
  MkDirs("../test-result/imagefile.iso.dir/");
  lstat64(Filename, &Stat);
  ContainerInfo CITemp;
  memset(&CITemp,0,sizeof(ContainerInfo));
  strcpy(CITemp.Source, Filename);
  strcpy(CITemp.Partdir, NewDir);
  strcpy(CITemp.Partname, "imagefile.iso");
  strcpy(CITemp.PartnameNew, "imagefile.iso.dir");
  CITemp.Stat = Stat; 
  CITemp.PI.Cmd = 19;
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
  strcpy(Queue[0].ChildRecurse, "../test-result/imagefile.iso.dir");
  /* test TraverseChild */
  int Pid;
  Pid = fork();
  if (Pid == 0)
  {
    TraverseChild(Index, &CITemp, NewDir);
  } else
  {
    ParentWait();
    existed = file_dir_existed("../test-result/imagefile.iso.dir/test.cpio");
    CU_ASSERT_EQUAL(existed, 1); // existing  
    existed = file_dir_existed("../test-result/imagefile.iso.dir/test.cpio.dir");
    CU_ASSERT_EQUAL(existed, 0); // not existing
  }
}

/**
 * @brief unpack debian source
 */
void testTraverseChild4DebianSourceFile()
{
  deleteTmpFiles("../test-result/");
  existed = file_dir_existed("../test-result/");

  Filename = "../test-data/testdata4unpack/fcitx_3.6.2-1.dsc";
  MkDirs("../test-result/fcitx_3.6.2-1.dsc.dir/");
  lstat64(Filename, &Stat);
  ContainerInfo CITemp;
  memset(&CITemp,0,sizeof(ContainerInfo));
  strcpy(CITemp.Source, Filename);
  strcpy(CITemp.Partdir, NewDir);
  strcpy(CITemp.Partname, "fcitx_3.6.2-1.dsc");
  strcpy(CITemp.PartnameNew, "fcitx_3.6.2-1.dsc.dir");
  ParentInfo PITemp = {26, 1287725739, 1287725739, 0};
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
    existed = file_dir_existed("../test-result/");
    CU_ASSERT_EQUAL(existed, 1); // existing
    existed = file_dir_existed("../test-result/");
    CU_ASSERT_EQUAL(existed, 0); // not existing
  }
}

/**
 * @brief test the partition file 
 */
void testTraverseChild4PartitionFile()
{
  deleteTmpFiles("../test-result/");
  existed = file_dir_existed("../test-result/");

  Filename = "../test-data/testdata4unpack/initrd.img-2.6.26-2-686";
  MkDirs("../test-result/initrd.img-2.6.26-2-686.dir/");
  lstat64(Filename, &Stat);
  ContainerInfo CITemp;
  memset(&CITemp,0,sizeof(ContainerInfo));
  strcpy(CITemp.Source, Filename);
  strcpy(CITemp.Partdir, NewDir);
  strcpy(CITemp.Partname, "imagefile.iso");
  strcpy(CITemp.PartnameNew, "imagefile.iso.dir");
  ParentInfo PITemp = {25, 1287725739, 1287725739, 0};
  lstat64(Filename, &Stat);
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
    existed = file_dir_existed("../test-result/");
    CU_ASSERT_EQUAL(existed, 1); // existing
    existed = file_dir_existed("../test-result/");
    CU_ASSERT_EQUAL(existed, 0); // not existing
  }
}


CU_TestInfo TraverseChild_testcases[] =
{
    {"Testing TraverseChild for iso file:", testTraverseChild4IsoFile},
    {"Testing TraverseChild for debian source file:", testTraverseChild4DebianSourceFile},
    {"Testing TraverseChild for departition:", testTraverseChild4PartitionFile},
    CU_TEST_INFO_NULL
};
