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
host_t* host_init(char* name, char* address, char* agent_dir, int max, char** tags, int n_tags)
{
  host_t* host = g_new0(host_t, 1);

  host->name = g_strdup(name);
  host->address = g_strdup(address);
  host->agent_dir = g_strdup(agent_dir);
  host->max = max;
  host->running = 0;
  host->n_tags = n_tags;
  if (n_tags > 0 && tags != NULL) {
    host->tags = g_new0(char*, n_tags + 1);  /* NULL-terminated */
    for (int i = 0; i < n_tags; i++)
      host->tags[i] = g_strdup(tags[i]);
    host->tags[n_tags] = NULL;
  } else {
    host->tags = NULL;
  }

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
  if (host->tags) {
    for (int i = 0; i < host->n_tags; i++)
      g_free(host->tags[i]);
    g_free(host->tags);
  }

  host->name = NULL;
  host->address = NULL;
  host->agent_dir = NULL;
  host->tags = NULL;
  host->n_tags = 0;
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
 * Gets a host for which there are at least num agents available to start
 * new agents on.
 *
 * @param queue GList of available hosts
 * @param num the number of agents to start on the host
 * @return the host with that number of available slots, NULL if none exist
 */
host_t* get_host(GList** queue, uint8_t num)
{
  GList*  host_queue = *queue;
  GList*  curr       = NULL;
  host_t* ret        = NULL;

  for(curr = host_queue; curr != NULL; curr = curr->next)
  {
    ret = curr->data;
    if(ret->max - ret->running >= num)
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

/**
 * @brief Check whether a host is tagged for a given agent type.
 *
 * A host with zero tags (n_tags == 0) is considered a "universal" host and
 * matches any agent type.
 *
 * @param host        The host to check
 * @param agent_type  The agent type string (e.g. "nomos")
 * @return TRUE if the host can run agents of this type
 */
static gboolean host_accepts_agent(const host_t* host, const char* agent_type)
{
  if (host->n_tags == 0)
    return TRUE;  /* no tags → accepts everything (backward compat) */

  for (int i = 0; i < host->n_tags; i++) {
    if (strcmp(host->tags[i], agent_type) == 0)
      return TRUE;
  }
  return FALSE;
}

/**
 * @brief Select a host that can run the given agent type.
 *
 * Iterates the round-robin queue and returns the first host that has at least
 * @p num free agent slots and is tagged for @p agent_type (or has no tags,
 * i.e. is a universal host that accepts any agent type).
 *
 * The selected host is moved to the tail of the queue to implement round-robin
 * scheduling within the eligible set.
 *
 * @param scheduler   The scheduler owning the host_queue
 * @param agent_type  The agent type to dispatch (e.g. "nomos")
 * @param num         Required number of free slots
 * @return A suitable host, or NULL if none are available
 */
host_t* get_host_for(scheduler_t* scheduler, const char* agent_type, uint8_t num)
{
  GList*  curr = NULL;
  host_t* host = NULL;

  for (curr = scheduler->host_queue; curr != NULL; curr = curr->next) {
    host = curr->data;
    if (host->max - host->running >= num && host_accepts_agent(host, agent_type))
      break;
  }

  if (curr == NULL)
    return NULL;

  scheduler->host_queue = g_list_remove(scheduler->host_queue, host);
  scheduler->host_queue = g_list_append(scheduler->host_queue, host);
  return host;
}
