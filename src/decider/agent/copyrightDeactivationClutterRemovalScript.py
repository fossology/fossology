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

THE_PROBABLE_LOGIC_POS_CHECK = ['NOUN', 'NUM', 'PROPN', 'PROPN']
THE_PROBABLE_LOGIC_NER_CHECK = ['DATE', 'PERSON', 'CARDINAL', 'ORG']
CLUTTER_REGEX = r"(Copyright|copyright\s*(©)?([\w \-\,\[\]]+)?(\.com)?\.?|Copyright\s*(©)?|©([\w \-\,\.\[\]]+)?(Copyright)?)|(Copyright\s*(\(c\))?|\(c\)\s*(Copyright)?(?:[\w \,\-\.\"\[\]]{2,53}\s*)?\.?|\(c\)\s*(Copyright)?)|Copyright|([\w,]|(\s*\d+(\s(?:\,|-)\s*\d+)?\s*))(\s*\d+(\s*(?:\,|-)\s*\d+)?\,?\s*)\s*[a-zA-Z\&\| ,\s0-9]{3,50}(\.com)?\.?|(\.|\,)?\s*(\@[^>]*?\.com)|Inc+(\.)?| Company+|Corporation+|& Co+|GmbH+|All rights reserved(?:\.)?|Ltd|\<|[\w.]+@[a-zA-Z0-9-_.]+|\>|([A-Za-z0-9]+\.com)|rights\s*reserved|(?:(?:https?|ftp|file):\/\/|www\.|ftp\.)(?:\([-\w+&@#/%=~|$?!:,.]*\)|[-\w+&@#/%=~|$?!:,.])*(?:\([\w+&@#/%=~|$?!:,.]*\)|[\w+&@#/%=~|$])|\<|\>|\(|\)"


def preprocess(file, clutter_flag):
    df = pd.read_json(file, orient='records')
    df['content'] = df['content'].str.lower()
    copyright_deactivation_main(df, clutter_flag)
    print(df.to_json(orient='records'))


def copyright_deactivation_main(df, clutter_flag):
    nlp = spacy.load("en_core_web_sm")

    df['lemma_list'] = df['content'].apply(lambda x: [token.lemma_ for token in nlp(x)])
    df['filtered_sentence'] = df['lemma_list'].apply(lambda x: [word for word in x if not nlp.vocab[word].is_stop])
    punctuations = "?:!.,;'"
    df['filtered_sentence'] = df['filtered_sentence'].apply(lambda x: [word for word in x if word not in punctuations])
    df['normalized_text'] = df['filtered_sentence'].apply(lambda x: " ".join(x))

    substring = "( c )"
    cp_symbol = '\xa9'  # Unicode for copyright Symbol

    df['normalized_text'] = df['normalized_text'].str.replace(substring, "copyright")
    df['normalized_text'] = df['normalized_text'].str.replace(cp_symbol, "copyright")

    df['ner_tags'] = df['normalized_text'].apply(lambda x: [(ent.text, ent.label_) for ent in nlp(x).ents])
    df['pos_tags'] = df['lemma_list'].apply(lambda x: [(token.text, token.pos_) for token in nlp(" ".join(x)) if not token.is_punct and not token.is_space])

    df['is_copyright'] = False

    def entity_check(row):
        ner_tags = row['ner_tags']
        pos_tags = row['pos_tags']
        
        if any(tag[1] in THE_PROBABLE_LOGIC_POS_CHECK for tag in pos_tags):
            if any(tag[1] in THE_PROBABLE_LOGIC_NER_CHECK for tag in ner_tags):
                for entity, value in ner_tags:
                    if THE_PROBABLE_LOGIC_NER_CHECK[0] in value:
                        row['is_copyright'] = True
                    elif THE_PROBABLE_LOGIC_NER_CHECK[2] in value:
                        pattern_regex = r'''((?:19|20)\d{2}|\d{2})(?!\d)(?:[, \t-]{1,3}((?:19|20)\d{2}|\d{1,2}))?'''
                        extract_list = re.search(pattern_regex, entity)
                        if extract_list:
                            row['is_copyright'] = True
                    elif THE_PROBABLE_LOGIC_NER_CHECK[1] in value or THE_PROBABLE_LOGIC_NER_CHECK[3] in value:
                        if THE_PROBABLE_LOGIC_NER_CHECK[0] in value:
                            row['is_copyright'] = True
            else:
                row['is_copyright'] = False
        
        if clutter_flag == 1 and row['is_copyright']:
            row['edited_text'] = clutter_removal(row['content'], ner_tags)
        else:
            row['edited_text'] = row['content']
        
        return row

    df = df.apply(entity_check, axis=1)
    print(df.to_json(orient='records'))


def clutter_removal(text, ner_tags):
    string1 = "all rights reserved"
    string2 = "distributed under the mit software license"
    
    if string1 in text:
        clutter_removed = text[:text.index(string1)]
    elif string2 in text:
        clutter_removed = text[:text.index(string1)]
    else:
        org_pos = -1
        person_pos = -1
        if 'ORG' in [tag[1] for tag in ner_tags]:
            string3 = re.sub(r'''[^\w\s()]+''', '', text)
            las_ent = len(ner_tags) - [tag[1] for tag in ner_tags][::-1].index('ORG') - 1
            org_name = ner_tags[las_ent][0]
            try:
                org_pos = string3.rindex(org_name) + len(org_name)
            except ValueError:
                org_pos = -1
        if 'PERSON' in [tag[1] for tag in ner_tags]:
            las_ent = len(ner_tags) - [tag[1] for tag in ner_tags][::-1].index('PERSON') - 1
            person_name = ner_tags[las_ent][0]
            try:
                person_pos = string3.rindex(person_name) + len(person_name)
            except ValueError:
                person_pos = -1
        if org_pos > 0 or person_pos > 0:
            del_upto = max(org_pos, person_pos)
            clutter_removed = text[:del_upto]
        else:
            clutter_removed = re.findall(CLUTTER_REGEX, text, re.MULTILINE)
            clutter_removed = ''.join(clutter_removed) if clutter_removed else ""
    
    return clutter_removed


if __name__ == "__main__":
    parser = argparse.ArgumentParser()
    parser.add_argument("-f", "--file", help="File to be processed", required=True)
    parser.add_argument("-c", "--clutter", help="Integer Flag for clutter removal", required=True)
    args = parser.parse_args()
    file = args.file
    clutter_flag = int(args.clutter)
    preProcessing(file, clutter_flag)
