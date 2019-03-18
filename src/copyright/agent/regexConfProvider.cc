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
/**
 * \file regexConfProvider.cc
 */
#include "regexConfProvider.hpp"

using namespace std;

/**
 * \brief Map to store RegexMap with a string key
 *
 * Where key is the identity file name from which the RegexMap is loaded
 * and value is the RegexMap from the identity file
 */
map<string,RegexMap> RegexConfProvider::_regexMapMap = {};

/**
 * \brief Check if a given file exists
 * \param filename The file path to check
 * \return True if exists and readable, false otherwise.
 */
bool testIfFileExists(const string& filename)
{
  ifstream f(filename);
  bool exists = f.good();
  f.close();
  return exists;
}

/**
 * \brief Get the regex conf file
 *
 * Checks for regex file in
 *   -# Current directory
 *   -# Relative to test directory
 *   -# In installed directory
 *
 * Return the first match found
 * \param identity Name of file to be found (without \b ".conf" extension)
 * \return Path of conf file as a string
 */
string getRegexConfFile(const string& identity)
{
  string confInSameDir(identity + ".conf");

  string confRelativeToTestDir("../../agent/" + identity + ".conf");

  string confInInstallDir((sysconfigdir ? string(sysconfigdir) : "/usr/local/share/fossology/")
                          + "/mods-enabled/" + identity +  "/agent/" + identity + ".conf");

  if(testIfFileExists( confInSameDir ))
  {
    return confInSameDir;
  }
  else if(testIfFileExists( confRelativeToTestDir ))
  {
    return confRelativeToTestDir;
  }
  else
  {
    return confInInstallDir;
  }
}

/**
 * \brief Constructor to set verbosity level
 */
RegexConfProvider::RegexConfProvider(const bool isVerbosityDebug)
  : _isVerbosityDebug(isVerbosityDebug) {}

/**
 * \brief Get file stream for regex conf file
 * \param[in]  identity Name of file to be found (without \b ".conf" extension)
 * \param[out] stream   Input file stream created from identity
 * \return True on success, false otherwise
 */
bool RegexConfProvider::getRegexConfStream(const string& identity,
                                           /*out*/ ifstream& stream)
{
  string confFile = getRegexConfFile(identity);

  if (_isVerbosityDebug)
    cout << "try to open conf: " << confFile << endl;
  stream.open(confFile.c_str());

  return stream.is_open();
}

/**
 * \brief Check if identity already loaded in RegexMap, if not
 * load them
 * \param identity Identity to be matched
 */
void RegexConfProvider::maybeLoad(const std::string& identity)
{
  map<string,RegexMap>& rmm = RegexConfProvider::_regexMapMap;
  if (rmm.find(identity) == rmm.end())
  {
#pragma omp critical(rmm)
    {
      if (rmm.find(identity) == rmm.end())
      {
        ifstream stream;
        if (getRegexConfStream(identity, stream))
        {
          rmm[identity] = readConfStreamToMap(stream, _isVerbosityDebug);
          stream.close();
        }
        else
        {
          cout << "cannot open regex definitions in conf: " << getRegexConfFile(identity) << endl;
        }
      }
      else if (_isVerbosityDebug)
      {
        cout << "the identity " << identity << " is already loaded" << endl;
      }
    }
  }
}

/**
 * \overload
 * \param stream   Stream to read from
 */
void RegexConfProvider::maybeLoad(const string& identity,
                                  istringstream& stream)
{
  map<string,RegexMap>& rmm = RegexConfProvider::_regexMapMap;
  if (rmm.find(identity) == rmm.end())
  {
#pragma omp critical(rmm)
    {
      if (rmm.find(identity) == rmm.end())
      {
        rmm[identity] = readConfStreamToMap(stream, _isVerbosityDebug);
      }
      else if (_isVerbosityDebug)
      {
        cout << "the identity " << identity << " is already loaded" << endl;
      }
    }
  }
}

/**
 * \brief Get the regex as string from the RegexMap
 * \param identity Identity from which the map was loaded
 * \param key      Key of the regex value required
 * \return Regex value as a null terminated string
 */
const char* RegexConfProvider::getRegexValue(const string& identity,
                                             const string& key)
{
  const string* rv;
#pragma omp critical(rmm)
  {
    rv = &(RegexConfProvider::_regexMapMap[identity][key]);
  }
  return (*rv).c_str();
}
