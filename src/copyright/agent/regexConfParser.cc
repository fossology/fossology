/*
 * Copyright (C) 2015, Siemens AG
 * Author: Maximilian Huber
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

#include "regexConfParser.hpp"
#include <string>
#include <iostream>

using namespace std;

RegexDict readConfStreamToDict(std::istringstream& stream,
                               const bool isVerbosityDebug)
{
  map<string, string> regexDict;
  for (string line; getline(stream, line); )
    addRegexToDict(regexDict, line, isVerbosityDebug);

  return regexDict;
}

RegexDict readConfStreamToDict(std::ifstream& stream,
                               const bool isVerbosityDebug)
{
  map<string, string> regexDict;
  for (string line; getline(stream, line); )
    addRegexToDict(regexDict, line, isVerbosityDebug);
  stream.close();
  return regexDict;
}

void addRegexToDict(/*in and out*/ RegexDict& regexDict,
                    const std::string& regexDesc,
                    const bool isVerbosityDebug)
{
  if (regexDesc[0] == '#')
    return;

  istringstream is_line(regexDesc);
  string key, value;
  if (getline(is_line, key, '=')) {
    if(getline(is_line, value)) {
      value=replaceTokens(regexDict, value);
      regexDict[key]=value;
      if (isVerbosityDebug)
        cout << "loaded or updated regex definition: " << key << " -> \"" << value << "\"" << endl;
    } else {
      cout << "empty regex definition in conf: \"" << regexDesc << "\"" << endl;
    }
  } else {
    cout << "bad regex definition in conf: \"" << regexDesc << "\"" << endl;
  }
}

string replaceTokens(/*in*/ RegexDict& regexDict,
                     const string& constInput)
{
#define RGX_SEPARATOR "@@"
#define RGX_SEPARATOR_LEN 2

  string input(constInput);
  stringstream output;

  size_t pos = 0;
  string token;
  while ((pos = input.find(RGX_SEPARATOR)) != string::npos) { // find start of token
    output << input.substr(0, pos);
    input.erase(0, pos + RGX_SEPARATOR_LEN);

    if ((pos = input.find(RGX_SEPARATOR)) != string::npos) { // find end of token
      output << regexDict[input.substr(0, pos)];
      input.erase(0, pos + RGX_SEPARATOR_LEN);

    }else{
      cout << "uneven number of delimiters: " << constInput << endl;
    }
  }
  output << input;
  return output.str();
}
