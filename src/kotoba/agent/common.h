/*
 Author: Harshit Gandhi
 SPDX-FileCopyrightText: © 2025 Harshit Gandhi <gandhiharshit716@gmail.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

#ifndef KOTOBA_AGENT_COMMON_H
#define KOTOBA_AGENT_COMMON_H

#include "kotoba.h"
#include "diff.h"

void scheduler_disconnect(KotobaState* state, int exitval);
void bail(KotobaState* state, int exitval);
void queryAgentId(KotobaState* state, const char* agentName, const char* agentDesc);
#endif // KOTOBA_AGENT_COMMON_H
