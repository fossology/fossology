/*
 Author: Daniele Fognini, Andreas Wuerl
 SPDX-FileCopyrightText: © 2013-2014 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

#ifndef MONK_AGENT_FILE_OPERATIONS_H
#define MONK_AGENT_FILE_OPERATIONS_H

#include <glib.h>

int readTokensFromFile(const char* fileName, GArray** tokens, const char* delimiters);

#endif // MONK_AGENT_FILE_OPERATIONS_H
