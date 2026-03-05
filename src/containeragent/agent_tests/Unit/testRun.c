/*
 SPDX-FileCopyrightText: © 2026 Fossology contributors

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * \dir
 * \brief Unit tests for containeragent
 */
#include <stdio.h>
#include <stdlib.h>
#include <assert.h>
#include "CUnit/CUnit.h"
#include "CUnit/Automated.h"

#include "libfossology.h"
#include "libfocunit.h"
#include "libfodbreposysconf.h"

/* test case sets */
extern CU_TestInfo testcases_ContainerAgent[];

char *DBConfFile = NULL;
fo_dbManager *dbManager = NULL;

#define AGENT_DIR "../"

/**
 * \brief Initialize the test database environment
 */
int ContainerAgentDBInit()
{
  dbManager = createTestEnvironment(AGENT_DIR, "containeragent", 1);
  DBConfFile = get_dbconf();
  return dbManager ? 0 : 1;
}

/**
 * \brief Tear down the test database environment
 */
int ContainerAgentDBClean()
{
  if (dbManager) {
    dropTestEnvironment(dbManager, AGENT_DIR, "containeragent");
  }
  return 0;
}

/* Register test suites */
CU_SuiteInfo suites[] = {
  {
    "Testing containeragent parsing and DB functions:",
    NULL, NULL,
    (CU_SetUpFunc)ContainerAgentDBInit,
    (CU_TearDownFunc)ContainerAgentDBClean,
    testcases_ContainerAgent
  },
  CU_SUITE_INFO_NULL
};

int main(int argc, char *argv[])
{
  return focunit_main(argc, argv, "containeragent_Tests", suites);
}
