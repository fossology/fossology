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

void test_interface_init()
{
  scheduler_t* scheduler;
  GThread* interface_thread;

  scheduler = scheduler_init(testdb, NULL);
  scheduler_foss_config(scheduler);
  interface_init(scheduler);

  FO_ASSERT_TRUE(scheduler->i_created);
  FO_ASSERT_FALSE(scheduler->i_terminate);

  FO_ASSERT_PTR_NOT_NULL(scheduler->server);
  FO_ASSERT_PTR_NOT_NULL(scheduler->workers);
  FO_ASSERT_PTR_NOT_NULL(scheduler->cancel);

  interface_thread = scheduler->server;
  interface_init(scheduler);

  FO_ASSERT_TRUE(scheduler->i_created);
  FO_ASSERT_FALSE(scheduler->i_terminate);

  FO_ASSERT_PTR_NOT_NULL(scheduler->server);
  FO_ASSERT_PTR_EQUAL(scheduler->server, interface_thread);

  interface_destroy(scheduler);
  scheduler_destroy(scheduler);
}

void test_interface_destroy()
{
  scheduler_t* scheduler;

  scheduler = scheduler_init(testdb, NULL);
  scheduler_foss_config(scheduler);
  interface_destroy(scheduler);

  FO_ASSERT_FALSE(scheduler->i_created);
  FO_ASSERT_FALSE(scheduler->i_terminate);

  interface_init(scheduler);

  FO_ASSERT_TRUE(scheduler->i_created);
  FO_ASSERT_FALSE(scheduler->i_terminate);

  interface_destroy(scheduler);

  FO_ASSERT_FALSE(scheduler->i_created);
  FO_ASSERT_TRUE(scheduler->i_terminate);

  scheduler_destroy(scheduler);
}

void test_interface_listen_thread()
{
  mint_t result;
  scheduler_t* scheduler;

  scheduler = scheduler_init(testdb, NULL);
  scheduler_foss_config(scheduler);
  scheduler->i_terminate = TRUE;
  scheduler->i_created   = TRUE;
  result = (mint_t)interface_listen_thread(scheduler);
  FO_ASSERT_FALSE(result);

  scheduler->i_terminate = FALSE;
  scheduler->i_created   = FALSE;
  result = (mint_t)interface_listen_thread(scheduler);
  FO_ASSERT_FALSE(result);

  scheduler_destroy(scheduler);
}

void test_interface_pool()
{
  scheduler_t* scheduler;
  char buffer[256];
  int soc;

  scheduler = scheduler_init(testdb, NULL);
  scheduler_foss_config(scheduler);
  scheduler->i_terminate = FALSE;
  scheduler->i_created   = FALSE;
  interface_init(scheduler);
  sleep(1);

  FO_ASSERT_EQUAL(g_thread_pool_get_max_threads(scheduler->workers), CONF_interface_nthreads);
  FO_ASSERT_EQUAL(g_thread_pool_unprocessed(scheduler->workers), 0);

  snprintf(buffer, sizeof(buffer), "%d", scheduler->i_port);
  soc = socket_connect("localhost", buffer);
  sleep(1);

  FO_ASSERT_TRUE(soc);
  FO_ASSERT_EQUAL(g_thread_pool_unprocessed(scheduler->workers), 0);

  close(soc);
  interface_destroy(scheduler);
  scheduler_destroy(scheduler);
}

/* ************************************************************************** */
/* **** test the interface_thread function                               **** */
/* ****   The interface thread function is rather complicated, so it     **** */
/* ****   gets its own test suite.                                       **** */
/* ************************************************************************** */

#define CREATE_INTERFACE(name)              \
  scheduler_t* name;                         \
  name = scheduler_init(testdb, NULL);       \
  scheduler_foss_config(name);               \
  scheduler_agent_config(name);              \
  event_loop_destroy();                      \
  interface_init(name)

#define SEND_RECEIVE(string, len, res)              \
  snprintf(buffer, sizeof(buffer), string);          \
  result = write(soc, buffer, strlen(buffer));       \
  FO_ASSERT_EQUAL((int)result, (int)strlen(buffer)); \
  sleep(1);                                          \
  memset(buffer, '\0', sizeof(buffer));              \
  result = read(soc, buffer, sizeof(buffer));        \
  FO_ASSERT_EQUAL((int)result, (int)len);            \
  FO_ASSERT_STRING_EQUAL(buffer, res)

void test_sending_close()
{
  // buffer for the port that the interface is listening on
  char buffer[1024];
  int soc;
  ssize_t result;

  // create data structures
  CREATE_INTERFACE(scheduler);

  // Create the connection to the scheduler
  snprintf(buffer, sizeof(buffer), "%d", scheduler->i_port);
  soc = socket_connect("localhost", buffer);
  FO_ASSERT_TRUE_FATAL(soc);

  snprintf(buffer, sizeof(buffer), "close");

  result = write(soc, buffer, strlen(buffer));
  FO_ASSERT_EQUAL((int)result, 5);
  sleep(1);

  memset(buffer, '\0', sizeof(buffer));
  result = read(soc, buffer, sizeof(buffer));
  FO_ASSERT_EQUAL((int)result, 15)
  FO_ASSERT_STRING_EQUAL(buffer,
      "received\n"
      "CLOSE\n");

  close(soc);
  interface_destroy(scheduler);
  scheduler_destroy(scheduler);
}

void test_sending_load()
{
  char buffer[1024];
  int soc;
  ssize_t result;

  // create data structures
  CREATE_INTERFACE(scheduler);
  host_insert(host_init("localhost", "localhost", "AGENT_DIR", 10), scheduler);

  // create the connection
  snprintf(buffer, sizeof(buffer), "%d", scheduler->i_port);
  soc = socket_connect("localhost", buffer);
  FO_ASSERT_TRUE_FATAL(soc);
  SEND_RECEIVE("load", 64,
      "received\n"
      "host:localhost address:localhost max:10 running:0\n"
      "\nend\n");

  close(soc);
  interface_destroy(scheduler);
  scheduler_destroy(scheduler);
}

void test_sending_kill()
{
  char buffer[1024];
  int soc;
  ssize_t result;

  // create data structures
  CREATE_INTERFACE(scheduler);
  sleep(1);

  // create the connection
  snprintf(buffer, sizeof(buffer), "%d", scheduler->i_port);
  soc = socket_connect("localhost", buffer);
  FO_ASSERT_TRUE_FATAL(soc);

  /* test no arguments to kill
   *
   * Sending: kill
   * Receive: received
   *          Invalid kill command: "kill"
   */
  SEND_RECEIVE("kill", 38,
      "received\n"
      "Invalid kill command: \"kill\"\n");

  /* test one argument to kill
   *
   * Sending: kill 1
   * Receive: received
   *          Invalid kill command: "kill 1"
   */
  SEND_RECEIVE("kill 1", 40,
      "received\n"
      "Invalid kill command: \"kill 1\"\n");

  /* test only second argument to kill
   *
   * Sending: kill "test"
   * Receive: received
   *          Invalid kill command: "kill "test""
   */
  SEND_RECEIVE("kill \"test\"", 45,
      "received\n"
      "Invalid kill command: \"kill \"test\"\"\n");

  /* test valid kill command
   *
   * Sending: kill 1 "test"
   * Receive: received
   */
  SEND_RECEIVE("kill 1 \"test\"", 9,
      "received\n");

  result = g_async_queue_length(event_loop_get()->queue);
  FO_ASSERT_EQUAL((int)result, 1);

  close(soc);
  interface_destroy(scheduler);
  scheduler_destroy(scheduler);
}

void test_sending_pause()
{
  char buffer[1024];
  int soc;
  ssize_t result;

  // create data structures
  CREATE_INTERFACE(scheduler);

  // create the connection
  snprintf(buffer, sizeof(buffer), "%d", scheduler->i_port);
  soc = socket_connect("localhost", buffer);
  FO_ASSERT_TRUE_FATAL(soc);

  /* Pause command no arguments
   *
   * Sending: pause
   * Receive: received
   *          Invalid pause command: "pause:
   */
  SEND_RECEIVE("pause", 40,
      "received\n"
      "Invalid pause command: \"pause\"\n");

  /* Pause command with wrong arugment type
   *
   * Sending: pause "test"
   * Receive: received
   *          Invalid pause command: "pause "test""
   */
  SEND_RECEIVE("pause \"test\"", 47,
      "received\n"
      "Invalid pause command: \"pause \"test\"\"\n");

  /* Correct pause command
   *
   * Sending: pause 1
   * Receive: received
   */
  SEND_RECEIVE("pause 1", 9,
      "received\n");

  result = g_async_queue_length(event_loop_get()->queue);
  FO_ASSERT_EQUAL((int)result, 1);

  close(soc);
  interface_destroy(scheduler);
  scheduler_destroy(scheduler);
}

void test_sending_reload()
{
  char buffer[1024];
  int soc;
  ssize_t result;
  event_t* event;

  // create data structures
  CREATE_INTERFACE(scheduler);

  // create the connection
  snprintf(buffer, sizeof(buffer), "%d", scheduler->i_port);
  soc = socket_connect("localhost", buffer);
  FO_ASSERT_TRUE_FATAL(soc);

  SEND_RECEIVE("reload", 9,
        "received\n");

  result = g_async_queue_length(event_loop_get()->queue);
  event = g_async_queue_pop(event_loop_get()->queue);
  FO_ASSERT_EQUAL((int)result, 1);
  FO_ASSERT_PTR_EQUAL((void*)event->func, (void*)scheduler_config_event);
  FO_ASSERT_STRING_EQUAL(event->source_name, "interface.c");

  close(soc);
  interface_destroy(scheduler);
  scheduler_destroy(scheduler);
}

void test_sending_agents()
{
  char buffer[1024];
  int soc;
  ssize_t result;
  event_t* event;

  // create data structures
  CREATE_INTERFACE(scheduler);

  // create the connection
  snprintf(buffer, sizeof(buffer), "%d", scheduler->i_port);
  soc = socket_connect("localhost", buffer);
  FO_ASSERT_TRUE_FATAL(soc);

  SEND_RECEIVE("agents", 9,
      "received\n");

  result = g_async_queue_length(event_loop_get()->queue);
  event = g_async_queue_pop(event_loop_get()->queue);
  FO_ASSERT_EQUAL((int)result, 1);
  FO_ASSERT_PTR_EQUAL((void*)event->func, (void*)list_agents_event);
  FO_ASSERT_STRING_EQUAL(event->source_name, "interface.c");

  close(soc);
  interface_destroy(scheduler);
  scheduler_destroy(scheduler);
}

/* ************************************************************************** */
/* **** suite declaration *************************************************** */
/* ************************************************************************** */

CU_TestInfo tests_interface[] =
{
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
    {"Test sending \"status\"", test_sending_reload },
    CU_TEST_INFO_NULL
};




