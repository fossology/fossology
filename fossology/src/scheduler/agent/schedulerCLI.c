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

#define P_WIDTH 30
#define F_WIDTH 10

#ifndef FOSS_CONF
#define FOSS_CONF ".fossology.conf"
#endif

#define vprintf(...) if(verbose) printf(__VA_ARGS__);

int s;          ///< the socket that the CLI will use to communicate
int verbose;    ///< the verbose flag for the cli
PGconn* db;     ///< connection to the fossology database

/* ************************************************************************** */
/* **** utility functions *************************************************** */
/* ************************************************************************** */

void print_sql_table(PGresult* db_result)
{
  char sepl[1024];
  char line[1024];
  char temp[32];
  char* curr;
  int i, j, n_fields, n_tuples;
  int* field_w;

  n_fields = PQnfields(db_result);
  n_tuples = PQntuples(db_result);
  field_w = g_new0(int, n_fields);
  sprintf(line, "results: [%d,%d]", n_tuples, n_fields);
  printf("%s\n", line);
  strcpy(line, "| ");
  strcpy(sepl, "+");
  for(i = 0; i < n_fields; i++)
  {
    curr = PQfname(db_result, i);
    field_w[i] = strlen(curr) < F_WIDTH ? F_WIDTH : strlen(curr);
    sprintf(temp, "%-*s", field_w[i], curr);
    strcat(line, temp);
    strcat(line, " | ");
    strcat(sepl, "-");
    for(j = 0; j < field_w[i]; j++)
      strcat(sepl, "-");
    strcat(sepl, "-+");
  }

  printf("%s\n%s\n%s\n", sepl, line, sepl);
  for(i = 0; i < n_tuples; i++)
  {
    strcpy(line, "| ");

    for(j = 0; j < n_fields; j++)
    {
      curr = PQgetvalue(db_result, i, j);
      if(strlen(curr) > field_w[j])
      {
        curr[field_w[j]    ] = '\0';
        curr[field_w[j] - 1] = '*';
        curr[field_w[j] - 2] = '*';
        //curr[field_w[j] - 3] = '*';
      }

      sprintf(temp, "%-*s", field_w[j], curr);
      strcat(line, temp);
      strcat(line, " | ");
    }

    printf("%s\n%s\n", line, sepl);
  }

  g_free(field_w);
}

void interface_usage()
{
  /* print cli usage */
  printf("FOSSology scheduler command line interface\n");
  printf("for all options any prefix will work when sending command\n");
  printf("+-------------------------------------------------------------------------------+\n");
  printf("|%*s:   EFFECT                                       |\n", P_WIDTH, "COMMAND [optional] <required>");
  printf("+-------------------------------------------------------------------------------+\n");
  printf("|%*s:   prints this usage statement                  |\n", P_WIDTH, "help");
  printf("|%*s:   close the connection to scheduler            |\n", P_WIDTH, "close");
  printf("|%*s:   shutdown the scheduler gracefully            |\n", P_WIDTH, "stop");
  printf("|%*s:   pauses a job indefinitely                    |\n", P_WIDTH, "pause <job id>");
  printf("|%*s:   reload the configuration information         |\n", P_WIDTH, "reload");
  printf("|%*s:   scheduler responds with status information   |\n", P_WIDTH, "status [job id]");
  printf("|%*s:   restart a paused job                         |\n", P_WIDTH, "restart <job id>");
  printf("|%*s:   query/change the scheduler/job verbosity     |\n", P_WIDTH, "verbose [job id] [level]");
  printf("|%*s:   causes the scheduler to check the job queue  |\n", P_WIDTH, "database");
  printf("+-------------------------------------------------------------------------------+\n");
  printf("|%*s:   goes into the schedule dialog                |\n", P_WIDTH, "get");
  printf("|%*s:   uploads a file and schedulers a set of jobs  |\n", P_WIDTH, "upload");
  printf("+-------------------------------------------------------------------------------+\n");
  fflush(stdout);
}

/* ************************************************************************** */
/* **** commands ************************************************************ */
/* ************************************************************************** */

void get_cmd()
{
  /* locals */
  PGresult* db_result;
  char* curr;
  char buffer[1024];
  char cmd[1024];

  memset(buffer, '\0', sizeof(buffer));
  memset(cmd,    '\0', sizeof(cmd));
  vprintf("Please enter the SQL select statement that you would like to run\n");
  vprintf("Multiline SQL can be achieved using the '\\' character\n");
  fflush(stdout);

  buffer[0] = '\\'; buffer[1] = '\n';
  while(buffer[strlen(buffer) - 2] == '\\')
  {
    vprintf("#: ");
    fflush(stdout);

    memset(buffer, '\0', sizeof(buffer));
    if(fgets(buffer, sizeof(buffer), stdin) == NULL)
    {
      fprintf(stderr, "ERROR: %s.%d: could not read SQL statement",
          __FILE__, __LINE__);
      fprintf(stderr, "ERROR: errno: %s\n", strerror(errno));
      return;
    }

    /* choosing how many meta characters are at the end of buffer and then */
    /* concatinates the result onto cmd                                    */
    strncat(cmd, buffer, strlen(buffer) -
        (buffer[strlen(buffer) - 2] == '\\' ? 2 : 1));
  }

  strcpy(buffer, cmd);
  for(curr = buffer; *curr; curr++) *curr = g_ascii_tolower(*curr);
  if(!g_str_has_prefix(buffer, "select") ||
      (strchr(cmd, ';') != cmd + strlen(cmd) - 1))
  {
    fprintf(stderr, "ERROR: Invalid SQL: %s\n", cmd);
    fprintf(stderr, "ERROR: SQL must be SELECT statement and end in ';'\n");
    return;
  }

  db_result = PQexec(db, cmd);
  if(PQresultStatus(db_result) != PGRES_TUPLES_OK)
  {
    fprintf(stderr, "ERROR %s.%d: fail to execute SQL command from CLI\n",
        __FILE__, __LINE__);
    fprintf(stderr, "ERROR postgresql error: %s\n", PQresultErrorMessage(db_result));
  }
  else if(verbose)
  {
    print_sql_table(db_result);
  }
  else
  {
    printf("[%d,%d]\n", PQntuples(db_result), PQnfields(db_result));
  }

  PQclear(db_result);
}

void upload_cmd()
{
  char file[FILENAME_MAX];
  char agts[1024];
  char path[1024];
  char exec_call[4096];

  /* get the file */
  memset(file, '\0', sizeof(file));
  vprintf("file: ");
  if(fgets(file, sizeof(file), stdin) == NULL) return;
  file[strlen(file) - 1] = '\0';

  /* get agents list */
  memset(agts, '\0', sizeof(agts));
  vprintf("agents: ");
  if(fgets(agts, sizeof(agts), stdin) == NULL) return;
  agts[strlen(agts) - 1] = '\0';

  /* get repo path for file */
  memset(path, '\0', sizeof(path));
  vprintf("repo path: ");
  if(fgets(path, sizeof(path), stdin) == NULL) return;
  path[strlen(path) - 1] = '\0';

  /* compile call to shell */
  snprintf(exec_call, sizeof(exec_call), "%s/cp2foss -f %s -q %s %s",
      CLI_DIR, path, agts, file);

  if(system(exec_call) != 0); // TODO
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
  int c, closing;             // flags and loop variables
  size_t bytes;               // variable to caputre return of read
  char host[FILENAME_MAX];    // string to hold the name of the host
  char buffer[1024];          // string buffer used to read
  FILE* istr;                 // file used for reading configuration
  char db_conf[FILENAME_MAX]; // the file to use for the database configuration
  GOptionContext* options;    // the command line options parser
  char* poss;                 // used to split incomming string on \n

  /* initialize memory */
  strcpy(host, "localhost");
  memset(buffer, '\0', sizeof(buffer));
  closing = 0;
  verbose = 0;

  /* set the fossology database config */
  snprintf(db_conf, FILENAME_MAX, "%s/%s", getenv("HOME"), FOSS_CONF);

  GOptionEntry entries[] =
  {
      {"conf",  'c', 0, G_OPTION_ARG_STRING, &db_conf,
          "Set the file that will be used for the database configuration"},
      {"host",  'h', 0, G_OPTION_ARG_STRING, &host,
          "Set the host that the scheduler is on"},
      {"port",  'p', 0, G_OPTION_ARG_INT,    &port_number,
          "Set the port that the scheduler is listening on"},
      {"quiet", 'q', 0, G_OPTION_ARG_NONE,   &verbose,
          "Cause the CLI to not print usage hints"},
      {NULL}
  };

  options = g_option_context_new("- command line tool for FOSSology scheduler");
  g_option_context_add_main_entries(options, entries, NULL);
  g_option_context_set_ignore_unknown_options(options, TRUE);
  g_option_context_parse(options, &argc, &argv, NULL);
  g_option_context_free(options);

  /* change the verbose to conform to quite option */
  verbose = !verbose;

  /* check the scheduler config for port number */
  if(port_number < 0)
  {
    snprintf(buffer, sizeof(buffer), "%s/fossology.conf", DEFAULT_SETUP);
    istr = fopen(buffer, "r");
    while(port_number < 0 && fgets(buffer, sizeof(buffer) - 1, istr) != NULL)
    {
      if(buffer[0] == '#' || buffer[0] == '\0') { }
      else if(strncmp(buffer, "port=", 5) == 0)
        port_number = atoi(buffer + 5);
    }
    memset(buffer, '\0', sizeof(buffer));
  }

  /* set up the connection to the database */
  setenv("FOSSDBCONF", db_conf, 1);
  if((db = fo_dbconnect()) == NULL)
    return -1;

  /* open the connection to the scheduler */
  s = socket(AF_INET, SOCK_STREAM, IPPROTO_TCP);

  host_info = gethostbyname(host);
  memcpy(&host_addr, host_info->h_addr, host_info->h_length);

  addr.sin_addr.s_addr = host_addr;
  addr.sin_port = htons(port_number);
  addr.sin_family = AF_INET;

  if(connect(s, (struct sockaddr*)&addr, sizeof(addr)) == -1)
  {
    fprintf(stderr, "ERROR: could not connect to host\n");
    fprintf(stderr, "ERROR: attempted to connect to \"%s:%d\"\n",
        host, port_number);
    return 0;
  }

  /* listen to the scheulder */
  if(verbose) interface_usage();
  while(!closing)
  {
    /* prepare for read */
    FD_ZERO(&fds);
    FD_SET(s, &fds);
    FD_SET(fileno(stdin), &fds);
    memset(buffer, '\0', sizeof(buffer));
    c = select(s + 1, &fds, NULL, NULL, NULL);

    /* check the socket */
    if(FD_ISSET(s, &fds))
    {
      memset(buffer, '\0', sizeof(buffer));
      bytes = read(s, buffer, sizeof(buffer));

      if(bytes == 0)
        closing = 1;

      poss = strtok(buffer, "\n");
      for(poss = strtok(buffer, "\n"); poss != NULL; poss = strtok(NULL, "\n"))
      {
        if(strncmp(poss, "CLOSE", 5) == 0)
          closing = 1;
        else if(strcmp(poss, "end") != 0)
          printf("%s\n", poss);
      }
      fflush(stdout);
    }

    /* check stdin */
    if(FD_ISSET(fileno(stdin), &fds))
    {
      bytes = read(fileno(stdin), buffer, sizeof(buffer));
      if(strcmp(buffer, "help\n") == 0)
      {
        interface_usage();
        continue;
      }

      if(strcmp(buffer, "get\n") == 0)
      {
        get_cmd();
        continue;
      }

      if(strcmp(buffer, "upload\n") == 0)
      {
        upload_cmd();
        continue;
      }

      bytes = write(s, buffer, strlen(buffer) - 1);
    }
  }

  PQfinish(db);
  return 0;
}
