/* **************************************************************
Copyright (C) 2011 Hewlett-Packard Development Company, L.P.

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
#include <host.h>
#include <logging.h>
#include <scheduler.h>

/* other library includes */
#include <glib.h>

/* ************************************************************************** */
/* **** Locals ************************************************************** */
/* ************************************************************************** */

/**
 * TODO
 */
struct host_internal {
  char* name;         ///< the name of the host, used to store host internally to scheduler
  char* address;      ///< the address of the host, used by ssh when starting a new agent
  char* agent_dir;    ///< the location on the host machine where the executables are
  int max;            ///< the max number of agents that can run on this host
  int running;        ///< the number of agents currently running on this host
};

GTree* host_list = NULL;

/**
 * used strictly by the find_host function. This is used so that two different parameter
 * can be passed to the find_host even though it is a GTraverFunction
 */
struct find_struct {
    host h;   ///< a pointer to the host so that the host can be set
    int req;  ///< the number of agents that need to be run on the host
};

/**
 * TODO
 *
 * @param host_name
 * @param h
 * @param fs
 * @return
 */
int find_host(char* host_name, host h, struct find_struct* fs)
{
  if(h->max - h->running > fs->req)
  {
    fs->h = h;
    return 1;
  }

  return 0;
}

/**
 * TODO
 *
 * @param host_name
 * @param h
 * @param func
 * @return
 */
int for_host(char* host_name, host h, void(*func)(host))
{
  func(h);
  return 0;
}

/* ************************************************************************** */
/* **** Contructor Destructor *********************************************** */
/* ************************************************************************** */

/**
 * TODO
 */
void host_list_clean()
{
  if(host_list != NULL)
  {
    g_tree_destroy(host_list);
    host_list = NULL;
  }
}

/**
 * TODO
 *
 * @param name
 * @param address
 * @param agent_dir
 * @param max
 */
void host_init(char* name, char* address, char* agent_dir, int max)
{
  host h = g_new0(struct host_internal, 1);

  h->name = g_strdup(name);
  h->address = g_strdup(address);
  h->agent_dir = g_strdup(agent_dir);
  h->max = max;
  h->running = 0;

  if(host_list == NULL)
      host_list = g_tree_new_full(string_compare, NULL, NULL, (GDestroyNotify)host_destroy);
  g_tree_insert(host_list, h->name, h);
}

/**
 * TODO
 *
 * @param h
 */
void host_destroy(host h)
{
  g_free(h->name);
  g_free(h->address);
  g_free(h->agent_dir);
  g_free(h);
}

/* ************************************************************************** */
/* **** Functions and events ************************************************ */
/* ************************************************************************** */

/**
 * Gets the name associated with the given host
 *
 * @param h the host to get the address for
 * @return the name of the host
 */
char* host_name(host h)
{
  return h->name;
}

/**
 * Gets the address associated with the given host
 *
 * @param h the host to get the address for
 * @return the address
 */
char* host_address(host h)
{
  return h->address;
}

/**
 * Gets the directory that agents will be in on the host
 *
 * @param h the host to get the directory for
 * @return the location of the agents on the host
 */
char* host_agent_dir(host h)
{
  return h->agent_dir;
}

/**
 * TODO
 *
 * @param h
 */
void host_increase_load(host h)
{
  h->running++;
  VERBOSE3("HOST[%s] load increased to %d\n", h->name, h->running);
}

/**
 * TODO
 *
 * @param h
 */
void host_decrease_load(host h)
{
  h->running--;
  VERBOSE3("HOST[%s] load decreased to %d\n", h->name, h->running);
}

/**
 * Gets a host for which there are at least num agents available to start
 * new agents on.
 *
 * @param num the number of agents to start on the host
 * @return the host with that number of available slots, NULL if none exist
 */
host get_host(int num)
{
  struct find_struct fs;
  fs.h = NULL;
  fs.req = num;
  g_tree_foreach(host_list, (GTraverseFunc)find_host, &fs);
  fs.h->running++;
  return fs.h;
}

/**
 * Gets a host by name, This is provided if a job needs to be run on a specific
 * host instead of on any host. This is done when the location of the files is
 * to be analyzed is on the host in question.
 *
 * @param name the name of the host to get
 * @return the host named
 */
host name_host(char* name)
{
  return g_tree_lookup(host_list, name);
}

/**
 * Calls the given function, passing each host as an argument to the function
 *
 * @param callback the function to call on every host
 */
void for_each_host(void(*callback)(host))
{
  g_tree_foreach(host_list, (GTraverseFunc)for_host, callback);
}

/**
 * Gets the number of hosts that were in the configuration data. This is needed
 * because there must be at least one host to run the agents on.
 *
 * @return the number of hosts read from the config files
 */
int num_hosts()
{
  return g_tree_nnodes(host_list);
}
