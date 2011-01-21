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
#include <stdlib.h>
#include <stdio.h>

/* unix includes */
#include <unistd.h>
#include <stdio.h>
#include <string.h>

/* glib inclused */
#include <glib.h>
#include <gio/gio.h>

int main(int argc, char** argv)
{
  GSocketConnection* host_conn;
  GSocketClient*     client;
  int port_number = -1;
  int c;
  char host[FILENAME_MAX];

  /* initialize memory */
  host_conn = NULL;
  client = NULL;
  memset(host, '\0', sizeof(host));

  /* parse command line options */
  while((c = getopt(argc, argv, "c:p:h")) != -1)
  {
    switch(c)
    {
      case 'c':
        strncpy(host, optarg, sizeof(host));
        break;
      case 'p':
        port_number = atoi(optarg);
        break;
      case 'h': default:
        // TODO usage
        return 0;
    }
  }

  /* check the scheduler shared memory for port number */
  if(port_number != 0)
  {
    // TODO
  }

  /* open the connection to the scheduler */
  g_type_init();
  client = g_socket_client_new();

  if(host[0])
    host_conn = g_socket_client_connect_to_host(client, host, port_number, NULL, NULL);
  else
    host_conn = g_socket_client_connect_to_host(client, "127.0.0.1", port_number, NULL, NULL);

  sleep(1);

  //g_io_stream_close((GIOStream)host_conn, NULL, NULL);
  return 0;
}
