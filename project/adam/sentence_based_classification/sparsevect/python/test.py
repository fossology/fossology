#!/usr/bin/python

import sparsevect
import random
import pickle
import numpy

vects = []
numvs = []

n = 10000
dim = 100

print "Filling in vectors with random data"
for i in xrange(0,n):
    print "On vector %s" % i
    v = sparsevect.SparseVector(dim)
    numv = numpy.zeros(dim)
    for j in xrange(0, 10):
        index = random.randint(0,dim-1)
        value = random.random()
        v[index] = value
        numv[index] = value
    vects.append(v)
    numvs.append(numv)

# print "Pickling!"
# f = open("/tmp/vects.pickle", "w")
# pickle.dump(vects, f)
# 
# print "Loading pickle..."
# f = open("/tmp/vects.pickle", "r")
# vects = pickle.load(f)

for i in xrange(0, n):
    print "Dotting with i=%s" % i
    i = 0
    for j in xrange(0, n):
        a = vects[i] + vects[j]
        b = numvs[i] + numvs[j]
        for k in xrange(0,dim):
            if (a[k]!=b[k]):
                print "oh shit v%s+v%s != c!!!" % (i,j)
                print a
                print k
                print vects[i]
                print vects[j]
                break
        a = vects[i] - vects[j]
        b = numvs[i] - numvs[j]
        for k in xrange(0,dim):
            if (a[k]!=b[k]):
                print "oh shit v%s-v%s != c!!!" % (i,j)
                print a
                print k
                print vects[i]
                print vects[j]
                break
        c = vects[i].inner(vects[j])
        d = numpy.inner(numvs[i],numvs[j])
        #if (c!=d):
        if abs(c - d) > 5e-16:
            print "oh shit vi*vj != c!!!"
            print "Difference: %s" % repr(abs(c - d))
            print repr(c)
            print repr(d)


