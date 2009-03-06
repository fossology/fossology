#include <stdio.h>
#include <stdlib.h>
#include "sparsevect.h"

int main(void) {
    sv_vector vect, a, b, c;
    double *expanded;
    int i;

    vect = sv_new(1000);
    sv_set_element(vect, 5, 10.0);
    sv_set_element(vect, 10, 11.5);
    sv_set_element(vect, 30, 2);

    sv_print(vect);

    sv_set_element(vect, 6, 0);
    sv_print(vect);

    sv_set_element(vect, 5, 0);
    sv_print(vect);

#if 0
    sv_delete(vect);
    vect = sv_new(-10);
    sv_print(vect);
    sv_set_element(vect, 2, 10);
    sv_print(vect);
    sv_set_element(vect, 4294967287, 14);
    sv_print(vect);
#endif

    sv_delete(vect);
    vect = sv_new(100);
    sv_set_element(vect, 5, 10);
    sv_set_element(vect, 12, 18);
    sv_set_element(vect, 13, 18);
    sv_print(vect);
    sv_set_element(vect, 13, 0);
    sv_print(vect);
    sv_set_element(vect, 12, 0);
    sv_print(vect);
    sv_set_element(vect, 5, 0);
    sv_print(vect);

    sv_delete(vect);
    vect = sv_new(100);
    sv_set_element(vect, 5, 10);
    sv_set_element(vect, 12, 18);
    sv_set_element(vect, 13, 18);
    sv_print(vect);
    sv_set_element(vect, 12, 0);
    sv_print(vect);
    sv_delete(vect);

    a = sv_new(2);
    sv_set_element(a, 0, 1);
    b = sv_new(2);
    sv_set_element(b, 1, 1);
    printf("a dot b: %f\n", sv_inner(a, b));
    sv_set_element(b, 0, 1);
    printf("a dot b: %f\n", sv_inner(a, b));

#if 0
    c = sv_new(3);
    printf("a dot c: %f\n", sv_inner(a, c));
    sv_delete(c)
#endif

    c = sv_scalar_mult(a, 5.2);
    sv_print(c);

    sv_delete(a);
    sv_delete(b);
    sv_delete(c);

    a = sv_new(5);
    sv_set_element(a, 2, 5.0);
    sv_set_element(a, 4, -2.5);
    sv_print(a);
    expanded = sv_expand(a);
    printf("expanded: [ ");
    for (i = 0; i < sv_dimension(a); i++) {
        printf("%f ", expanded[i]);
    }
    printf("]\n");
    free(expanded);
    printf("sum: %f\n", sv_sum(a));
    sv_delete(a);

    return 0;
}
