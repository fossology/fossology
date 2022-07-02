/*
 SPDX-FileCopyrightText: © 2006-2009 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

#ifndef _PROCESS_H
#define _PROCESS_H

void processRawSource();
void processRegularFiles();
#ifdef DEAD_CODE
void stripLine(char *textp, int offset, int size);
#endif /* DEAD_CODE */

#endif /* _PROCESS_H */
