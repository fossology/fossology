/*
 Author: Daniele Fognini, Andreas Wuerl
 SPDX-FileCopyrightText: © 2013-2014 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

#ifndef MONK_AGENT_HASH_H
#define MONK_AGENT_HASH_H

#include <stdint.h>

uint32_t hash(const char* string);

uint32_t hash_init();

void hash_add(const char* value, uint32_t* currentHash);

#endif // MONK_AGENT_HASH_H
