/*
Author: Daniele Fognini
Copyright (C) 2015, Siemens AG

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
