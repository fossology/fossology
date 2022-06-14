/*
 SPDX-FileCopyrightText: © 2018 Siemens AG
 Author: Gaurav Mishra <mishra.gaurav@siemens.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

#ifndef EMAILFORMATTER_H_INCLUDE
#define EMAILFORMATTER_H_INCLUDE

#include <agent.h>

/**
 * Structure to hold information about a single agent required by email formatter
 */
typedef struct
{
  guint id;         ///< Job queue id for the agent
  GString* agent;   ///< Agent name
  gboolean status;  ///< Agent status (Pass => true, fail => false)
} agent_info;

/* Format rows as plain text */
const gchar* email_format_text(GPtrArray *rows, gchar *fossy_url);

/* Callback function for email process */
void mail_process_exit_callback(GPid pid, gint wait_status, gpointer ignore);

#endif /* EMAILFORMATTER_H_INCLUDE */

