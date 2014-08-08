/***************************************************************
 Copyright (C) 2006-2014 Hewlett-Packard Development Company, L.P.
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
