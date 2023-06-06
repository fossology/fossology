#!/usr/bin/env python3
'''
 Author: Kaushlendra Pratap Singh
 SPDX-FileCopyrightText: © 2021 Kaushlendra Pratap <kaushlendrapratap.9837@gmail.com>

 SPDX-License-Identifier: GPL-2.0-only
'''
import spacy
import pandas as pd
import argparse
import re
import csv

THE_PROBABLE_LOGIC_POS_CHECK = ['NOUN', 'NUM', 'PROPN', 'PROPN']
THE_PROBABLE_LOGIC_NER_CHECK = ['DATE', 'PERSON', 'CARDINAL', 'ORG']
CLUTTER_REGEX = r"(Copyright|copyright\s*(©)?([\w \-\,\[\]]+)?(\.com)?\.?|Copyright\s*(©)?|©([\w \-\,\.\[\]]+)?(Copyright)?)|(Copyright\s*(\(c\))?|\(c\)\s*(Copyright)?(?:[\w \,\-\.\"\[\]]{2,53}\s*)?\.?|\(c\)\s*(Copyright)?)|Copyright|([\w,]|(\s*\d+(\s(?:\,|-)\s*\d+)?\s*))(\s*\d+(\s*(?:\,|-)\s*\d+)?\,?\s*)\s*[a-zA-Z\&\| ,\s0-9]{3,50}(\.com)?\.?|(\.|\,)?\s*(\@[^>]*?\.com)|Inc+(\.)?| Company+|Corporation+|& Co+|GmbH+|All rights reserved(?:\.)?|Ltd|\<|[\w.]+@[a-zA-Z0-9-_.]+|\>|([A-Za-z0-9]+\.com)|rights\s*reserved|(?:(?:https?|ftp|file):\/\/|www\.|ftp\.)(?:\([-\w+&@#/%=~|$?!:,.]*\)|[-\w+&@#/%=~|$?!:,.])*(?:\([\w+&@#/%=~|$?!:,.]*\)|[\w+&@#/%=~|$])|\<|\>|\(|\)"


def preProcessing(file, clutter_flag):
  with open(file, 'r') as f:
    df = pd.read_json(f, orient='records')
    df['content_lower'] = df['content'].str.lower()
  copyrightDeactivationMain(df, clutter_flag)


def copyrightDeactivationMain(df, clutter_flag):
  nlp = spacy.load("en_core_web_sm")
  # Iterating through each row and doing preprocessing over it.
  # Picking out the manual tags from the csv and putting them into separate column "Original Tag"
  for index in df.index.to_list():
    text = df.loc[index, 'content_lower']
    doc = nlp(text)

    if type(text) == float:
      continue

    # Lemmatization
    lemma_list = []
    for token in doc:
      lemma_list.append(token.lemma_)

    # Filter the stopword
    filtered_list = []
    for word in lemma_list:
      lexeme = nlp.vocab[word]
      if lexeme.is_stop == False:
        filtered_list.append(word)

    # Remove punctuation
    punctuations = "?:!.,;'"
    for word in filtered_list:
      if word in punctuations:
        filtered_list.remove(word)

    # List joining and Filtering (c) and copyright unicode symbol
    copyright_string = " ".join(map(str, filtered_list))
    substring = "( c )"
    cp_symbol = '\xa9'  # Unicode for copyright Symbol

    # Replace ( c ) and the copyright unicode symbol © with "copyright"
    copyright_string = copyright_string.replace(substring, "copyright")
    copyright_string = copyright_string.replace(cp_symbol, "copyright")

    # Implementing NER and POS Tags after normalization
    doc2 = nlp(copyright_string)

    # All the NER taggings will be contained in a dictionary having "Entity" and "Values" as keys
    ent_dict = {}

    full_table_ner = {"Entity": [], "Values": []}

    for x in doc2.ents:
      ent_dict[x.text] = x.label_

    for key in ent_dict:
      full_table_ner["Entity"].append(key)
      full_table_ner["Values"].append(ent_dict[key])

    # All the POS taggings will be contained in a dictionary having "Entity" and "POS_TAGS" as keys
    pos_dict = {}
    full_table_pos = {"Entity": [], "POS_TAG": []}

    for token in doc:
      if not token.is_punct | token.is_space:
        pos_dict[token.text] = token.pos_

    for key in pos_dict:
      full_table_pos["Entity"].append(key)
      full_table_pos["POS_TAG"].append(pos_dict[key])

    # The checking function call happening with each iteration
    entityCheck(full_table_pos, full_table_ner, index, clutter_flag, df)
  print(df.to_json(orient='records'))


def entityCheck(listA, listB, index, clutter_flag, df):
  if set(THE_PROBABLE_LOGIC_POS_CHECK).intersection(set(listA["POS_TAG"])):
    if set(THE_PROBABLE_LOGIC_NER_CHECK).intersection(set(listB["Values"])):
      for _values in listB["Values"]:
        if THE_PROBABLE_LOGIC_NER_CHECK[0] in _values:
          df.loc[index, 'is_copyright'] = "t"
        elif THE_PROBABLE_LOGIC_NER_CHECK[2] in _values:
          for _val in listB["Entity"]:
            pattern_regex = r'''((?:19|20)\d{2}|\d{2})(?!\d)(?:[, \t-]{1,3}((?:19|20)\d{2}|\d{1,2}))?'''
            extract_list = re.search(pattern_regex, _val)
            if extract_list:
              df.loc[index, 'is_copyright'] = "t"
        elif THE_PROBABLE_LOGIC_NER_CHECK[1] in _values or THE_PROBABLE_LOGIC_NER_CHECK[3] in _values:
          if THE_PROBABLE_LOGIC_NER_CHECK[0] in _values:
            df.loc[index, 'is_copyright'] = "t"
    else:
      df.loc[index, 'is_copyright'] = "f"

    if clutter_flag == 1 and df.loc[index, 'is_copyright'] == "t":
      clutterRemoval(df, index, listB)
  return


def clutterRemoval(df, index, ner_list):
  string1 = "all rights reserved"
  string2 = "distributed under the mit software license"
  string3 = df.loc[index, 'content']

  if string1 in string3:
    clutter_removed = string3[:string3.index(string1)]
    df.loc[index, 'edited_text'] = clutter_removed

  elif string2 in string3:
    clutter_removed = string3[:string3.index(string1)]
    df.loc[index, 'edited_text'] = clutter_removed
  org_pos = -1
  person_pos = -1
  if 'ORG' in ner_list['Values']:
    string3 = re.sub(
        r'''[^\w\s()]+''', '', string3)  # I am not sure why this is in only one of the branch
    las_ent = len(ner_list['Values']) - ner_list['Values'][::-
                                                           1].index('ORG') - 1  # Get the last org
    org_name = ner_list['Entity'][las_ent]
    try:
      org_pos = string3.rindex(org_name) + len(org_name)
    except:
      org_pos = -1
  if 'PERSON' in ner_list['Values']:
    # Get the last person
    las_ent = len(ner_list['Values']) - \
        ner_list['Values'][::-1].index('PERSON') - 1
    person_name = ner_list['Entity'][las_ent]
    try:
      person_pos = string3.rindex(person_name) + len(person_name)
    except:
      person_pos = -1
  if org_pos > 0 or person_pos > 0:
    del_upto = max(org_pos, person_pos)
    df.loc[index, 'edited_text'] = string3[:del_upto]
  else:
    clutter_removed = re.finditer(CLUTTER_REGEX, string3, re.MULTILINE)
    clutter_collect = list()
    for _clutter in clutter_removed:
      start1 = _clutter.start()
      end1 = _clutter.end()
      clutter_collect.append(string3[start1:end1])
    clutter_removed = ''.join(clutter_collect)
    if clutter_removed:
      df.loc[index, 'edited_text'] = clutter_removed
    else:
      df.loc[index, 'edited_text'] = string3


if __name__ == "__main__":
  parser = argparse.ArgumentParser()
  parser.add_argument(
      "-f", "--file", help="File to be processed", required=True)
  parser.add_argument("-c", "--clutter",
                      help="Integer Flag for clutter removal", required=True)
  args = parser.parse_args()
  file = args.file
  clutter_flag = int(args.clutter)
  preProcessing(file, clutter_flag)
