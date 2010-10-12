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

/* ************************************************************************** */
/* **** Data Types ********************************************************** */
/* ************************************************************************** */

/** internal structure for the agent. */
struct agent_internal {
  pthread_t thread;         ///< the thread that communicates with this agent
  int parent_to_child[2];   ///< pipe connected to child's stdin
  int child_to_parent[2];   ///< pipe connected to child's stdout

};

