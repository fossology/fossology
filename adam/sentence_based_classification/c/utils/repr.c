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
#include <string.h>

char char_table[256][3];

void repr_init() {
    int i;

    for (i = 0; i < 256; i++) {
        char_table[i][0] = i;
        char_table[i][1] = '\0';
    }

    char_table['\n'][0] = '\\';
    char_table['\n'][1] = 'n';
    char_table['\n'][2] = '\0';

    char_table['\r'][0] = '\\';
    char_table['\r'][1] = 'r';
    char_table['\r'][2] = '\0';

    char_table['\''][0] = '\\';
    char_table['\''][1] = '\'';
    char_table['\''][2] = '\0';

    char_table['\"'][0] = '\\';
    char_table['\"'][1] = '"';
    char_table['\"'][2] = '\0';

    char_table['\t'][0] = '\\';
    char_table['\t'][1] = 't';
    char_table['\t'][2] = '\0';

    char_table['\\'][0] = '\\';
    char_table['\\'][1] = '\\';
    char_table['\\'][2] = '\0';

    char_table['\?'][0] = '\\';
    char_table['\?'][1] = '?';
    char_table['\?'][2] = '\0';

    char_table['\a'][0] = '\\';
    char_table['\a'][1] = 'a';
    char_table['\a'][2] = '\0';

    char_table['\b'][0] = '\\';
    char_table['\b'][1] = 'b';
    char_table['\b'][2] = '\0';

    char_table['\f'][0] = '\\';
    char_table['\f'][1] = 'f';
    char_table['\f'][2] = '\0';

    char_table['\v'][0] = '\\';
    char_table['\v'][1] = 'v';
    char_table['\v'][2] = '\0';

    char_table[' '][0] = '\\';
    char_table[' '][1] = '_';
    char_table[' '][2] = '\0';

    char_table['\0'][0] = '\\';
    char_table['\0'][1] = '0';
    char_table['\0'][2] = '\0';
}

int repr_string(char *rstr, char *str) {
    int i = 0;
    int j = 0;
    char *cptr = NULL;

    if (char_table['\n'][0] != '\\') {
        repr_init();
    }

    for (cptr = str; *cptr != '\0'; cptr++) {
        strcat(rstr,char_table[*cptr]);
    }

    return 0;
}
