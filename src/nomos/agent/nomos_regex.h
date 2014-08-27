/***************************************************************
 Copyright (C) 2006-2009 Hewlett-Packard Development Company, L.P.
 Copyright (C) 2014, Siemens AG

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

 ***************************************************************/

#ifndef _NOMOS_REGEX_H
#define _NOMOS_REGEX_H
#include <regex.h>
#include <ctype.h>
#include "nomos.h"
#include "util.h"
#include "_autodefs.h"

regex_t regc[NFOOTPRINTS];

void regexError(int ret, regex_t *regc, char *regex);
int endsIn(char *s, char *suffix);
int lineInFile(char *pathname, char *regex);
int textInFile(char *pathname, char *regex, int flags);
int strGrep(char *regex, char *data, int flags);
int idxGrep(int index, char *data, int flags);
int idxGrep_recordPosition(int index, char *data, int flags);
int idxGrep_recordPositionDoctored(int index, char *data, int flags);
int idxGrep_recordIndex(int index, char *data, int flags);
int idxGrep_base(int index, char *data, int flags,  int mode);
int strNbuf(char *data, char *str);
int strNbuf_noGlobals(char *data, char *str , regmatch_t* matchPos, int doSave , char* saveData);
int matchOnce(int isPlain,char *data, char* regex, regex_t *rp, regmatch_t* regmatch );
regmatch_t* getRegmatch_t(GArray* in,int  index);
void rememberWhatWeFound(GArray* highlight, GArray* regmatch_tArray,  int index, int mode);
void recordIndex(GArray* indexList, int index);

#endif /* _NOMOS_REGEX_H */
