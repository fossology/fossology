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

def labeled_stems(text):
    sections = []
    for iter in RE_LS.finditer(text):
        sections.append(iter.start())
        sections.append(iter.end())
    if len(sections)==0 or sections[0] != 0:
        sections.insert(0,0)
    if sections[-1] != len(text):
        sections.append(len(text))
    stems = []
    labels = []
    for i in xrange(len(sections)-1):
        s = parser.stemmed_words(RE_LS.sub(r'\g<text>',text[sections[i]:sections[i+1]]))
        stems.extend(s)
        if text[sections[i]:sections[i]+17] == '<LICENSE_SECTION>':
            labels.extend([1 for p in xrange(len(s))])
        else:
            labels.extend([-1 for p in xrange(len(s))])

    return stems,labels

def features(stems,lw,rw):
    feats = []
    for i in xrange(len(stems)):
        f = dict([(j,stems[i+j]) for j in xrange(-lw+1,rw) if (i+j>=0 and i+j<len(stems))])
        feats.append(f)
    return feats

# this caclculates the probabilities used to classify license.
# if ignore is True then text wrapped in <LICENSE_SECTION> is removed
def train_word_dict(files,lw,rw):
    pos_word_dict = {}
    pos_bigram_dict = {}
    pos_word_matrix = {}
    neg_word_dict = {}
    neg_bigram_dict = {}
    neg_word_matrix = {}
    D = float(len(files))
    for file in files:
        text = unicode(open(file).read(64000),errors='ignore')
        if len(text)==0:
            D -= 1.0
            continue
        stems,labels = labeled_stems(text)
        feats = features(stems,lw,rw)
        for i in xrange(len(feats)):
            if labels[i] == 1:
                pos_word_dict[feats[i][0]] = 1.0 + pos_word_dict.get(feats[i][0],0.0)
                if feats[i].get(1,False):
                    pos_bigram_dict[feats[i][0]] = pos_bigram_dict.get(feats[i][0],{})
                    pos_bigram_dict[feats[i][0]][feats[i][1]] = 1.0 + pos_bigram_dict[feats[i][0]].get(feats[i][1],0.0)
            else:
                neg_word_dict[feats[i][0]] = 1.0 + neg_word_dict.get(feats[i][0],0.0)
                if feats[i].get(1,False):
                    neg_bigram_dict[feats[i][0]] = neg_bigram_dict.get(feats[i][0],{})
                    neg_bigram_dict[feats[i][0]][feats[i][1]] = 1.0 + neg_bigram_dict[feats[i][0]].get(feats[i][1],0.0)

            for j in xrange(-lw+1,rw):
                if j == 0 or not feats[i].get(j,False):
                    continue
                if labels[i] == 1:
                    pos_word_matrix[feats[i][0]] = pos_word_matrix.get(feats[i][0],{})
                    pos_word_matrix[feats[i][0]][feats[i][j]] = 1.0 + pos_word_matrix[feats[i][0]].get(feats[i][j],0.0)
                else:
                    neg_word_matrix[feats[i][0]] = neg_word_matrix.get(feats[i][0],{})
                    neg_word_matrix[feats[i][0]][feats[i][j]] = 1.0 + neg_word_matrix[feats[i][0]].get(feats[i][j],0.0)

    # Normalize the probabilities so they sum to 1.
    ss = sum(pos_word_dict.values())
    for stem,value in pos_word_dict.items():
        pos_word_dict[stem] = (value/ss)
    for stem in pos_bigram_dict:
        s = sum(pos_bigram_dict[stem].values())
        for k,v in pos_bigram_dict[stem].items():
            pos_bigram_dict[stem][k] = (v/s)
    for stem in pos_word_matrix:
        s = sum(pos_word_matrix[stem].values())
        for k,v in pos_word_matrix[stem].items():
            pos_word_matrix[stem][k] = (v/s)

    ss = sum(neg_word_dict.values())
    for stem,value in neg_word_dict.items():
        neg_word_dict[stem] = (value/ss)
    for stem in neg_bigram_dict:
        s = sum(neg_bigram_dict[stem].values())
        for k,v in neg_bigram_dict[stem].items():
            neg_bigram_dict[stem][k] = (v/s)
    for stem in neg_word_matrix:
        s = sum(neg_word_matrix[stem].values())
        for k,v in neg_word_matrix[stem].items():
            neg_word_matrix[stem][k] = (v/s)

    return (pos_word_dict, pos_bigram_dict, pos_word_matrix, neg_word_dict, neg_bigram_dict, neg_word_matrix)

class LicenseTestModel:
    pos_word_dict = {}   # P(word|license)
    neg_word_dict = {}    # P(word|non-license)
    pos_bigram_dict = {} # P(word_i,word_i+1|license)
    neg_bigram_dict = {}  # P(word_i,word_i+1|non-license)
    pos_word_matrix = {} # P(word_i,word_j|license)
    neg_word_matrix = {}  # P(word_i,word_j|non-license)

    # default paramerters
    lw = 3        # left window
    rw = 3        # right window
    pr = 0.4      # probability of finding a license in a random window
    smoothing = False
    files = []
    
    # smoothing parameter
    epsilon = (1.0/1000000.0)

    def __init__(self, files, pr, lw, rw, smoothing):
        self.files = files
        self.files.sort()
        self.pr = pr
        self.lw = lw
        self.rw = rw
        self.smoothing = smoothing
    
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
            
    def test_text(self, text, ignore=False):
        if ignore:
            text = RE_LS.sub('',text)
        else:
            text = RE_LS.sub(r' \g<text> ',text)
        if len(text)==0:
            return (False, [], [])
        stems, offsets = parser.stemmed_words_with_offsets(text)
        feats = features(stems,self.lw,self.rw)
        w = len(feats)
        
        score = []
    
        for i in xrange(w):
            lp = 0.0
            #lp += math.log(self.pr/(1.0-self.pr))
            for j in xrange(-self.lw+1,self.rw):
                if feats[i].get(j,False):
                    lp += self.pos_word_dict.get(feats[i][j],0)
                    if feats[i].get(j+1,False):
                        lp += self.pos_bigram_dict.get(feats[i][j],{}).get(feats[i][j+1],0)
                    for k in xrange(-self.lw+1,self.rw):
                        if j == k or not feats[i].get(k,False):
                            continue
                        lp += self.pos_word_matrix.get(feats[i][j],{}).get(feats[i][k],0)
                    
                    # lp += math.log(self.pos_word_dict.get(feats[i][j],self.epsilon)/self.neg_word_dict.get(feats[i][j],self.epsilon))
                    # if feats[i].get(j+1,False):
                    #     lp += math.log(self.pos_bigram_dict.get(feats[i][j],{}).get(feats[i][j+1],self.epsilon)/self.neg_bigram_dict.get(feats[i][j],{}).get(feats[i][j+1],self.epsilon))
                    # for k in xrange(-self.lw+1,self.rw):
                    #     if j == k or not feats[i].get(k,False):
                    #         continue
                    #     lp += math.log(self.pos_word_matrix.get(feats[i][j],{}).get(feats[i][k],self.epsilon)/self.neg_word_matrix.get(feats[i][j],{}).get(feats[i][k],self.epsilon))
            score.append(lp)

        l = self.smooth_score(score)
        license_offsets = []
        is_license = sum(l)>0

        if is_license:
            window = [-1,-1]
            for i in range(len(l)):
                if window[0]==-1 and l[i]==True:
                    window[0] = offsets[i][0]
                if window[0]!=-1 and (l[i]==False or i==len(l)-1):
                    window[1] = offsets[i][0]-1
                    license_offsets.append(window[:])
                    window = [-1,-1]

        return (is_license, l, license_offsets)

    def train(self):

        # Calculate P(word|license) and P(word|non_license)
        (self.pos_word_dict, self.pos_bigram_dict, self.pos_word_matrix, self.neg_word_dict, self.neg_bigram_dict, self.neg_word_matrix) = train_word_dict(self.files,self.lw,self.rw)

    def accuracy(self, text):
        stems, labels = labeled_stems(text)
        feats = features(stems,self.lw,self.rw)
        (is_license, l, license_offsets) = self.test_text(text)
        d = {True:1, False:-1}
        l = [d[l[i]] for i in xrange(len(l))]
        misses = [labels[i]-l[i] for i in xrange(len(l))]
        
        fn_words = [stems[i] for i in xrange(len(misses)) if misses[i] == +2]
        fp_words = [stems[i] for i in xrange(len(misses)) if misses[i] == -2]
        if len(misses)==0:
            correct = 1.0
        else:
            correct = float(len(misses)-(len(fn_words)+len(fp_words)))/float(len(misses))
        
        return fn_words, fp_words, l, correct
        
    # methods for pickling the license test model
    def __getstate__(self):
        return (
            self.pos_word_dict.copy(),
            self.neg_word_dict.copy(),
            self.pos_bigram_dict.copy(),
            self.neg_bigram_dict.copy(),
            self.pos_word_matrix.copy(),
            self.neg_word_matrix.copy(),
            self.lw,
            self.rw,
            self.pr,
            self.smoothing,
            self.files[:],
            self.epsilon,
        )

    def __setstate__(self, state):
        i = 0
        self.pos_word_dict = state[i].copy()
        i += 1
        self.neg_word_dict = state[i].copy()
        i += 1
        self.pos_bigram_dict = state[i].copy()
        i += 1
        self.neg_bigram_dict = state[i].copy()
        i += 1
        self.pos_word_matrix = state[i].copy()
        i += 1
        self.neg_word_matrix = state[i].copy()
        i += 1
        self.lw = state[i]
        i += 1
        self.rw = state[i]
        i += 1
        self.pr = state[i]
        i += 1
        self.smoothing = state[i]
        i += 1
        self.files = state[i][:]
        i += 1
        self.epsilon = state[i]
        i += 1
        
