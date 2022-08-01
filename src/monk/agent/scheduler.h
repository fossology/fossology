/*
 Author: Daniele Fognini
 SPDX-FileCopyrightText: © 2013-2014 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

#ifndef MONK_AGENT_SCHEDULER_H
#define MONK_AGENT_SCHEDULER_H

#include "match.h"

int handleSchedulerMode(MonkState* state, const Licenses* licenses);

int sched_onNoMatch(MonkState* state, const File* file);
int sched_onFullMatch(MonkState* state, const File* file, const License* license, const DiffMatchInfo* matchInfo);
int sched_onDiffMatch(MonkState* state, const File* file, const License* license, const DiffResult* diffResult);
int sched_ignore(MonkState* state, const File* file);
int sched_noop(MonkState* state);

#endif // MONK_AGENT_SCHEDULER_H
