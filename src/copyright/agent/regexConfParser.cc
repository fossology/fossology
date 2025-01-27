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

#include <codecvt>
#include <string>
#include <iostream>
#include <locale>

using namespace std;

/**
 * \brief Read a string stream and crate a RegexMap
 * \param stream           String stream to read from
 * \param isVerbosityDebug Print debug messages if true
 * \return RegexMap created using stream
 */
RegexMap readConfStreamToMap(std::wistringstream& stream,
                             const bool isVerbosityDebug)
{
  map<string, icu::UnicodeString> regexMap;
  for (wstring line; getline(stream, line); )
    addRegexToMap(regexMap, line, isVerbosityDebug);

  return regexMap;
}

/**
 * \overload
 */
RegexMap readConfStreamToMap(std::wifstream& stream,
                             const bool isVerbosityDebug)
{
  map<string, icu::UnicodeString> regexMap;
  for (wstring line; getline(stream, line); )
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
                   const std::wstring& regexDesc,
                   const bool isVerbosityDebug)
{
  if (regexDesc[0] == '#')
    return;

  wistringstream is_line(regexDesc);
  wstring key, value;
  if (getline(is_line, key, L'='))
  {
    if(getline(is_line, value))
    {
      std::wstring_convert<codecvt_utf8<wchar_t>> converter;
      string const convertedKey = converter.to_bytes(key);
      regexMap[convertedKey] = replaceTokens(regexMap, value);
      if (isVerbosityDebug)
      {
        string convertedValue;
        regexMap[convertedKey].toUTF8String(convertedValue);
        cout << "loaded or updated regex definition: " << convertedKey << " -> \"" << convertedValue << "\"" << endl;
      }
    }
    else
    {
      wcout << L"empty regex definition in conf: \"" << regexDesc << L"\"" << endl;
    }
  }
  else
  {
    wcout << L"bad regex definition in conf: \"" << regexDesc << L"\"" << endl;
  }
}

/**
 * \brief Removes tokens separated by RGX_SEPARATOR_LEFT
 * in constInput using regexMap
 * \param[in] regexMap   Map to be used for removal
 * \param[in] constInput Input which has to be removed
 * \return String with tokens removed
 */
icu::UnicodeString replaceTokens(/*in*/ RegexMap& regexMap,
                                 const wstring& constInput)
{
#define RGX_SEPARATOR_LEFT u"__"
#define RGX_SEPARATOR_RIGHT RGX_SEPARATOR_LEFT
#define RGX_SEPARATOR_LEN 2

  icu::UnicodeString input = icu::UnicodeString::fromUTF32(
    reinterpret_cast<const UChar32*>(constInput.c_str()),
    constInput.length());
  icu::UnicodeString output;

  int32_t pos = 0;
  string token;
  while ((pos = input.indexOf(RGX_SEPARATOR_LEFT)) != -1) // find start of the next token
  {
    output.append(input.tempSubString(0, pos));
    input.removeBetween(0, pos + RGX_SEPARATOR_LEN);

    if ((pos = input.indexOf(RGX_SEPARATOR_RIGHT)) != -1) // find end of token
    {
      std::string utf8String;
      input.tempSubString(0, pos).toUTF8String(utf8String);
      output.append(regexMap[utf8String]);
      input.removeBetween(0, pos + RGX_SEPARATOR_LEN);
    }
    else
    {
      wcout << L"uneven number of delimiters: " << constInput << endl;
    }
  }
  output.append(input);
  return output;
}
