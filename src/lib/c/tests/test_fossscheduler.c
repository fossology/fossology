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

/**
 * @file test_fossscheduler.c
 * @brief unit tests for the fossscheduler library section of libfossology.
 */

/* includes for files that will be tested */
#include <libfossscheduler.h>

/* library includes */
#include <string.h>
#include <stdio.h>
#include <unistd.h>

/* cunit includes */
#include <libfocunit.h>

#ifndef SVN_REV
#define SVN_REV "SVN_REV Unknown"
#endif

/* ************************************************************************** */
/* *** declaration of private members *************************************** */
/* ************************************************************************** */

extern int  items_processed;
extern char buffer[];
extern int  valid;
extern int  found;
extern int  agent_verbose;
extern void fo_heartbeat();

/* ************************************************************************** */
/* *** set up and tear down for fossschduler ******************************** */
/* ************************************************************************** */

char buffer[1024];
int in_sub[2];
int out_sub[2];
int stdin_t;
int stdout_t;
FILE* read_from;
FILE* write_to;

#define FROM_UNIT "UNIT\n"
#define VERBOSE_TEST 7
#define NC_TEST "Not a Command\n"

#define write_con(...) \
  fprintf(write_to, __VA_ARGS__); \
  fflush(write_to);

/**
 * @brief Since the fossscheduler library depends on reading and writing very
 *        specific data to stdin and stdout, these both need to be replaced
 *        with pipes. The set_up function pretends to be a test so that it can
 *        do this before any other test gets run.
 *
 * @return void
 */
void set_up(void) {
  FO_ASSERT_TRUE_FATAL(!pipe(in_sub));
  FO_ASSERT_TRUE_FATAL(!pipe(out_sub));

  stdin_t  = dup(fileno(stdin));
  stdout_t = dup(fileno(stdout));

  dup2(out_sub[1], fileno(stdout));
  dup2( in_sub[0],  fileno(stdin));
  read_from = fdopen(out_sub[0], "rb");
  write_to  = fdopen( in_sub[1], "wb");

  memset(buffer, '\0', sizeof(buffer));
}

/**
 * @brief This function closes the pipes created in the setup function and
 *        returns stdin and stdout to their original values. This essentially
 *        is the inverse of the set_up() function.
 *
 * @return void
 */
void tear_down(void) {
  fclose(read_from);
  fclose(write_to);

  close(in_sub[0]);
  close(in_sub[1]);
  close(out_sub[0]);
  close(out_sub[1]);

  dup2(stdin_t,  fileno(stdin));
  dup2(stdout_t, fileno(stdout));
}

/**
 * @brief Since the fo_scheduler_next() function will block until either a
 *        "CLOSE\n" or a non-command message is sent a signal is needed to test
 *        the intermediate state of the connection. This will check that the
 *        "END\n" command left the connection in the correct state and then send
 *        a "CLOSE\n" command so that fo_scheduler_next() will return in the
 *        main thread.
 * @return void
 */
void signal_connect_end()
{
  FO_ASSERT_FALSE(valid);
  FO_ASSERT_STRING_EQUAL(fgets(buffer, sizeof(buffer), read_from), "OK\n");

  write_con("CLOSE\n");
}

/**
 * @brief Serves the same purpose for the verbose command as the
 *        signal_connect_end() function does for the end command
 * @return void
 */
void signal_connect_verbose()
{
  FO_ASSERT_FALSE(valid);
  FO_ASSERT_EQUAL(agent_verbose, VERBOSE_TEST);

  agent_verbose = 0;

  write_con("CLOSE\n");
}

void signal_connect_version()
{
  FO_ASSERT_FALSE(valid);
  FO_ASSERT_PTR_NOT_NULL(fgets(buffer, sizeof(buffer), read_from));
  buffer[strlen(buffer) - 1] = '\0';
  FO_ASSERT_STRING_EQUAL(buffer, SVN_REV);

  write_con("CLOSE\n");
}

/* ************************************************************************** */
/* *** tests **************************************************************** */
/* ************************************************************************** */

/**
 * @brief tests calling an fo_scheduler_connect in a situation where it
 *        wouldn't create a connection to the scheduler. This will not pass
 *        --scheduler_start as a command line arg to fo_scheduler_connect
 * @return void
 */
void test_scheduler_no_connect()
{
  int argc = 2;
  char* argv[] = {"./testlibs", "--config=./scheddata"};

  fo_scheduler_connect(&argc, argv);

  FO_ASSERT_FALSE(found);
  FO_ASSERT_EQUAL(items_processed, 0);
  FO_ASSERT_FALSE(valid);
  FO_ASSERT_FALSE(agent_verbose);

  /* make sure that fo_scheduler_connect didn't write anything to stdout */
  fprintf(stdout, FROM_UNIT);
  FO_ASSERT_PTR_NOT_NULL(fgets(buffer, sizeof(buffer), read_from));
  FO_ASSERT_STRING_EQUAL(buffer, FROM_UNIT);

  /* reset stdout for the next test */
  while(strcmp(buffer, FROM_UNIT) != 0)
    FO_ASSERT_PTR_NOT_NULL(fgets(buffer, sizeof(buffer), read_from));
}

/**
 * @brief tests calling an fo_scheduler_connect in a situation where it will
 *        create a connection to the scheduler. This will pass --scheduler_start
 *        as a command line arg to fo_scheduler_connect. The alarm, sleep and
 *        following assert check that the heart beat was correctly created.
 * @return void
 */
void test_scheduler_connect()
{
  int argc = 2;
  char* argv[] = {"./testlibs", "--config=./scheddata", "--scheduler_start"};
  char* tmp;

  fo_scheduler_connect(&argc, argv);

  FO_ASSERT_TRUE(found);
  FO_ASSERT_EQUAL(items_processed, 0);
  FO_ASSERT_FALSE(valid);
  FO_ASSERT_FALSE(agent_verbose);

  /* check that the correct stuff was written to stdout */
  memset(buffer, '\0', sizeof(buffer));
  tmp = fgets(buffer, sizeof(buffer), read_from);
  FO_ASSERT_PTR_NOT_NULL(tmp);
  FO_ASSERT_STRING_EQUAL(buffer, SVN_REV);

  tmp = fgets(buffer, sizeof(buffer), read_from);
  FO_ASSERT_PTR_NOT_NULL(tmp);
  FO_ASSERT_STRING_EQUAL(tmp, "OK\n");

  ualarm(10, 0);
  usleep(20);

  FO_ASSERT_STRING_EQUAL(
      fgets(buffer, sizeof(buffer), read_from),
      "HEART: 0\n");
}

/**
 * @brief Tests sending "CLOSE\n" to stdin for the scheduler next function.
 * @return void
 */
void test_scheduler_next_close()
{
  write_con("CLOSE\n");

  FO_ASSERT_PTR_NULL(fo_scheduler_next());
  FO_ASSERT_FALSE(valid);
}

/**
 * @brief Tests sending "END\n" to the stdin for the scheduler next function.
 * @return void
 */
void test_scheduler_next_end()
{
  write_con("END\n");

  signal(SIGALRM, signal_connect_end);
  ualarm(10, 0);

  FO_ASSERT_PTR_NULL(fo_scheduler_next());
  FO_ASSERT_FALSE(valid);
}

/**
 * @brief Tests sending "VERBOSE #\n" to the stdin for the scheduler next
 *        function
 * @return void
 */
void test_scheduler_next_verbose()
{
  write_con("VERBOSE %d\n", VERBOSE_TEST);

  signal(SIGALRM, signal_connect_verbose);
  ualarm(10, 0);

  FO_ASSERT_PTR_NULL(fo_scheduler_next());
  FO_ASSERT_FALSE(valid);
}

/**
 * @brief Tests sending "VERSION\n" to the stdin for the scheduler next function
 * @return void
 */
void test_scheduler_next_version()
{
  write_con("VERSION\n");

  signal(SIGALRM, signal_connect_version);
  ualarm(10, 0);

  FO_ASSERT_PTR_NULL(fo_scheduler_next());
  FO_ASSERT_FALSE(valid);
}

/**
 * @brief Tests send a non-command to the stdin for the scheduler next function.
 *        unlike the other scheduler next test functions, this does not need to
 *        setup a signal since fo_scheduler_next() will return without any extra
 *        commands.
 * @return void
 */
void test_scheduler_next_oth()
{
  char* ret;

  write_con(NC_TEST);

  FO_ASSERT_PTR_NOT_NULL((ret = fo_scheduler_next()));
  FO_ASSERT_STRING_EQUAL(ret, NC_TEST);
  FO_ASSERT_TRUE(valid);
}

/**
 * @brief Tests the scheduler current function.
 * @return void
 */
void test_scheduler_current()
{
  FO_ASSERT_STRING_EQUAL(fo_scheduler_current(), NC_TEST);

  write_con("CLOSE\n");

  FO_ASSERT_PTR_NULL(fo_scheduler_next());
  FO_ASSERT_PTR_NULL(fo_scheduler_current());
}

/**
 * @brief Tests the scheduler disconnection function.
 * @return void
 */
void test_scheduler_disconnect()
{
  found = 1;

  fo_scheduler_disconnect(2);
  FO_ASSERT_STRING_EQUAL(fgets(buffer, sizeof(buffer), read_from), "BYE 2\n");
  FO_ASSERT_FALSE(valid);
  FO_ASSERT_FALSE(found);
}

/**
 * @brief Test the scheduler heart function. This function must set up the
 *        heartbeat again so that it can check that the heartbeat will increase
 *        correctly.
 * @return void
 */
void test_scheduler_heart()
{
  FO_ASSERT_EQUAL(items_processed, 0);
  fo_scheduler_heart(1);
  FO_ASSERT_EQUAL(items_processed, 1);
  fo_scheduler_heart(10);
  FO_ASSERT_EQUAL(items_processed, 11);

  signal(SIGALRM, fo_heartbeat);
  ualarm(10, 0);
  usleep(20);

  FO_ASSERT_STRING_EQUAL(
      fgets(buffer, sizeof(buffer), read_from),
      "HEART: 11\n");
}

/* ************************************************************************** */
/* *** cunit test info ****************************************************** */
/* ************************************************************************** */

CU_TestInfo fossscheduler_testcases[] =
{
    { "fossscheduler set up",       set_up                      },
    { "fossscheduler no connect",   test_scheduler_no_connect   },
    { "fossscheduler connect",      test_scheduler_connect      },
    { "fossscheduler next close",   test_scheduler_next_close   },
    { "fossscheduler next end",     test_scheduler_next_end     },
    { "fossscheduler next verbose", test_scheduler_next_verbose },
    { "fossscheduler next version", test_scheduler_next_version },
    { "fossscheduler next oth",     test_scheduler_next_oth     },
    { "fossscheduler current",      test_scheduler_current      },
    { "fossscheduler disconnect",   test_scheduler_disconnect   },
    { "fossscheduler heat",         test_scheduler_heart        },
    { "fossscheduler tear down",    tear_down                   },
    CU_TEST_INFO_NULL
};


