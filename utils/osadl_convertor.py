#!/usr/bin/env python3
#  SPDX-FileCopyrightText: © 2024 Siemens AG
#  SPDX-FileContributor: Gaurav Mishra <mishra.gaurav@siemens.com>
#
#  SPDX-License-Identifier: GPL-2.0-only

import argparse
import json
import logging
import textwrap
import time
from typing import Union, Optional

import osadl_matrix
import psycopg2
import yaml

logging.basicConfig()

logger = logging.getLogger("osadl_convertor")
logger.setLevel(logging.INFO)


class MatrixItem:
  """
  Class to hold information about a single rule.
  :ivar __first_license: First license of the rule.
  :ivar __second_license: Second license of the rule.
  :ivar __first_type: First type of the license.
  :ivar __second_type: Second type of the license.
  :ivar __result: Compatibility result of the rule.
  :ivar __comment: Comment on the rule.
  """
  def __init__(self):
    self.__first_license: Optional[str] = None
    self.__second_license: Optional[str] = None
    self.__first_type: Optional[str] = None
    self.__second_type: Optional[str] = None
    self.__result: Optional[Union[bool, osadl_matrix.OSADLCompatibility]] = \
        osadl_matrix.OSADLCompatibility.NO
    self.__comment: str = ""

  @property
  def first_license(self) -> Optional[str]:
    """
    Get name of the first license.
    """
    return self.__first_license

  @first_license.setter
  def first_license(self, first_license: str) -> None:
    """
    Set name of the first license.
    """
    self.__first_license = first_license

  @property
  def second_license(self) -> Optional[str]:
    """
    Get name of the second license.
    """
    return self.__second_license

  @second_license.setter
  def second_license(self, second_license: str) -> None:
    """
    Set name of the second license.
    """
    self.__second_license = second_license

  @property
  def first_type(self) -> Optional[str]:
    """
    Get type of the first license.
    """
    return self.__first_type

  @first_type.setter
  def first_type(self, first_type: Optional[str]) -> None:
    """
    Set type of the first license.
    """
    self.__first_type = first_type

  @property
  def second_type(self) -> Optional[str]:
    """
    Get type of the second license.
    """
    return self.__second_type

  @second_type.setter
  def second_type(self, second_type: Optional[str]) -> None:
    """
    Set type of the second license.
    """
    self.__second_type = second_type

  @property
  def result(self) -> bool:
    """
    Get result of the rule as boolean.
    """
    if isinstance(self.__result, bool):
      return self.__result
    if self.__result == osadl_matrix.OSADLCompatibility.YES \
        or self.__result == osadl_matrix.OSADLCompatibility.CHECKDEP:
      return True
    return False

  @result.setter
  def result(self,
             result: Union[osadl_matrix.OSADLCompatibility, bool]) -> None:
    """
    Set result of the rule. It can be boolean or an object of OSADLCompatibility
    enum.
    """
    self.__result = result

  @property
  def comment(self) -> str:
    """
    Get comment on the rule.
    :return:
    """
    return self.__comment

  @comment.setter
  def comment(self, comment: str) -> None:
    """
    Set comment on the rule.
    """
    self.__comment = comment

  def __eq__(self, other) -> bool:
    """
    Two rules are equal if:

    - They talk about the same licenses and have same result.
    - They talk about the same license types and have same result.
    """
    if (
      (
        self.first_license is not None and
        self.second_license is not None and
        other.first_license is not None and
        other.second_license is not None
      ) and ((
        self.first_license == other.first_license and
        self.second_license == other.second_license
      ) or (
        self.first_license == other.second_license and
        self.second_license == other.first_license
      ))
    ) or ((
      self.first_type is not None and
      self.second_type is not None and
      other.first_type is not None and
      other.second_type is not None
    ) and ((
      self.first_type == other.first_type and
      self.second_type == other.second_type
    ) or (
      self.first_type == other.second_type and
      self.second_type == other.first_type
    ))) and self.result == other.result:
      return True
    return False

  def __repr__(self):
    return f"{self.__class__.__name__}(firstname={self.first_license}, " \
           f"secondname={self.second_license}, firsttype={self.first_type}, " \
           f"secondtype={self.second_type}, compatibility={self.result}, " \
           f"comment={self.comment})"


def compliance_representer(dumper: yaml.Dumper, data: MatrixItem) -> yaml.Node:
  """
  Represent MatrixItem (rules) in format FOSSology understands as a YAML map.
  """
  value = [
    (dumper.represent_data("firstname"),
     dumper.represent_data(data.first_license)),
    (dumper.represent_data("secondname"),
     dumper.represent_data(data.second_license)),
    (dumper.represent_data("firsttype"),
     dumper.represent_data(data.first_type)),
    (dumper.represent_data("secondtype"),
     dumper.represent_data(data.second_type)),
    (dumper.represent_data("compatibility"),
     dumper.represent_data(data.result)),
    (dumper.represent_data("comment"),
     dumper.represent_data(data.comment))
  ]

  return yaml.nodes.MappingNode(u"tag:yaml.org,2002:map", value)


class LicenseHandler:
  """
  Handle license information from FOSSology.
  """
  def __init__(self, host: str, port: str, user: str, password: str,
               database: str):
    """
    Create connection to DB.
    :param host: Host of the database.
    :param port: Port of the database.
    :param user: User of the database.
    :param password: Password of the database.
    :param database: Name of the database.
    """
    self.__conn = psycopg2.connect(dbname=database, user=user,
                                   password=password, host=host, port=port)

  def get_license_type(self, license_name: str) -> Optional[str]:
    """
    Get the type of the license from DB.
    :param license_name: Name of the license to get type for.
    :return: Type of the license if found, None otherwise.
    """
    cur = self.__conn.cursor()
    cur.execute("SELECT rf_licensetype FROM license_ref WHERE "
                "lower(rf_shortname) = lower(%s);", (license_name,))
    resp = cur.fetchone()
    if resp is not None:
      return resp[0]
    return None

  def different_type_exists(self) -> bool:
    """
    Check if different type of licenses exists in DB.

    Check if threshold of "Permissive" or None license type (default type) is
    bellow 80% of the total licenses in database.
    :return: True if threshold is bellow 80%, False otherwise.
    """
    cur = self.__conn.cursor()
    cur.execute("SELECT rf_licensetype, count(*) AS count "
                "FROM license_ref GROUP BY rf_licensetype;")
    resp: list[tuple[Optional[str], int]] = cur.fetchall()
    if len(resp) < 2:
      return False
    total_count = 0
    permissive_count = 0
    for row in resp:
      type_name = "None" if row[0] is None else row[0]
      type_count = row[1]
      total_count += type_count
      if type_name == "Permissive" or type_name == "None":
        permissive_count += type_count
    return (permissive_count / total_count) < 0.8

  def get_license_types(self) -> list[Optional[str]]:
    """
    Get list of different license types from database.
    :return: List of license types in DB.
    """
    cur = self.__conn.cursor()
    cur.execute("SELECT DISTINCT rf_licensetype FROM license_ref;")
    resp: list[tuple[Optional[str]]] = cur.fetchall()
    type_list: list[Optional[str]] = []
    for row in resp:
      if row is not None:
        type_list.append(row[0])
    return type_list

  def license_exists(self, license_name: str) -> bool:
    """
    Check if there is a license with the given name in database or not.
    :param license_name: Name to check
    :return: True if exists, False otherwise
    """
    cur = self.__conn.cursor()
    cur.execute("SELECT 1 FROM license_ref WHERE "
                "lower(rf_shortname) = lower(%s);", (license_name,))
    resp = cur.fetchone()
    if resp is not None:
      return resp[0] == 1
    return False


def remove_items(compatibility_matrix: list[MatrixItem],
                 first_type: Optional[str], second_type: Optional[str],
                 result: bool) -> list[MatrixItem]:
  """
  Given the type of licenses and result of the rule, remove them from the list.

  Stores licenses if:
  - They are based on only types (already filtered)
  - Their license types and results do not match.
  :param compatibility_matrix: List of rules to filter from.
  :param first_type:  First license type for removal.
  :param second_type: Second license type for removal.
  :param result:      Result of rule for removal.
  :return: Filtered list of rules.
  """
  return [item for item in compatibility_matrix
          if (
            item.first_license is None and item.second_license is None
          ) or (((
            item.first_type != first_type or item.second_type != second_type
          ) and (
            item.first_type != second_type or item.second_type != first_type
          )) or item.result != result)]

def remove_type_for_license(compatibility_matrix: list[MatrixItem]) \
      -> list[MatrixItem]:
  """
  Remove license type information from rules if they contain licenses. It
  should be called after remove_items().
  :param compatibility_matrix: List of rules.
  :return: List of rules with license types removed.
  """
  new_list = []
  for item in [item for item in compatibility_matrix
               if item.first_license is not None]:
    item.first_type = None
    item.second_type = None
    new_list.append(item)
  new_list.extend([item for item in compatibility_matrix
                   if item.first_license is None])
  return new_list


def reduce_matrix(license_handler: LicenseHandler,
                  compatibility_matrix: list[MatrixItem],
                  type_dict: dict[tuple[str, str, bool], int]):
  """
  Reduce the original list of rules by combining rules based on license types
  and other criteria. The function also checks if the license type threshold
  is passed to reduce the list based on types. If not, it simply removes the
  license types from the rules.

  Requires dictionary of license type and result in following format:

  ```
  { ('license_type_1', 'license_type_2', result<bool>): count of occurrences }
  ```
  :param license_handler: Object of LicenseHandler
  :param compatibility_matrix: List of rules
  :param type_dict: Dictionary storing information about license type and
                    result counts.
  :return: Reduced list of rules.
  """
  if not license_handler.different_type_exists():
    for item in compatibility_matrix:
      item.first_type = None
      item.second_type = None
    return compatibility_matrix

  license_types = license_handler.get_license_types()
  reduced_list = compatibility_matrix
  for license_type_first in license_types:
    for license_type_second in license_types:
      true_count = 0
      false_count = 0
      if (license_type_first, license_type_second, True) in type_dict:
        true_count = type_dict[(license_type_first, license_type_second, True)]
      elif (license_type_second, license_type_first, True) in type_dict:
        true_count = type_dict[(license_type_second, license_type_first, True)]
      if (license_type_first, license_type_second, False) in type_dict:
        false_count = type_dict[(license_type_first, license_type_second,
                                 False)]
      elif (license_type_second, license_type_first, False) in type_dict:
        false_count = type_dict[(license_type_second, license_type_first,
                                 False)]
      max_type = true_count > false_count
      reduced_list = remove_items(reduced_list, license_type_first,
                                  license_type_second, max_type)
      type_only_item = MatrixItem()
      type_only_item.first_type = license_type_first
      type_only_item.second_type = license_type_second
      type_only_item.result = max_type
      type_only_item.comment = f"{type_only_item.first_type} -> " \
                               f"{type_only_item.second_type} -> " \
                               f"{type_only_item.result}"
      reduced_list.append(type_only_item)
  return remove_type_for_license(reduced_list)


def save_yaml(location: str, compliance_matrix: list[MatrixItem]) -> None:
  with open(location, "w") as save_file:
    yaml.add_representer(MatrixItem, compliance_representer)
    yaml.dump({
      "default": False,
      "rules": compliance_matrix
    }, save_file)
    logger.info(f"Saved {len(compliance_matrix)} rules in {location}.")


def convert_json_to_matrix(license_handler: LicenseHandler, json_loc: str) \
      -> tuple[list[MatrixItem], dict[tuple[str, str, bool], int]]:
  """
  Convert the OSADL matrix JSON from library into list of rules. The rules
  are made sure to be not duplicated. The type of license is also added to
  the rules.
  :param license_handler: LicenseHandler object
  :param json_loc: Location of OSADL JSON
  :return: List of rules and type dictionary for reduce_matrix()
  """
  matrix: Union[dict[str, dict[str, str]], None] = None
  compatibility_matrix: list[MatrixItem] = []
  type_dict: dict[tuple[str, str, bool], int] = dict()
  with open(json_loc, "r") as jsoninput:
    matrix = json.load(jsoninput)
  if matrix is None:
    raise Exception("Unable to read JSON")
  for first_license, comp_list in matrix.items():
    if first_license in ["timestamp", "timeformat"] or not \
        license_handler.license_exists(first_license):
      continue
    for second_license, result in comp_list.items():
      if not license_handler.license_exists(second_license):
        continue
      row = MatrixItem()
      row.first_license = first_license
      row.second_license = second_license
      row.result = osadl_matrix.OSADLCompatibility.from_text(result)
      row.comment = f"{first_license} -> {second_license} -> {row.result}"
      row.first_type = license_handler.get_license_type(row.first_license)
      row.second_type = license_handler.get_license_type(row.second_license)
      if row not in compatibility_matrix:
        logger.debug(row.comment)
        compatibility_matrix.append(row)
        updated = False
        if (row.first_type, row.second_type, row.result) not in type_dict:
          if (row.second_type, row.first_type, row.result) in type_dict:
            type_dict[(row.second_type, row.first_type, row.result)] += 1
            updated = True
        if not updated:
          type_dict[(row.first_type, row.second_type, row.result)] = \
            type_dict.get((row.first_type, row.second_type, row.result), 0) + 1
  return compatibility_matrix, type_dict


def main(parsed_args):
  start_time = time.time()
  license_handler = LicenseHandler(parsed_args.host, parsed_args.port,
                                   parsed_args.user, parsed_args.password,
                                   parsed_args.database)
  compatibility_matrix, type_dict = convert_json_to_matrix(
    license_handler, osadl_matrix.OSADL_MATRIX_JSON)
  reduce_start = int(round(time.time() * 1000))
  reduced_list = reduce_matrix(license_handler, compatibility_matrix, type_dict)
  reduce_end = int(round(time.time() * 1000))
  save_yaml(parsed_args.yaml, reduced_list)
  time_taken = time.time() - start_time
  logger.info(f"Took {(reduce_end - reduce_start):.2f} ms for reducing list.")
  logger.info(f"Took {time_taken:.2f} seconds for processing.")


if __name__ == "__main__":
  parser = argparse.ArgumentParser(
    description=textwrap.dedent("""
      Convert OSADL matrix to FOSSology's compatibility YAML
    """)
  )
  parser.add_argument(
    "--user", type=str, help="Database username", default="fossy"
  )
  parser.add_argument(
    "--password", type=str, help="Database password", required=True
  )
  parser.add_argument(
    "--database", type=str, help="Database name", default="fossology"
  )
  parser.add_argument(
    "--host", type=str, help="Database host", default="localhost"
  )
  parser.add_argument(
    "--port", type=str, help="Database port", default="5432"
  )
  parser.add_argument(
    "--yaml", type=str, help="Location to store result file", required=True
  )
  parser.add_argument(
    "-d", "--debug", action="store_true", help="Increase verbosity",
    default=False
  )
  args = parser.parse_args()
  if args.debug:
    logger.setLevel(logging.DEBUG)
  main(args)
