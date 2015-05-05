/*********************************************************************
Copyright (C) 2011 Hewlett-Packard Development Company, L.P.

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

#include <stdio.h>
#include <stdlib.h>
#include <assert.h>
#include "CUnit/CUnit.h"
#include "CUnit/Automated.h"
#include "testRun.h"

/**
 * \file testRun.c
 * \brief main function for in this testing module
 */

char *DBConfFile = NULL;

extern CU_SuiteInfo suites[];

/**
 * \brief initialize db
 */
int DelagentDBInit()
{
  char CMD[256];
  int rc;
 
  rc = create_db_repo_sysconf(0, "delagent");
  if (rc != 0)
  {
    printf("Database initialize ERROR!\n");
    DelagentClean();
    return -1;
  }
  DBConfFile = get_dbconf();

  memset(CMD, '\0', sizeof(CMD));
  //sprintf(CMD, "sh testInitDB.sh %s", get_db_name());
  sprintf(CMD, "pg_restore -Ufossy -d %s ../testdata/testdb_all.tar", get_db_name());
  printf("restore database command: %s\n", CMD);
  rc = system(CMD); 
  //if (rc != 0)
  //{
  //  printf("Database initialize ERROR!\n");
  //  DelagentClean();
  //  return -1; 
  //}

  return 0;
}
/**
 * \brief clean db
 */
int DelagentClean()
{
  drop_db_repo_sysconf(get_db_name());
  return 0;
}

/**
 * \brief init db and repo
 */
int DelagentInit()
{
  char CMD[256];
  int rc;

  if (DelagentDBInit()!=0) return -1;

  memset(CMD, '\0', sizeof(CMD));
  sprintf(CMD, "sh testInitRepo.sh %s", get_repodir());
  rc = system(CMD);
  if (rc != 0)
  {
    printf("Repository Init ERROR!\n");
    DelagentClean();
    return -1;
  }

  return 0;
}

/**
 * \brief  main test function
 */
int main( int argc, char *argv[] )
{
  return focunit_main(argc, argv, "delagent_Tests", suites);
}

