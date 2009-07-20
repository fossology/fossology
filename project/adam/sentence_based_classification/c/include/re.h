/*********************************************************************
Copyright (C) 2009 Hewlett-Packard Development Company, L.P.

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
version 2 as published by the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License along
with this program; if not, write to the Free Software Foundation, Inc.,
51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*********************************************************************/

#include <stdio.h>
#include <stdlib.h>
#include <malloc.h>
#include <string.h>
#include <ctype.h>
#include "list.h"
#include <pcre.h>

#ifndef _RE__h_
#define _RE__h_

#if defined(__cplusplus)
extern "C" {
#endif

typedef pcre cre;
#define RE_DOTALL PCRE_DOTALL
#define OVECCOUNT 30    /* should be a multiple of 3 */

void re_print_error(int id);
int re_compile(char *pattern, int options, cre **re);
void re_free(cre *re);
int re_find_all(cre *re, char* subject, default_list **list, void*(*helpFunc)(char*, int, int));

#if defined(__cplusplus)
}
#endif

#endif
