/*
Author: Daniele Fognini, Andreas Wuerl
Copyright (C) 2013-2014, Siemens AG

This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License version 2 as published by the Free Software Foundation.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this program; if not, write to the Free Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
*/
#ifndef MONK_AGENT_MONK_H
#define MONK_AGENT_MONK_H

#define AGENT_NAME "monk" ///< the name of the agent, used to get agent key
#define AGENT_DESC "monk agent" ///< what program this is
#define AGENT_ARS  "monk_ars" 

#define MODE_SCHEDULER 1
#define MODE_CLI 2
#define MODE_BULK 3
#define MODE_BULK_NEGATIVE 4

#define FULL_MATCH "M"
#define DIFF_TYPE_MATCH "M0"
#define DIFF_TYPE_ADDITION "M+"
#define DIFF_TYPE_REMOVAL "M-"
#define DIFF_TYPE_REPLACE "MR"

#define DELIMITERS " \t\n\r\f"

#define MAX_ALLOWED_DIFF_LENGTH 200
#define MIN_TRAILING_MATCHES 5
#define MIN_ALLOWED_RANK 66

#include <glib.h>
#include "libfossdbmanager.h"

#if GLIB_CHECK_VERSION(2,32,0)
#define MONK_MULTI_THREAD
#endif

typedef struct {
  long uploadId;
  int removing;
  int userId;
  int groupId;
  char* licenseName;
  char* fullLicenseName;
  char* refText;
} BulkArguments;

typedef struct {
    fo_dbManager* dbManager;
    int agentId;
    int scanMode;
    int verbosity;
    BulkArguments* bulkArguments;
} MonkState;

typedef struct {
    long refId;
    char * shortname;
    GArray * tokens;
} License;

typedef struct {
    long id;
    char * fileName;
    GArray * tokens;
} File;

#endif
