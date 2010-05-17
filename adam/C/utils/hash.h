/*********************************************************************
Copyright (C) 2010 Hewlett-Packard Development Company, L.P.

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

#ifndef __HASH_H__
#define __HASH_H__

#if defined(__cplusplus)
extern "C" {
#endif

static unsigned long sdbm(char *str) {
    unsigned long hash = 0;
    int c;

    while (c = *str++) {
        hash = c + (hash << 6) + (hash << 16) - hash;
    }

    return hash;
}

#if defined(__cplusplus)
}

static unsigned long sdbm_string(string s) {
    unsigned long hash = 0;
    int c;
    int pos;

    for(string::iterator iter = s.begin(); iter != s.end(); iter++) {
      hash = *iter + (hash << 6) + (hash << 16) - hash;
    }

    return hash;
}
#endif
#endif
