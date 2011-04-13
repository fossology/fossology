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
GCancellable* cancel;   ///< used to shut down all interfaces when closing the scheudler

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
  char org[sizeof(buffer)];
  char* cmd, * tmp;
  unsigned long size;
  arg_int* params;

  while(g_input_stream_read_all(conn->istr, &header, sizeof(header), &size, cancel, NULL))
  {
    memset(buffer, '\0', sizeof(buffer));
    if(g_input_stream_read_all(conn->istr, buffer, header.bytes_following, &size, cancel, NULL) == 0)
    {
      clprintf("ERROR: unable to read from interface socket, attempted to read %d bytes", header.bytes_following);
      g_thread_exit(NULL);
    }

    if(TVERBOSE2) clprintf("INTERFACE: recieved \"%s\"\n", buffer);
    /* convert all characters before first ' ' to lower case */
    memcpy(org, buffer, sizeof(buffer));
    for(cmd = buffer; *cmd; cmd++)
      *cmd = g_ascii_tolower(*cmd);
    cmd = strtok(buffer, " ");
    size = 0;

    if(cmd == NULL)
    {
      break;
    }
    else if(strcmp(cmd, "exit") == 0)
    {
      g_output_stream_write(conn->ostr, "CLOSE", 5, NULL, NULL);
      if(TVERBOSE2) clprintf("INTERFACE: closing connection to user interface\n");
      return NULL;
    }
    else if(strcmp(cmd, "close") == 0)
    {
      g_output_stream_write(conn->ostr, "CLOSE", 5, NULL, NULL);
      if(TVERBOSE2) clprintf("INTERFACE: shutting down scheduler\n");
      event_signal(scheduler_close_event, NULL);
      return NULL;
    }
    else if(strcmp(cmd, "pause") == 0)
    {
      params = g_new0(arg_int, 1);
      params->first = get_job(atoi(strtok(NULL, " ")));
      params->second = 1;
      event_signal(job_pause_event, params);
    }
    else if(strcmp(cmd, "reload") == 0)
      load_config();
    else if(strcmp(cmd, "status") == 0)
    {
      params = g_new0(arg_int, 1);
      params->first = conn->ostr;
      params->second = (cmd = strtok(NULL, " ")) == NULL ? 0 : atoi(cmd);
      event_signal(job_status_event, params);
    }
    else if(strcmp(cmd, "restart") == 0)
    {
      event_signal(job_restart_event, get_job(atoi(strtok(NULL, " "))));
    }
    else if(strcmp(cmd, "verbose") == 0 && (tmp = strtok(NULL, " ")) != NULL)
    {
      if((cmd = strtok(NULL, " ")) == NULL) verbose = atoi(tmp);
      else job_verbose_event(job_verbose(get_job(atoi(tmp)), atoi(cmd)));
    }
    else if(strcmp(cmd, "database") == 0)
      event_signal(database_update_event, NULL);
    else
    {
      clprintf("ERROR %s.%d: Interface received invalid command: %s\n", __FILE__, __LINE__, cmd);
    }

    memset(buffer, '\0', sizeof(buffer));
  }

  clprintf("ERROR %s.%d: Interface connection closed unexpectantly\n", __FILE__, __LINE__);

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
  interface_connection* inter = g_new0(interface_connection, 1);

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
    clprintf("INTERFACE: listenning port is %d\n", i_port);

  clprintf("ENTERING LOOP\n");
  /* wait for new connections */
  for(;;)
  {
    new_connection = g_socket_listener_accept(server_socket, NULL, cancel, NULL);

    if(i_terminate)
      break;
    if(TVERBOSE2)
      clprintf("INTERFACE: new interface connection\n");

    client_threads = g_list_append(client_threads,
        interface_conn_init(new_connection));
  }

  VERBOSE2("INTERFACE: socket listening thread closing\n");
  g_socket_listener_close(server_socket);
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

  cancel = g_cancellable_new();
}

/**
 * TODO
 */
void interface_destroy()
{
  GList* iter;

  /* only destroy the interface if it has been created */
  if(i_created)
  {
    i_terminate = 1;
    g_cancellable_cancel(cancel);
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
