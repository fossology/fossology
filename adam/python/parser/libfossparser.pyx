# Copyright (C) 2010 Hewlett-Packard Development Company, L.P.
# 
# This program is free software; you can redistribute it and/or
# modify it under the terms of the GNU General Public License
# version 2 as published by the Free Software Foundation.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License along
# with this program; if not, write to the Free Software Foundation, Inc.,
# 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.

import re

MONTHS = ['JAN','FEB','MAR','MAY','APR','JUL','JUN','AUG','OCT','SEP','NOV','DEC','January', 'February', 'March', 'April', 'June', 'July', 'August', 'September', 'SEPT', 'October', 'November', 'December',]
RE_EMAIL = re.compile('([A-Za-z0-9\-_\.\+]+@[A-Za-z0-9\-_\.\+]+\.[A-Za-z]+)')
RE_URL   = re.compile('([A-Za-z0-9]+://[A-Za-z0-9\/#&\?\.\+\_]*|www[A-Za-z0-9\/#&\?\.\+\_]*)')
RE_PATH  = re.compile('([^\s\*]{0,100}[\\\/][^\s\*]{1,100}|[^\s\*]{1,100}[\\\/][^\s\*]{0,100})')
RE_YEAR  = re.compile('[^0-9](19[0-9][0-9]|20[0-9][0-9])[^0-9]')
RE_DATE = re.compile('(((\d+)[\ ]*(%s)[,\ ]*(\d+))|((%s)[\ ]*(\d+)[\ ,]*(\d+))|((\d+)(\ *[-\/\.]\ *)(\d+)(\ *[-\/\.]\ *)(\d+)))' % ('|'.join(MONTHS),'|'.join(MONTHS)), re.I)
RE_TIME = re.compile('(\d\d\ *:\ *\d\d\ *:\ *\d\d(\ *\+\d\d\d\d)?)')
RE_FLOAT = re.compile('(\d+\.\d+)')
RE_COPYRIGHT = re.compile('(\([c]\)|c?opyright|\&copy\;)',re.I)
RE_ALPHA = re.compile('(\w+)')
RE_SPLITTER = re.compile('\W*\s+\W*|\w+\S+\w+', re.DOTALL)
RE_BREAK = re.compile('[\.]')

def token_type(word):
    a = {}

    if RE_ALPHA.search(word):
        a['word'] = True
    else:
        a['word'] = False

    if a['word'] and RE_EMAIL.search(word):
        a['email'] = True
    else:
        a['email'] = False
        
    if a['word'] and RE_URL.search(word):
        a['url'] = True
    else:
        a['url'] = False
        
    if a['word'] and not a['url'] and RE_PATH.search(word):
        a['path'] = True
    else:
        a['path'] = False
        
    if a['word'] and RE_DATE.search(word):
        a['date'] = True
    else:
        a['date'] = False
        
    if a['word'] and RE_TIME.search(word):
        a['time'] = True
    else:
        a['time'] = False
        
    if a['word'] and RE_YEAR.search(word):
        a['year'] = True
    else:
        a['year'] = False
        
    if a['word'] and RE_FLOAT.search(word):
        a['float'] = True
    else:
        a['float'] = False
        
    if a['word'] and RE_COPYRIGHT.search(word):
        a['copyright'] = True
    else:
        a['copyright'] = False

    if not a['word'] and RE_BREAK.search(word):
        a['break'] = True
    else:
        a['break'] = False

    a['token'] = word

    return a

def tokenize(text, abbreviations):
    iter = RE_SPLITTER.finditer(text)
    
    tokens = []
    start = True

    for it in iter:
        word = it.group()
        token = token_type(word)
        token['start_byte'] = it.start()
        token['end_byte'] = it.end()
        if not start and token['break'] and tokens[-1]['word'] and (re.match('^[a-zA-Z]$',tokens[-1]['token']) or tokens[-1]['token'] in abbreviations):
            token['break'] = False
        if len(tokens) > 2 and not token['word'] and token['token'][0] == ')' and len(tokens[-1]['token']) == 1 and tokens[-1]['token'].lower() == 'c' and not tokens[-2]['word'] and tokens[-2]['token'][-1] == ')':
            tokens[-1]['copyright'] = True
        tokens.append(token)
        start = False
    
    return tokens
