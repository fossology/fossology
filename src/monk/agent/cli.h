/*
 Author: Daniele Fognini, Andreas Wuerl
 SPDX-FileCopyrightText: © 2013-2014 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

#ifndef MONK_AGENT_CLI_H
#define MONK_AGENT_CLI_H

#include "monk.h"
#include "diff.h"

int handleCliMode(MonkState* state, const Licenses* licenses, int argc, char** argv, int fileOptInd);
int cli_onNoMatch(MonkState* state, const File* file);
int cli_onFullMatch(MonkState* state, const File* file, const License* license, const DiffMatchInfo* matchInfo);
int cli_onDiff(MonkState* state, const File* file, const License* license, const DiffResult* diffResult);
int cli_onBeginOutput(MonkState* state);
int cli_onBetweenIndividualOutputs(MonkState* state);
int cli_onEndOutput(MonkState* state);

#endif // MONK_AGENT_CLI_H
