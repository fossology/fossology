#!/usr/bin/python

from distutils.core import setup
from distutils.extension import Extension
from Pyrex.Distutils import build_ext

# Eventually, sparsevect will be a shared library, and we'll use libraries=
# instead of extra_objects=.
#
# When we do that, we'll probably need to rename this module...  sparsevector
# probably makes the most sense.

setup(
    name = "sparsevect",
    ext_modules = [ 
        Extension("sparsevect", ["sparsevect.pyx"],
                  extra_objects=['../sparsevect.o'])
    ],
    cmdclass = {'build_ext': build_ext}
)
