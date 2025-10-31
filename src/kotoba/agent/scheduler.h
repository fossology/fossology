/*
 Author: Harshit Gandhi
 SPDX-FileCopyrightText: © 2025 Harshit Gandhi <gandhiharshit716@gmail.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

#ifndef KOTOBA_AGENT_SCHEDULER_H
#define KOTOBA_AGENT_SCHEDULER_H

#include "match.h"

int handleSchedulerMode(KotobaState* state, const Licenses* licenses);

int sched_onNoMatch(KotobaState* state, const File* file);
int sched_onFullMatch(KotobaState* state, const File* file, const License* license, const DiffMatchInfo* matchInfo);
int sched_onDiffMatch(KotobaState* state, const File* file, const License* license, const DiffResult* diffResult);
int sched_ignore(KotobaState* state, const File* file);
int sched_noop(KotobaState* state);

#endif // KOTOBA_AGENT_SCHEDULER_H
