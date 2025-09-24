/*
 Author: Harshit Gandhi
 SPDX-FileCopyrightText: © 2025 Harshit Gandhi <gandhiharshit716@gmail.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

#ifndef KOTOBA_AGENT_FILE_OPERATIONS_H
#define KOTOBA_AGENT_FILE_OPERATIONS_H

#include <glib.h>

int readTokensFromFile(const char* fileName, GArray** tokens, const char* delimiters);

#endif // KOTOBA_AGENT_FILE_OPERATIONS_H
