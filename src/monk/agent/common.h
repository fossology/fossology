/*
 Author: Daniele Fognini, Andreas Wuerl
 SPDX-FileCopyrightText: © 2013-2014 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

#ifndef MONK_AGENT_COMMON_H
#define MONK_AGENT_COMMON_H

#include "monk.h"
#include "diff.h"

void scheduler_disconnect(MonkState* state, int exitval);
void bail(MonkState* state, int exitval);
void queryAgentId(MonkState* state, const char* agentName, const char* agentDesc);
#endif // MONK_AGENT_COMMON_H
