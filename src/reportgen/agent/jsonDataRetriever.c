#define _POSIX_C_SOURCE 2
#include <stdio.h>
#include <glib.h>

#ifndef INSTALLDIR
#define INSTALLDIR "."
#error
#endif

#define GETCLEAREDCMD   INSTALLDIR "/getCleared"
#define GETCLEAREDCOPY  INSTALLDIR "/getClearedCopy"

static char* pipeRun(const char* cmdLine)
{
  FILE* pipe = popen(cmdLine, "r");

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

char* getClearedLicenses()
{
  return pipeRun(GETCLEAREDCMD);
}

char* getClearedCopyright()
{
  return pipeRun(GETCLEAREDCOPY);
}