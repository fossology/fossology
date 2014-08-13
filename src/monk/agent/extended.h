/*
Author: Daniele Fognini, Andreas Wuerl
Copyright (C) 2013-2014, Siemens AG

This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License version 2 as published by the Free Software Foundation.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this program; if not, write to the Free Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
*/
#ifndef MONK_AGENT_EXTENDED_H
#define MONK_AGENT_EXTENDED_H

#include "monk.h"
#include "diff.h"

int handleArguments(MonkState* state, int argc, char** argv);
void onNoMatch(File* file);
void onFullMatch(File* file, License* license, DiffMatchInfo* matchInfo);
void onDiffMatch(File* file, License* license, DiffResult* diffResult, unsigned short rank);

#endif
