/*
Author: Daniele Fognini, Andreas Wuerl
Copyright (C) 2013-2014, Siemens AG

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
*/

#ifndef MONK_AGENT_BULK_H
#define MONK_AGENT_BULK_H

#define AGENT_BULK_NAME "monkbulk" ///< the name of the agent, used to get agent key
#define AGENT_BULK_DESC "monkbulk agent" ///< what program this is
#define AGENT_BULK_ARS  "monkbulk_ars"

#include "monk.h"

#define BULK_DECISION_TYPE_MEANING "bulk"
#define BULK_DECISION_TYPE 2
#define BULK_DECISION_SCOPE "upload"

typedef struct {
  long bulkId;
  long uploadTreeId;
  long uploadTreeLeft;
  long uploadTreeRight;
  long licenseId;
  int uploadId;
  int jobId;
  int removing;
  int userId;
  int groupId;
  char* refText;
} BulkArguments;

#endif // MONK_AGENT_BULK_H
