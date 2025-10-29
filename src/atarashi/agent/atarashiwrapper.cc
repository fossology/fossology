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

#include "atarashiwrapper.hpp"

bool scanFileWithAtarashi(const State& state, const std::string &fileLocation, const std::string& outputFile)
{
  string projectUser = fo_config_get(sysconfig, "DIRECTORIES", "PROJECTUSER", NULL);

  string command = "PYTHONPATH='/home/" + projectUser + "/pythondeps/' ";
  command += "python3 run_atarashi_scan_on_files.py";

  command += " --agent " + state.getAgentName();
  if (!state.getSimilarityMethod().empty()) {
    command += " --similarity " + state.getSimilarityMethod();
  }
  if (state.isVerbose()) {
    command += " --verbose";
    std::cout << "[Atarashi] Verbose: running on file list: " << fileLocation << "\n";
    std::cout << "[Atarashi] Executing command: " << command 
              << " " << fileLocation << " " << outputFile << "\n";
  }

  command += " " + fileLocation + " " + outputFile + " 2>/tmp/atarashi_err.log";

  int returnValue = system(command.c_str());
  char cwd[1024]; // Buffer to store the directory path

  if (getcwd(cwd, sizeof(cwd)) != NULL) {
      std::cout << "Current working directory: " << cwd << std::endl;
  } else {
      perror("getcwd() error"); // Prints error if getcwd() fails
  }

  if (returnValue != 0) {
    std::cerr << "[Atarashi] Could not execute command: " << command << std::endl;
    bail(1);
    return false;
  }

  return true;
}

vector<LicenseMatch> extractLicensesFromAtarashiResult(const string& jsonContent) {
  Json::CharReaderBuilder json_reader_builder;
  auto scanner = std::unique_ptr<Json::CharReader>(json_reader_builder.newCharReader());
  Json::Value atarashiResultObj;
  string errs;
  bool parseSuccess = scanner->parse(
      jsonContent.c_str(),
      jsonContent.c_str() + jsonContent.size(),
      &atarashiResultObj,
      &errs);

  if (!parseSuccess) {
    LOG_FATAL("Failed to parse Atarashi result JSON: %s \n", jsonContent.c_str());
    bail(-30);
  }

  vector<LicenseMatch> matches;
  Json::Value resultArray = atarashiResultObj["results"];
  for (unsigned int index = 0; index < resultArray.size(); ++index) {
    Json::Value resultObject = resultArray[index];
    LicenseMatch m(resultObject["shortname"].asString(),
                   static_cast<unsigned>(resultObject["sim_score"].asDouble() * 100.0));
    matches.push_back(m);
  }
  return matches;
}
