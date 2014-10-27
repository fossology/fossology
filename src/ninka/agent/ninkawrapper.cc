/*
 * Copyright (C) 2014, Siemens AG
 *
 * This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License version 2 as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with this program; if not, write to the Free Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 */

#include <iostream>
#include <boost/tokenizer.hpp>
#include "ninkawrapper.hpp"
#include "utils.hpp"

string scanFileWithNinka(State* state, fo::File* file) {
  FILE *in;
  char buffer[512];
  string command = "ninka " + file->getFileName();
  string result;

  if (!(in = popen(command.c_str(), "r"))) {
    cout << "could not execute ninka command: " << command << endl;
    bail(state, 1);
  }

  while (fgets(buffer, sizeof(buffer), in) != NULL) {
      result += buffer;
  }

  if (pclose(in) != 0) {
    cout << "could not execute ninka command: " << command << endl;
    bail(state, 1);
  }

  return result;
}

vector<string> extractLicensesFromNinkaResult(string ninkaResult) {
  string licensePart = extractLicensePartFromNinkaResult(ninkaResult);
  return splitLicensePart(licensePart);
}

// Ninka result format: filename;license1,license2,...,licenseN;details...
string extractLicensePartFromNinkaResult(string ninkaResult) {
  string delimiters = ";\r\n";

  size_t first = ninkaResult.find_first_of(delimiters);
  size_t last = ninkaResult.find_first_of(delimiters, first + 1);

  return ninkaResult.substr(first + 1, last - 1 - first);
}

vector<string> splitLicensePart(string licensePart) {
  typedef boost::tokenizer<boost::char_separator<char>> tokenizer;
  boost::char_separator<char> separator(",");
  tokenizer tokens(licensePart, separator);

  vector<string> licenses;

  for (tokenizer::iterator iter = tokens.begin(); iter != tokens.end(); ++iter) {
    licenses.push_back(*iter);
  }

  return licenses;
}

vector<LicenseMatch> createMatches(vector<string> ninkaLicenseNames) {
  vector<LicenseMatch> matches;
  for (vector<string>::const_iterator it = ninkaLicenseNames.begin(); it != ninkaLicenseNames.end(); ++it) {
    const string& ninkaLicenseName = *it;
    string fossologyLicenseName = mapLicenseFromNinkaToFossology(ninkaLicenseName);
    unsigned percentage = (ninkaLicenseName.compare("NONE") == 0 || ninkaLicenseName.compare("UNKNOWN") == 0) ? 0 : 100;
    LicenseMatch match = LicenseMatch(fossologyLicenseName, percentage);
    matches.push_back(match);
  }
  return matches;
}

string mapLicenseFromNinkaToFossology(string name) {
  if (name.compare("NONE") == 0) return string("No_license_found");
  if (name.compare("UNKNOWN") == 0) return string("UnclassifiedLicense");
  return name;
};
