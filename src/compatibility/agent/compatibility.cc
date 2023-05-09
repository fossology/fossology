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

#include <stdexcept>
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

/**
 * \brief Check and print license compatibility of scan result.
 * \param lic_type_loc    Location of CSV holding license types
 * \param rule_loc        Location of YAML holding rules
 * \param json            Is JSON output required?
 * \param lic_result_json Location of JSON holding scan results
 * \param main_license    Main license for the package to use
 */
void check_file_compatibility(const string& lic_type_loc,
                              const string& rule_loc, bool json,
                              const string& lic_result_json,
                              const string& main_license)
{
  bool printComma = false;

  set<string> all_license_list;
  const unordered_map<string, string>& license_map =
      initialize_license_map(lic_type_loc);
  const map<tuple<string, string, string, string>, bool>& rule_list =
      initialize_rule_list(rule_loc);
  map<tuple<string, string>, bool> compatibility_results;

  auto main_license_set = mainLicenseToSet(main_license);

  if (json)
  {
    cout << "[\n";
  }

  Json::Value root;
  std::ifstream ifs(lic_result_json.c_str());
  ifs >> root;
  ifs.close();

  for (const auto& fileItems : root["results"]) // iterating the json_file
  {
    const string fileName = fileItems["file"].asString();
    set<string> file_license_set = set<string>(main_license_set);
    for (const auto& license : fileItems["licenses"]) // iterating the
                                                      // license array
    {
      string str = license.asString();
      if (str == "Dual-license" || str == "No_license_found" || str == "Void")
      {
        continue;
      }
      file_license_set.insert(str);
      all_license_list.insert(str);
    }
    if (file_license_set.size() > 1) // Compute only if file has > 1 license
    {
      vector<tuple<string, string, bool>> result;
      result = checkCompatibility(file_license_set, license_map, rule_list,
                                  compatibility_results);
      if (json)
      {
        appendToJson(result, fileName, printComma);
      }
      else
      {
        printResultToStdout(result, fileName);
      }
    }
  }
  vector<tuple<string, string, bool>> result;
  string name = "null";
  result = checkCompatibility(all_license_list, license_map, rule_list,
                              compatibility_results);
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
    cout << "\n]\n";
  }
}

int main(int argc, char** argv)
{
  CompatibilityCliOptions cliOptions;
  vector<string> licenseNames;
  string lic_types, rule, jFile, mainLicense;
  if (!parseCliOptions(argc, argv, cliOptions, lic_types, rule, jFile,
                       mainLicense))
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
      int groupId = fo_scheduler_groupID();

      if (uploadId == 0)
      {
        continue;
      }

      int arsId = writeARS(state, 0, uploadId, 0, dbManager);

      if (arsId <= 0)
      {
        bail(5);
      }

      if (!processUploadId(state, uploadId, databaseHandler, groupId))
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
    try
    {
      check_file_compatibility(lic_types, rule, json, jFile, mainLicense);
    }
    catch (invalid_argument& e)
    {
      cerr << e.what();
      return -1;
    }
  }
  return 0;
}


/**
 * \brief Check the licenses against rules and get the compatibility result.
 * \param first_name  Name of the license 1
 * \param first_type  Type of the license 1
 * \param second_name Name of the license 2
 * \param second_type Type of the license 2
 * \param rule_list Map of the compatibility rules
 * \return True or False based on rule list or default value.
 */
bool are_licenses_compatible(const string& first_name,
    const string& first_type, const string& second_name,
    const string& second_type,
    const map<tuple<string, string, string, string>, bool>& rule_list)
{
  auto result_check =
      rule_list.find(make_tuple(first_name, "", second_name, ""));
  if (result_check == rule_list.end())
  {
    result_check = rule_list.find(make_tuple(second_name, "", first_name, ""));
  }
  if (result_check != rule_list.end())
  {
    return result_check->second;
  }
  result_check = rule_list.find(make_tuple("", first_type, "", second_type));
  if (result_check == rule_list.end())
  {
    result_check = rule_list.find(make_tuple("", second_type, "", first_type));
  }
  if (result_check != rule_list.end())
  {
    return result_check->second;
  }
  result_check = rule_list.find(make_tuple(first_name, "", "", second_type));
  if (result_check == rule_list.end())
  {
    result_check = rule_list.find(make_tuple("", first_type, second_name, ""));
  }
  if (result_check != rule_list.end())
  {
    return result_check->second;
  }
  auto default_value = rule_list.find(make_tuple("~", "~", "~", "~"));
  if (default_value == rule_list.end())
  {
    return false;
  }
  else
  {
    return default_value->second;
  }
}

/**
 * @brief find the compatibility for cli mode
 * @param license_set containing licenses present in the file
 * @param license_map  map of license name and its type
 * @param rule_list    Parsed map of YAML rule file
 * @return vector of tuple
 */
vector<tuple<string, string, bool>> checkCompatibility(
    const set<string>& license_set,
    const unordered_map<string, string>& license_map,
    const map<tuple<string, string, string, string>, bool>& rule_list,
    map<tuple<string, string>, bool>& scan_results)
{
  string first_name, first_type, second_name, second_type;
  vector<tuple<string, string, bool>> res;
  vector<string> license_list = vector<string>(license_set.begin(),
                                               license_set.end());

  unsigned int license_len = license_list.size();
  for (unsigned int i = 0; i < (license_len - 1); i++)
  {
    first_name = license_list[i];
    try
    {
      first_type = license_map.at(first_name);
    }
    catch (out_of_range&)
    {
      first_type = "";
    }
    for (unsigned int j = (i + 1); j < license_len; j++)
    {
      second_name = license_list[j];
      try
      {
        second_type = license_map.at(second_name);
      }
      catch (out_of_range&)
      {
        second_type = "";
      }

      auto existing_result = scan_results.find(
          make_tuple(first_name, second_name));
      if (existing_result == scan_results.end())
      {
        existing_result = scan_results.find(
            make_tuple(second_name, first_name));
      }

      if (existing_result != scan_results.end())
      {
        res.emplace_back(first_name, second_name, existing_result->second);
        continue;
      }

      bool compatibility = are_licenses_compatible(first_name, first_type,
                                                   second_name, second_type,
                                                   rule_list);
      res.emplace_back(first_name, second_name, compatibility);
      scan_results[make_tuple(first_name, second_name)] = compatibility;
    }
  }
  return res;
}

/**
 * \brief Get the column index for shortname and licensetype columns from CSV
 * header.
 * \param header        String containing CSV header
 * \param[out] name_col Index of shortname column
 * \param[out] type_col Index of licensetype column
 * \return -1 if shortname not found, -2 if licensetype not found, 0 if both
 * found.
 */
int get_column_ids(const string& header, int& name_col, int& type_col)
{
  stringstream lineStream(header);
  string cell;
  int i = 0;
  while(getline(lineStream, cell, ','))
  {
    string::size_type start = cell.find_first_not_of(' ');
    string::size_type end = cell.find_last_not_of(' ');

    cell = cell.substr(start, end - start + 1);

    if (cell == "shortname")
    {
      name_col = i;
    }
    else if (cell == "licensetype")
    {
      type_col = i;
    }
    i++;
  }
  if (name_col == -1)
  {
    return -1;
  }
  else if (type_col == -1)
  {
    return -2;
  }
  return 1;
}

/**
 * \brief Parse license type CSV and create a map
 * \param file_location Location of the CSV file
 * \return Map with license name as key and type as value
 * \throws invalid_argument If CSV is missing required headers
 */
unordered_map<string, string> initialize_license_map(
    const string& file_location)
{
  std::ifstream ip(file_location);
  string line, name, type;
  unordered_map<string, string> license_type_map;
  int name_column = -1, type_column = -1;
  getline(ip, line);
  int retval = get_column_ids(line, name_column, type_column);
  if (retval != 1)
  {
    throw invalid_argument("missing header `shortname` and/or `licensetype` "
      "from CSV file.");
  }
  while (getline(ip, line)) // parsing the csv file
  {
    stringstream ss(line);
    string cell;
    int i = 0;
    while (getline(ss, cell, ','))
    {
      if (i == name_column)
      {
        name = cell;
      }
      if (i == type_column)
      {
        type = cell;
      }
      i++;
    }
    license_type_map[name] = type;
  }
  return license_type_map;
}

/**
 * \brief Read YAML file of rules and parse it as map.
 *
 * The map has key of tuple (first name, first type, second name, second
 * type) and value as boolean of compatibility result.
 *
 * A special key with tuple (~, ~, ~, ~) holds the default value.
 * \param file_location Location to rule YAML
 * \return Map of rules
 */
map<tuple<string, string, string, string>, bool> initialize_rule_list(
    const string& file_location)
{
  YAML::Node root = YAML::LoadFile(file_location);
  map<tuple<string, string, string, string>, bool> rule_map;

  for (const auto& yml_rule : root["rules"]) // iterating the yml.rules
  {
    string first_type, second_type, first_name, second_name;
    bool ans = false;
    for (const auto& tag : yml_rule)
    {
      string first = tag.first.as<string>();
      if (first == "comment")
      {
        continue;
      }
      string second;
      if (tag.second.IsNull())
      {
        second = "~";
      }
      else
      {
        second = tag.second.as<string>();
      }
      if (first == "compatibility")
      {
        ans = second == "true";
      }
      else if (first == "firsttype" && (second != "~"))
      {
        first_type = second;
      }
      else if (first == "secondtype" && (second != "~"))
      {
        second_type = second;
      }
      else if (first == "firstname" && (second != "~"))
      {
        first_name = second;
      }
      else if (first == "secondname" && (second != "~"))
      {
        second_name = second;
      }
    }
    rule_map[make_tuple(first_name, first_type, second_name, second_type)] = ans;
  }
  rule_map[make_tuple("~", "~", "~", "~")] =
      root["default"].as<string>() == "true";
  return rule_map;
}
