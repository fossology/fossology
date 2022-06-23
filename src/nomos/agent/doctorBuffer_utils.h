/*
 SPDX-FileCopyrightText: © 2006-2014 Hewlett-Packard Development Company, L.P.
 SPDX-FileCopyrightText: © 2014 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

#ifndef DOCTORBUFFER_UTILS_H_
#define DOCTORBUFFER_UTILS_H_
int compressDoctoredBuffer( char* textBuffer);
void removeHtmlComments(char* buf);
void removeLineComments(char* buf);
void cleanUpPostscript(char* buf);
void removeBackslashesAndGTroffIndicators(char* buf);
void convertWhitespaceToSpaceAndRemoveSpecialChars( char* buf,int isCR);
void dehyphen(char* buf);
void removePunctuation(char* buf);
void ignoreFunctionCalls(char* buf);
void convertSpaceToInvisible(char* buf);
void doctorBuffer(char *buf, int isML, int isPS, int isCR);

#ifdef DOCTORBUFFER_OLD
void doctorBuffer_old(char *buf, int isML, int isPS, int isCR);
#endif
#endif /* DOCTORBUFFER_UTILS_H_ */
