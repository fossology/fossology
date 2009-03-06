#include <stdlib.h>
#include <stdio.h>
#include <assert.h>
#include <err.h>

#include "sparsevect.h"

struct sv_node {
    long int i;
    double v;
    struct sv_node *prev;
    struct sv_node *next;
};

struct sv_vector_internal {
    struct sv_node *first;
    struct sv_node *current;
    long int dim;
    long int nonzeros;
};

/* Create a new sv_vector with dimension dim */
sv_vector sv_new(long int dim) {
    sv_vector vect;

    if (dim <= 0) {
        errx(1, "sv_new: dim must be > 0; you supplied %ld", dim);
    }

    vect = malloc(sizeof(struct sv_vector_internal));
    if (vect == NULL) {
        /* Go ahead and return NULL.  errno will be left alone. */
        return NULL;
    }

    vect->dim = dim;
    vect->nonzeros = 0;
    vect->first = NULL;
    vect->current = NULL;

    return vect;
}

/* Return a pointer to the node in vect at position i
 *
 * Return NULL if no node exists at that position.
 */
static struct sv_node *sv_get_node(sv_vector vect, long int i) {
    struct sv_node *node;

    if (i < 0 || i >= vect->dim) {
        errx(1, "sv_get_node: i must satisfy 0 <= i < %ld; you supplied %ld",
                vect->dim, i);
    }

    node = vect->first;

    while (node != NULL) {
        if (node->i == i) {
            return node;
        } else if (node->i > i) {
            return NULL;
        } else {
            node = node->next;
        }
    }
    return NULL;
}

/* Return an sv_element struct for the element in vect at position i */
struct sv_element sv_get_element(sv_vector vect, long int i) {
    struct sv_node *node;
    struct sv_element element;

    if (i < 0 || i >= vect->dim) {
        errx(1, "sv_get_element: i must satisfy 0 <= i < %ld; you supplied "
                "%ld", vect->dim, i);
    }

    element.i = i;
    node = sv_get_node(vect, i);
    if (node != NULL) {
        element.v = node->v;
    } else {
        element.v = 0.0;
    }

    return element;
}

/* Get the value in vect at position i */
double sv_get_element_value(sv_vector vect, long int i) {
    struct sv_node *node;
    double v = 0.0;

    if (i < 0 || i >= vect->dim) {
        errx(1, "sv_get_element_value: i must satisfy 0 <= i < %ld; you "
                "supplied %ld", vect->dim, i);
    }

    node = sv_get_node(vect, i);
    if (node != NULL) {
        v = node->v;
    }
    return v;
}

/* Insert node before vect->current
 * Handles incrementing vect->nonzeros and keeping track of vect->first as well
 * If vect->current == NULL, this first sets vect->current = vect->first
 */
static void insert_before(sv_vector vect, struct sv_node *node) {
    if (vect->current == NULL) {
        vect->current = vect->first;
    }
    node->next = vect->current;
    if (node->next != NULL) {
        node->prev = node->next->prev;
        node->next->prev = node;
    } else {
        node->prev = NULL;
    }
    if (node->prev != NULL) {
        node->prev->next = node;
    } else {
        /* node is the first one in the chain */
        vect->first = node;
    }
    vect->nonzeros++;
    vect->current = node;
}

/* Insert node after vect->current
 * Handles incrementing vect->nonzeros and keeping track of vect->first as well
 * If vect->current == NULL, this first sets vect->current = vect->first
 */
static void insert_after(sv_vector vect, struct sv_node *node) {
    if (vect->current == NULL) {
        vect->current = vect->first;
    }
    node->prev = vect->current;
    if (node->prev != NULL) {
        node->next = node->prev->next;
        node->prev->next = node;
    } else {
        node->next = NULL;
        /* First (and only...) node in the chain */
        vect->first = node;
    }
    if (node->next != NULL) {
        node->next->prev = node;
    }
    vect->nonzeros++;
    vect->current = node;
}

/* Delete node from vect
 * Handles freeing node, keeping track of vect->first and vect->nonzeros
 */
static void delete_node(sv_vector vect, struct sv_node *node) {
    if (node->prev != NULL) {
        node->prev->next = node->next;
    } else {
        /* We're deleting the first node in the chain */
        vect->first = node->next;
    }
    if (node->next != NULL) {
        node->next->prev = node->prev;
    } else {
        /* We're deleting the last node in the chain */
        if (node->prev != NULL) {
            node->prev->next = NULL;
        }
    }
    free(node);
    vect->current = NULL;
    vect->nonzeros--;
}

/* Set the element in vect at position i to value v
 *
 * On successful operation, this function returns 0.  If there is a problem
 * allocating memory, it returns -1, and leaves errno alone, so the caller can
 * check the contents of errno.  Other errors result in program exit.
 */
int sv_set_element(sv_vector vect, long int i, double v) {
    struct sv_node *vect_node;
    struct sv_node *node = NULL;

    if (i < 0 || i >= vect->dim) {
        errx(1, "sv_set_element: i must satisfy 0 <= i < %ld; you "
                "supplied %ld", vect->dim, i);
    }

    if (vect->current == NULL) {
        vect->current = vect->first;
    }

    /* Find the node (or the node that should be adjacent to it) */
    vect_node = vect->current;
    while (vect_node != NULL) {
        if (vect_node->i > i && vect->current->i > i) {
            /* Need to search backwards */
            vect->current = vect_node;
            vect_node = vect_node->prev;
        } else if (vect_node->i < i && vect->current->i < i) {
            /* Need to search forward */
            vect->current = vect_node;
            vect_node = vect_node->next;
        } else if (vect_node->i == i) {
            /* There's already a node with the index we want */
            vect->current = node = vect_node;
            break;
        } else {
            /* We know there isn't a node existing with our index, since
             * we've passed that index.  Now vect_node points either to the
             * element just before or just after (depending on our
             * traversal direction) we need to insert our new node.
             */
            break;
        }
    }
    if (node != NULL) {
        if (v != 0.0) {
            /* Just set the value and be done with it */
            node->v = v;
        } else {
            /* Setting equal to zero is equivalent to deleting a node */
            delete_node(vect, node);
        }
    } else if (v != 0.0) {
        /* We need to create a new node */
        node = malloc(sizeof(struct sv_node));
        if (node == NULL) {
            /* Unable to allocate memory...  Return -1 and leave errno alone */
            return -1;
        }
        node->i = i;
        node->v = v;

        if (vect_node != NULL) {
            /* We need to insert this node either before or after
             * vect_node, depending on direction of traversal
             */
            if (vect->current->i > i) {
                /* We were traversing backwards.  Insert after vect_node,
                 * since we're one "left" of where this node should be.
                 */
                vect->current = vect_node;
                insert_after(vect, node);
            } else if (vect->current->i < i) {
                /* We were traversing forward.  Insert before vect_node,
                 * since we're one "right" of where this node should be.
                 */
                vect->current = vect_node;
                insert_before(vect, node);
            } else {
                /* Shouldn't get here! */
                printf("Shouldn't get here.\n");
                assert(0);
            }
        } else {
            /* We made it all the way past the beginning or end. */
            if (vect->current == NULL) {
                /* No elements!  Doesn't matter which insert method we use. */
                insert_before(vect, node);
            } else if (vect->current->i > i) {
                /* We were traversing backwards, so we just need to insert
                 * this node at the beginning.  vect->current is the first
                 * node in the chain.
                 */
                insert_before(vect, node);
            } else if (vect->current->i < i) {
                /* We were traversing forward, so we need to append this
                 * node to the end.  vect->current is the last element in the
                 * chain.
                 */
                insert_after(vect, node);
            } else {
                printf("Shouldn't get here.\n");
                assert(0);
            }
        }
    }
    return 0;
}

/* This function does an element by element multiplication. Returns a new 
 * vector.
 */
sv_vector sv_element_multiply(sv_vector a, sv_vector b) {
    sv_vector newvect;
    struct sv_node *a_node;
    struct sv_node *b_node;

    if (a->dim != b->dim) {
        /* 
        printf("ERROR: Dimensions %u and %u don't match!\n", a->dim, b->dim);
        exit(1);
        */
        return NULL;
    }

    newvect = sv_new(a->dim);

    a_node = a->first;
    b_node = b->first;
    while (a_node != NULL) {
        while (b_node != NULL) {
            if (b_node->i < a_node->i) {
                b_node = b_node->next;
            } else if (b_node->i == a_node->i) {
                sv_set_element(newvect, b_node->i, ((b_node->v) * (a_node->v)));
                a_node = a_node->next;
                b_node = b_node->next;
                break;
            } else if (b_node->i > a_node->i) {
                a_node = a_node->next;
                break;
            } else {
                /* We shouldn't get here... */
                assert(0);
            }
        }
        if (b_node == NULL && a_node != NULL) {
            a_node = a_node->next;
        }
    }
    while (b_node != NULL) {
        b_node = b_node->next;
    }

    return newvect;
}

/* If b_multiplier == +1: return a + b; if b_multiplier == -1: return a - b
 *
 * Technically you could use this to more efficiently calculate e.g. (a - 5*b),
 * but I'm not exposing that (and anyway, you'd want the multiplier to be a
 * double in that case).  This just gives a convenient way to keep the logic
 * common for addition and subtraction.
 */
static sv_vector add_or_subtract(sv_vector a, sv_vector b, char b_multiplier) {
    sv_vector newvect;
    struct sv_node *a_node;
    struct sv_node *b_node;

    if (a->dim != b->dim) {
        errx(1, "add_or_subtract: dimensions %ld and %ld don't match",
                a->dim, b->dim);
    }

    newvect = sv_new(a->dim);

    a_node = a->first;
    b_node = b->first;
    while (a_node != NULL) {
        while (b_node != NULL) {
            if (b_node->i < a_node->i) {
                sv_set_element(newvect, b_node->i, b_multiplier * b_node->v);
                b_node = b_node->next;
            } else if (b_node->i == a_node->i) {
                sv_set_element(newvect, b_node->i, b_multiplier * b_node->v
                                                   + a_node->v);
                a_node = a_node->next;
                b_node = b_node->next;
                break;
            } else if (b_node->i > a_node->i) {
                sv_set_element(newvect, a_node->i, a_node->v);
                a_node = a_node->next;
                break;
            } else {
                /* We shouldn't get here... */
                assert(0);
            }
        }
        if (b_node == NULL && a_node != NULL) {
            sv_set_element(newvect, a_node->i, a_node->v);
            a_node = a_node->next;
        }
    }
    while (b_node != NULL) {
        sv_set_element(newvect, b_node->i, b_multiplier * b_node->v);
        b_node = b_node->next;
    }

    return newvect;
}

/* Return a + b */
sv_vector sv_add(sv_vector a, sv_vector b) {
    if (a->dim != b->dim) {
        errx(1, "sv_add: dimensions %ld and %ld don't match",
                a->dim, b->dim);
    }
    return add_or_subtract(a, b, 1);
}

/* Return a - b */
sv_vector sv_subtract(sv_vector a, sv_vector b) {
    if (a->dim != b->dim) {
        errx(1, "sv_subtract: dimensions %ld and %ld don't match",
                a->dim, b->dim);
    }
    return add_or_subtract(a, b, -1);
}

/* Return the inner product of a and b */
double sv_inner(sv_vector a, sv_vector b) {
    double product = 0.0;
    struct sv_node *a_node, *b_node;
    sv_vector c;

    if (a->nonzeros > b->nonzeros) {
        /* Swap a and b, so that we traverse fewer times */
        c = a;
        a = b;
        b = c;
    }

    if (a->dim != b->dim) {
        errx(1, "sv_inner: dimensions %ld and %ld don't match",
                a->dim, b->dim);
    }

    a_node = a->first;
    b_node = b->first;
    while (a_node != NULL) {
        while (b_node != NULL && b_node->i <= a_node->i) {
            if (a_node->i == b_node->i) {
                product += a_node->v * b_node->v;
            }
            b_node = b_node->next;
        }
        a_node = a_node->next;
    }

    return product;
}

/* Scalar multiplication - return a new sv_vector */
sv_vector sv_scalar_mult(sv_vector vect, double scalar) {
    sv_vector newvect;
    struct sv_node *node;

    newvect = sv_new(vect->dim);
    node = vect->first;
    while (node != NULL) {
        sv_set_element(newvect, node->i, node->v * scalar);
        node = node->next;
    }
    return newvect;
}

/* Sum of all elements in vect */
double sv_sum(sv_vector vect) {
    double sum = 0;
    struct sv_node *node;

    node = vect->first;
    while (node != NULL) {
        sum += node->v;
        node = node->next;
    }
    return sum;
}

/* Return the number of nonzero elements in vect */
long int sv_nonzeros(sv_vector vect) {
    return vect->nonzeros;
}

/* Return the dimension of vect */
long int sv_dimension(sv_vector vect) {
    return vect->dim;
}

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
struct sv_element *sv_get_elements(sv_vector vect) {
    struct sv_element *elements, *cur_element;
    struct sv_node *node;

    elements = malloc(vect->nonzeros * sizeof(struct sv_element));
    if (elements == NULL) {
        /* Just return NULL, leaving errno alone */
        return NULL;
    }
    cur_element = elements;
    node = vect->first;
    while (node != NULL) {
        cur_element->i = node->i;
        cur_element->v = node->v;
        cur_element++;
        node = node->next;
    }
    return elements;
}

/* Return an array of the indices with nonzero values in vect
 *
 * The array will be of length sv_nonzeros(vect), and it is the caller's
 * responsibility to free() the returned pointer when finished.
 *
 * If there is a memory allocation problem, this function returns NULL, leaving
 * errno intact.
 */
long int *sv_indices(sv_vector vect) {
    long int *indices, *cur_index;
    struct sv_node *node;

    indices = malloc(vect->nonzeros * sizeof(long int));
    if (indices == NULL) {
        /* Return NULL, leaving errno intact */
        return NULL;
    }
    cur_index = indices;
    node = vect->first;
    while (node != NULL) {
        *cur_index = node->i;
        cur_index++;
        node = node->next;
    }
    return indices;
}

/* Expand vect into a "dense" array of doubles
 *
 * There will be sv_dimension(vect) doubles in the allocated array.  It's the
 * caller's responsibility to free the array when finished.
 *
 * If there is a memory allocation problem, this function returns NULL, leaving
 * errno intact.
 */
double *sv_expand(sv_vector vect) {
    double *expanded;
    struct sv_node *node;

    /* Initialize the vector with zeros, so we actually only have to manually
     * set the non-zero elements */
    expanded = calloc(sv_dimension(vect), sizeof(double));
    if (expanded == NULL) {
        /* Return NULL, leaving errno intact */
        return NULL;
    }
    node = vect->first;
    while (node != NULL) {
        expanded[node->i] = node->v;
        node = node->next;
    }
    return expanded;
}

void sv_print(sv_vector vect) {
    struct sv_node *node;

    printf("[");
    printf("{dim: %ld, nonzeros: %ld}\n", vect->dim, vect->nonzeros);
    node = vect->first;
    while (node != NULL) {
        printf("(%ld, %f)\n", node->i, node->v);
        node = node->next;
    }
    printf("]\n");
}

/* Free all memory associated with vect */
void sv_delete(sv_vector vect) {
    struct sv_node *node, *next;

    node = vect->first;
    while (node != NULL) {
        next = node->next;
        free(node);
        node = next;
    }
    free(vect);
}
