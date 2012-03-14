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

/* ************************************************************************** */
/* **** Locals ************************************************************** */
/* ************************************************************************** */

GTree* host_list = NULL;
GList* host_queue = NULL;

/**
 * allows a particular function to be called for every host. This is currently
 * used during the self test of every type of agent.
 *
 * @param host_name unused
 * @param h the host to pass into the function
 * @param func the function to call for every host
 * @return always return 0 so that it happens for all hosts
 */
int for_host(char* host_name, host h, void(*func)(host))
{
  func(h);
  return 0;
}

/**
 * GTraversFunction that allows the information for all hosts to be printed
 *
 * @param host_name  the string name of the host
 * @param h          the host struct paired with the name
 * @param ostr       the output stream that the info will be printed to
 * @return 0 to cause the traversal to continue
 */
int print_host_all(char* host_name, host h, GOutputStream* ostr)
{
  host_print(h, ostr);
  return 0;
}

/* ************************************************************************** */
/* **** Contructor Destructor *********************************************** */
/* ************************************************************************** */

/**
 * Create the host list. The host list should be created so that it destroys
 * any hosts when it is cleaned up.
 */
void host_list_init()
{
  host_list = g_tree_new_full(string_compare, NULL, NULL,
      (GDestroyNotify)host_destroy);
  host_queue = NULL;
}

/**
 * removes all hosts from the host list, leaving a clean copy.
 */
void host_list_clean()
{
  if(host_list != NULL)
  {
    g_tree_destroy(host_list);
    g_list_free(host_queue);
    host_list_init();
  }
}

/**
 * creates a new host, and adds it to the host list.
 *
 * @param name the name of the host
 * @param address address that can be passed to ssh when starting agent on host
 * @param agent_dir the location of agent binaries on the host
 * @param max the max number of agent that can run on this host
 */
void host_init(char* name, char* address, char* agent_dir, int max)
{
  host h = g_new0(struct host_internal, 1);

  h->name = g_strdup(name);
  h->address = g_strdup(address);
  h->agent_dir = g_strdup(agent_dir);
  h->max = max;
  h->running = 0;

  g_tree_insert(host_list, h->name, h);
  host_queue = g_list_append(host_queue, h);
}

/**
 * frees any memory associated with the host stucture
 *
 * @param h the host to free the memory for.
 */
void host_destroy(host h)
{
  host_queue = g_list_remove(host_queue, h);

  g_free(h->name);
  g_free(h->address);
  g_free(h->agent_dir);
  g_free(h);
}

/* ************************************************************************** */
/* **** Functions and events ************************************************ */
/* ************************************************************************** */

/**
 * Increase the number of running agents on a host by 1
 *
 * @param h the relevant host
 */
void host_increase_load(host h)
{
  h->running++;
  V_HOST("HOST[%s] load increased to %d\n", h->name, h->running);
}

/**
 * Decrease the number of running agents on a hsot by 1
 *
 * @param h the relevant host
 */
void host_decrease_load(host h)
{
  h->running--;
  V_HOST("HOST[%s] load decreased to %d\n", h->name, h->running);
}

/**
 * Prints the information about a host to the output stream
 *
 * @param h     the relevant host
 * @param ostr  the output stream to write to
 */
void host_print(host h, GOutputStream* ostr)
{
  char* buf;

  buf = g_strdup_printf("host:%s address:%s max:%d running:%d\n",
      h->name, h->address, h->max, h->running);
  g_output_stream_write(ostr, buf, strlen(buf), NULL, NULL);

  g_free(buf);
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
  GList* curr = NULL;
  host ret    = NULL;

  for(curr = host_queue; curr != NULL; curr = curr->next)
  {
    ret = curr->data;
    V_HOST("HOST[%s]: max = %d, running = %d\n", ret->name, ret->max, ret->running);
    if(ret->max - ret->running > num)
      break;
  }

  if(curr == NULL)
    return NULL;

  host_queue = g_list_remove(host_queue, ret);
  host_queue = g_list_append(host_queue, ret);

  return ret;
}

/**
 * TODO
 *
 * @param name
 * @return
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
 *
 *
 * @param ostr
 */
void print_host_load(GOutputStream* ostr)
{
  g_tree_foreach(host_list, (GTraverseFunc)print_host_all, ostr);
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
