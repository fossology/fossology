/*
 SPDX-FileCopyrightText: © 2010, 2011, 2012 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * \file
 * \brief Host related operations
 */

/* local includes */
#include <host.h>
#include <logging.h>
#include <scheduler.h>

/* ************************************************************************** */
/* **** Locals ************************************************************** */
/* ************************************************************************** */

/**
 * @brief GTraversFunction that allows the information for all hosts to be printed
 *
 * @param host_name  the string name of the host
 * @param host       the host struct paired with the name
 * @param ostr       the output stream that the info will be printed to
 * @return 0 to cause the traversal to continue
 * @sa host_print()
 */
static int print_host_all(gchar* host_name, host_t* host, GOutputStream* ostr)
{
  host_print(host, ostr);
  return 0;
}

/* ************************************************************************** */
/* **** Contructor Destructor *********************************************** */
/* ************************************************************************** */

/**
 * @brief Creates a new host, and adds it to the host list.
 *
 * @param name        The name of the host
 * @param address     Address that can be passed to ssh when starting agent on host
 * @param agent_dir   The location of agent binaries on the host
 * @param max         The max number of agent that can run on this host
 * @returns New host
 */
host_t* host_init(char* name, char* address, char* agent_dir, int max)
{
  return host_init_with_caps(name, address, agent_dir, max, NULL);
}

/**
 * @brief Creates a new host with a specific agent capability list.
 *
 * @param name        The name of the host
 * @param address     Address that can be passed to ssh when starting agent on host
 * @param agent_dir   The location of agent binaries on the host
 * @param max         The max number of agents that can run on this host
 * @param agent_caps  List of agent names this host can run (NULL = accept all)
 * @returns New host
 */
host_t* host_init_with_caps(char* name, char* address, char* agent_dir,
                            int max, GList* agent_caps)
{
  host_t* host = g_new0(host_t, 1);

  host->name = g_strdup(name);
  host->address = g_strdup(address);
  host->agent_dir = g_strdup(agent_dir);
  host->max = max;
  host->running = 0;
  host->agent_caps = agent_caps;

  return host;
}

/**
 * @brief Frees and uninitializes any memory associated with the host struct
 *
 * @param host The host to free the memory for.
 */
void host_destroy(host_t* host)
{
  g_free(host->name);
  g_free(host->address);
  g_free(host->agent_dir);

  if(host->agent_caps)
    g_list_free_full(host->agent_caps, g_free);

  host->name = NULL;
  host->address = NULL;
  host->agent_dir = NULL;
  host->agent_caps = NULL;
  host->max = 0;
  host->running = 0;

  g_free(host);
}

/* ************************************************************************** */
/* **** Functions and events ************************************************ */
/* ************************************************************************** */

/**
 * @brief Inserts a new host into the scheduler structure.
 *
 * @param host       the host that will be added to the scheduler
 * @param scheduler  the relevant scheduler struct
 */
void host_insert(host_t* host, scheduler_t* scheduler)
{
  g_tree_insert(scheduler->host_list, host->name, host);
  scheduler->host_queue = g_list_append(scheduler->host_queue, host);
}

/**
 * @brief Increase the number of running agents on a host by 1
 *
 * @param host The relevant host
 */
void host_increase_load(host_t* host)
{
  host->running++;
  V_HOST("HOST[%s] load increased to %d\n", host->name, host->running);
}

/**
 * @brief Decrease the number of running agents on a host by 1
 *
 * @param host the relevant host
 */
void host_decrease_load(host_t* host)
{
  host->running--;
  V_HOST("HOST[%s] load decreased to %d\n", host->name, host->running);
}

/**
 * @brief Prints the information about a host to the output stream
 *
 * @param host  the relevant host
 * @param ostr  the output stream to write to
 */
void host_print(host_t* host, GOutputStream* ostr)
{
  char* buf;

  buf = g_strdup_printf("host:%s address:%s max:%d running:%d\n",
      host->name, host->address, host->max, host->running);
  g_output_stream_write(ostr, buf, strlen(buf), NULL, NULL);

  g_free(buf);
}

/**
 * @brief Checks if a host supports running a specific agent.
 *
 * If the host has no agent_caps (NULL), it accepts all agents.
 * Otherwise, returns TRUE only if the agent name appears in the caps list.
 *
 * @param host        The host to check
 * @param agent_name  The name of the agent to check for
 * @return TRUE if the host supports the agent, FALSE otherwise
 */
gboolean host_supports_agent(host_t* host, const char* agent_name)
{
  GList* iter;

  if(host == NULL || agent_name == NULL)
    return FALSE;

  /* NULL agent_caps means the host accepts all agents */
  if(host->agent_caps == NULL)
    return TRUE;

  for(iter = host->agent_caps; iter != NULL; iter = iter->next)
  {
    if(strcmp((char*)iter->data, agent_name) == 0)
      return TRUE;
  }

  return FALSE;
}

/**
 * Gets a host for which there are at least num agents available to start
 * new agents on, and which supports the given agent type.
 *
 * @param queue       GList of available hosts
 * @param num         the number of agents to start on the host
 * @param agent_name  the agent type to check capability for (NULL = any agent)
 * @return the host with that number of available slots, NULL if none exist
 */
host_t* get_host(GList** queue, uint8_t num, const char* agent_name)
{
  GList*  host_queue = *queue;
  GList*  curr       = NULL;
  host_t* ret        = NULL;

  for(curr = host_queue; curr != NULL; curr = curr->next)
  {
    ret = curr->data;
    if(ret->max - ret->running >= num &&
       (agent_name == NULL || host_supports_agent(ret, agent_name)))
      break;
  }

  if(curr == NULL)
    return NULL;

  host_queue = g_list_remove(host_queue, ret);
  host_queue = g_list_append(host_queue, ret);

  *queue = host_queue;
  return ret;
}

/**
 * @brief Prints the host information to ostr
 *
 * @param host_list
 * @param ostr
 * @sa print_host_all()
 */
void print_host_load(GTree* host_list, GOutputStream* ostr)
{
  g_tree_foreach(host_list, (GTraverseFunc)print_host_all, ostr);
  g_output_stream_write(ostr, "\nend\n", 5, NULL, NULL);
}
