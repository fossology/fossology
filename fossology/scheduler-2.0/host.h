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

#ifndef HOST_H_INCLUDE
#define HOST_H_INCLUDE

/* std includes */
#include <stdio.h>

#define HOSTNAME_MAX 255

/**
 * TODO
 */
typedef struct {
  char name[HOSTNAME_MAX + 1];      ///< the name of the host, used to store host internally to scheduler
  char address[HOSTNAME_MAX + 1];   ///< the address of the host, used by ssh when starting a new agent
  char agent_dir[FILENAME_MAX];     ///< the location on the host machine where the executables are
}* host;

#endif /* HOST_H_INCLUDE */
