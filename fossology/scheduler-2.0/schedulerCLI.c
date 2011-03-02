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
#include <schedulerCLI.h>

/* std library includes */
#include <stdio.h>
#include <stdlib.h>
#include <string.h>

/* unix includes */
#include <ctype.h>
#include <fcntl.h>
#include <sys/socket.h>
#include <sys/types.h>
#include <netinet/in.h>
#include <netdb.h>
#include <unistd.h>

#ifndef DEFAULT_SETUP
#define DEFAULT_SETUP ""
#endif

int s;        ///< the socket that the CLI will use to communicate
int verbose;  ///< the verbose flag for the cli

/* ************************************************************************** */
/* **** utility functions *************************************************** */
/* ************************************************************************** */

int network_write(void* buf, size_t count)
{
  /* locals */
  network_header header;

  /* send message size */
  header.bytes_following = count;
  if(write(s, &header, sizeof(network_header)) == 0)
  {
    fprintf(stderr, "ERROR writing to scheduler socket\n");
    return 0;
  }

  /* send the actual message */
  return write(s, buf, count);
}

/* ************************************************************************** */
/* **** main types ********************************************************** */
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

  /* initialize memory */
  strcpy(host, "localhost");
  memset(buffer, '\0', sizeof(buffer));
  closing = 0;
  verbose = 0;

  /* parse command line options */
  while((c = getopt(argc, argv, "c:p:vh")) != -1)
  {
    switch(c)
    {
      case 'c':
        strncpy(host, optarg, sizeof(host));
        break;
      case 'p':
        port_number = atoi(optarg);
        break;
      case 'v':
        verbose = 1;
        break;
      case 'h': default:
        // TODO usage
        return 0;
    }
  }

  /* check the scheduler config for port number */
  if(port_number < 0)
  {
    istr = fopen("Scheduler.conf", "r");
    while(port_number < 0 && fgets(buffer, sizeof(buffer) - 1, istr) != NULL)
    {
      if(buffer[0] == '#' || buffer[0] == '\0');
      else if(strncmp(buffer, "port=", 5) == 0)
        port_number = atoi(buffer + 5);
    }
    memset(buffer, '\0', sizeof(buffer));
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
    fprintf(stderr, "ERROR: could not connect to host\n");
    fprintf(stderr, "ERROR: attempted to connect to \"%s:%d\"\n", host, port_number);
    return 0;
  }

  /* listen to the scheulder */
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
      bytes = read(s, buffer, sizeof(buffer));

      if(strncmp(buffer, "CLOSE", 5) == 0)
        closing = 1;
      else
        printf("SCHEDULER: %s\n", buffer);
    }

    /* check stdin */
    if(FD_ISSET(fileno(stdin), &fds))
    {
      bytes = read(fileno(stdin), buffer, sizeof(buffer));
      bytes = network_write(buffer, strlen(buffer) - 1);
    }

    if(verbose) {
      printf("RECIEVED: %s\n", buffer);
    }
  }

  return 0;
}
