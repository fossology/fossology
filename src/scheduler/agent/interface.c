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
#include <agent.h>
#include <database.h>
#include <event.h>
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

#define FIELD_WIDTH 10
#define BUFFER_SIZE 1024

int i_created = 0;      ///< flag indicating if the interface already been created
int i_terminate = 0;    ///< flag indicating if the interface has been killed
int i_port = -1;        ///< the port that the scheduler is listening on
GThread* socket_thread; ///< thread that will create new connections
GList* client_threads;  ///< threads that are currently running some form of scheduler interface
GCancellable* cancel;   ///< used to shut down all interfaces when closing the scheudler

#define netw g_output_stream_write

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
 * function that will run the thread associated with a particular interface
 * instance. Since multiple different command line a graphical user interfaces
 * can exists simultatiously, this allows the scheduler to quickly perform any
 * requests.
 *
 * handle commands:
 *     exit: close connection with scheduler
 *    close: shutdown the scheduler
 *    pause: pause a job that is currently running
 *   reload: reload configuration information
 *   status: request status for scheduler or job
 *  restart: restart a paused job
 *  verbose: change verbose level for scheduler or job
 * priority: change the priority of job
 * database: check the database job queue
 *
 * @param  pointer to the interface_connection structure
 * @return not currently used
 */
void* interface_thread(void* param)
{
  interface_connection* conn = param;
  char buffer[BUFFER_SIZE];
  char org[sizeof(buffer)];
  char* cmd, * tmp;
  arg_int* params;
  int i;

  memset(buffer, '\0', sizeof(buffer));

  while(g_input_stream_read(conn->istr, buffer, sizeof(buffer), cancel, NULL))
  {
    V_INTERFACE("INTERFACE: received \"%s\"\n", buffer);
    /* convert all characters before first ' ' to lower case */
    memcpy(org, buffer, sizeof(buffer));
    for(cmd = buffer; *cmd; cmd++)
      *cmd = g_ascii_tolower(*cmd);
    cmd = strtok(buffer, " ");

    /* ??? this shouldn't be able to happen */
    if(cmd == NULL)
      break;

    /* acknowledge that you have received the command */
    V_INTERFACE("INTERFACE: send \"received\"\n");
    g_output_stream_write(conn->ostr, "received\n", 9, NULL, NULL);

    /* the interface has chosen to close, acknowledge and end the thread */
    if(strcmp(cmd, "close") == 0)
    {
      g_output_stream_write(conn->ostr, "CLOSE\n", 6, NULL, NULL);
      V_INTERFACE("INTERFACE: closing connection to user interface\n");
      return NULL;
    }

    /* scheduler instructed to shutdown, acknowledge and create close event */
    else if(strcmp(cmd, "stop") == 0)
    {
      g_output_stream_write(conn->ostr, "CLOSE\n", 6, NULL, NULL);
      V_INTERFACE("INTERFACE: shutting down scheduler\n");
      event_signal(scheduler_close_event, NULL);
      return NULL;
    }

    else if(strcmp(cmd, "load") == 0)
      print_host_load(conn->ostr);

    /* scheduler instructed to pause a job, create a job pause event */
    else if(strcmp(cmd, "pause") == 0)
    {
      params = g_new0(arg_int, 1);
      params->first = get_job(atoi(strtok(NULL, " ")));
      params->second = 1;
      event_signal(job_pause_event, params);
    }

    /* scheduler instructed to reload it configuration data */
    else if(strcmp(cmd, "reload") == 0)
      event_signal(load_config, NULL);

    else if(strcmp(cmd, "agents") == 0)
      event_signal(list_agents, conn->ostr);

    /* a status request has been made for scheduler or job      */
    /* * if scheduler request, print scheduler status followed  */
    /*     by simple status for each job                        */
    /* * if a job request, print simple status for job followed */
    /*     by the status of every agent belonging to the job    */
    else if(strcmp(cmd, "status") == 0)
    {
      params = g_new0(arg_int, 1);
      params->first = conn->ostr;
      params->second = (cmd = strtok(NULL, " ")) == NULL ? 0 : atoi(cmd);
      event_signal(job_status_event, params);
    }

    /* restart a paused job, simply create the apropriate event */
    else if(strcmp(cmd, "restart") == 0)
    {
      tmp = strtok(NULL, " ");
      i = atoi(tmp);
      if(i == 0 && tmp[0] != '0')
      {
        snprintf(buffer, sizeof(buffer) - 1,
            "ERROR: invalid argument for \"restart\" command: %s\n", tmp);
        g_output_stream_write(conn->ostr, buffer, strlen(buffer), NULL, NULL);
        continue;
      }
      event_signal(job_restart_event, get_job(atoi(strtok(NULL, " "))));
    }

    /* change the verbose level of the scheduler or a job */
    else if(strcmp(cmd, "verbose") == 0)
    {
      if((tmp = strtok(NULL, " ")) == NULL)
      {
        if(verbose < 8)
          sprintf(buffer, "level: %d\n", verbose);
        else
        {
          strcpy(buffer, "mask:       h d i e s a j\nmask: ");
          for(i = 1; i < 0x10000; i <<= 1)
            strcat(buffer, i & verbose ? "1 " : "0 ");
          strcat(buffer, "\n");
        }
        g_output_stream_write(conn->ostr, buffer, strlen(buffer), NULL, NULL);
      }
      else if((cmd = strtok(NULL, " ")) == NULL) verbose = atoi(tmp);
      else job_verbose_event(job_verbose(get_job(atoi(tmp)), atoi(cmd)));
    }

    /* changes the priority of a job */
    else if(strcmp(cmd, "priority") == 0)
    {
      if((cmd = strtok(NULL, " ")) && ((tmp = strtok(NULL, " "))))
      {
        params = g_new0(arg_int, 1);
        params->first = get_job(atoi(cmd));
        params->second = atoi(tmp);
        event_signal(job_priority_event, params);
      }
      else
        ERROR("invalid priority cmd sent to scheduler");
    }

    /* check the job queue for any newly queued jobs */
    else if(strcmp(cmd, "database") == 0)
      event_signal(database_update_event, NULL);

    /* scheudler recieved an unknown command from interface */
    else
    {
      g_output_stream_write(conn->ostr, "Invalid command: \"", 18, NULL, NULL);
      g_output_stream_write(conn->ostr, buffer, strlen(buffer), NULL, NULL);
      g_output_stream_write(conn->ostr, "\"\n", 2, NULL, NULL);
      clprintf("ERROR %s.%d: Interface received invalid command: %s\n", __FILE__, __LINE__, cmd);
    }

    memset(buffer, '\0', sizeof(buffer));
  }

  return NULL;
}

/**
 * Given a new sockect, this will create the interface connection structure.
 *
 * @param conn the socket that this interface is connected to
 * @return the newly allocated and populated interface connection
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
 * free the memory associated with an interface connection. It is important to
 * note that this will block until the thread associated with the interface has
 * closed correctly.
 *
 * @param inter the interface_connection that should be freed
 */
void interface_conn_destroy(interface_connection* inter)
{
  g_object_unref(inter->conn);
  g_thread_join(inter->thread);
}

/**
 * function that will listen for new connections to the server sockets. This
 * creates a g_socket_listener and will loop waiting for new connections until
 * the scheduler is closed.
 *
 * @param  unused
 * @return unused
 */
void* listen_thread(void* unused)
{
  GSocketListener* server_socket;
  GSocketConnection* new_connection;
  GError* error = NULL;

  /* validate new thread */
  if(i_terminate || !i_created)
  {
    ERROR("Could not create server socket thread\n");
    return NULL;
  }

  /* create the server socket to listen for connections on */
  server_socket = g_socket_listener_new();
  if(server_socket == NULL)
    FATAL("could not create the server socket");

  g_socket_listener_add_inet_port(server_socket, i_port, NULL, &error);
  if(error)
    FATAL("%s", error->message);

  V_INTERFACE("INTERFACE: listening port is %d\n", i_port);

  /* wait for new connections */
  for(;;)
  {
    new_connection = g_socket_listener_accept(server_socket, NULL, cancel, &error);

    if(i_terminate)
      break;
    V_INTERFACE("INTERFACE: new interface connection\n");
    if(error)
      FATAL("INTERFACE closing for %s", error->message);

    client_threads = g_list_append(client_threads,
        interface_conn_init(new_connection));
  }

  V_INTERFACE("INTERFACE: socket listening thread closing\n");
  g_socket_listener_close(server_socket);
  g_object_unref(server_socket);
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
 * closes all interface threads and closes the listening thread. This will block
 * until all threads have closed correctly.
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
 * Change the port that the scheudler will listen on.
 *
 * @param port_n the port number to listen on
 */
void set_port(int port_n)
{
  if(port_n < 0 || port_n > USHRT_MAX)
    ERROR("Unable to set port number, porvided port number was %d", port_n);

  i_port = port_n;
}

/**
 * testes if the scheduler port has been correctly set
 */
int is_port_set()
{
  return i_port != -1;
}
