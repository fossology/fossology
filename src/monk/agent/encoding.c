/*
 Author: Daniele Fognini
 SPDX-FileCopyrightText: © 2015 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

#include "encoding.h"

#ifdef HAVE_CHARDET
#include <uchardet.h>
#else
#include <magic.h>
#endif

#include <string.h>
#include <stdio.h>

iconv_t guessConverter(const char* buffer, size_t len)
{
  char* const target = "utf-8";

  iconv_t iconvCookie = NULL;

  gchar* encoding = guessEncoding(buffer, len);
  if (encoding && (strcmp(encoding, target) != 0))
  {
    iconvCookie = iconv_open(target, encoding);
    g_free(encoding);
  }

  return iconvCookie;
}

gchar* guessEncoding(const char* buffer, size_t len) {
  gchar* result = NULL;
#ifdef HAVE_CHARDET
  uchardet_t cd = uchardet_new();
  if (!uchardet_handle_data(cd, buffer, len)) {
    uchardet_data_end(cd);

    const char* chardet = uchardet_get_charset(cd);

    if (chardet && strcmp(chardet, "")!=0) {
      result = g_strdup(chardet);
    }
  }

  uchardet_delete(cd);
#else
  magic_t cookie = magic_open(MAGIC_MIME);
  magic_load(cookie, NULL);

  const char* resp = magic_buffer(cookie, buffer, len);

  if (!resp)
  {
    printf("magic error: %s\n", magic_error(cookie));
    goto done;
  }

  char* charset = strstr(resp, "charset=");

  if (!charset)
  {
    goto done;
  }

  charset += 8; // len of "charset="

  result = g_strdup(charset);

done:
  magic_close(cookie);
#endif
  return result;
}
