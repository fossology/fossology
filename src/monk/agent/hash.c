/*
Author: Daniele Fognini, Andreas Wuerl
Copyright (C) 2013-2014, Siemens AG

This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License version 2 as published by the Free Software Foundation.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this program; if not, write to the Free Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
*/

#include "hash.h"
#include <stdio.h>

uint32_t hash(char * string) {

  uint32_t result = hash_init();

  char * ptr = string;
  
  while (*ptr) {
    hash_add(ptr, &result);
    ptr++;
  }

  return result;
}

inline uint32_t hash_init() {
  return 5231;
}

inline void hash_add(char * value, uint32_t * currentHash) {
  *currentHash = ((*currentHash << 6) + *currentHash) + * value;
}
