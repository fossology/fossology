/*
 SPDX-FileCopyrightText: © 2011 Hewlett-Packard Development Company, L.P.
 SPDX-FileCopyrightText: © 2015, 2018 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * \file libfodbreposysconf.c
 * \brief api for db, sysconfig, repo.
 *        you can create/drop a DB/sysconfig/repo
 */

#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <stdarg.h>
#include <sys/types.h>
#include <sys/stat.h>
#include <unistd.h>

#include <libfossscheduler.h>
#include <libfossdb.h>
#include "libfodbreposysconf.h"

#ifndef TESTDBDIR
// this is only to make IDEs happy
#define TESTDBDIR "../../../testing/db/"
#error
#endif

static char* Sysconf = NULL;
static char DBName[ARRAY_LENGTH];
static char DBConf[ARRAY_LENGTH];
static char RepoDir[ARRAY_LENGTH];
static char confFile[ARRAY_LENGTH];

fo_dbManager* createTestEnvironment(const char* srcDirs, const char* doConnectAsAgent, int initDbTables) {
  GString* gString = g_string_new(TESTDBDIR "/createTestEnvironment.php");
  if (srcDirs) {
    g_string_append_printf(gString, " -d '%s'", srcDirs);
  }
  if (initDbTables) {
    g_string_append_printf(gString, " -f");
  }
  gchar* cmd = g_string_free(gString, FALSE);

  FILE* pipe = popen(cmd, "r");

  if (!pipe) {
    printf("cannot run create test environment script: %s\n", cmd);
    goto createError;
  }

  Sysconf = calloc(1, ARRAY_LENGTH + 1);
  size_t count = fread(Sysconf, 1, ARRAY_LENGTH, pipe);

  int rv = pclose(pipe);

  if (rv != 0 || count == 0) {
    printf("command %s failed with output:\n%s\n", cmd, Sysconf);
    goto createError;
  }

  g_free(cmd);

  fo_dbManager* result = NULL;
  if (doConnectAsAgent) {
    char* argv[] = {(char*) doConnectAsAgent, "-c", Sysconf};
    int argc = 3;

    fo_scheduler_connect_dbMan(&argc, argv, &result);
  } else {
    char buffer[ARRAY_LENGTH + 1];
    snprintf(buffer, ARRAY_LENGTH, "%s/Db.conf", Sysconf);
    char* errorMsg = NULL;
    PGconn* conn = fo_dbconnect(buffer, &errorMsg);

    if (!errorMsg) {
      result = fo_dbManager_new_withConf(conn, buffer);
    } else {
      printf("error connecting: %s\n", errorMsg);
    }
  }
  return result;

createError:
  if (cmd) {
    g_free(cmd);
  }
  return NULL;
}

void dropTestEnvironment(fo_dbManager* dbManager, const char* srcDir, const char* doConnectAsAgent) {
  if (dbManager) {
    fo_dbManager_finish(dbManager);
  }
  if (doConnectAsAgent) {
    fo_scheduler_disconnect(0);
  }

  if (Sysconf) {
    char buffer[ARRAY_LENGTH];
    snprintf(buffer, ARRAY_LENGTH, TESTDBDIR "/purgeTestEnvironment.php -d '%s' -c '%s'", srcDir, Sysconf);
    FILE* pipe = popen(buffer, "r");

    if (!pipe) {
      printf("cannot run purge test environment script: %s\n", buffer);
      return;
    }

    pclose(pipe);
    free(Sysconf);
  }
}

/**
 * \brief get command output
 *
 * \param char *command - the command will be executed
 */
static void command_output(char* command) {
  FILE* stream;
  char tmp[ARRAY_LENGTH];
  int i = 0;
  int status = 0;

  stream = popen(command, "r");
  if (!stream) status = 1;
  memset(tmp, '\0', sizeof(tmp));
  if (fgets(tmp, ARRAY_LENGTH, stream) != NULL) {
    while ((tmp[i] != '\n') && (tmp[i] != ' ') && (tmp[i] != EOF))
      i++;
    Sysconf = malloc(i);
    memcpy(Sysconf, tmp, i);
    Sysconf[i] = '\0';
  }
  int rc = pclose(stream);
  if (rc != 0) status = 1;
  if (status == 1) {
    printf("Failed to run %s, exit code is:%d .\n", command, rc >> 8);
    exit(1);
  }
  return;
}

/**
 * \biref create DB, the db name looks like fosstestxxxxx
 *
 * \param int type - 0 on create db, sysconf dir and repository, the db is empty
 *                   1 on create db, sysconf dir and repository, the db is initialized
 *
 * \return 0 on sucess, other on failure
 */
int create_db_repo_sysconf(int type, char* agent_name, char* sysconfdir) {
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
#define CREATEDB TESTDBDIR "/createTestDB.php"
  const char INIT_CMD[] = CREATEDB;
  char *CMD, *tmp;

  CMD = (char *)malloc(strlen(INIT_CMD) + 1);
  if (!CMD)
  {
    return -1;
  }
  sprintf(CMD, "%s", INIT_CMD);

  if (sysconfdir)
  {
    tmp = (char *)malloc(strlen(CMD) + 4 + strlen(sysconfdir) + 1);
    if (!tmp)
    {
      free(CMD);
      return -1;
    }
    sprintf(tmp, "%s -c %s", CMD, sysconfdir);
    free(CMD);
    CMD = tmp;
  }

  switch(type)
  {
    case 0:
      tmp = (char *)malloc(strlen(CMD) + 4);
      if (!tmp)
      {
        free(CMD);
        return -1;
      }
      sprintf(tmp, "%s -e", CMD);
      free(CMD);
      CMD = tmp;
    case 1:
      command_output(CMD);
      break;
    default:
      break;
  }

  free(CMD);

  int argc = 3;
  char* argv[] = {agent_name, "-c", Sysconf};

  PGconn* unused;
  fo_scheduler_connect(&argc, argv, &unused);

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
void drop_db_repo_sysconf(char* DBName) {
  char CMD[ARRAY_LENGTH];
  memset(CMD, '\0', sizeof(CMD));
  sprintf(CMD, "%s/createTestDB.php -d %s", TESTDBDIR, DBName);
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
char* get_test_name() {
  char* TestName = strstr(Sysconf, "Conf") + 4;
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
char* get_db_name() {
  memset(DBName, '\0', sizeof(DBName));
  char* TestName = get_test_name();
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
char* get_sysconfdir() {
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
char* get_dbconf() {
  memset(DBConf, '\0', sizeof(DBConf));
  sprintf(DBConf, "%s/Db.conf", Sysconf);
  return DBConf;
}

char* get_confFile() {
  memset(confFile, '\0', sizeof(confFile));
  sprintf(confFile, "%s/fossology.conf", Sysconf);
  return confFile;
}
/**
 * \brief get repo path just created by  create_db_repo_sysconf()
 *
 * \return repo path
 */
char* get_repodir() {
  strncpy(RepoDir, Sysconf, ARRAY_LENGTH);
  RepoDir[ARRAY_LENGTH-1] = '\0';

  char* test_name_tmp = strstr(RepoDir, "testDbConf");
  if (test_name_tmp)
  {
    *test_name_tmp = '\0';
  }

  char *tmp = malloc(strlen(RepoDir) + 1);
  if (!tmp) {
    return NULL;
  }

  sprintf(tmp, "%s", RepoDir);
  sprintf(RepoDir, "%stestDbRepo%s", tmp, get_test_name());

  free(tmp);

#ifdef TEST
  printf("RepoDir is:%s\n", RepoDir);
#endif
  return RepoDir;
}

/**
 * \brief create a dummy sysConfDir for a given agent
 *
 * \param char* cwd       - Current directory of the agent's Unit test
 * \param char* agentName - Name of the agent for which the directory is to be created
 *
 * \return repo path
 */
char *createTestConfDir(char* cwd, char* agentName)
{
  struct stat st = {0};
  int rc;
  char CMD[2048];
  FILE *testConfFile;

  char *confDir = malloc((strlen(cwd) + 10) * sizeof(char));
  char confFile[1024];
  char agentDir[1024];

  if(cwd == NULL || agentName == NULL || cwd[0] == '\0' || agentName[0] == '\0')
  {
    return NULL;
  }

  sprintf(confDir, "%s/testconf", cwd);
  sprintf(confFile, "%s/fossology.conf", confDir);
  sprintf(agentDir, "%s/..", cwd);

  if (stat(confDir, &st) == -1)
  {
    mkdir(confDir, 0775);
  }

  memset(CMD, '\0', sizeof(CMD));
  sprintf(CMD, "%s/mods-enabled/%s", confDir, agentName);
  if (stat(CMD, &st) == -1)
  {
    mkdir(CMD, 0775);
  }

  testConfFile = fopen(confFile,"w");
  fprintf(testConfFile, ";fossology.conf for testing\n");
  fprintf(testConfFile, "[FOSSOLOGY]\nport = 24693\n");
  fprintf(testConfFile, "address = localhost\n");
  fprintf(testConfFile, "depth = 0\n");
  fprintf(testConfFile, "path = %s\n", confDir);
  fprintf(testConfFile, "[HOSTS]\n");
  fprintf(testConfFile, "localhost = localhost AGENT_DIR 10\n");
  fprintf(testConfFile, "[REPOSITORY]\n");
  fprintf(testConfFile, "localhost = * 00 ff\n");
  fprintf(testConfFile, "[DIRECTORIES]\n");
  fprintf(testConfFile, "PROJECTUSER=fossy\n");
  fprintf(testConfFile, "PROJECTGROUP=fossy\n");
  fprintf(testConfFile, "MODDIR=%s/../../../..\n", cwd);
  fprintf(testConfFile, "LOGDIR=%s\n", confDir);
  fclose(testConfFile);

  memset(CMD, '\0', sizeof(CMD));
  sprintf(CMD, "install -D %s/../VERSION %s/VERSION", cwd, confDir);
  rc = system(CMD);

  memset(CMD, '\0', sizeof(CMD));
  sprintf(CMD, "install -D %s/../../../install/gen/Db.conf %s/Db.conf", cwd, confDir);
  rc = system(CMD);

  memset(CMD, '\0', sizeof(CMD));
  sprintf(CMD, "install -D %s/VERSION %s/mods-enabled/%s/VERSION", agentDir, confDir, agentName);
  rc = system(CMD);

  memset(CMD, '\0', sizeof(CMD));
  sprintf(CMD, "ln -fs %s/agent %s/mods-enabled/%s", agentDir, confDir, agentName);
  rc = system(CMD);

  if (rc != -1)
    return confDir;
  else
    return NULL;
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
