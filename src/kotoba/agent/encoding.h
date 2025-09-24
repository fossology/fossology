/*
 Author: Harshit Gandhi
 SPDX-FileCopyrightText: © 2025 Harshit Gandhi <gandhiharshit716@gmail.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

#ifndef KOTOBA_AGENT_ENCODING_H
#define KOTOBA_AGENT_ENCODING_H

#include <iconv.h>
#include <glib.h>

iconv_t guessConverter(const char* buffer, size_t len);
gchar* guessEncoding(const char* buffer, size_t len);

#endif
