/*
 SPDX-FileCopyrightText: © 2010, 2011, 2012 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * \file
 * \brief Scheduler interface operations
 */

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
#include <unistd.h>

/* glib includes */
#include <glib.h>
#include <gio/gio.h>

#define FIELD_WIDTH 10
#define BUFFER_SIZE 1024

#define netw g_output_stream_write

#define PROXY_PROTOCOL     "socks5"
#define PROXY_DEFAULT_PORT 1080

/* ************************************************************************** */
/* **** Data Types ********************************************************** */
/* ************************************************************************** */

/**
 * Data needed to manage the connection between the scheduler any type of interface.
 * This includes the thread, the socket and the GInputStream and GOutputStream
 */
typedef struct interface_connection
{
    GSocketConnection* conn;  ///< The socket that is our connection
    GInputStream*  istr;      ///< Stream to read from the interface
    GOutputStream* ostr;      ///< Stream to write to the interface
} interface_connection;

/* ************************************************************************** */
/* **** Local Functions ***************************************************** */
/* ************************************************************************** */

/**
 * Given a new socket, this will create the interface connection structure.
 *
 * @param conn    The socket that this interface is connected to
 * @param threads Thread pool handling sockets
 * @return the newly allocated and populated interface connection
 */
static interface_connection* interface_conn_init(
    GSocketConnection* conn, GThreadPool* threads)
{
  interface_connection* inter = g_new0(interface_connection, 1);

  inter->conn = conn;
  inter->istr = g_io_stream_get_input_stream((GIOStream*)inter->conn);
  inter->ostr = g_io_stream_get_output_stream((GIOStream*)inter->conn);
  g_thread_pool_push(threads, inter, NULL);

  return inter;
}

/**
 * @brief Free the memory associated with an interface connection.
 *
 * @note This will block until the thread associated with the interface has
 * closed correctly.
 *
 * @param inter the interface_connection that should be freed
 */
static void interface_conn_destroy(interface_connection* inter)
{
  g_object_unref(inter->conn);
  g_free(inter);
}

/**
 * @brief Function that will run the thread associated with a particular interface
 * instance.
 *
 * Since multiple different command line a graphical user interfaces
 * can exists simultaneously, this allows the scheduler to quickly perform any
 * requests.
 *
 * Handle commands:
 *
 * | Command | Description |
 * | ---: | :--- |
 * |     exit | Close connection with scheduler |
 * |     kill | Kill a particular job |
 * |     load | Get the host status |
 * |    close | Shutdown the scheduler |
 * |    pause | Pause a job that is currently running |
 * |   reload | Reload configuration information |
 * |   status | Request status for scheduler or job |
 * |  restart | Restart a paused job |
 * |  verbose | Change verbose level for scheduler or job |
 * | priority | Change the priority of job |
 * | database | Check the database job queue |
 *
 * @param  conn      Pointer to the interface_connection structure
 * @param  scheduler Pointer to the relevant scheduler structure
 * @return not currently used
 */
void interface_thread(interface_connection* conn, scheduler_t* scheduler)
{
  GMatchInfo* regex_match;
  job_t* job;
  char buffer[BUFFER_SIZE];
  char org[sizeof(buffer)];
  char* arg1, * arg2, * arg3;
  char* cmd;
  arg_int* params;
  int i;

  memset(buffer, '\0', sizeof(buffer));

  while(g_input_stream_read(conn->istr, buffer, sizeof(buffer), scheduler->cancel, NULL) > 0)
  {
    V_INTERFACE("INTERFACE: received \"%s\"\n", buffer);
    /* convert all characters before first ' ' to lower case */
    memcpy(org, buffer, sizeof(buffer));
    for(cmd = buffer; *cmd; cmd++)
      *cmd = g_ascii_tolower(*cmd);
    g_regex_match(scheduler->parse_interface_cmd, buffer, 0, &regex_match);
    cmd = g_match_info_fetch(regex_match, 1);

    if(cmd == NULL)
    {
      g_output_stream_write(conn->ostr, "Invalid command: \"", 18, NULL, NULL);
      g_output_stream_write(conn->ostr, buffer, strlen(buffer), NULL, NULL);
      g_output_stream_write(conn->ostr, "\"\n", 2, NULL, NULL);
      g_match_info_free(regex_match);
      WARNING("INTERFACE: invalid command: \"%s\"", buffer);
      continue;
    }

    /* acknowledge that you have received the command */
    V_INTERFACE("INTERFACE: send \"received\"\n");
    g_output_stream_write(conn->ostr, "received\n", 9, NULL, NULL);

    /* command: "close"
     *
     * The interface has chosen to close the connection. Return the command
     * in acknowledgment of the command and end this thread.
     */
    if(strcmp(cmd, "close") == 0)
    {
      g_output_stream_write(conn->ostr, "CLOSE\n", 6, NULL, NULL);
      V_INTERFACE("INTERFACE: closing connection to user interface\n");

      g_match_info_free(regex_match);
      g_free(cmd);
      return;
    }

    /* command: "stop"
     *
     * The interface has instructed the scheduler to shut down gracefully. The
     * scheduler will wait for all currently executing agents to finish
     * running, then exit the vent loop.
     */
    else if(strcmp(cmd, "stop") == 0)
    {
      g_output_stream_write(conn->ostr, "CLOSE\n", 6, NULL, NULL);
      V_INTERFACE("INTERFACE: shutting down scheduler gracefully\n");
      event_signal(scheduler_close_event, (void*)0);

      g_match_info_free(regex_match);
      g_free(cmd);
      return;
    }

    /* command: "die"
     *
     * The interface has instructed the scheduler to shut down. The scheduler
     * should acknowledge the command and proceed to kill all current executing
     * agents and exit the event loop
     */
    else if(strcmp(cmd, "die") == 0)
    {
      g_output_stream_write(conn->ostr, "CLOSE\n", 6, NULL, NULL);
      V_INTERFACE("INTERFACE: killing the scheduler\n");
      event_signal(scheduler_close_event, (void*)1);

      g_match_info_free(regex_match);
      g_free(cmd);
      return;
    }

    /* command: "load"
     *
     * The interface has requested information about the load that the different
     * hosts are under. The scheduler should respond with the status of all the
     * hosts.
     */
    else if(strcmp(cmd, "load") == 0)
    {
      print_host_load(scheduler->host_list, conn->ostr);
    }

    /* command: "kill <job_id> <"message">"
     *
     * The interface has instructed the scheduler to kill and fail a particular
     * job. Both arguments are required for this command.
     *
     * job_id: The jq_pk for the job that needs to be killed
     * message: A message that will be in the email notification and the
     *          jq_endtext field of the job queue
     */
    else if(strcmp(cmd, "kill") == 0)
    {
      arg1 = g_match_info_fetch(regex_match, 3);
      arg2 = g_match_info_fetch(regex_match, 8);

      if(arg1)
        i = atoi(arg1);
      if(arg1 == NULL || arg2 == NULL || strlen(arg1) == 0 || strlen(arg2) == 0)
      {
        g_free(cmd);
        cmd = g_strdup_printf("Invalid kill command: \"%s\"\n", buffer);
        g_output_stream_write(conn->ostr, cmd, strlen(cmd), NULL, NULL);
      }
      else if((job = g_tree_lookup(scheduler->job_list, &i)) == NULL)
      {
        arg3 = g_strdup_printf(jobsql_failed, arg2, i);
        event_signal(database_exec_event, arg3);
      }
      else
      {
        if(job->message)
          g_free(job->message);
        job->message = strdup(((arg2 == NULL) ? "no message" : arg2));
        event_signal(job_fail_event, job);
      }

      g_free(arg1);
      g_free(arg2);
    }

    /* command: "pause <job_id>"
     *
     * The interface has instructed the scheduler to pause a job. This is used
     * to free up resources on a particular host. The argument is required and
     * is the jq_pk for the job that needs to be paused.
     */
    else if(strcmp(cmd, "pause") == 0)
    {
      arg1 = g_match_info_fetch(regex_match, 3);

      if(arg1 == NULL || strlen(arg1) == 0)
      {
        arg1 = g_strdup_printf("Invalid pause command: \"%s\"\n", buffer);
        WARNING("received invalid pause command: %s", buffer);
        g_output_stream_write(conn->ostr, arg1, strlen(arg1), NULL, NULL);
        g_free(arg1);
      }
      else
      {
        params = g_new0(arg_int, 1);
        params->second = atoi(arg1);
        params->first = g_tree_lookup(scheduler->job_list, &params->second);
        event_signal(job_pause_event, params);
        g_free(arg1);
      }
    }

    /* command: "reload"
     *
     * The scheduler should reload its configuration information. This should
     * be used if a change to an agent or fossology.conf has been made since
     * the scheduler started running.
     */
    else if(strcmp(cmd, "reload") == 0)
    {
      event_signal(scheduler_config_event, NULL);
    }

    /* command: "agents"
     *
     * The interface has requested a list of agents that the scheduler is able
     * to run correctly.
     */
    else if(strcmp(cmd, "agents") == 0)
    {
      event_signal(list_agents_event, conn->ostr);
    }

    /* command: "status [job_id]"
     *
     * fetches the status of the a particular job or the scheduler. The
     * argument is not required for this command.
     *
     * with job_id:
     *   print job status followed by status of agent belonging to the job
     * without job_id:
     *   print scheduler statsu followed by status of every job
     */
    else if(strcmp(cmd, "status") == 0)
    {
      arg1 = g_match_info_fetch(regex_match, 3);

      params = g_new0(arg_int, 1);
      params->first = conn->ostr;
      params->second = (arg1 == NULL) ? 0 : atoi(arg1);
      event_signal(job_status_event, params);

      g_free(arg1);
    }

    /* command: "restart <job_id>"
     *
     * The interface has instructed the scheduler to restart a job that has been
     * paused. The argument for this command is required and is the jq_pk for
     * the job that should be restarted.
     */
    else if(strcmp(cmd, "restart") == 0)
    {
      arg1 = g_match_info_fetch(regex_match, 3);

      if(arg1 == NULL)
      {
        arg1 = g_strdup(buffer);
        WARNING("received invalid restart command: %s", buffer);
        snprintf(buffer, sizeof(buffer) - 1,
                    "ERROR: Invalid restart command: %s\n", arg1);
        g_output_stream_write(conn->ostr, buffer, strlen(buffer), NULL, NULL);
        g_free(arg1);
      }
      else
      {
        params = g_new0(arg_int, 1);
        params->second = atoi(arg1);
        params->first = g_tree_lookup(scheduler->job_list, &params->second);
        event_signal(job_restart_event, params);
        g_free(arg1);
      }
    }

    /* command: "verbose <job_id|level> [level]"
     *
     * The interface has either requested a change in a verbose level, or it
     * has requested the current verbose level. This command can have no
     * arguments, 1 argument or 2 arguments.
     *
     * no arguments: respond with the verbose level of the scheduler
     *  1 argument:  change the verbose level of the scheduler to the argument
     *  2 arguments: change the verbose level of the job with the jq_pk of the
     *               first arguement to the second argument
     */
    else if(strcmp(cmd, "verbose") == 0)
    {
      arg1 = g_match_info_fetch(regex_match, 3);
      arg2 = g_match_info_fetch(regex_match, 5);

      if(arg1 == NULL)
      {
        if(verbose < 8)
        {
          sprintf(buffer, "level: %d\n", verbose);
        }
        else
        {
          strcpy(buffer, "mask:       h d i e s a j\nmask: ");
          for(i = 1; i < 0x10000; i <<= 1)
            strcat(buffer, i & verbose ? "1 " : "0 ");
          strcat(buffer, "\n");
        }
        g_output_stream_write(conn->ostr, buffer, strlen(buffer), NULL, NULL);
      }
      else if(arg2 == NULL)
      {
        verbose = atoi(arg1);
        g_free(arg1);
      }
      else
      {
        i = atoi(arg1);
        if((job = g_tree_lookup(scheduler->job_list, &i)) == NULL)
        {
          g_free(cmd);
          cmd = g_strdup_printf("Invalid verbose command: \"%s\"\n", buffer);
          g_output_stream_write(conn->ostr, cmd, strlen(cmd), NULL, NULL);
        }
        else
        {
          job->verbose = atoi(arg2);
          event_signal(job_verbose_event, job);
        }

        g_free(arg1);
        g_free(arg2);
      }
    }

    /* command: "priority <job_id> <level>"
     *
     * Scheduler should change the priority of a job. This will change the
     * systems priority of the relevant job and change the priority of the job
     * in the database to match. Both arguments are required for this command.
     */
    else if(strcmp(cmd, "priority") == 0)
    {
      arg1 = g_match_info_fetch(regex_match, 3);
      arg2 = g_match_info_fetch(regex_match, 5);

      if(arg1 != NULL && arg2 != NULL)
      {
        i = atoi(arg1);

        params = g_new0(arg_int, 1);
        params->first = g_tree_lookup(scheduler->job_list, &i);
        params->second = atoi(arg2);
        event_signal(job_priority_event, params);
        g_free(arg1);
        g_free(arg2);
      }
      else
      {
        if(arg1) g_free(arg1);
        if(arg2) g_free(arg2);

        arg1 = g_strdup(buffer);
        WARNING("Invalid priority command: %s\n", buffer);
        snprintf(buffer, sizeof(buffer) - 1,
            "ERROR: Invalid priority command: %s\n", arg1);
        g_output_stream_write(conn->ostr, buffer, strlen(buffer), NULL, NULL);
        g_free(arg1);
      }
    }

    /* command: "database"
     *
     * The scheduler should check the database. This will normaly be sent by
     * the ui when a new job has been queue and must be run.
     */
    else if(strcmp(cmd, "database") == 0)
    {
      event_signal(database_update_event, NULL);
    }

    /* command: unknown
     *
     * The command sent does not match any of the known commands, log an error
     * and inform the interface that this wasn't a command.
     */
    else
    {
      g_output_stream_write(conn->ostr, "Invalid command: \"", 18, NULL, NULL);
      g_output_stream_write(conn->ostr, buffer, strlen(buffer), NULL, NULL);
      g_output_stream_write(conn->ostr, "\"\n", 2, NULL, NULL);
      con_printf(main_log, "ERROR %s.%d: Interface received invalid command: %s\n", __FILE__, __LINE__, cmd);
    }

    g_match_info_free(regex_match);
    g_free(cmd);
    memset(buffer, '\0', sizeof(buffer));
  }

  interface_conn_destroy(conn);
  return;
}

/**
 * @brief Function that will listen for new connections to the server sockets.
 *
 * This creates a g_socket_listener and will loop waiting for new connections until
 * the scheduler is closed.
 *
 * @param  scheduler Relevant scheduler structure
 * @return (void*)0 on failure, (void*)1 on success
 */
void* interface_listen_thread(scheduler_t* scheduler)
{
  GSocketListener* server_socket;
  GSocketConnection* new_connection;
  GError* error = NULL;

  /* validate new thread */
  if(scheduler->i_terminate || !scheduler->i_created)
  {
    ERROR("Could not create server socket thread\n");
    return (void*)0;
  }

  /* create the server socket to listen for connections on */
  server_socket = g_socket_listener_new();
  if(server_socket == NULL)
    FATAL("could not create the server socket");

  g_socket_listener_add_inet_port(server_socket, scheduler->i_port, NULL, &error);
  if(error)
    FATAL("[port:%d]: %s", scheduler->i_port, error->message);
  scheduler->cancel  = g_cancellable_new();

  V_INTERFACE("INTERFACE: listening port is %d\n", scheduler->i_port);

  /* wait for new connections */
  for(;;)
  {
    new_connection = g_socket_listener_accept(server_socket, NULL,
        scheduler->cancel, &error);

    if(scheduler->i_terminate)
      break;
    V_INTERFACE("INTERFACE: new interface connection\n");
    if(error)
      FATAL("INTERFACE closing for %s", error->message);

    interface_conn_init(new_connection, scheduler->workers);
  }

  V_INTERFACE("INTERFACE: socket listening thread closing\n");
  g_socket_listener_close(server_socket);
  g_object_unref(server_socket);
  return (void*)1;
}

/* ************************************************************************** */
/* **** Constructor Destructor ********************************************** */
/* ************************************************************************** */

/**
 * @brief Create the interface thread and thread pool that handle UI connections
 *
 * The GUI and the CLI use a socket connection to communicate with the
 * scheduler. This function creates the socket connection as well as everything
 * needed to handle incoming connections and messages.
 *
 * @note If interface_init() is called multiple times without a call to
 *       interface_destroy(), it will become a no-op after the second call
 */
void interface_init(scheduler_t* scheduler)
{
  if(!scheduler->i_created)
  {
    scheduler->i_created = 1;
    scheduler->i_terminate = 0;

    scheduler->cancel = NULL;
    scheduler->workers = g_thread_pool_new((GFunc)interface_thread,
        scheduler, CONF_interface_nthreads, FALSE, NULL);

#if GLIB_MAJOR_VERSION >= 2 && GLIB_MINOR_VERSION >= 32
    scheduler->server = g_thread_new("interface",
        (GThreadFunc)interface_listen_thread, scheduler);
#else
    scheduler->server = g_thread_create((GThreadFunc)interface_listen_thread,
        scheduler, TRUE, NULL);
#endif

    while(scheduler->cancel == NULL)
      usleep(100);
  }
  else
  {
    WARNING("Multiple attempts made to initialize the interface");
  }
}

/**
 * @brief Closes the server socket and thread pool that service UI connections
 *
 * @note If interface_destroy() is called before interface_init(), then it will
 *       be a no-op.
 */
void interface_destroy(scheduler_t* scheduler)
{
  /* only destroy the interface if it has been created */
  if(scheduler->i_created)
  {
    scheduler->i_terminate = 1;
    scheduler->i_created = 0;

    g_cancellable_cancel(scheduler->cancel);
    g_thread_join(scheduler->server);
    g_thread_pool_free(scheduler->workers, FALSE, TRUE);

    scheduler->server  = NULL;
    scheduler->cancel  = NULL;
    scheduler->workers = NULL;
  }
  else
  {
    WARNING("Attempt to destroy the interface without initializing it");
  }
}
