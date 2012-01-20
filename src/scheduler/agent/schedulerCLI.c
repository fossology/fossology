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

int s;          ///< the socket that the CLI will use to communicate
int verbose;    ///< the verbose flag for the cli
fo_conf* conf;  ///< the loaded configuration data

/* ************************************************************************** */
/* **** utility functions *************************************************** */
/* ************************************************************************** */

void interface_usage()
{
  /* print cli usage */
  printf("FOSSology scheduler command line interface\n");
  printf("+----------------------------------------------------------------------------+\n");
  printf("|%*s:   EFFECT                                       |\n", P_WIDTH, "CMD [optional] <required>");
  printf("+----------------------------------------------------------------------------+\n");
  printf("|%*s:   prints this usage statement                  |\n", P_WIDTH, "help");
  printf("|%*s:   close the connection and exit cli            |\n", P_WIDTH, "close");
  printf("|%*s:   shutdown the scheduler gracefully            |\n", P_WIDTH, "stop");
  printf("|%*s:   get load information for host machines       |\n", P_WIDTH, "load");
  printf("|%*s:   kills a currently running job (ungraceful)   |\n", P_WIDTH, "kill <job id> <\"message\">");
  printf("|%*s:   pauses a job indefinitely                    |\n", P_WIDTH, "pause <job id>");
  printf("|%*s:   reload the configuration information         |\n", P_WIDTH, "reload");
  printf("|%*s:   prints a list of valid agents                |\n", P_WIDTH, "agents");
  printf("|%*s:   scheduler responds with status information   |\n", P_WIDTH, "status [job id]");
  printf("|%*s:   restart a paused job                         |\n", P_WIDTH, "restart <job id>");
  printf("|%*s:   query/change the scheduler/job verbosity     |\n", P_WIDTH, "verbose [job id] [level]");
  printf("|%*s:   change the priority of a particular job      |\n", P_WIDTH, "priority <job id> <level>");
  printf("|%*s:   causes the scheduler to check the job queue  |\n", P_WIDTH, "database");
  printf("+----------------------------------------------------------------------------+\n");
  printf("|%*s:   goes into the schedule dialog                |\n", P_WIDTH, "sql");
  printf("|%*s:   uploads a file and schedulers a set of jobs  |\n", P_WIDTH, "upload");
  printf("+----------------------------------------------------------------------------+\n");
  fflush(stdout);
}

/* ************************************************************************** */
/* **** main **************************************************************** */
/* ************************************************************************** */

int main(int argc, char** argv)
{
  /* local variables */
  struct sockaddr_in addr;    // used when creating socket to scheduler
  struct hostent* host_info;  // information used to connect to correct host
  fd_set fds;                 // file descriptor set used in select statement
  int port_number = -1;       // the port that the CLI will connect on
  long host_addr;             // the address of the host
  int closing;                // flags and loop variables
  int response = 1;           // flag to indicate if a response is expected
  size_t bytes;               // variable to capture return of read
  char* host;                 // string to hold the name of the host
  char buffer[1024];          // string buffer used to read
  char* config;               // FOSSology configuration directory
  GOptionContext* options;    // the command line options parser
  char* poss;                 // used to split incoming string on '\n'
  GError* error = NULL;

  /* command bool and info */
  int c_stop     = 0;
  int c_pause    = 0;
  int c_reload   = 0;
  int c_restart  = 0;
  int c_verbose  = 0;
  int c_database = 0;

  /* initialize memory */
  host = "localhost";
  config = DEFAULT_SETUP;
  memset(buffer, '\0', sizeof(buffer));
  closing = 0;
  verbose = 0;

  GOptionEntry entries[] =
  {
      {"config",   'c', 0, G_OPTION_ARG_STRING, config,
          "Set the directory for the system configuration", "string"},
      {"host",     'H', 0, G_OPTION_ARG_STRING, host,
          "Set the host that the scheduler is on", "string"},
      {"port",     'p', 0, G_OPTION_ARG_INT,    &port_number,
          "Set the port that the scheduler is listening on", "integer"},
      {"quiet",    'q', 0, G_OPTION_ARG_NONE,   &verbose,
          "Cause the CLI to not print usage hints", NULL},
      {"stop",     's', 0, G_OPTION_ARG_NONE,   &c_stop,
          "CLI will send stop command and close", NULL},
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
  g_option_context_free(options);

  if(error)
  {
    fprintf(stderr, "ERROR %s.%d: error parsing command line options: %s\n",
        __FILE__, __LINE__, error->message);
    return -1;
  }
  /* set the basic configuration */
  /* change the verbose to conform to quite option */
  verbose = !verbose;

  snprintf(buffer, sizeof(buffer), "%s/fossology.conf", config);
  conf = fo_config_load(buffer, &error);
  if(error)
  {
    fprintf(stderr, "ERROR %s.%d: error loading config: %s\n",
        __FILE__, __LINE__, error->message);
    return -1;
  }

  /* check the scheduler config for port number */
  if(port_number < 0)
  {
    port_number = atoi(fo_config_get(conf, "FOSSOLOGY", "port", &error));
  }

  /* open the connection to the scheduler */
  s = socket(AF_INET, SOCK_STREAM, IPPROTO_TCP);

  host_info = gethostbyname(host);
  memcpy(&host_addr, host_info->h_addr, host_info->h_length);

  addr.sin_addr.s_addr = host_addr;
  addr.sin_port = htons(port_number);
  addr.sin_family = AF_INET;

  if(connect(s, (struct sockaddr*)&addr, sizeof(addr)) == -1)
  {
    fprintf(stderr, "ERROR: Could not connect to scheduler at %s:%d.  Is the scheduler running?\n", host, port_number);
    return 0;
  }

  /* check specific command instructions */
  if(c_stop || c_pause || c_reload || c_restart || c_verbose || c_database)
  {
    if(c_reload)
      bytes = write(s, "reload", 6);
    if(c_database)
      bytes = write(s, "database", 8);
    if(c_stop)
      bytes = write(s, "stop", 4);

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
    {
      memset(buffer, '\0', sizeof(buffer));
      bytes = 0;

      do
      {
        bytes = read(s, buffer + bytes, sizeof(buffer) - bytes);

        if(bytes == 0)
          closing = 1;
        bytes = strlen(buffer);
      } while(buffer[bytes - 1] != '\n');

      for(poss = strtok(buffer, "\n"); poss != NULL; poss = strtok(NULL, "\n"))
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
      }
      fflush(stdout);
    }

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

      bytes = write(s, buffer, strlen(buffer) - 1);
    }
  }

  return 0;
}
