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

/* std library includes */
#include <stdio.h>
#include <stdlib.h>
#include <string.h>

/* unix includes */
#include <ctype.h>
#include <errno.h>
#include <fcntl.h>
#include <pwd.h>
#include <sys/socket.h>
#include <sys/types.h>
#include <netinet/in.h>
#include <netdb.h>
#include <unistd.h>

/* other library includes */
#include <libfossology.h>
#include <glib.h>

#define P_WIDTH 27
#define F_WIDTH 10

#define vprintf(...) if(verbose) printf(__VA_ARGS__);

int response = 1; ///< is a response expected from the scheduler
int s;            ///< the socket that the CLI will use to communicate
int verbose;      ///< the verbose flag for the cli
fo_conf* conf;    ///< the loaded configuration data

/* ************************************************************************** */
/* **** utility functions *************************************************** */
/* ************************************************************************** */

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
  struct addrinfo* servs, * curr = NULL;

  memset(&hints, 0, sizeof(hints));
  hints.ai_family   = AF_UNSPEC;
  hints.ai_socktype = SOCK_STREAM;
  if(getaddrinfo(host, port, &hints, &servs) == -1)
  {
    fprintf(stderr, "ERROR: %s.%d: unable to connect to %s port: %s\n",
        __FILE__, __LINE__, host, port);
    fprintf(stderr, "ERROR: errno: %s\n", strerror(errno));
    return -1;
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
    return -1;
  }

  freeaddrinfo(servs);
  return fd;
}

/**
 * @brief performs the actions necessary to receive from the scheduler.
 *
 * @param s       the socket that is connected to the scheduler
 * @param buffer  buffer that is used to store messages from the scheduler
 * @param max     the capacity of the buffer
 * @param end     used to determine when this function should return
 * @return
 */
uint8_t receive(int s, char* buffer, size_t max, uint8_t end)
{
  size_t bytes = 0;
  uint8_t closing = 0;
  char* poss;

  do
  {
    /* start by clearing the buffer */
    memset(buffer, '\0', max);
    bytes = 0;

    /* read from the socket */
    do
    {
      bytes = read(s, buffer + bytes, max - bytes);

      if(bytes == 0)
      {
        printf("ERROR: connection to scheduler closed\nERROR: closing cli\n");
        closing = 1;
      }

      bytes = strlen(buffer);
    } while(!closing && buffer[bytes - 1] != '\n');

    /* interpret the results */
    for(poss = strtok(buffer, "\n"); !closing && poss != NULL;
        poss = strtok(NULL, "\n"))
    {
      if(strncmp(poss, "received", 8) == 0)
      {
        if(response)
          printf("Command received\n");
      }
      else if(strncmp(poss, "CLOSE", 5) == 0)
      {
        closing = 1;
      }
      else if(strcmp(poss, "end") != 0)
      {
        printf("%s\n", poss);
      }
      else
      {
        end = 0;
      }
    }

    fflush(stdout);
  } while(end);

  return closing;
}

void interface_usage()
{
  printf("FOSSology scheduler command line interface\n");
  printf("+-----------------------------------------------------------------------------+\n");
  printf("|%*s:   EFFECT                                        |\n", P_WIDTH, "CMD [optional] <required>");
  printf("+-----------------------------------------------------------------------------+\n");
  printf("|%*s:   prints this usage statement                   |\n", P_WIDTH, "help");
  printf("|%*s:   close the connection and exit cli             |\n", P_WIDTH, "close");
  printf("|%*s:   shutdown will wait for agents be stopping     |\n", P_WIDTH, "stop");
  printf("|%*s:   shutdown will shutdown immediately            |\n", P_WIDTH, "die");
  printf("|%*s:   get load information for host machines        |\n", P_WIDTH, "load");
  printf("|%*s:   kills a currently running job (ungraceful)    |\n", P_WIDTH, "kill <jq_pk> <\"message\">");
  printf("|%*s:   pauses a job indefinitely                     |\n", P_WIDTH, "pause <jq_pk>");
  printf("|%*s:   reload the configuration information          |\n", P_WIDTH, "reload");
  printf("|%*s:   prints a list of valid agents                 |\n", P_WIDTH, "agents");
  printf("|%*s:   scheduler responds with status information    |\n", P_WIDTH, "status [jq_pk]");
  printf("|%*s:   restart a paused job                          |\n", P_WIDTH, "restart <jq_pk>");
  printf("|%*s:   query/change the scheduler/job verbosity      |\n", P_WIDTH, "verbose [jq_pk] [level]");
  printf("|%*s:   change priority for job that this jq_pk is in |\n", P_WIDTH, "priority <jq_pk> <level>");
  printf("|%*s:   causes the scheduler to check the job queue   |\n", P_WIDTH, "database");
  printf("+-----------------------------------------------------------------------------+\n");
  printf("|%*s:   goes into the schedule dialog                 |\n", P_WIDTH, "sql");
  printf("|%*s:   uploads a file and schedulers a set of jobs   |\n", P_WIDTH, "upload");
  printf("+-----------------------------------------------------------------------------+\n");
  fflush(stdout);
}

/* ************************************************************************** */
/* **** main **************************************************************** */
/* ************************************************************************** */

int main(int argc, char** argv)
{
  /* local variables */
  fd_set fds;                 // file descriptor set used in select statement
  int closing;                // flags and loop variables
  size_t bytes;               // variable to capture return of read
  char* host;                 // string to hold the name of the host
  char* port;                 // string port number to connect to
  char buffer[1024];          // string buffer used to read
  char* config;               // FOSSology configuration directory
  GOptionContext* options;    // the command line options parser
  GError* error = NULL;

  /* command bool and info */
  uint8_t c_die      = 0;
  uint8_t c_stop     = 0;
  uint8_t c_load     = 0;
  uint8_t c_pause    = 0;
  uint8_t c_reload   = 0;
  uint8_t c_status   = 0;
  uint8_t c_agents   = 0;
  uint8_t c_restart  = 0;
  uint8_t c_verbose  = 0;
  uint8_t c_database = 0;

  /* initialize memory */
  host = NULL;
  port = NULL;
  config = DEFAULT_SETUP;
  memset(buffer, '\0', sizeof(buffer));
  closing = 0;
  verbose = 0;

  GOptionEntry entries[] =
  {
      {"config",   'c', 0, G_OPTION_ARG_STRING, &config,
          "Set the directory for the system configuration", "string"},
      {"host",     'H', 0, G_OPTION_ARG_STRING, &host,
          "Set the host that the scheduler is on", "string"},
      {"port",     'p', 0, G_OPTION_ARG_STRING, &port,
          "Set the port that the scheduler is listening on", "integer"},
      {"quiet",    'q', 0, G_OPTION_ARG_NONE,   &verbose,
          "Cause the CLI to not print usage hints", NULL},
      {"load",     'l', 0, G_OPTION_ARG_NONE,   &c_load,
          "CLI will send a load command and close"},
      {"agents",   'a', 0, G_OPTION_ARG_NONE,   &c_agents,
          "CLI will send an agents command and close"},
      {"status",   'S', 0, G_OPTION_ARG_NONE,   &c_status,
          "CLI will send a status command and close"},
      {"stop",     's', 0, G_OPTION_ARG_NONE,   &c_stop,
          "CLI will send stop command and close", NULL},
      {"die",      'D', 0, G_OPTION_ARG_NONE,   &c_die,
          "CLI will send a die command and close"},
      {"pause",    'P', 0, G_OPTION_ARG_INT,    &c_pause,
          "CLI will send a pause command and close", "integer"},
      {"reload",   'r', 0, G_OPTION_ARG_NONE,   &c_reload,
          "CLI will send a reload command and close", NULL},
      {"restart",  'R', 0, G_OPTION_ARG_INT,    &c_restart,
          "CLI will send a restart command and close", "integer"},
      {"verbose",  'v', 0, G_OPTION_ARG_INT,    &c_verbose,
          "CLI will change the scheduler's verbose level", "integer"},
      {"database", 'd', 0, G_OPTION_ARG_NONE,   &c_database,
          "CLI will send a database command to scheduler", NULL},
      {NULL}
  };

  options = g_option_context_new("- command line tool for FOSSology scheduler");
  g_option_context_add_main_entries(options, entries, NULL);
  g_option_context_set_ignore_unknown_options(options, FALSE);
  g_option_context_parse(options, &argc, &argv, &error);

  if(error)
  {
    config = g_option_context_get_help(options, FALSE, NULL);
    fprintf(stderr, "ERROR: %s\n%s", error->message, config);
    g_free(config);
    return -1;
  }

  g_option_context_free(options);

  /* set the basic configuration */
  /* change the verbose to conform to quite option */
  verbose = !verbose;

  snprintf(buffer, sizeof(buffer), "%s/fossology.conf", config);
  conf = fo_config_load(buffer, &error);
  if(error)
  {
    fprintf(stderr, "ERROR: %s.%d: error loading config: %s\n",
        __FILE__, __LINE__, error->message);
    return -1;
  }

  /* check the scheduler config for port number */
  if(port == NULL)
    port = fo_config_get(conf, "FOSSOLOGY", "port", &error);
  if(!error && host == NULL)
    host = fo_config_get(conf, "FOSSOLOGY", "address", &error);

  /* open the connection to the scheduler */
  if((s = socket_connect(host, port)) < 0)
    return -1;

  /* check specific command instructions */
  if(c_die || c_stop || c_load || c_pause || c_reload || c_status || c_agents
      || c_restart || c_verbose || c_database)
  {
    response = 0;

    /* simple no parameter commands */
    if(c_reload)
      bytes = write(s, "reload", 6);
    if(c_database)
      bytes = write(s, "database", 8);
    if(c_stop)
      bytes = write(s, "stop", 4);
    if(c_die)
      bytes = write(s, "die", 3);

    /* simple commands that require a parameter */
    if(c_verbose)
    {
      snprintf(buffer, sizeof(buffer) - 1, "verbose %d", c_verbose);
      bytes = write(s, buffer, strlen(buffer));
    }

    if(c_pause)
    {
      snprintf(buffer, sizeof(buffer) - 1, "pause %d", c_pause);
      bytes = write(s, buffer, strlen(buffer));
    }

    if(c_restart)
    {
      snprintf(buffer, sizeof(buffer) - 1, "restart %d", c_restart);
      bytes = write(s, buffer, strlen(buffer));
    }

    /* requests for information */
    if(c_load)
    {
      bytes = write(s, "load", 4);
      receive(s, buffer, sizeof(buffer), TRUE);
    }

    if(c_status)
    {
      bytes = write(s, "status", 6);
      receive(s, buffer, sizeof(buffer), TRUE);
    }

    if(c_agents)
    {
      bytes = write(s, "agents", 6);
      receive(s, buffer, sizeof(buffer), TRUE);
    }

    return 0;
  }

  /* listen to the scheulder */
  if(verbose)
    interface_usage();
  while(!closing)
  {
    /* prepare for read */
    FD_ZERO(&fds);
    FD_SET(s, &fds);
    FD_SET(fileno(stdin), &fds);
    memset(buffer, '\0', sizeof(buffer));
    select(s + 1, &fds, NULL, NULL, NULL);

    /* check the socket */
    if(FD_ISSET(s, &fds))
      closing = receive(s, buffer, sizeof(buffer), FALSE);

    /* check stdin */
    if(FD_ISSET(fileno(stdin), &fds))
    {
      if(read(fileno(stdin), buffer, sizeof(buffer)) == 0)
        break;

      if(strcmp(buffer, "help\n") == 0)
      {
        interface_usage();
        continue;
      }

      response = (strncmp(buffer, "agents",  6) == 0 ||
                  strncmp(buffer, "status",  6) == 0 ||
                  strcmp (buffer, "verbose\n" ) == 0 ||
                  strcmp (buffer, "load\n"    ) == 0) ?
                      FALSE : TRUE;

      if((bytes = write(s, buffer, strlen(buffer) - 1)) != strlen(buffer) - 1)
      {
        printf("ERROR: couldn't write %ld bytes to socket\n", bytes);
        closing = 1;
      }
    }
  }

  return 0;
}
