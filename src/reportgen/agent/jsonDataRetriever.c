/*
 Copyright (C) 2014, Siemens AG
 Author: Daniele Fognini

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
 */
#define _POSIX_C_SOURCE 2
#include <stdio.h>
#include <glib.h>

#include <stdarg.h>

#ifndef INSTALLDIR
#define INSTALLDIR "."
#error
#endif

#define GETBULKMATCHECMD   INSTALLDIR "/getBulkMatches -u %d --gId=%d"
#define GETKEYWORDSCMD   INSTALLDIR "/getKeywords -u %d"
#define GETCLEAREDCMD   INSTALLDIR "/getCleared -u %d --gId=%d"
#define GETCLEAREDCOPY  INSTALLDIR "/getClearedCopy -u %d"
#define GETCLEAREDIP  INSTALLDIR "/getClearedIp -u %d"
#define GETCLEAREDECC  INSTALLDIR "/getClearedEcc -u %d"

static char* pipeRun(const char* cmdLineFmt, ...)
{
  va_list args;
  va_start(args, cmdLineFmt);
  gchar* cmd = g_strdup_vprintf(cmdLineFmt, args);
  va_end(args);

  FILE* pipe = popen(cmd, "r");
  g_free(cmd);

  //TODO replace _POSIX_C_SOURCE and popen with more secure dup2+fork

  GString* stringBuffer = g_string_new("");
  char buffer[4096];

  size_t readBytes;
  while ((!feof(pipe)) && (readBytes = fread(buffer, 1, sizeof(buffer), pipe)) > 0)
  {
    g_string_append_len(stringBuffer, buffer, readBytes);
  }

  pclose(pipe);

  return g_string_free(stringBuffer, FALSE);
}

char* getClearedLicenses(int uploadId,int groupId)
{
  return pipeRun(GETCLEAREDCMD, uploadId, groupId);
}

char* getClearedCopyright(int uploadId)
{
  return pipeRun(GETCLEAREDCOPY, uploadId);
}

char* getClearedIp(int uploadId)
{
  return pipeRun(GETCLEAREDIP, uploadId);
}

char* getClearedEcc(int uploadId)
{
  return pipeRun(GETCLEAREDECC, uploadId);
}

char* getMatches(int uploadId,int groupId)
{
  return pipeRun(GETBULKMATCHECMD, uploadId, groupId);
}

char* getKeywords(int uploadId)
{
  return pipeRun(GETKEYWORDSCMD, uploadId);
}