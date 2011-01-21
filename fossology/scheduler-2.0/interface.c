/* **************************************************************
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
************************************************************** */

/* local includes */
#include <interface.h>
#include <job.h>
#include <logging.h>
#include <scheduler.h>

/* std library includes */
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <limits.h>

/* unix library includes */
#include <fcntl.h>
#include <pthread.h>
#include <sys/stat.h>
#include <sys/types.h>

/* glib includes */
#include <glib.h>
#include <gio/gio.h>

#ifndef FIFO_LOCATION
#define FIFO_LOCATION "/usr/local/share/fossology/scheduler.fifo"
#endif

#define FIFO_PERMISSIONS    666  ///< the permissions given to the fifo

int i_created = 0;      ///< flag indicating if the interface already been created
int i_terminate = 0;    ///< flag indicating if the interface has been killed
int i_port = -1;        ///< the port that the scheduler is listening on
GThread* socket_thread; ///< thread that will create new connections
GList* client_threads;  ///< threads that are currently running some form of scheduler interface

/* ************************************************************************** */
/* **** Data Types ********************************************************** */
/* ************************************************************************** */

/**
 * Data needed to manage the connection between the scheudler any type of interface.
 * This includes the thread, the socket and the GInputStream and GOutputStream
 */
typedef struct interface_connection
{
    GThread* thread;          ///< the thread that the connection is running in
    GSocketConnection* conn;  ///< the socket that is our connection
    GInputStream*  istr;      ///< stream to read from the interface
    GOutputStream* ostr;      ///< stream to write to the interface
} interface_connection;

/* ************************************************************************** */
/* **** Local Functions ***************************************************** */
/* ************************************************************************** */

/**
 * TODO
 *
 * @param
 * @return
 */
void* interface_thread(void* param)
{
  interface_connection* conn = param;
  char buffer[1024];

  while(g_input_stream_read(conn->istr, buffer, sizeof(buffer), NULL, NULL) != 0)
  {
    if(verbose > 1)
      lprintf("INTERFACE: recieved \"%s\"\n", buffer);

    // TODO make this thread safe
    if(strncmp(buffer, "CLOSE", 5) == 0)
      return NULL;
    else if(strncmp(buffer, "PAUSE", 5) == 0)
      job_pause(get_job(atoi(&buffer[10])));
    else if(strncmp(buffer, "RELOAD", 6) == 0)
      load_config();
    else if(strncmp(buffer, "STATUS", 6) == 0)
    {

    }
    else if(strncmp(buffer, "RESTART", 7) == 0)
      job_restart(get_job(atoi(&buffer[10])));
    else if(strncmp(buffer, "VERBOSE", 7) == 0)
    {
      if(buffer[10] == '\0')
        verbose = buffer[8] - '0';
      else
        job_verbose_event(job_verbose(get_job(atoi(&buffer[10])), buffer[8] - '0'));
    }
    else if(strncmp(buffer, "DATABASE", 8) == 0); // TODO check database event
    else break;

    memset(buffer, '\0', sizeof(buffer));
  }

  lprintf("ERROR: %s.%d: Interface connection closed unexpectantly\n", __FILE__, __LINE__);

  return NULL;
}

/**
 * TODO
 *
 * @param conn
 * @return
 */
interface_connection* interface_conn_init(GSocketConnection* conn)
{
  interface_connection* inter = (interface_connection*)calloc(1, sizeof(interface_connection));

  inter->conn = conn;
  inter->istr = g_io_stream_get_input_stream((GIOStream*)inter->conn);
  inter->ostr = g_io_stream_get_output_stream((GIOStream*)inter->conn);
  inter->thread = g_thread_create(interface_thread, inter, 1, NULL);

  return inter;
}

/**
 * TODO
 *
 * @param inter
 */
void interface_conn_destroy(interface_connection* inter)
{
  g_io_stream_close((GIOStream*)inter->conn, NULL, NULL);
  g_thread_join(inter->thread);
}

/**
 * TODO
 *
 * @param unused
 * @return
 */
void* listen_thread(void* unused)
{
  GSocketListener* server_socket;
  GSocketConnection* new_connection;

  /* validate new thread */
  if(i_terminate || !i_created)
  {
    ERROR("Could not create server socket thread\n");
    return NULL;
  }

  /* create the server socket to listen for connections on */
  server_socket = g_socket_listener_new();
  if(i_port < 0)
    i_port = g_socket_listener_add_any_inet_port(server_socket, NULL, NULL);
  else if(!g_socket_listener_add_inet_port(server_socket, i_port, NULL, NULL))
    FATAL("Could not create interface, invalid port number: %d", i_port);


  if(verbose > 1)
    lprintf("INTERFACE: listenning port is %d\n", i_port);

  /* wait for new connections */
  for(;;)
  {
    new_connection = g_socket_listener_accept(server_socket, NULL, NULL, NULL);

    if(i_terminate)
      break;
    if(verbose > 1)
      lprintf("INTERFACE: new interface connection\n");

    client_threads = g_list_append(client_threads,
        interface_conn_init(new_connection));
  }

  return NULL;
}

/* ************************************************************************** */
/* **** Constructor Destructor ********************************************** */
/* ************************************************************************** */

/**
 * Create all of the pieces of the interface between the scheduler and the different
 * user interfaces. The interface is how the scheduler knows that the database has
 * been updated and how it becomes aware of changes in debugging state.
 */
void interface_init()
{
  if(!i_created)
  {
    i_created = 1;
    socket_thread = g_thread_create(listen_thread, NULL, 1, NULL);
  }
}

/**
 * TODO
 */
void interface_destroy()
{
  GSocketClient* client;
  GList* iter;

  /* only destroy the interface if it has been created */
  if(i_created)
  {
    i_terminate = 1;
    client = g_socket_client_new();
    g_socket_client_connect_to_host(client, "127.0.0.1", i_port, NULL, NULL);
    g_thread_join(socket_thread);

    for(iter = client_threads; iter != NULL; iter = iter->next)
    {
      interface_conn_destroy(iter->data);
    }
  }
}

/* ************************************************************************** */
/* **** Access Functions **************************************************** */
/* ************************************************************************** */

/**
 *
 * @param port_n
 */
void set_port(int port_n)
{
  if(port_n < 0 || port_n > USHRT_MAX)
    ERROR("Unable to set port number, porvided port number was %d", port_n);

  i_port = port_n;
}

