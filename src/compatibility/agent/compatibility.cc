/*
 SPDX-FileCopyrightText: © 2024 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @dir
 * @brief The COMPATIBILITY agent
 * @file
 * @brief Entry point for compatibility agent
 * @page compatibility COMPATIBILITY Agent
 * @tableofcontents
 *
 * Compatibility agent to find the compatibility between licenses
 * present in a file.
 * Can run in scheduler as well as cli mode
 *
 * The agent runs in multi-threaded mode and creates a new thread for
 * every pfile for faster processing.
 *
 * @section compatibilityactions Supported actions
 * | Command line flag | Description |
 * | ---: | :--- |
 * | -h [--help] | Shows help |
 * | -v [--verbose] | Increase verbosity |
 * | --file arg | Json File to scan |
 * | -J [--json] | Output JSON |
 * | --types arg | CSV File to scan |
 * | --rules arg | YAML File to scan |
 * | -c [ --config ] arg | Path to the sysconfigdir |
 * | --scheduler_start | Specifies, that the command was called by the |
 * || scheduler |
 * | --userID arg | The id of the user that created the job (only in |
 * || combination with --scheduler_start) |
 * | --groupID arg | The id of the group of the user that created the job |
 * || (only in combination with --scheduler_start) |
 * | --jobId arg | The id of the job (only in combination with |
 * || --scheduler_start) |
 * @section compatibilitysource Agent source
 *   - @link src/compatibility/agent @endlink
 *   - @link src/compatibility/ui @endlink
 */

#include "compatibility.hpp"

using namespace fo;

/**
 * @def return_sched(retval)
 * Send disconnect to scheduler with retval and return function with retval.
 */
#define return_sched(retval) \
  do \
  { \
    fo_scheduler_disconnect((retval)); \
    return (retval); \
  } while (0)

int main(int argc, char** argv)
{
  CompatibilityCliOptions cliOptions;
  vector<string> licenseNames;
  string lic_types, rule, jFile;
  if (!parseCliOptions(argc, argv, cliOptions, lic_types, rule, jFile))
  {
    return_sched(1);
  }

  bool json = cliOptions.doJsonOutput();
  CompatibilityState state = getState(std::move(cliOptions));

  if (jFile.empty())
  {
    DbManager dbManager(&argc, argv);
    CompatibilityDatabaseHandler databaseHandler(dbManager);

    state.setAgentId(queryAgentId(dbManager));

    while (fo_scheduler_next() != nullptr)
    {
      int uploadId = atoi(fo_scheduler_current());

      if (uploadId == 0)
      {
        continue;
      }

      int arsId = writeARS(state, 0, uploadId, 0, dbManager);

      if (arsId <= 0)
      {
        bail(5);
      }

      if (!processUploadId(state, uploadId, databaseHandler))
      {
        bail(2);
      }

      fo_scheduler_heart(0);
      writeARS(state, arsId, uploadId, 1, dbManager);
    }
    fo_scheduler_heart(0);

    /* do not use bail, as it would prevent the destructors from running */
    fo_scheduler_disconnect(0);
  }
  else
  {
    bool fileError = false;
    bool printComma = false;

    string main_name, main_type, sub_name, sub_type;
    set<string> allLic;
    if (json)
    {
      cout << "[" << endl;
    }

    Json::Value root;
    std::ifstream ifs(jFile.c_str());
    ifs >> root;

    for (const auto& fileItems : root["results"]) // iterating the json_file
    {
      const string fileName = fileItems["file"].asString();
      vector<string> myVec;
      if (fileItems["licenses"].size()
          > 1) // checking if a file contains more than one license
      {
        for (const auto& license :
             fileItems["licenses"]) // iterating the license array
        {
          string str = license.asString();
          if (str == "Dual-license" || str == "No_license_found")
          {
            continue;
          }
          myVec.push_back(str);
          allLic.insert(str);
        }
      }
      vector<tuple<string, string, string>> result;
      result = checkCompatibility(myVec, lic_types, rule);
      if (json)
      {
        appendToJson(result, fileName, printComma);
      }
      else
      {
        printResultToStdout(result, fileName);
      }
    }
    vector<tuple<string, string, string>> result;
    string name = "null";
    result = checkCompatibility(vector<string>(allLic.begin(), allLic.end()),
                                lic_types, rule);
    if (json)
    {
      appendToJson(result, name, printComma);
    }
    else
    {
      printResultToStdout(result, name);
    }

    if (json)
    {
      cout << endl << "]" << endl;
    }
    return fileError ? 1 : 0;
  }
  return 0;
}

/**
 * @brief find the compatibility for cli mode
 * @param myVec containing licenses present in the file
 * @param lic_types containing location of the csv file in which license
 * information is present
 * @param rule containing location of the yaml file in which the rules are
 * defined
 * @return vector of tuple
 */
vector<tuple<string, string, string>> checkCompatibility(
    vector<string> myVec, const string& lic_types, const string& rule)
{
  std::ifstream ip(lic_types.c_str());
  YAML::Node rules = YAML::LoadFile(rule);
  string main_name, main_type, sub_name, sub_type;
  vector<tuple<string, string, string>> res;

  string line, name, type;
  vector<string> license_name;
  vector<string> license_type;

  while (getline(ip, line)) // parsing the csv file
  {
    stringstream ss(line);
    getline(ss, name, ',');
    getline(ss, type, ',');
    license_name.push_back(name);
    license_type.push_back(type);
  }

  unsigned int vecSize = myVec.size();
  for (unsigned int argn = 0; argn < (vecSize - 1); argn++)
  {
    for (unsigned int argn2 = (argn + 1); argn2 < vecSize; argn2++)
    {
      for (unsigned int i = 1; i < license_name.size(); i++)
      {
        if (myVec[argn] == license_name[i])
        {
          main_name = license_name[i];
          main_type = license_type[i];
        }
        if (myVec[argn2] == license_name[i])
        {
          sub_name = license_name[i];
          sub_type = license_type[i];
        }
      }

      int def = 0, priority = 0;

      for (const auto& yml_rfile : rules["rules"]) // iterating the yml.rules
      {
        string type = "", type2 = "", name = "", name2 = "", ans = "";
        int rule1 = 0, rule2 = 0, rule3 = 0, rule4 = 0;
        for (const auto& tag : yml_rfile)
        {
          string first = tag.first.as<string>();
          string second = tag.second.as<string>();
          if (first == "text")
          {
            continue;
          }
          if (first == "compatibility")
          {
            ans = second;
            rule1++;
            rule4++;
            rule2++;
            rule3++;
          }
          if (first == "maintype" && (second != "~"))
          {
            rule1++;
            rule4++;
            type = second;
          }
          if (first == "subtype" && (second != "~"))
          {
            rule1++;
            rule3++;
            type2 = second;
          }
          if (first == "mainname" && (second != "~"))
          {
            rule2++;
            rule3++;
            name = second;
          }
          if (first == "subname" && (second != "~"))
          {
            rule2++;
            rule4++;
            name2 = second;
          }
          if ((rule1 == 3)
              && (main_type == type
                  && sub_type == type2)) // rule1 for maintype and subtype
          {
            res.emplace_back(main_name, sub_name, ans);
            def++;
            continue;
          }
          if ((rule2 == 3)
              && (((main_name == name) || (main_name == name2))
                  && ((sub_name == name)
                      || (sub_name
                          == name2)))) // rule2 for mainname and subname
          {
            res.emplace_back(main_name, sub_name, ans);
            ++priority;
            def++;
            continue;
          }
          if ((rule3 == 3) && (priority == 0)
              && ((main_name == name) || (sub_name == name))
              && ((main_type == type2)
                  || (sub_type == type2))) // rule3 for mainname and subtype
          {
            res.emplace_back(main_name, sub_name, ans);
            def++;
          }
        }
      }
      if (def == 0)
      {
        res.emplace_back(main_name, sub_name, rules["default"].as<string>());
      }
    }
  }
  return res;
}
