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

class LicenseTestModel:
    pos_word_dict = {}   # P(word|license)
    neg_word_dict = {}    # P(word|non-license)
    pos_word_matrix = {} # P(word_i,word_i+1|license)
    neg_word_matrix = {}  # P(word_i,word_i+1|non-license)

    # default paramerters
    lw = 3        # left window
    rw = 3        # right window
    pr = 0.4      # probability of finding a license in a random window
    smoothing = True
    pos_files = []
    neg_files = []
    
    # smoothing parameter
    epsilon = 1.0/100000.0

    def __init__(self, pos_files, neg_files, pr, lw, rw, smoothing):
        self.pos_files = pos_files
        self.neg_files = neg_files
        self.pr = pr
        self.lw = lw
        self.rw = rw
        self.smoothing = smoothing
    
    # this caclculates the probabilities used to classify license.
    # if ignore is True then text wrapped in <LICENSE_SECTION> is removed
    def train_word_dict(self,files,ignore=True):
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
    
    def reweight(self, file):
        text = open(file).read().decode('ascii','ignore')
        text = RE_LS.sub('',text)
        if len(text)==0:
            return
        stems = parser.stemmed_words(text)
        w = len(stems)
    
        score = test_file(file)
    
        l = smooth_score(score)
    
        for i in xrange(w):
            if l:
                if self.yes_word_dict.get(stems[i],0.0) > no_word_dict.get(stems[i],0.0):
                    self.no_word_dict[stems[i]] = self.yes_word_dict.get(stems[i],0.0)+0.01
                if i+1<w:
                    if self.yes_word_matrix.get(stems[i],{}).get(stems[i+1],0.0) > self.no_word_matrix.get(stems[i],{}).get(stems[i+1],0.0):
                        self.no_word_matrix[stems[i]] = self.no_word_matrix.get(stems[i],{})
                        self.no_word_matrix[stems[i]][stems[i+1]] = self.yes_word_matrix.get(stems[i],{}).get(stems[i+1],0.0) + 0.01
        
    
    def smooth_score(self,score):
        if len(score)==0:
            return []
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
            
    def test_text(self, text, ignore=True):
        if ignore:
            text = RE_LS.sub('',text)
        else:
            RE_LS.sub(r'\g<text>',text)
        if len(text)==0:
            return []
        stems, offsets = parser.stemmed_words_with_offsets(text)
        w = len(stems)
        
        score = []
    
        for i in xrange(w):
            ylp = math.log(self.pr)
            nlp = math.log(1.0-self.pr)
            for j in xrange(-self.rw+1,self.lw):
                if i+j >= 0 and i+j < w:
                    ylp += math.log(self.pos_word_dict.get(stems[i+j],0.0)+self.epsilon)
                    nlp += math.log(self.neg_word_dict.get(stems[i+j],0.0)+self.epsilon)
                    if i+j+1<w:
                        ylp += math.log(self.pos_word_matrix.get(stems[i+j],{}).get(stems[i+j+1],0.0)+self.epsilon)
                        nlp += math.log(self.neg_word_matrix.get(stems[i+j],{}).get(stems[i+j+1],0.0)+self.epsilon)
            score.append(ylp-nlp)

        l = self.smooth_score(score)
        license_offsets = []
        is_license = sum(l)>0
        if is_license:
            window = [-1,-1]
            for i in range(len(l)):
                if window[0]==-1 and l[i]==True:
                    window[0] = offsets[i][0]
                if window[0]!=-1 and l[i]==False:
                    window[1] = offsets[i][0]-1
                    license_offsets.append(window[:])
                    window = [-1,-1]

        return is_license, license_offsets

    def train(self):

        # Calculate P(*|license)
        (self.pos_word_dict, self.pos_word_matrix) = self.train_word_dict(self.pos_files,False)
        
        # Calculate P(*|non-license)
        (self.neg_word_dict, self.neg_word_matrix) = self.train_word_dict(self.neg_files)
        
        if self.smoothing:
            for file in self.neg_files:
                model.reweight(file)

    # methods for pickling the license test model
    def __getstate__(self):
        return (
            self.pos_word_dict.copy(),
            self.neg_word_dict.copy(),
            self.pos_word_matrix.copy(),
            self.neg_word_matrix.copy(),
            self.lw,
            self.rw,
            self.pr,
            self.smoothing,
            self.pos_files[:],
            self.neg_files[:],
            self.epsilon,
        )

    def __setstate__(self, state):
        self.pos_word_dict = state[0].copy()
        self.neg_word_dict = state[1].copy()
        self.pos_word_matrix = state[2].copy()
        self.neg_word_matrix = state[3].copy()
        self.lw = state[4]
        self.rw = state[5]
        self.pr = state[6]
        self.smoothing = state[7]
        self.pos_files = state[8][:]
        self.neg_files = state[9][:]
        self.epsilon = state[10]
        
