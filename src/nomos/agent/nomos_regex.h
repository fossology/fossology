/*
 SPDX-FileCopyrightText: © 2006-2009 Hewlett-Packard Development Company, L.P.
 SPDX-FileCopyrightText: © 2014 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

#ifndef _NOMOS_REGEX_H
#define _NOMOS_REGEX_H
#include <regex.h>
#include <ctype.h>
#include "nomos.h"
#include "util.h"
#include "_autodefs.h"

extern regex_t regc[NFOOTPRINTS];

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
