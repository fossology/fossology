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
  df['is_copyright'] = agent.predict(df['content'])
  if clutter_flag == 1:
    df['decluttered_content'] = SafaaAgent.declutter(df['content'], df['is_copyright'])
  print(df.to_json(orient='records'))


if __name__ == "__main__":
  parser = argparse.ArgumentParser()
  parser.add_argument(
      "-f", "--file", help="File to be processed", required=True)
  parser.add_argument("-c", "--clutter",
                      help="Integer Flag for clutter removal", required=True)
  args = parser.parse_args()
  file = args.file
  clutter_flag = int(args.clutter)
  CopyrightFalsePositiveDetection(file, clutter_flag)
