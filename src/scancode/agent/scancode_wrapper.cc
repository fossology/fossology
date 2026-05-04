/*
 SPDX-FileCopyrightText: © 2021 Sarita Singh <saritasingh.0425@gmail.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

#include "scancode_wrapper.hpp"

extern "C" {
#include "libfossology.h"
}

#define MINSCORE 50

/**
 * @brief converts start line to start UChar16 offset of the matched text
 *
 * Locates the match in the file by line number and text search (byte-level),
 * then converts the resulting byte offset to a UTF-16 code unit (UChar16)
 * offset so that it is consistent with the ICU-based in-house agent output.
 *
 * @param filename   name of the file uploaded
 * @param start_line start line of the matched text by scancode
 * @param match_text text in the codefile matched by scancode
 * @return UChar16 offset of the matched text on success, 0 on failure
 */
unsigned getFilePointer(const string &filename, size_t start_line,
                        const string &match_text) {
  ifstream checkfile(filename);
  string str;
  if (checkfile.is_open()) {
    for (size_t i = 0; i < start_line - 1; i++) {
      getline(checkfile, str);
    }
    unsigned int file_p = checkfile.tellg();
    getline(checkfile, str);
    unsigned int pos = str.find(match_text);
    if (pos != string::npos) {
      size_t byteOffset = file_p + pos;

      /* Convert the byte offset to a UChar16 offset by reading the file
       * prefix in binary mode and counting UTF-16 code units. */
      ifstream binFile(filename, ios::binary);
      vector<unsigned char> buf(byteOffset);
      binFile.read(reinterpret_cast<char*>(buf.data()), byteOffset);
      size_t bytesRead = static_cast<size_t>(binFile.gcount());
      return static_cast<unsigned>(fo_utf8ByteLenToUChar16Len(buf.data(), bytesRead));
    } else {
      LOG_NOTICE("Failed to find startbyte for %s\n", filename.c_str());
    }
  }
  return 0;
}


/**
 * @brief scan file with scancode-toolkit
 *
 * using cli command for custom template
 * scancode <scancode flags> --custom-output <output> --custom-template scancode_template.html <input>
 * scancode is a parametric agent, depending upon user's choice flags will be set.
 * -l flag scans for license
 * -c flag scans for copyright and holder
 * license score in ScanCode is percentage and
 * copyright holder in scancode is author in FOSSology
 * The option --license-text is a sub-option of and requires the option --license, it provides the text
 * in the upload file matched with the scancode license rule.
 * custom template provide only those information which
 * user wants to see.
 * --quiet helps to remove summary and/or progress message
 *
 * @param state  an object of class State which can provide agent Id and CliOptions
 * @param file  code/binary file sent by scheduler
 * @return scanned data output on success, null otherwise
 *
 * @see https://scancode-toolkit.readthedocs.io/en/latest/cli-reference/list-options.html#all-basic-scan-options
 */
void scanFileWithScancode(const State &state, string fileLocation, string outputFile) {
  string projectUser = fo_config_get(sysconfig, "DIRECTORIES", "PROJECTUSER",
  NULL);
  string cacheDir = fo_config_get(sysconfig, "DIRECTORIES", "CACHEDIR",
  NULL);

  string command =
    "PYTHONPATH='/home/" + projectUser + "/pythondeps/' " +
    "python3 runscanonfiles.py -" + state.getCliOptions() + " " +
    ((state.getCliOptions().find('l') != string::npos) ? "-m " +
    to_string(MINSCORE): "") + " " + fileLocation + " " + outputFile;

  int returnvalue = system(command.c_str());

  if (returnvalue != 0) {
    LOG_FATAL("could not execute scancode command: %s \n", command.c_str());
    bail(1);
  }

  if (unlink(fileLocation.c_str()) != 0)
  {
    LOG_FATAL("Unable to delete file %s \n", fileLocation.c_str());
  }
}

/**
 * @brief extract data from scancode scanned result
 *
 * In licenses array:
 * spdx_license_key-> license spdx key
 * score-> score of a rule to matched with the output licenes
 * name-> license full name
 * text_url-> license text reference url
 * matched_text-> text in code file matched for the license
 * start_line-> matched text start line
 *
 * Incase there is no license found by scancode,
 * FOSSology has "No_license_found" license short name.
 *
 * In copyright array:
 * value-> copyright statement
 * start-> start line of copyright statement
 *
 * In holder(copyright holder) array:
 * value-> copyright holder name(author in FOSSology)
 * start-> start line of copyright holder
 *
 * @param scancodeResult  scanned result by scancode
 * @param filename        name of the file uploaded
 * @return map having key as type of scanned and value as content for the type
 */

map<string, vector<Match>> extractDataFromScancodeResult(const string& scancodeResult, const string& filename) {
  Json::CharReaderBuilder json_reader_builder;
  auto scanner = unique_ptr<Json::CharReader>(json_reader_builder.newCharReader());
  Json::Value scancodevalue;
  string errors;
  const bool isSuccessful = scanner->parse(scancodeResult.c_str(),
      scancodeResult.c_str() + scancodeResult.length(), &scancodevalue, &errors);
  map<string, vector<Match>> result;
  vector<Match> licenses;
  if (isSuccessful) {
    Json::Value licensearrays = scancodevalue["licenses"];
    if(licensearrays.empty())
    {
      result["scancode_license"].push_back(Match("No_license_found"));
    }
    else
    {
      for (auto oneresult : licensearrays)
      {
          string licensename = oneresult["license_expression_spdx"].asString();
          int percentage = (int)oneresult["score"].asFloat();
          string full_name=oneresult["license_expression"].asString();
          string text_url=oneresult["rule_url"].asString();
          string match_text = oneresult["matched_text"].asString();
          unsigned long start_line=oneresult["start_line"].asUInt();
          string temp_text= match_text.substr(0,match_text.find("\n"));
          unsigned start_pointer = getFilePointer(filename, start_line, temp_text);
          unsigned length = static_cast<unsigned>(fo_utf8ByteLenToUChar16Len(
              reinterpret_cast<const unsigned char*>(match_text.c_str()),
              match_text.length()));
          result["scancode_license"].push_back(Match(licensename,percentage,full_name,text_url,start_pointer,length));
      }
    }

    Json::Value copyarrays = scancodevalue["copyrights"];
    for (auto oneresult : copyarrays) {
        string copyrightname = oneresult["value"].asString();
        unsigned long start_line=oneresult["start"].asUInt();
        string temp_text= copyrightname.substr(0,copyrightname.find("[\n\t]"));
        unsigned start_pointer = getFilePointer(filename, start_line, temp_text);
        unsigned length = static_cast<unsigned>(fo_utf8ByteLenToUChar16Len(
            reinterpret_cast<const unsigned char*>(copyrightname.c_str()),
            copyrightname.length()));
        string type="scancode_statement";
        result["scancode_statement"].push_back(Match(copyrightname,type,start_pointer,length));
    }

    Json::Value holderarrays = scancodevalue["holders"];
    for (auto oneresult : holderarrays) {
        string holdername = oneresult["value"].asString();
        unsigned long start_line=oneresult["start"].asUInt();
        string temp_text= holdername.substr(0,holdername.find("\n"));
        unsigned start_pointer = getFilePointer(filename, start_line, temp_text);
        unsigned length = static_cast<unsigned>(fo_utf8ByteLenToUChar16Len(
            reinterpret_cast<const unsigned char*>(holdername.c_str()),
            holdername.length()));
        string type="scancode_author";
        result["scancode_author"].push_back(Match(holdername,type,start_pointer,length));
    }

    Json::Value emailarrays = scancodevalue["emails"];
    for (auto oneresult : emailarrays) {
        string emailname = oneresult["value"].asString();
        unsigned long start_line=oneresult["start"].asUInt();
        string temp_text= emailname.substr(0,emailname.find("\n"));
        unsigned start_pointer = getFilePointer(filename, start_line, temp_text);
        unsigned length = static_cast<unsigned>(fo_utf8ByteLenToUChar16Len(
            reinterpret_cast<const unsigned char*>(emailname.c_str()),
            emailname.length()));
        string type="scancode_email";
        result["scancode_email"].push_back(Match(emailname,type,start_pointer,length));
    }

    Json::Value urlarrays = scancodevalue["urls"];
    for (auto oneresult : urlarrays) {
        string urlname = oneresult["value"].asString();
        unsigned long start_line=oneresult["start"].asUInt();
        string temp_text= urlname.substr(0,urlname.find("\n"));
        unsigned start_pointer = getFilePointer(filename, start_line, temp_text);
        unsigned length = static_cast<unsigned>(fo_utf8ByteLenToUChar16Len(
            reinterpret_cast<const unsigned char*>(urlname.c_str()),
            urlname.length()));
        string type="scancode_url";
        result["scancode_url"].push_back(Match(urlname,type,start_pointer,length));
    }
  } else {
    LOG_FATAL("JSON parsing failed %s \n", errors.c_str());
  }
  return result;
}
