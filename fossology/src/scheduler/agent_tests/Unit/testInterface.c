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

/* include functions to test */
#include <testRun.h>

/* scheduler includes */
#include <event.h>
#include <host.h>
#include <interface.h>
#include <scheduler.h>

/* library includes */
#include <gio/gio.h>
#include <glib.h>
#include <sys/socket.h>
#include <sys/types.h>
#include <netinet/in.h>
#include <netdb.h>
#include <unistd.h>

/* ************************************************************************** */
/* **** local declarations ************************************************** */
/* ************************************************************************** */

#if defined(__amd64__)
  #define mint_t int64_t
#else
  #define mint_t int32_t
#endif

int interface_init_suite(void)
{
  return init_suite();
}

int interface_clean_suite(void)
{
  return clean_suite();
}

/**
 * @brief Create a socket connection
 *
 * Creates a new socket that connects to the given host and port.
 *
 * @param host  Cstring name of the host to connect to
 * @param port  Cstring representation of the port to connec to
 * @return      The file descriptor of the new socket
 */
int socket_connect(char* host, char* port)
{
  int fd;
  struct addrinfo hints;
  struct addrinfo* servs, * curr;

  servs = NULL;
  curr  = NULL;

  memset(&hints, 0, sizeof(hints));
  hints.ai_family   = AF_UNSPEC;
  hints.ai_socktype = SOCK_STREAM;
  if(getaddrinfo(host, port, &hints, &servs) == -1)
  {
    fprintf(stderr, "ERROR: %s.%d: unable to connect to %s port: %s\n",
        __FILE__, __LINE__, host, port);
    fprintf(stderr, "ERROR: errno: %s\n", strerror(errno));
    return 0;
  }

  for(curr = servs; curr != NULL; curr = curr->ai_next)
  {
    if((fd = socket(curr->ai_family, hints.ai_socktype, curr->ai_protocol)) < 0)
      continue;

    if(connect(fd, curr->ai_addr, curr->ai_addrlen) == -1)
      continue;

    break;
  }

  if(curr == NULL)
  {
    fprintf(stderr, "ERROR: %s.%d: unable to connect to %s port: %s\n",
        __FILE__, __LINE__, host, port);
    return 0;
  }

  freeaddrinfo(servs);
  return fd;
}

void* interface_listen_thread(void* unused);

/* ************************************************************************** */
/* **** interface function tests ******************************************** */
/* ************************************************************************** */

void test_set_port()
{

}

void test_interface_init()
{
    interface_init();

  FO_ASSERT_TRUE(i_created);

  FO_ASSERT_PTR_NOT_NULL(socket_thread);
  FO_ASSERT_PTR_NOT_NULL(cancel);
  FO_ASSERT_PTR_NOT_NULL(threads);
  FO_ASSERT_PTR_NOT_NULL(cmd_parse);
  FO_ASSERT_PTR_NOT_NULL(pro_parse);

  i_terminate = 1;
  i_created = 0;

  g_cancellable_cancel(cancel);
  g_thread_pool_free(threads, TRUE, TRUE);
  g_regex_unref(cmd_parse);
  g_regex_unref(pro_parse);
  g_thread_join(socket_thread);

  socket_thread = NULL;
  cancel        = NULL;
  threads       = NULL;
  cmd_parse     = NULL;
  pro_parse     = NULL;
}

void test_interface_destroy()
{
    interface_init();

  FO_ASSERT_TRUE(i_created);
  FO_ASSERT_FALSE(i_terminate);

  interface_destroy();

  FO_ASSERT_FALSE(i_created);
  FO_ASSERT_TRUE(i_terminate);

  FO_ASSERT_PTR_NULL(socket_thread);
  FO_ASSERT_PTR_NULL(cancel);
  FO_ASSERT_PTR_NULL(threads);
  FO_ASSERT_PTR_NULL(cmd_parse);
  FO_ASSERT_PTR_NULL(pro_parse);
}

void test_interface_listen_thread()
{
    mint_t result;

  // test error conditions
  i_terminate = TRUE;
  i_created   = TRUE;
  result = (mint_t)interface_listen_thread(NULL);
  FO_ASSERT_FALSE(result);

  i_terminate = FALSE;
  i_created   = FALSE;
  result = (mint_t)interface_listen_thread(NULL);
  FO_ASSERT_FALSE(result);
}

void test_interface_pool()
{
  char buffer[256];
  int soc;

  i_terminate = FALSE;
  i_created   = FALSE;
  interface_init();
  sleep(1);

  FO_ASSERT_EQUAL(g_thread_pool_get_max_threads(threads), CONF_interface_nthreads);
  FO_ASSERT_EQUAL(g_thread_pool_unprocessed(threads), 0);

  snprintf(buffer, sizeof(buffer), "%d", i_port);
  soc = socket_connect("localhost", buffer);
  sleep(1);

  FO_ASSERT_TRUE(soc);
  FO_ASSERT_EQUAL(g_thread_pool_unprocessed(threads), 0);

  close(soc);
  interface_destroy();
}

/* ************************************************************************** */
/* **** test the interface_thread function                               **** */
/* ****   The interface thread function is rather complicated, so it     **** */
/* ****   gets its own test suite.                                       **** */
/* ************************************************************************** */

int interface_thread_init(void)
{
  i_created   = FALSE;
  i_terminate = FALSE;

  socket_thread = NULL;
  threads       = NULL;
  cancel        = NULL;
  cmd_parse     = NULL;
  pro_parse     = NULL;

  i_port = 12354;

  return init_suite();
}

int interface_thread_clean(void)
{
  return clean_suite();
}

void test_sending_close()
{
    // buffer for the port that the interface is listening on
  char buffer[1024];
  int soc;
  ssize_t result;

  // create the interface and server socket
  interface_init();
  sleep(1);

  // Create the connection to the scheduler
  snprintf(buffer, sizeof(buffer), "%d", i_port);
  soc = socket_connect("localhost", buffer);
  FO_ASSERT_TRUE_FATAL(soc);

  snprintf(buffer, sizeof(buffer), "close");

  result = write(soc, buffer, strlen(buffer));
  FO_ASSERT_EQUAL((int)result, 5);
  sleep(1);

  memset(buffer, '\0', sizeof(buffer));
  result = read(soc, buffer, sizeof(buffer));
  FO_ASSERT_EQUAL((int)result, 15)
  FO_ASSERT_STRING_EQUAL(buffer, "received\nCLOSE\n");

  close(soc);
  interface_destroy();
}

void test_sending_load()
{
  char buffer[1024];
  int soc;
  ssize_t result;

  // create data structures
  host_list_init();
  interface_init();
  host_init("localhost", "localhost", "AGENT_DIR", 10);
  sleep(1);

  // create the connection
  snprintf(buffer, sizeof(buffer), "%d", i_port);
  soc = socket_connect("localhost", buffer);
  FO_ASSERT_TRUE_FATAL(soc);

  snprintf(buffer, sizeof(buffer), "load");

  result = write(soc, buffer, strlen(buffer));
  FO_ASSERT_EQUAL((int)result, 4);
  sleep(1);

  memset(buffer, '\0', sizeof(buffer));
  result = read(soc, buffer, sizeof(buffer));
  FO_ASSERT_EQUAL((int)result, 64);
  FO_ASSERT_STRING_EQUAL(buffer, "received\n"
      "host:localhost address:localhost max:10 running:0\n"
      "\nend\n");

  close(soc);
  interface_destroy();
}

void test_sending_kill()
{
  char buffer[1024];
  int soc;
  ssize_t result;

  // create data structures
  interface_init();
  sleep(1);

  // create the connection
  snprintf(buffer, sizeof(buffer), "%d", i_port);
  soc = socket_connect("localhost", buffer);
  FO_ASSERT_TRUE_FATAL(soc);

  /* test no arguments to kill */
  snprintf(buffer, sizeof(buffer), "kill");
  result = write(soc, buffer, strlen(buffer));
  FO_ASSERT_EQUAL((int)result, 4);
  sleep(1);

  memset(buffer, '\0', sizeof(buffer));
  result = read(soc, buffer, sizeof(buffer));

  FO_ASSERT_EQUAL((int)result, 38);
  FO_ASSERT_STRING_EQUAL(buffer, "received\n"
      "Invalid kill command: \"kill\"\n");

  /* test one argument to kill */
  snprintf(buffer, sizeof(buffer), "kill 1");
  result = write(soc, buffer, strlen(buffer));
  FO_ASSERT_EQUAL((int)result, 6);
  sleep(1);

  memset(buffer, '\0', sizeof(buffer));
  result = read(soc, buffer, sizeof(buffer));

  FO_ASSERT_EQUAL((int)result, 40);
  FO_ASSERT_STRING_EQUAL(buffer, "received\n"
      "Invalid kill command: \"kill 1\"\n");

  /* test only second argument to kill */
  snprintf(buffer, sizeof(buffer), "kill \"test\"");
  result = write(soc, buffer, strlen(buffer));
  FO_ASSERT_EQUAL((int)result, 11);
  sleep(1);

  memset(buffer, '\0', sizeof(buffer));
  result = read(soc, buffer, sizeof(buffer));

  FO_ASSERT_EQUAL((int)result, 45);
  FO_ASSERT_STRING_EQUAL(buffer, "received\n"
      "Invalid kill command: \"kill \"test\"\"\n");

  /* test valid kill cmd */
  snprintf(buffer, sizeof(buffer), "kill 1 \"test\"");
  result = write(soc, buffer, strlen(buffer));
  FO_ASSERT_EQUAL((int)result, 13);
  sleep(1);

  memset(buffer, '\0', sizeof(buffer));
  result = read(soc, buffer, sizeof(buffer));

  FO_ASSERT_EQUAL((int)result, 9);
  FO_ASSERT_STRING_EQUAL(buffer, "received\n");

  result = g_async_queue_length(event_loop_get()->queue);
  FO_ASSERT_EQUAL((int)result, 1);

  close(soc);
  interface_destroy();
  event_loop_destroy();
}

void test_sending_pause()
{
  char buffer[1024];
  int soc;
  ssize_t result;

  // create data structures
  interface_init();
  sleep(1);

  // create the connection
  snprintf(buffer, sizeof(buffer), "%d", i_port);
  soc = socket_connect("localhost", buffer);
  FO_ASSERT_TRUE_FATAL(soc);

  /* Invalid no argument pause command */
  snprintf(buffer, sizeof(buffer), "pause");
  result = write(soc, buffer, strlen(buffer));
  FO_ASSERT_EQUAL((int)result, 5);
  sleep(1);

  memset(buffer, '\0', sizeof(buffer));
  result = read(soc, buffer, sizeof(buffer));

  FO_ASSERT_EQUAL((int)result, 40);
  FO_ASSERT_STRING_EQUAL(buffer, "received\n"
      "Invalid pause command: \"pause\"\n");

  /* Invalid wrong argument type pause command */
  snprintf(buffer, sizeof(buffer), "pause \"test\"");
  result = write(soc, buffer, strlen(buffer));
  FO_ASSERT_EQUAL((int)result, 12);
  sleep(1);

  memset(buffer, '\0', sizeof(buffer));
  result = read(soc, buffer, sizeof(buffer));

  FO_ASSERT_EQUAL((int)result, 47);
  FO_ASSERT_STRING_EQUAL(buffer, "received\n"
      "Invalid pause command: \"pause \"test\"\"\n");

  /* valid pause command */
  snprintf(buffer, sizeof(buffer), "pause 1");
  result = write(soc, buffer, strlen(buffer));
  FO_ASSERT_EQUAL((int)result, 7);
  sleep(1);

  memset(buffer, '\0', sizeof(buffer));
  result = read(soc, buffer, sizeof(buffer));

  FO_ASSERT_EQUAL((int)result, 9);
  FO_ASSERT_STRING_EQUAL(buffer, "received\n");

  result = g_async_queue_length(event_loop_get()->queue);
  FO_ASSERT_EQUAL((int)result, 1);

  close(soc);
  interface_destroy();
  event_loop_destroy();
}

void test_sending_status()
{
  char buffer[1024];
  int soc;
  ssize_t result;

  // create data structures
  interface_init();
  sleep(1);

  // create the connection
  snprintf(buffer, sizeof(buffer), "%d", i_port);
  soc = socket_connect("localhost", buffer);
  FO_ASSERT_TRUE_FATAL(soc);



  close(soc);
  interface_destroy();
  event_loop_destroy();
}

/* ************************************************************************** */
/* **** suite declaration *************************************************** */
/* ************************************************************************** */

CU_TestInfo tests_interface[] =
{
    {"Test set_port",                test_set_port                },
    {"Test interface_init",          test_interface_init          },
    {"Test interface_destroy",       test_interface_destroy       },
    {"Test interface_listen_thread", test_interface_listen_thread },
    {"Test interface_pool",          test_interface_pool          },
    CU_TEST_INFO_NULL
};

CU_TestInfo tests_interface_thread[] =
{
    {"Test sending \"close\"",  test_sending_close  },
    {"Test sending \"load\"",   test_sending_load   },
    {"Test sending \"kill\"",   test_sending_kill   },
    {"Test sending \"pause\"",  test_sending_pause  },
    {"Test sending \"status\"", test_sending_status },
    CU_TEST_INFO_NULL
};




