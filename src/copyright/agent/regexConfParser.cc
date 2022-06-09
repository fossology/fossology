/*
 SPDX-FileCopyrightText: © 2015 Siemens AG
 Author: Maximilian Huber

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * \file regexConfParser.cc
 * \brief Handles RegexMap related requests
 */
#include "regexConfParser.hpp"
#include <string>
#include <iostream>

using namespace std;

/**
 * \brief Read a string stream and crate a RegexMap
 * \param stream           String stream to read from
 * \param isVerbosityDebug Print debug messages if true
 * \return RegexMap created using stream
 */
RegexMap readConfStreamToMap(std::istringstream& stream,
                             const bool isVerbosityDebug)
{
  map<string, string> regexMap;
  for (string line; getline(stream, line); )
    addRegexToMap(regexMap, line, isVerbosityDebug);

  return regexMap;
}

/**
 * \overload
 */
RegexMap readConfStreamToMap(std::ifstream& stream,
                             const bool isVerbosityDebug)
{
  map<string, string> regexMap;
  for (string line; getline(stream, line); )
    addRegexToMap(regexMap, line, isVerbosityDebug);
  stream.close();
  return regexMap;
}

/**
 * \brief Given a single line as 'key=value' pair,
 * create a RegexMap
 * \param[out] regexMap         Map to add pairs
 * \param[in]  regexDesc        String containing the pair to be added
 * \param[in]  isVerbosityDebug Print debug messages if true
 */
void addRegexToMap(/*in and out*/ RegexMap& regexMap,
                   const std::string& regexDesc,
                   const bool isVerbosityDebug)
{
  if (regexDesc[0] == '#')
    return;

  istringstream is_line(regexDesc);
  string key, value;
  if (getline(is_line, key, '='))
  {
    if(getline(is_line, value))
    {
      value=replaceTokens(regexMap, value);
      regexMap[key]=value;
      if (isVerbosityDebug)
        cout << "loaded or updated regex definition: " << key << " -> \"" << value << "\"" << endl;
    }
    else
    {
      cout << "empty regex definition in conf: \"" << regexDesc << "\"" << endl;
    }
  }
  else
  {
    cout << "bad regex definition in conf: \"" << regexDesc << "\"" << endl;
  }
}

/**
 * \brief Removes tokens separated by RGX_SEPARATOR_LEFT
 * in constInput using regexMap
 * \param[in] regexMap   Map to be used for removal
 * \param[in] constInput Input which has to be removed
 * \return String with tokens removed
 */
string replaceTokens(/*in*/ RegexMap& regexMap,
                     const string& constInput)
{
#define RGX_SEPARATOR_LEFT "__"
#define RGX_SEPARATOR_RIGHT RGX_SEPARATOR_LEFT
#define RGX_SEPARATOR_LEN 2

  string input(constInput);
  stringstream output;

  size_t pos = 0;
  string token;
  while ((pos = input.find(RGX_SEPARATOR_LEFT)) != string::npos) // find start of the next token
  {
    output << input.substr(0, pos);
    input.erase(0, pos + RGX_SEPARATOR_LEN);

    if ((pos = input.find(RGX_SEPARATOR_RIGHT)) != string::npos) // find end of token
    {
      output << regexMap[input.substr(0, pos)];
      input.erase(0, pos + RGX_SEPARATOR_LEN);

    }
    else
    {
      cout << "uneven number of delimiters: " << constInput << endl;
    }
  }
  output << input;
  return output.str();
}
