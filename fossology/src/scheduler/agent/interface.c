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

int i_created = 0;       ///< flag indicating if the interface already been created
int i_terminate = 0;     ///< flag indicating if the interface has been killed
int i_port = -1;         ///< the port that the scheduler is listening on
GThread* socket_thread;  ///< thread that will create new connections
GList* client_threads;   ///< threads that are currently running some form of scheduler interface
GCancellable* cancel;    ///< used to shut down all interfaces when closing the scheudler
GRegex* cmd_parse;       ///< regex to make parsing command more reliable
GRegex* pro_parse;       ///< regex to parse the proxy formatting
GSocketClient* outgoing; ///< creates outgoing socket connections

GRegex* rep_init_r;
GRegex* get_host_r;
GRegex* dot_repl_r;
GRegex* cmd_recv_r;

#define netw g_output_stream_write

#define PROXY_PROTOCOL     "socks5"
#define PROXY_DEFAULT_PORT 1080

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
 *     kill: kill a particular job
 *     load: get the host status
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
  GMatchInfo* regex_match;
  interface_connection* conn = param;
  job to_kill;
  char buffer[BUFFER_SIZE];
  char org[sizeof(buffer)];
  char* cmd;
  char* arg1;
  char* arg2;
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
    g_regex_match(cmd_parse, buffer, 0, &regex_match);
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
      return NULL;
    }

    /* command: "stop"
     *
     * The interface has instructed the scheduler to shut down. The scheduler
     * should acknowledge with the command and proceed to kill and agents and
     * exit the event loop
     */
    else if(strcmp(cmd, "stop") == 0)
    {
      g_output_stream_write(conn->ostr, "CLOSE\n", 6, NULL, NULL);
      V_INTERFACE("INTERFACE: shutting down scheduler\n");
      event_signal(scheduler_close_event, NULL);

      g_match_info_free(regex_match);
      g_free(cmd);
      return NULL;
    }

    /* command: "load"
     *
     * The interface has requested information about the load that the different
     * hosts are under. The scheduler should respond with the status of all the
     * hosts.
     */
    else if(strcmp(cmd, "load") == 0)
    {
      print_host_load(conn->ostr);
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

      if((to_kill = get_job(atoi(arg1))) == NULL)
      {
        snprintf(buffer, sizeof(buffer),
            "Invalid kill command: job %d does not exist\n", atoi(arg1));
        g_output_stream_write(conn->ostr, buffer, strlen(buffer), NULL, NULL);
      }
      else
      {
        if(to_kill->message)
          g_free(to_kill->message);
        to_kill->message = strdup(((arg2 == NULL) ? "no message" : arg2));
        event_signal(job_fail_event, to_kill);
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

      params = g_new0(arg_int, 1);
      params->first = get_job(atoi(arg1));
      params->second = 1;
      event_signal(job_pause_event, params);

      g_free(arg1);
    }

    /* command: "reload"
     *
     * The scheduler should reload its configuration information. This should
     * be used if a change to an agent or fossology.conf has been made since
     * the scheduler started running.
     */
    else if(strcmp(cmd, "reload") == 0)
    {
      event_signal(load_config, NULL);
    }

    /* command: "agents"
     *
     * The interface has requested a list of agents that the scheduler is able
     * to run correctly.
     */
    else if(strcmp(cmd, "agents") == 0)
    {
      event_signal(list_agents, conn->ostr);
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
        WARNING("received invalid restart command: %s", buffer);
        snprintf(buffer, sizeof(buffer) - 1,
                    "ERROR: Invalid restart command: %s\n", buffer);
        g_output_stream_write(conn->ostr, buffer, strlen(buffer), NULL, NULL);
      }
      else
      {
        event_signal(job_restart_event, get_job(atoi(arg1)));
        g_free(arg1);
      }
    }

    /* command: "verbose [job_id|level] [level]"
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
        job_verbose_event(
            job_verbose(get_job(atoi(arg1)), atoi(arg2)));
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
        params = g_new0(arg_int, 1);
        params->first = get_job(atoi(arg1));
        params->second = atoi(arg2);
        event_signal(job_priority_event, params);
        g_free(arg1);
        g_free(arg2);
      }
      else
      {
        if(arg1) g_free(arg1);
        if(arg2) g_free(arg2);
        WARNING("Invalid priority command: %s\n", buffer);
        snprintf(buffer, sizeof(buffer) - 1,
            "ERROR: Invalid priority command: %s\n", buffer);
        g_output_stream_write(conn->ostr, buffer, strlen(buffer), NULL, NULL);
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
      clprintf("ERROR %s.%d: Interface received invalid command: %s\n", __FILE__, __LINE__, cmd);
    }

    g_match_info_free(regex_match);
    g_free(cmd);
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
"Alex Norton" <norton@localhost>
 */
void interface_init()
{
  if(!i_created)
  {
    i_created = 1;
    socket_thread = g_thread_create(listen_thread, NULL, 1, NULL);
  }

  outgoing = g_socket_client_new();
  cancel = g_cancellable_new();

  cmd_parse = g_regex_new(
      "(\\w+)(\\s+(\\d+))?(\\s+((\\d+)|(\"(.*)\")))?",
      0, G_REGEX_MATCH_NEWLINE_LF, NULL);
  pro_parse = g_regex_new(
      "(.*):(\\d+)?",
      0, 0, NULL);

  get_host_r  = g_regex_new(
      "((\".+\")\\s)?(<.+@([^\\.]+(\\.[a-z]{2,})?)>)",
      0, 0, NULL);
  dot_repl_r  = g_regex_new(
      "^\\.",
      G_REGEX_MULTILINE, 0, NULL);
  cmd_recv_r  = g_regex_new(
      "^(\\d{3})\\s+(.*?)\\s+(.*)$",
      G_REGEX_MULTILINE, 0, NULL);
}

/**
 * closes all interface threads and closes the listening thread. This will block
 * until all threads have closed correctly.
 *
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

/* ************************************************************************** */
/* **** Networking Functions ************************************************ */
/* ************************************************************************** */

/**
 * Create a socket connection to a remote host
 *
 * @param host  the host to connect to
 * @param port  the port to connect on
 * @return  a new GOIStream that allows communication over the connection
 */
GIOStream* connect_to(gchar* host, guint16 port, GError** in_error)
{
  GError* error = NULL;
  GMatchInfo* info;

  GSocketConnection* sock_conn = NULL;
  GSocketAddress*    i_addr    = NULL;
  GProxyAddress*     p_addr    = NULL;
  GInetAddress*      address   = NULL;
  GSocket*           sock      = NULL;
  GList*             addr_list = NULL;
  GList*             iter      = NULL;

  GProxy*    proxy    = g_proxy_get_default_for_protocol(PROXY_PROTOCOL);
  GResolver* resolver = g_resolver_get_default();
  GIOStream* ret_val  = NULL;

  gchar*   use_host = host;
  gboolean use_proxy = FALSE;
  guint16  use_port = port;

  /* check to make sure we don't need to connect through a proxy */
  if(fo_config_has_key(sysconfig, "FOSSOLOGY", "socks_proxy"))
  {
    use_proxy = TRUE;
    use_host = fo_config_get(sysconfig, "FOSSOLOGY", "socks_proxy", NULL);

    g_regex_match(pro_parse, use_host,  0, &info);
    use_host = g_match_info_fetch(info, 2);
    use_port = use_host == NULL ? PROXY_DEFAULT_PORT : atoi(use_host);

    g_free(use_host);
    use_host = g_match_info_fetch(info, 1);

    g_match_info_free(info);
  }

  addr_list = g_resolver_lookup_by_name( resolver, host, NULL, in_error);
  if(*in_error) return NULL;

  for(iter = addr_list; iter; iter = iter->next)
  {
    address = (GInetAddress*)iter->data;
    i_addr = g_inet_socket_address_new(address, use_port);

    sock = g_socket_new(g_inet_address_get_family(address),
        G_SOCKET_TYPE_STREAM, G_SOCKET_PROTOCOL_TCP, &error);
    if(error)
    {
      g_clear_error(&error);
      g_object_unref(i_addr);
      continue;
    }

    g_socket_connect(sock, i_addr, NULL, &error);
    if(error)
    {
      g_clear_error(&error);
      g_object_unref(i_addr);
      g_object_unref(sock);
      continue;
    }

    sock_conn = g_socket_connection_factory_create_connection(sock);
    if(use_proxy)
    {
      p_addr = (GProxyAddress*)g_proxy_address_new(
          (GInetAddress*)i_addr, use_port, PROXY_PROTOCOL, host, port,
          NULL, NULL);
      ret_val = g_proxy_connect(
          proxy, (GIOStream*)sock_conn,
          p_addr, NULL, &error);

      if(error)
      {
        g_clear_error(&error);
        WARNING("%s", error->message);
      }
      g_object_unref(p_addr);
    }
    else
    {
      ret_val = g_object_ref(sock_conn);
    }

    g_object_unref(i_addr);
    g_object_unref(sock);
    g_object_unref(ret_val);

    if(ret_val != NULL)
      break;
  }

  if(use_proxy)
    g_free(use_host);
  g_list_free(addr_list);

  if(ret_val == NULL)
    g_set_error( in_error, 0, 0, "ERROR: unable to connect to \"%s:%d\"",
        host, port);

  g_object_unref(proxy);

  return ret_val;
}

/**
 * Simple function to replace all '.' at the beginning of a line with a '..'.
 * This is done because the smtp protocol uses the '.' character at the
 * beginning of a line to mean that the message has ended.
 *
 * @param match   not used in this function
 * @param ret     the string that the '..' should be appended to
 * @param unsued  not used in this function
 * @return  FALSE, indicating that the replace should continue
 */
gboolean dot_replace(const GMatchInfo* match, GString* ret, gpointer unsued)
{
  g_string_append(ret, "..");
  return FALSE;
}

#define free_all()                        \
    do {                                  \
      g_free(to_url);                     \
      g_free(to_email);                   \
      g_free(from_url);                   \
      g_free(from_email);                 \
      g_free(msg);                        \
      g_free(tmp);                        \
      if(match) g_match_info_free(match); \
      return;                             \
    } while(0)

#define valid_smtp(valid)                                                       \
    do {                                                                        \
      if((size = g_input_stream_read(istr, buf, sizeof(buf), NULL, error)) < 0) \
        free_all();                                                             \
      if(!g_regex_match_full(cmd_recv_r, buf, size, 0, 0, &match, error))       \
        free_all();                                                             \
      if(strcmp(tmp = g_match_info_fetch(match, 1), valid) != 0)                \
        free_all();                                                             \
      g_free(tmp);                                                              \
      g_match_info_free(match);                                                 \
      tmp = NULL;                                                               \
      match = NULL;                                                             \
    } while (0)

/**
 * TODO
 *
 * @param to
 * @param from
 * @param subject
 * @param message
 * @param error
 */
void send_email(gchar* to, gchar* from, gchar* subject, gchar* message,
    GError** error)
{
  GMatchInfo* match;
  gchar* to_email;
  gchar* to_url;
  gchar* from_email;
  gchar* from_url;
  gchar* tmp = NULL;
  GIOStream* conn;
  GInputStream*  istr;
  GOutputStream* ostr;

  gssize size = 0;
  gchar buf[BUFFER_SIZE];

  /* get the date/time */
  time_t rawtime;
  const struct tm* timeinfo;

  time(&rawtime);
  timeinfo = localtime(&rawtime);

  /* replace all '.' at the start of lines */
  gchar* msg = g_regex_replace_eval(dot_repl_r, message, -1,
      0, 0, (GRegexEvalCallback)dot_replace, NULL, error);
  if(*error)
    return;

  /* get the information about to and from */
  if(!g_regex_match_full(get_host_r, to, -1, 0, 0, &match, error))
  {
    g_free(msg);
    return;
  }
  to_url   = g_match_info_fetch(match, 4);
  to_email = g_match_info_fetch(match, 3);
  g_match_info_free(match);

  if(!g_regex_match_full(get_host_r, from, -1, 0, 0, &match, error))
  {
    g_free(to_url);
    g_free(to_email);
    g_free(msg);
    return;
  }
  from_url   = g_match_info_fetch(match, 4);
  from_email = g_match_info_fetch(match, 3);
  g_match_info_free(match);

  /* connect to the url specified in the email */
  if((conn = connect_to(to_url, 25, error)) == NULL)
    free_all();
  istr = g_io_stream_get_input_stream(conn);
  ostr = g_io_stream_get_output_stream(conn);
  match = NULL;

  /* get the reply from connecting to smtp server */
  if((size = g_input_stream_read(istr, buf, sizeof(buf), NULL, error)) < 0)
    free_all();
  if(!g_regex_match_full(cmd_recv_r, buf, size, 0, 0, &match, error))
    free_all();
  if(strcmp(tmp = g_match_info_fetch(match, 1), "220") != 0)
    free_all();
  g_free(tmp);

  /* say/recieve hello */
  tmp = g_match_info_fetch(match, 2);
  snprintf(buf, sizeof(buf), "HELO %s\n", tmp);
  if(g_output_stream_write(ostr, buf, strlen(buf), NULL, error) < 0)
    free_all();
  g_free(tmp);
  g_match_info_free(match);
  tmp = NULL;
  match = NULL;
  valid_smtp("250");

  /* say who is sending the mail */
  snprintf(buf, sizeof(buf), "MAIL FROM:%s\n", from_email);
  if(g_output_stream_write(ostr, buf, strlen(buf), NULL, error) < 0)
    free_all();
  valid_smtp("250");

  /* say who is getting the mail */
  snprintf(buf, sizeof(buf), "RCPT TO:%s\n", to_email);
  if(g_output_stream_write(ostr, buf, strlen(buf), NULL, error) < 0)
    free_all();
  valid_smtp("250");

  /* say that we are sending the data */
  snprintf(buf, sizeof(buf), "DATA\n");
  if(g_output_stream_write(ostr, buf, strlen(buf), NULL, error) < 0)
    free_all();
  valid_smtp("354");

  /* send the email message itself */
  snprintf(buf, sizeof(buf), "FROM: %s\nTO: %s\n", from, to);
  if(g_output_stream_write(ostr, buf, strlen(buf), NULL, error) < 0)
    free_all();
  strftime(buf, sizeof(buf), "Date: %c\n", timeinfo);
  if(g_output_stream_write(ostr, buf, strlen(buf), NULL, error) < 0)
    free_all();
  snprintf(buf, sizeof(buf), "Subject: %s\n%s\n.\n", subject, msg);
  if(g_output_stream_write(ostr, buf, strlen(buf), NULL, error) < 0)
    free_all();
  valid_smtp("250");

  /* close the connection */
  snprintf(buf, sizeof(buf), "QUIT\n");
  if(g_output_stream_write(ostr, buf, strlen(buf), NULL, error) < 0)
    free_all();
  valid_smtp("221");

  g_object_unref(conn);
}

#undef free_all
