/*
 * Copyright (C) 2014-2015, Siemens AG
 * Author: Johannes Najjar, Daniele Fognini
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2
 * as published by the Free Software Foundation.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software Foundation,
 * Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 */

#include "regTypes.hpp"


const char* regAuthor::getType(){
  return "author";
};


#define EMAILRGX  "[\\<\\(]?([\\w\\-\\.\\+]{1,100}@[\\w\\-\\.\\+]{1,100}\\.[a-z]{1,4})[\\>\\)]?"
#define WEBSITE  "(?:http|https|ftp)\\://[a-zA-Z0-9\\-\\.]+\\.[a-zA-Z]{2,4}(:[a-zA-Z0-9]*)?/?([a-zA-Z0-9\\-\\._\\?\\,\'/\\\\+&amp;%\\$#\\=~])*[^\\.\\,\\)\\(\\s]"

const char* regAuthor::getRegex() {
// Alternative idea: use a heuristic similar to copyscan.cc
#define SPACECLS          "[\\t ]"
#define SPACES            SPACECLS "+"
#define SPACESALL         "[[:space:]]*"
#define PUNCT_OR_SPACE    "[[:punct:][:space:]]"
//#define ABBR_AND_BRACED   "[A-Z]{2,7}\\([^)]+\\)"
#define ALPHA             "[:alpha:]\u00c0-\u00d6\u00d9-\u00f6\u00f8-\u00ff"
#define NAME_OR_COMPANY   "(?:[" ALPHA "]+|" EMAILRGX "|" WEBSITE ")"
#define NAMESLIST         NAME_OR_COMPANY "(?:[\\-, &]+" NAME_OR_COMPANY ")*"
#define DATE              "((19|20)[[:digit:]]{2,2}|[[:digit:]]{1,2})"
#define DATESLIST         DATE "(([[:punct:][:space:]-]+)" DATE ")*"

 return 
  "(?:"
    "(?:(?:author|contributor|maintainer)s?)"
    "|(?:(?:written|contribut(?:ed|ions?)|maintained|modifi(?:ed|cations?)|put" SPACES "together)" SPACES "by)"
  ")"
  "[:]?"
  SPACESALL
  NAMESLIST
  "\\.?";
};

const char* regURL::getType(){
  return "url";
};

const char* regURL::getRegex() {
 return    "(?:(:?ht|f)tps?\\:\\/\\/[^\\s\\<]+[^\\<\\.\\,\\s])";
};


const char* regEmail::getType(){
  return "email";
};

const char* regEmail::getRegex() {
 return EMAILRGX;
};





const  char* regEcc::getType(){
  return "ecc";
};

const char* regEcc::getRegex() {
 return "("
    "eccn|tsu|ecc|ccl|wco"
    "|(export" SPACES "control)"
    "|(customs)"
    "|(foreign" SPACES "trade(" SPACES "regulations)?)"
    "|(commerce" SPACES "control)"
    "|(country" SPACES "of" SPACES "origin)"
    "|(export" SPACES "administration)"
    "|((k|c)rypt)"
    "|(information" SPACES "security)"
    "|(encryption)"
    "|(nuclear|surveillance|military|defense|marine|avionics|laser)"
    "|(propulsion" SPACES "systems)"
    "|(space" SPACES "vehicle(s)?)"
    "|(dual" SPACES "use)"
   ")"
   "[^)\n]{0,60}";     // \TODO
};

