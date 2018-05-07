/*********************************************************************
Copyright (C) 2011 Hewlett-Packard Development Company, L.P.

Copyright (C) 2018, Siemens AG

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
#include <unistd.h>
#include "CUnit/CUnit.h"
#include "CUnit/Automated.h"
#include "testRun.h"

/**
 * \file testRun.c
 * \brief main function for in this testing module
 */

char *DBConfFile = NULL;

extern CU_SuiteInfo suites[];

char* getUser()
{
  char CMD[200], *user;
  FILE *db_conf;
  int len;
  memset(CMD, '\0', sizeof(CMD));
  user = malloc(20 * sizeof(char));
  memset(user, '\0', 20);

  sprintf(CMD, "awk -F \"=\" '/user/ {print $2}' %s | tr -d '; '", DBConfFile);
  db_conf = popen(CMD, "r");
  if (db_conf != NULL)
  {
    if(fgets(user, sizeof(user)-1, db_conf) != NULL)
    {
      len = strlen(user);
      user[len-1] = '\0';
    }
  }
  pclose(db_conf);
  return user;
}

/**
 * \brief initialize db
 */
int DelagentDBInit()
{
  char CMD[256];
  int rc;

  char cwd[2048];
  char* confDir = NULL;

  if(getcwd(cwd, sizeof(cwd)) != NULL)
  {
    confDir = createTestConfDir(cwd, "delagent");
  }

  rc = create_db_repo_sysconf(0, "delagent", confDir);
  if (rc != 0)
  {
    printf("Database initialize ERROR!\n");
    DelagentClean();
    return -1;
  }
  DBConfFile = get_dbconf();

  memset(CMD, '\0', sizeof(CMD));
  sprintf(CMD, "psql -U %s -d %s < ../testdata/testdb_all.sql >/dev/null", getUser(), get_db_name());
  rc = system(CMD);
  if (WEXITSTATUS(rc) != 0)
  {
    printf("Database initialize ERROR!\n");
    DelagentClean();
    return -1;
  }

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

