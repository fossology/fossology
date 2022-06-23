/*
 Author: Daniele Fognini
 SPDX-FileCopyrightText: © 2015 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

#ifndef MONK_AGENT_ENCODING_H
#define MONK_AGENT_ENCODING_H

#include <iconv.h>
#include <glib.h>

iconv_t guessConverter(const char* buffer, size_t len);
gchar* guessEncoding(const char* buffer, size_t len);

#endif
