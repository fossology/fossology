/*********************************************************************
Copyright (C) 2014, Siemens AG

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
version 2 as published by the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License along
with this program; if not, write to the Free Software Foundation, Inc.,
51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*********************************************************************/

#include "cleanEntries.hpp"
#include <openssl/md5.h>


/* Trims a string of any characters provided in the char list */
std::string trim(std::string str, std::string charlist = " \t\f\v\n\r")
{
size_t last;
last = str.find_last_not_of(charlist);
// only contains chars that are being trimmed, return ""
if (last == std::string::npos)
{
  return "";
}

size_t first = str.find_first_not_of(charlist);
if (first == std::string::npos)
{
  first = 0;
}

return str.substr(first, (last-first)+1);
}



void print_md5_sum(unsigned char* md) {
    int i;
    for(i=0; i <MD5_DIGEST_LENGTH; i++) {
            printf("%02x",md[i]);
    }
}

void getMD5(DatabaseEntry& input){
  /* Step 1B: rearrange copyright statements to try and put the holder first,
   * followed by the rest of the statement, less copyright years.
   */
  /* Not yet implemented
   if ($row['type'] == 'statement') $content = $this->StmtReorder($content);
   */
  //This has to be calculated from the database
  //$row['copyright_count'] = 1;
  //TODO find a nicer way to write this...
  unsigned char* result = NULL;
  const unsigned char* tmp = reinterpret_cast<const unsigned char*>(input.content.c_str());
  result = MD5(tmp, strlen(input.content.c_str()), result);
  if (result)
  {

    char mdString[33];

     for(int i = 0; i < 16; i++)
          sprintf(&mdString[i*2], "%02x", (unsigned int)result[i]);

    input.hash = std::string(mdString);
  }
}

/**
 *
 * @return true if entry needs to be written to database
 */
bool CleanDatabaseEntry(DatabaseEntry& input) {

  std::string newtext = " ";

  input.content = boost::regex_replace(input.content, boost::regex("[\\x0-\\x1f]"), newtext);

    //This is ugly, we should not use strings, neither here nor in the database to distinguish types
  if (input.type.compare("statement") == 0 )
  {
    /* !"#$%&' */
    input.content = boost::regex_replace(input.content, boost::regex("([\\x21-\\x27])|([*@])"), newtext);
    /*  numbers-numbers, two or more digits, ', ' */

    input.content = boost::regex_replace(input.content, boost::regex("(([0-9]+)-([0-9]+))|([0-9]{2,})|(,)"), newtext);
    input.content = boost::regex_replace(input.content, boost::regex(" : "), newtext);// free :, probably followed a date
  }
  else
  if (input.type.compare("email") == 0 )
  {
    //$content = str_replace(":;<=>()", " ", $content);
    // I do not understand the above, I would assume they want to replace any of the characters with space
    // but the function replaces the sequence ...
    // This is a slow variant that does that. We need some | if we want to replace all of them
    input.content = boost::regex_replace(input.content, boost::regex(":;<=>()"), newtext);

  }

  /* remove double spaces */
  input.content = boost::regex_replace(input.content, boost::regex("\\s\\s+"), newtext);
  /* remove leading/trailing whitespace and some punctuation */
  input.content = trim(input.content, "\t \n\r<>./\"\'");


  /* remove leading "dnl " */
  if ((strlen(input.content.c_str()) > 4) &&
  (input.content.compare(0, 4,"dnl ") == 0))
    input.content = input.content.substr(4);

  /* skip empty content */
  if (input.content.size()==0) return false;

  /* Step 1B: rearrange copyright statments to try and put the holder first,
   * followed by the rest of the statement, less copyright years.
  */
  /* Not yet implemented
   if ($row['type'] == 'statement') $content = $this->StmtReorder($content);
  */

  //This has to be calculated from the database
  //$row['copyright_count'] = 1;

  //TODO find a nicer way to write this...
  getMD5(input);
//  $row['hash'] = md5($row['content']);

  return true;

}
