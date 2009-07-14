#include <stdio.h>
#include <stdlib.h>
#include <malloc.h>
#include <string.h>
#include <ctype.h>
#include "list.h"
#include <pcre.h>

#ifndef _RE__h_
#define _RE__h_

typedef pcre cre;
#define RE_DOTALL PCRE_DOTALL
#define OVECCOUNT 30    /* should be a multiple of 3 */

void re_print_error(int id);
int re_compile(char *pattern, int options, cre **re);
void re_free(cre *re);
int re_find_all(cre *re, char* subject, default_list **list, void*(*helpFunc)(char*, int, int));

#endif
