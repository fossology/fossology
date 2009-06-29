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

import math
__FAST__ = True
try:
    import sparsevect as sv
except:
    __FAST__ = False

class Vector():
    text_count = {}
    text_pos = {}
    text_array = []
    length = 0
    norm = None
    idf_wieghts = {}
    fast_vector = None

    def __init__(self,text_array=[],fast=False):
        '''Assume that text_array is an array of strings in order.'''

        self.text_count = {}
        self.text_pos = {}

        if type(text_array)==type([]):
            self.text_array = text_array[:]
            self.length = len(self.text_array)

            for i in xrange(self.length):
                t = self.text_array[i]
                self.text_count[t] = self.text_count.get(t,0.0)+1.0
                self.text_pos[t] = self.text_pos.get(t,[])
                self.text_pos[t].append(i)
        elif type(text_array)==type({}):
            self.text_array = text_array.copy()
            self.length = len(self.text_array)
            keys = self.text_array.keys()
            for i in xrange(len(keys)):
                k = keys[i]
                v = text_array[k]
                self.text_count[k] = v
                self.text_pos[k] = [0]

        if __FAST__:
            self.fast_vector = sv.SparseVector((2**31)-1)
            for k,v in self.text_count.items():
                index = hash(k) & (2**31)-1
                if index == (2**31)-1:
                    index -= 1
                self.fast_vector[index] = v

        self.getNorm()

    def setIDF(self,weights):
        '''Weights should be a dictionary of words to idf weights.'''

        pass

    def get(self,key,default=None):
        if not __FAST__:
            return self.text_count.get(key,default)
        else:
            index = hash(key) & (2**31)-1
            if index == (2**31)-1:
                index -= 1
            value = default
            try:
                value = self.fast_vector[index]
            except:
                value = default
            return value
    
    def set(self,key,value):
        if not __FAST__:
            self.text_count[key] = value
        else:
            index = hash(key) & (2**31)-1
            if index == (2**31)-1:
                index -= 1
            self.fast_vector[index] = value

    def getNorm(self):
        if self.norm != None:
            return self.norm
        self.norm = 0.0
        if not __FAST__:
            k = self.text_count.keys()
            for i in xrange(len(k)):
                a = self.text_count.get(k[i],0.0)
                self.norm += a*a
        else:
            self.norm = self.fast_vector.inner(self.fast_vector)

        self.norm = math.sqrt(self.norm)

        return self.norm

    def inner(self,other):
        if __FAST__:
            return self.fast_vector.inner(other.fast_vector)
        else:
            a = self.text_count.keys()
            b = other.text_count.keys()
            k = []
            if len(a)>len(b):
                k = b
            else:
                k = a

            tot = 0.0
            for i in xrange(len(k)):
                a = self.text_count.get(k[i],0.0)
                b = other.text_count.get(k[i],0.0)
                tot += a*b

        return tot

    def dot(self,other):
        '''returns the dot product of the two vectors.'''

        if self.getNorm() == 0 or other.getNorm() == 0:
            return 0.0
        
        if __FAST__:
            dot = self.fast_vector.inner(other.fast_vector)
        else:
            a = self.text_count.keys()
            b = other.text_count.keys()
            k = []
            if len(a)>len(b):
                k = b
            else:
                k = a

            dot = 0.0
            for i in xrange(len(k)):
                a = self.text_count.get(k[i],0.0)
                b = other.text_count.get(k[i],0.0)
                dot += a*b

        return dot/(self.getNorm()*other.getNorm())

