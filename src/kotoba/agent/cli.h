/*
 Author: Harshit Gandhi
 SPDX-FileCopyrightText: © 2025 Harshit Gandhi <gandhiharshit716@gmail.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

#ifndef KOTOBA_AGENT_CLI_H
#define KOTOBA_AGENT_CLI_H

#include "kotoba.h"
#include "diff.h"

int handleCliMode(KotobaState* state, const Licenses* licenses, int argc, char** argv, int fileOptInd);
int cli_onNoMatch(KotobaState* state, const File* file);
int cli_onFullMatch(KotobaState* state, const File* file, const License* license, const DiffMatchInfo* matchInfo);
int cli_onDiff(KotobaState* state, const File* file, const License* license, const DiffResult* diffResult);
int cli_onBeginOutput(KotobaState* state);
int cli_onBetweenIndividualOutputs(KotobaState* state);
int cli_onEndOutput(KotobaState* state);

#endif // KOTOBA_AGENT_CLI_H
