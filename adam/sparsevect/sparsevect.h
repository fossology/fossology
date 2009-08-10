//
// Copyright (C) 2007 Hewlett-Packard Development Company, L.P.
//
// This program is free software; you can redistribute it and/or
// modify it under the terms of the GNU General Public License
// version 2 as published by the Free Software Foundation.
//
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License along
// with this program; if not, write to the Free Software Foundation, Inc.,
// 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
//

#ifndef __SPARSEVECT_H__
#define __SPARSEVECT_H__
/* External representation of an element: i is the index, v is the value */
struct sv_element {
    unsigned long int i;
    double v;
};

/* The internals of sv_vector are hidden; all the needed functionality should
 * be made available by the public API.  This way, we can change the structure
 * of sv_vector for performance or other reasons and not break any code...
 */
typedef struct sv_vector_internal * sv_vector;

/* Create a new sv_vector with dimension dim
 * 
 * If there is a problem allocating memory, this function returns NULL.  It is
 * up to the caller to check for non-NULL-ness.  errno will be left intact, so
 * the user can see what malloc sets.
 */
sv_vector sv_new(unsigned long int dim);

/* Return an sv_element struct for the element in vect at position i */
struct sv_element sv_get_element(sv_vector vect, unsigned long int i);

/* Get the value in vect at position i */
double sv_get_element_value(sv_vector vect, unsigned long int i);

/* Set the element in vect at position i to value v
 *
 * On successful operation, this function returns 0.  If there is a problem
 * allocating memory, it returns -1, and leaves errno alone, so the caller can
 * check the contents of errno.  Other errors result in program exit.
 */
int sv_set_element(sv_vector vect, unsigned long int i, double v);

/* Return the inner product of a and b */
double sv_inner(sv_vector a, sv_vector b);

/* This function does an element by element multiplication. Returns a new 
 * vector.
 */
sv_vector sv_element_multiply(sv_vector a, sv_vector b);

/* Scalar multiplication - return a new sv_vector */
sv_vector sv_scalar_mult(sv_vector vect, double scalar);

/* Return a + b */
sv_vector sv_add(sv_vector a, sv_vector b);

/* Return a - b */
sv_vector sv_subtract(sv_vector a, sv_vector b);

/* Sum of all elements in vect */
double sv_sum(sv_vector vect);

/* Return the number of nonzero elements in vect */
unsigned long int sv_nonzeros(sv_vector vect);

/* Return the dimension of vect */
unsigned long int sv_dimension(sv_vector vect);

/* Return an array of sv_element structs of length sv_nonzeros(vect)
 *
 * Each sv_element has i and v members, indicating position in the vector and
 * value, respectively.  Only elements that are actually stored (i.e. nonzero
 * elements) are actually returned.
 *
 * It is the caller's responsibility to free() the resulting pointer when
 * finished.
 *
 * If there is a problem allocating memory, this function returns NULL, and
 * leaves errno alone so the caller can see what malloc set it to.
 */
struct sv_element *sv_get_elements(sv_vector vect);

/* Return an array of the indices with nonzero values in vect
 *
 * The array will be of length sv_nonzeros(vect), and it is the caller's
 * responsibility to free() the returned pointer when finished.
 *
 * If there is a memory allocation problem, this function returns NULL, leaving
 * errno intact.
 */
unsigned long int *sv_indices(sv_vector vect);

/* Expand vect into a "dense" array of doubles
 *
 * There will be sv_dimension(vect) doubles in the allocated array.  It's the
 * caller's responsibility to free the array when finished.
 *
 * If there is a memory allocation problem, this function returns NULL, leaving
 * errno intact.
 */
double *sv_expand(sv_vector vect);

void sv_print(sv_vector vect);

/* Free all memory associated with vect */
void sv_delete(sv_vector vect);

/*
   Writes a binary version of the vector to a file pointer.
*/
int sv_dump(sv_vector vect, FILE *file);
/*
   Loads a binary version of the vector from a file pointer.
   Returns a sv_vector.
*/
sv_vector sv_load(FILE *file);

#endif
