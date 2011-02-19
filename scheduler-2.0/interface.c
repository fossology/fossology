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
#include <database.h>
#include <event.h>
#include <interface.h>
#include <job.h>
#include <logging.h>
#include <scheduler.h>
#include <schedulerCLI.h>

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
  network_header header;
  char buffer[1024];
  char* cmd, * args;
  unsigned long size;

  while(g_input_stream_read(conn->istr, &header, sizeof(header), NULL, NULL) != 0)
  {
    if(g_input_stream_read_all(conn->istr, buffer, header.bytes_following, &size, NULL, NULL) == 0)
    {
      lprintf_c("ERROR: unable to read from interface socket, attempted to read %d bytes", header.bytes_following);
      g_thread_exit(NULL);
    }

    if(TVERBOSE2) lprintf_c("INTERFACE: recieved \"%s\"\n", buffer);
    /* convert all characters before first ' ' to lower case */
    for(cmd = buffer, args = NULL; *cmd && args == NULL; cmd++)
    {
      *cmd = g_ascii_tolower(*cmd);
      if(*cmd == ' ' && args == NULL)
      {
        args = cmd + 1;
        *cmd = '\0';
      }
    }
    cmd = buffer;

    if(g_str_has_prefix("exit", cmd))
    {
      g_output_stream_write(conn->ostr, "CLOSE", 5, NULL, NULL);
      if(TVERBOSE2) lprintf_c("INTERFACE: closing connection to user interface\n");
      return NULL;
    }
    else if(g_str_has_prefix("close", cmd))
    {
      g_output_stream_write(conn->ostr, "CLOSE", 5, NULL, NULL);
      if(TVERBOSE2) lprintf_c("INTERFACE: shutting down scheduler\n");
      event_signal(scheduler_close_event, NULL);
      return NULL;
    }
    else if(g_str_has_prefix("pause", cmd))
      job_pause(get_job(atoi(&buffer[10])));
    else if(g_str_has_prefix("reload", cmd))
      load_config();
    else if(g_str_has_prefix("status", cmd))
    {
      // TODO define the format for this output for jobs and scheduler
    }
    else if(g_str_has_prefix("restart", cmd))
      job_restart(get_job(atoi(args)));
    else if(g_str_has_prefix("verbose", cmd))
    {
      if((cmd = strchr(args, ' ')) == NULL) verbose = atoi(args);
      else job_verbose_event(job_verbose(get_job(atoi(args)), atoi(cmd+1)));
    }
    else if(g_str_has_prefix("database", cmd))
      event_signal(database_update_event, NULL);
    else
    {
      lprintf_c("ERROR %s.%d: Interface recieved invalid command: %s\n", cmd);
    }

    memset(buffer, '\0', sizeof(buffer));
  }

  lprintf_c("ERROR %s.%d: Interface connection closed unexpectantly\n", __FILE__, __LINE__);

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
  g_thread_join(inter->thread);
  g_io_stream_close((GIOStream*)inter->conn, NULL, NULL);
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


  if(TVERBOSE2)
    lprintf_c("INTERFACE: listenning port is %d\n", i_port);

  /* wait for new connections */
  for(;;)
  {
    new_connection = g_socket_listener_accept(server_socket, NULL, NULL, NULL);

    if(i_terminate)
      break;
    if(TVERBOSE2)
      lprintf_c("INTERFACE: new interface connection\n");

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
 * TODO
 *
 * @param port_n
 */
void set_port(int port_n)
{
  if(port_n < 0 || port_n > USHRT_MAX)
    ERROR("Unable to set port number, porvided port number was %d", port_n);

  i_port = port_n;
}

/**
 * TODO
 */
int is_port_set()
{
  return i_port != -1;
}
