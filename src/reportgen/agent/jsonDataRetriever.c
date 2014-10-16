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

#ifndef INSTALLDIR
#define INSTALLDIR "."
#error
#endif

#define GETCLEAREDCMD   INSTALLDIR "/getCleared -u %d"
#define GETCLEAREDCOPY  INSTALLDIR "/getClearedCopy -u %d"

static char* pipeRun(const char* cmdLineFmt, int uploadId)
{
  gchar* cmd = g_strdup_printf(cmdLineFmt, uploadId);
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

char* getClearedLicenses(int uploadId)
{
  return pipeRun(GETCLEAREDCMD, uploadId);
}

char* getClearedCopyright(int uploadId)
{
  return pipeRun(GETCLEAREDCOPY, uploadId);
}