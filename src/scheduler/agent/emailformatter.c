/*
 SPDX-FileCopyrightText: © 2018, 2022 Siemens AG
 Author: Gaurav Mishra <mishra.gaurav@siemens.com>

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * \file
 * \brief Format email body for a list of agents and their status
 *
 * The file can further be enhanced for different formats like HTML, XML, etc.
 */

#include <agent.h>
#include <emailformatter.h>

/**
 * @brief Format rows as plain text
 *
 * @param rows      rows of type agent_info
 * @param fossy_url host url of fossology
 * @return rows in plain text format
 */
const gchar* email_format_text(GPtrArray *rows, gchar *fossy_url)
{
  guint i;
  GString* ret = g_string_new("");
  if(rows == NULL)
  {
    return "";
  }
  g_string_append(ret, "Agents run:\n");
  g_string_append(ret, "    Job ID =>      Agent Name =>     Status => Link\n");
  for (i = 0; i < rows->len; i++)
  {
    agent_info *data = (agent_info *)g_ptr_array_index(rows, i);
    g_string_append_printf(ret, "%10d => %15s => ", data->id, data->agent->str);
    if (data->status == TRUE)
    {
      g_string_append(ret, " COMPLETED\n");
    }
    else
    {
      g_string_append_printf(ret, "%10s => http://%s?mod=showjobs&job=%d\n",
                             "FAILED", fossy_url, data->id);
    }
    g_string_free(data->agent, TRUE);
  }
  return ret->str;
}

/**
 * @brief Callback function for email process
 *
 * Checks if process exits successfully. If process failed, print the error
 * message.
 * Closes the process at end.
 * @param pid PID of spawned process
 * @param wait_status Status of the process
 * @param ignore Not used
 */
void mail_process_exit_callback(GPid pid, gint wait_status,
                                gpointer ignore)
{
  NOTIFY("Callback called for pid: %d", pid);
  GError* error = NULL;
  if (!g_spawn_check_wait_status(wait_status, &error))
  {
    ERROR("Mail process exited with code (%d) and message: %s", error->code,
          error->message);
    g_error_free(error);
  }
  else
  {
    NOTIFY("Mail process exited successfully.\n");
  }
  g_spawn_close_pid(pid);
  NOTIFY("PID closed\n");
}
