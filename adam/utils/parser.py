#!/usr/bin/python

## 
## Copyright (C) 2007 Hewlett-Packard Development Company, L.P.
## 
## This program is free software; you can redistribute it and/or
## modify it under the terms of the GNU General Public License
## version 2 as published by the Free Software Foundation.
##
## This program is distributed in the hope that it will be useful,
## but WITHOUT ANY WARRANTY; without even the implied warranty of
## MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
## GNU General Public License for more details.
##
## You should have received a copy of the GNU General Public License along
## with this program; if not, write to the Free Software Foundation, Inc.,
## 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
##

import re, unicodedata
from Stemmer import Stemmer
import math
import stopwords

# define unicode symbols so we can find WORDS and non-WORDS
S=''.join(unichr(i) for i in range(65536) if unicodedata.category(unichr(i))[0]=='S')
N=''.join(unichr(i) for i in range(65536) if unicodedata.category(unichr(i))[0]=='N')
L=''.join(unichr(i) for i in range(65536) if unicodedata.category(unichr(i))[0]=='L')
P=''.join(unichr(i) for i in range(65536) if unicodedata.category(unichr(i))[0]=='P')
P=P.replace('\\','\\\\').replace(']','\\]')
Z=''.join(unichr(i) for i in range(65536) if unicodedata.category(unichr(i))[0]=='Z')
C=''.join(unichr(i) for i in range(65536) if unicodedata.category(unichr(i))[0]=='C')
U=''.join(unichr(i) for i in range(65536) if unicodedata.category(unichr(i))=='Lu')

RE_SENTENCE = re.compile(r'<SENTENCE>(?P<text>.*?)</SENTENCE>',re.DOTALL)
RE_WORD = re.compile('['+L+N+']+')
RE_NONWORD = re.compile('[^'+L+N+']+')
RE_START_NONWORD = re.compile('^[^'+L+N+']+')
RE_TOKENS = re.compile('['+L+N+']+|[^'+L+N+']+')
RE_CAPPED = re.compile('^['+U+']')
RE_UPPER = re.compile('^['+U+']+$')
RE_NUMBER = re.compile('^['+N+']+$')
RE_INCNUM = re.compile('^['+L+N+']*['+U+']['+L+N+']*')
RE_RETURN = re.compile('^.*[\n\f\v]',re.DOTALL)
RE_PUNCT = re.compile('^.*['+P+']',re.DOTALL)
RE_DELIM = re.compile('^.*[\.\?\!]',re.DOTALL)
RE_WHITE = re.compile('^[\ \t]+$')
ENG_STEM = Stemmer('english')

STOPWORDS = stopwords.english()

def stemmed_words(text,except_re=re.compile('^.$')):
    words = RE_WORD.findall(text)
    stems = [ENG_STEM.stemWord(w.lower()) for w in words if not w.lower() in STOPWORDS or not except_re.match(w)==None]
    return stems

def stemmed_words_with_offsets(text,except_re=re.compile('^.$')):
    stems = []
    offsets = []
    for iter in RE_WORD.finditer(text):
        w = iter.group().lower()
        if w not in STOPWORDS or not except_re.match(w)==None:
            stems.append(ENG_STEM.stemWord(w))
            offsets.append(iter.span())
    return stems, offsets

def features(text):
    stuff = RE_TOKENS.findall(text)
    n = len(stuff)
    features = []
    for i in xrange(n):
        f = {}
        t = stuff[i]
        f['token'] = t
        f['length'] = len(t)
        f['word'] = not None == RE_WORD.match(t)
        f['capped'] = not None == RE_CAPPED.match(t)
        f['upper'] = not None == RE_UPPER.match(t) # is it upper case
        f['number'] = not None == RE_NUMBER.match(t) # is it a number
        f['incnum'] = not None == RE_INCNUM.match(t) # does it include a number
        f['return'] = not None == RE_RETURN.match(t) # does it have a '\n'
        f['punct'] = not None == RE_PUNCT.match(t) # is it a punctuation charater
        f['delim'] = not None == RE_DELIM.match(t) # is it a sentence delimitor
        f['white'] = not None == RE_WHITE.match(t) # is a whitespace token
        f['stopword'] = t.lower() in STOPWORDS # is it a stopword
        # stem it
        if f['word'] and not f['stopword']:
            f['stem'] = ENG_STEM.stemWord(t.lower())
        else:
            f['stem'] = ''
        features.append(f)
    return features

