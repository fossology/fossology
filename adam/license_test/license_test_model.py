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

# Import custom libraries. You should set $PYTHONPATH or make sure that python
# can find these libraries.
import parser
# End of custom libraries

import sys
import math
import re

# This regex will grab the Licesne section text that is tagged in a file.
RE_LS = re.compile(r'<LICENSE_SECTION>(?P<text>.*?)</LICENSE_SECTION>',re.DOTALL)

# smoothing parameter
epsilon = 1.0/100000.0

# this caclculates the probabilities used to classify license.
# if ignore is True then text wrapped in <LICENSE_SECTION> is removed
def train_word_dict(files,ignore=True):
    word_dict = {}
    word_matrix = {}
    D = float(len(files))
    for file in files:
        text = open(file).read().decode('ascii','ignore')
        if ignore:
            text = RE_LS.sub('',text)
        else:
            RE_LS.sub(r'\g<text>',text)
        if len(text)==0:
            D -= 1.0
            continue
        stems = parser.stemmed_words(text)
        for i in xrange(len(stems)):
            stem = stems[i]
            # we dont want to mess with words that are only one character
            if len(stem)<2:
                continue
            word_dict[stem] = 1.0 + word_dict.get(stem,0.0)
            word_matrix[stem] = word_matrix.get(stem,{})
            if i<len(stems)-1:
                word_matrix[stem][stems[i+1]] = 1.0 + word_matrix[stem].get(stems[i+1],0.0)
    
    # Normalize the probabilities so they sum to 1.
    ss = sum(word_dict.values())
    for stem,value in word_dict.items():
        word_dict[stem] = value/ss
    for stem in word_matrix:
        s = sum(word_matrix[stem].values())
        for k,v in word_matrix[stem].items():
            word_matrix[stem][k] = v/s

    return (word_dict, word_matrix)

def reweight(file, yes_word_dict, yes_word_matrix, no_word_dict, no_word_matrix, pr = 0.5, lw=1, rw=1):
    text = open(file).read().decode('ascii','ignore')
    text = RE_LS.sub('',text)
    if len(text)==0:
        return
    stems = parser.stemmed_words(text)
    w = len(stems)

    score = test_file(file,yes_word_dict,yes_word_matrix,no_word_dict,no_word_matrix,pr,lw,rw)

    l = smooth_score(score)

    for i in xrange(w):
        if l:
            if yes_word_dict.get(stems[i],0.0) > no_word_dict.get(stems[i],0.0):                no_word_dict[stems[i]] = yes_word_dict.get(stems[i],0.0)+0.01
            if i+1<w:
                if yes_word_matrix.get(stems[i],{}).get(stems[i+1],0.0) > no_word_matrix.get(stems[i],{}).get(stems[i+1],0.0):
                    no_word_matrix[stems[i]] = no_word_matrix.get(stems[i],{})
                    no_word_matrix[stems[i]][stems[i+1]] = yes_word_matrix.get(stems[i],{}).get(stems[i+1],0.0) + 0.01
    
    # we are talking to the real guys.
    #return (yes_word_dict, yes_word_matrix, no_word_dict, no_word_matrix)

def smooth_score(score):
    s = [v>0 for v in score]
    l = []
    if s[0]:
        l.append(1)
    else:
        l.append(0)
    for i in xrange(1,len(s)-1):
        if (not s[i]) == s[i-1] == s[i+1]:
            s[i] = s[i-1]
    for i in xrange(1,len(score)):
        if s[i]:
            l.append(1+l[-1])
        else:
            l.append(0)
    for i in xrange(len(score)-2,-1,-1):
        if l[i+1]>0 and l[i]>0:
            l[i] = l[i+1]
    l = [v>2 for v in l]

    return l
        
def test_file(file, yes_word_dict, yes_word_matrix, no_word_dict, no_word_matrix, pr = 0.5, lw=1, rw=1, ignore=True):
    text = open(file).read().decode('ascii','ignore')
    if ignore:
        text = RE_LS.sub('',text)
    else:
        RE_LS.sub(r'\g<text>',text)
    if len(text)==0:
        return []
    stems = parser.stemmed_words(text)
    w = len(stems)
    
    score = []

    for i in xrange(w):
        ylp = math.log(pr)
        nlp = math.log(1.0-pr)
        for j in xrange(-rw+1,lw):
            if i+j >= 0 and i+j < w:
                ylp += math.log(yes_word_dict.get(stems[i+j],0.0)+epsilon)
                nlp += math.log(no_word_dict.get(stems[i+j],0.0)+epsilon)
                if i+j+1<w:
                    ylp += math.log(yes_word_matrix.get(stems[i+j],{}).get(stems[i+j+1],0.0)+epsilon)
                    nlp += math.log(no_word_matrix.get(stems[i+j],{}).get(stems[i+j+1],0.0)+epsilon)
        score.append(ylp-nlp)
    return score
