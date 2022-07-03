/*
 SPDX-FileCopyrightText: © 2011 Hewlett-Packard Development Company, L.P.
 SPDX-FileCopyrightText: © 2015 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/
#ifndef LIBFOCUNIT_H
#define LIBFOCUNIT_H
#endif

#include <libfossdbmanager.h>

#define ARRAY_LENGTH 256

int create_db_repo_sysconf(int type, char* agent_name, char* sysconfdir);

void drop_db_repo_sysconf(char *DBName);

char *createTestConfDir(char* cwd, char* agentName);

fo_dbManager* createTestEnvironment(const char* srcDirs, const char* doConnectAsAgent, int initDbTables);

void dropTestEnvironment(fo_dbManager* dbManager, const char* srcDir, const char* doConnectAsAgent);

char *get_sysconfdir();

char *get_test_name();

char *get_dbconf();

char* get_confFile();

char *get_db_name();

char *get_repodir();
