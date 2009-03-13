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

cdef extern from "../sparsevect.h":
    cdef struct sv_element:
        long int i
        double v

    # This is an "opaque" pointer type
    ctypedef void * sv_vector

    sv_vector sv_new(long int dim)
    sv_element sv_get_element(sv_vector vect, long int i)
    double sv_get_element_value(sv_vector vect, long int i)
    int sv_set_element(sv_vector vect, long int i, double v)
    double sv_inner(sv_vector a, sv_vector b)
    sv_vector sv_element_multiply(sv_vector a, sv_vector b)
    sv_vector sv_scalar_mult(sv_vector vect, double scalar)
    sv_vector sv_add(sv_vector vect, sv_vector vect)
    sv_vector sv_subtract(sv_vector vect, sv_vector vect)
    long int sv_nonzeros(sv_vector vect)
    long int sv_dimension(sv_vector vect)
    sv_element *sv_get_elements(sv_vector vect)
    long int *sv_indices(sv_vector vect)
    void sv_delete(sv_vector vect)

cdef extern from "stdlib.h":
    void free(void *ptr)

import sys
import types
import StringIO

cdef class SparseVector:
    cdef sv_vector data

    def __cinit__(self, dim):
        self.data = sv_new(dim)

    def __init__(self, dim):
        pass

    def __dealloc__(self):
        sv_delete(self.data)

    def __str__(self):
        s = "["
        s = s + "{dim: %s, nonzeros: %s}\n" % (self.dimension, self.nonzeros)

        for element in self.elements:
            s = s + "(%s, %s)\n" % element
        s = s + "]"

        return s

    def inner(SparseVector a, SparseVector b):
        if a.dimension != b.dimension:
            raise ValueError("Dimensions of a and b must match")
        else:
            return sv_inner(a.data, b.data)
    
    def multiply(SparseVector a, SparseVector b):
        cdef SparseVector newvect

        newvect = SparseVector(1)
        sv_delete(newvect.data)
        newvect.data = sv_element_multiply(a.data, b.data)

        return newvect

    def __setitem__(self, index, value):
        if index >= self.dimension:
            raise IndexError("vector index out of range")
        sv_set_element(self.data, index, value)

    def __getitem__(self, index):
        if index >= self.dimension:
            raise IndexError("vector index out of range")
        return sv_get_element_value(self.data, index)

    def __mul__(a, b):
        """Multiply a and b

        If both a and b are SparseVectors, return the inner product of a and b.
        Otherwise, return the scalar product (one of the arguments needs to be
        coercible into a float)
        """
        if typecheck(a, SparseVector):
            if typecheck(b, SparseVector):
                return a.inner(b)
            else:
                return _scalar_mul(a, b)
        else:
            return _scalar_mul(b, a)

    def __div__(SparseVector a, double b):
        """Return a / b.  b must be coercible into a float."""
        return _scalar_mul(a, 1.0 / b)

    def __add__(SparseVector self, SparseVector other):
        cdef SparseVector newvect

        newvect = SparseVector(1)
        sv_delete(newvect.data)
        newvect.data = sv_add(self.data, other.data)

        return newvect

    def __sub__(SparseVector self, SparseVector other):
        cdef SparseVector newvect

        newvect = SparseVector(1)
        sv_delete(newvect.data)
        newvect.data = sv_subtract(self.data, other.data)

        return newvect

    def __iter__(self):
        return _SparseVectorIterator(self)

    property dimension:
        def __get__(self):
            return sv_dimension(self.data)

    property indices:
        def __get__(self):
            cdef long int i, *c_indices

            c_indices = sv_indices(self.data)
            indices = []
            for 0 <= i < self.nonzeros:
                indices.append(c_indices[i])
            free(c_indices)
            return indices

    property nonzeros:
        def __get__(self):
            return sv_nonzeros(self.data)

    property elements:
        def __get__(self):
            """Return a list of (index, value) tuples"""
            cdef int i
            cdef sv_element *sv_elements

            sv_elements = sv_get_elements(self.data)
            elements = []
            for 0 <= i < self.nonzeros:
                elements.append((sv_elements[i].i, sv_elements[i].v))
            free(sv_elements)
            return elements

    # Pickling
    def __reduce__(self):
        constructor = _newvector
        args = (self.dimension,)
 
        state = {}
        # old-style, really slow
        #state['elements'] = tuple(self.elements)

        s = StringIO.StringIO()
        for i, v in self.elements:
            s.write("%s:%r\n" % (i, v))
        state['dump'] = s.getvalue()

        return (constructor, args, state)

    def __setstate__(self, state):
        if state.has_key('elements'):
            # old-style
            elements = state['elements']
        else:
            # new-style
            elements = []
            for line in state['dump'].splitlines():
                index_s, value_s = line.split(':')
                index = int(index_s)
                value = float(value_s)
                elements.append((index, value))

        for i, v in elements:
            sv_set_element(self.data, i, v)

cdef class _SparseVectorIterator:
    cdef long int cur_index, vector_dim
    cdef dict elements

    def __init__(self, SparseVector vector):
        self.cur_index = 0

        self.vector_dim = vector.dimension
        self.elements = dict(vector.elements)

    def __next__(self):
        if self.cur_index >= self.vector_dim:
            raise StopIteration
        value = self.elements.get(self.cur_index, 0.0)
        self.cur_index = self.cur_index + 1
        return value

def _newvector(dim):
    return SparseVector(dim)

def _scalar_mul(SparseVector vect, double other):
    cdef SparseVector newvect

    newvect = SparseVector(1)
    sv_delete(newvect.data)
    newvect.data = sv_scalar_mult(vect.data, other)
    return newvect
