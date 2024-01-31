#!/usr/bin/env python3
'''
 Author: Kaushlendra Pratap Singh
 SPDX-FileCopyrightText: © 2021 Kaushlendra Pratap <kaushlendrapratap.9837@gmail.com>
 SPDX-FileCopyrightText: © 2023 Abdelrahman Jamal <abdelrahmanjamal5565@gmail.com>

 SPDX-License-Identifier: GPL-2.0-only
'''
import pandas as pd
import argparse
from safaa.Safaa import *

def CopyrightFalsePositiveDetection(file, clutter_flag):
  with open(file, 'r') as f:
    df = pd.read_json(f, orient='records')
  agent = SafaaAgent()
  df['is_copyright'] = agent.predict(df['content'], 0.5)
  if clutter_flag:
    df['decluttered_content'] = agent.declutter(df['content'],
                                                df['is_copyright'])
  print(df.to_json(orient='records'))


if __name__ == "__main__":
  parser = argparse.ArgumentParser()
  parser.add_argument("-f", "--file", help="File to be processed",
                      required=True)
  parser.add_argument("-c", "--clutter",
                      help="Integer Flag for clutter removal", required=False,
                      action="store_true")
  args = parser.parse_args()
  CopyrightFalsePositiveDetection(args.file, args.clutter)
