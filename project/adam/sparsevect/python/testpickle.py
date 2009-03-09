#!/usr/bin/python

import pickle
import sparsevect

v = sparsevect.SparseVector(10)
v[2] = 5
v[8] = 2
print v

f = open("/tmp/sparsevector.pickle", "w")
pickle.dump(v, f)
f.close()

f = open("/tmp/sparsevector.pickle", "r")
x = pickle.load(f)
print x
