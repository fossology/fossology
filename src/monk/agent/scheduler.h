/*
Author: Daniele Fognini
Copyright (C) 2013-2014, Siemens AG

This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License version 2 as published by the Free Software Foundation.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this program; if not, write to the Free Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
*/

#ifndef MONK_AGENT_SCHEDULER_H
#define MONK_AGENT_SCHEDULER_H

#include "match.h"

int sched_onNoMatch(MonkState* state, File* file);
int sched_onFullMatch(MonkState* state, File* file, License* license, DiffMatchInfo* matchInfo);
int sched_onDiffMatch(MonkState* state, File* file, License* license, DiffResult* diffResult);
int sched_ignore(MonkState* state, File* file);

#endif // MONK_AGENT_SCHEDULER_H
