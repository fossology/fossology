/*
 Author: Harshit Gandhi
 SPDX-FileCopyrightText: © 2025 Harshit Gandhi <gandhiharshit716@gmail.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

#ifndef KOTOBA_AGENT_HASH_H
#define KOTOBA_AGENT_HASH_H

#include <stdint.h>

uint32_t hash(const char* string);

uint32_t hash_init();

void hash_add(const char* value, uint32_t* currentHash);

#endif // KOTOBA_AGENT_HASH_H
