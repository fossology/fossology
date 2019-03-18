/* **************************************************************
 Copyright (C) 2018 Siemens AG
 Author: Gaurav Mishra <mishra.gaurav@siemens.com>

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

