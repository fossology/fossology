/* **************************************************************
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
************************************************************** */
/**
 * \file libfodbreposysconf.c
 * \brief api for db, sysconfig, repo. 
 *        you can create/drop a DB/sysconfig/repo
 */

#include "libfodbreposysconf.h"

static char *Sysconf = NULL;
static char DBName[ARRAY_LENGTH];
static char DBConf[ARRAY_LENGTH];
static char RepoDir[ARRAY_LENGTH];

/**
 * \brief get command output
 * 
 * \param char *command - the command will be executed
 */
static void command_output(char *command)
{
  FILE *stream;
  char tmp[ARRAY_LENGTH];
  int i=0;
  int status = 0;

  stream = popen(command, "r");
  if (!stream) status = 1;
  memset(tmp, '\0', sizeof(tmp));
  if (fgets(tmp, ARRAY_LENGTH, stream) != NULL)
  {
    while((tmp[i] != '\n') && (tmp[i] != ' ') && (tmp[i] != EOF))
      i++;
    Sysconf = malloc(i);
    memcpy(Sysconf, tmp, i);
    Sysconf[i] = '\0';
  }
  int rc = pclose(stream);
  if (rc != 0) status = 1;
  if (status == 1) 
  {
    printf("Failed to run %s, exit code is:%d .\n", command, rc>>8);
    exit(1);
  }
  return ;
}

/** 
 * \biref create DB, the db name looks linke fosstestxxxxx
 *
 * \param int type - 0 on create db, sysconf dir and repository, the db is empty
 *                   1 on create db, sysconf dir and repository, the db is initialized
 *
 * \return 0 on sucess, other on failure
 */
int create_db_repo_sysconf(int type, char *agent_name)
{
#if 0
  char *sysconfdir;
  /** get sysconfig dir from ENV */
  sysconfdir = getenv ("SYSCONFDIR");
  if (sysconfdir == NULL)
  {
    printf ("The SYSCONFDIR enviroment variable is not existed.\n");
    return 1;
  }
#endif
  char CMD[ARRAY_LENGTH] = "../../../testing/db/createTestDB.php";
  if (1 == type)
  {
    command_output(CMD);
  }
  else if (0 == type)
  {
    sprintf(CMD, "%s -e", CMD);
    command_output(CMD);
  }
  int argc = 3;
  char* argv[] = {agent_name, "-c", Sysconf};
  
  fo_scheduler_connect(&argc, argv);

#ifdef TEST
  printf("create_db_repo_sysconf sucessfully\n");
#endif
  return 0;
}

/**
 * \brief drop db, sysconfig dir and repo
 * 
 * \param char *DBName - the db name, looks like fosstestxxxx
 */
void drop_db_repo_sysconf(char *DBName)
{
  char CMD[ARRAY_LENGTH];
  memset(CMD, '\0', sizeof(CMD));
  sprintf(CMD, "../../../testing/db/createTestDB.php -d %s", DBName);
  command_output(CMD);
#ifdef TEST
  printf("remove DBName is:%s\n", DBName);
#endif
  fo_scheduler_disconnect(0);
  free(Sysconf);
  Sysconf = NULL;
#ifdef TEST
  printf("drop_db_repo_sysconf sucessfully\n");
#endif
}

/**
 * \brief get the test name just created by  create_db_repo_sysconf()
 *
 * \return the test name
 */
char *get_test_name()
{
  char *TestName = strstr(Sysconf, "Conf") + 4;
#ifdef TEST
  printf("TestName is:%s\n", TestName);
#endif
  return TestName;
}

/**
 * \brief get the DB name just created by  create_db_repo_sysconf()
 *
 * \return the DB name
 */
char *get_db_name()
{
  memset(DBName, '\0', sizeof(DBName));
  char *TestName = get_test_name();
  sprintf(DBName, "fosstest%s", TestName);
#ifdef TEST
  printf("DBName is:%s\n", DBName);
#endif
  return DBName;
}

/**
 * \brief get sysconfig dir path just created by  create_db_repo_sysconf()
 *
 * \return the sysconfig dir path 
 */
char *get_sysconfdir()
{
#ifdef TEST
  printf("Sysconf is:%s\n", Sysconf);
#endif
  return Sysconf;
}

/**
 * \brief get Db.conf path just created by  create_db_repo_sysconf()
 *
 * \return Db.conf path
 */
char *get_dbconf()
{
  memset(DBConf, '\0', sizeof(DBConf));
  sprintf(DBConf, "%s/Db.conf", Sysconf);
  return DBConf;
}

/**
 * \brief get repo path just created by  create_db_repo_sysconf()
 *
 * \return repo path
 */
char *get_repodir()
{
  memset(RepoDir, '\0', sizeof(RepoDir));
  strcpy(RepoDir, Sysconf);
  char *test_name_tmp = strstr(RepoDir, "testDbConf");
  *test_name_tmp = 0;
  sprintf(RepoDir, "%stestDbRepo%s", RepoDir, get_test_name()); 
#ifdef TEST
  printf("RepoDir is:%s\n", RepoDir);
#endif
  return RepoDir;
}

#if 0
int main()
{
  create_db_repo_sysconf(1);
  get_test_name();
  get_sysconfdir();
  get_db_name();
  drop_db_repo_sysconf(DBName);
}
#endif
