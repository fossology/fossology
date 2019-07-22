/*
 * Copyright (C) 2019-2020, Siemens AG
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * version 2 as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 */

#include <iostream>
#include <boost/tokenizer.hpp>
#include "atarashiwrapper.hpp"
#include "utils.hpp"

string scanFileWithAtarashi(const State& state, const fo::File& file)
{
  FILE* in;
  char buffer[512];
  string command = "atarashi -a DLD " + file.getFileName();   //Testing only for DLD Now
  string result;

  if (!(in = popen(command.c_str(), "r")))
  {
    cout << "could not execute atarashi command: " << command << endl;
    bail(1);
  }

  while (fgets(buffer, sizeof(buffer), in) != NULL)
  {
    result += buffer;
  }

  if (pclose(in) != 0)
  {
    cout << "could not execute atarashi command: " << command << endl;
    bail(1);
  }

  int jsonStart = result.find("{");
  return result.substr(jsonStart, string::npos);
}

vector<LicenseMatch> extractLicensesFromAtarashiResult(string atarashiResult)
{
  Json::Reader reader;
  Json::Value atarashiResultobj;
  bool parseSuccess = reader.parse(atarashiResult, atarashiResultobj);

  if (!parseSuccess)
  {
    cout << "Failed to parse" << reader.getFormattedErrorMessages();
    bail(-30);
  }

  vector<LicenseMatch> matches;
  Json::Value resultArray = atarashiResultobj["results"];
  for (unsigned int index = 0; index < resultArray.size(); ++index)
  {
    Json::Value resultObject = resultArray[index];
    LicenseMatch m(resultObject["shortname"].asString(),
        (unsigned)(resultObject["sim_score"].asDouble() * 100.0));
    matches.push_back(m);
  }
  return matches;
}
