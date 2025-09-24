/*
 Author: Harshit Gandhi
 SPDX-FileCopyrightText: © 2025 Harshit Gandhi <gandhiharshit716@gmail.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

#include "hash.h"
#include <stdio.h>

uint32_t hash(const char* string) {

  uint32_t result = hash_init();

  const char* ptr = string;

  while (*ptr) {
    hash_add(ptr, &result);
    ptr++;
  }

  return result;
}

uint32_t hash_init() {
  return 5231;
}

void hash_add(const char* value, uint32_t* currentHash) {
  *currentHash = ((*currentHash << 6) + *currentHash) + *value;
}
