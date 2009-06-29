/* 0.9.7.2 on Mon Jun 29 09:20:33 2009 */

#define PY_SSIZE_T_CLEAN
#include "Python.h"
#include "structmember.h"
#ifndef PY_LONG_LONG
  #define PY_LONG_LONG LONG_LONG
#endif
#if PY_VERSION_HEX < 0x02050000
  typedef int Py_ssize_t;
  #define PY_SSIZE_T_MAX INT_MAX
  #define PY_SSIZE_T_MIN INT_MIN
  #define PyInt_FromSsize_t(z) PyInt_FromLong(z)
  #define PyInt_AsSsize_t(o)	PyInt_AsLong(o)
#endif
#ifndef WIN32
  #ifndef __stdcall
    #define __stdcall
  #endif
  #ifndef __cdecl
    #define __cdecl
  #endif
#endif
#ifdef __cplusplus
#define __PYX_EXTERN_C extern "C"
#else
#define __PYX_EXTERN_C extern
#endif
#include <math.h>
#include "../sparsevect.h"
#include "stdlib.h"


typedef struct {PyObject **p; char *s;} __Pyx_InternTabEntry; /*proto*/
typedef struct {PyObject **p; char *s; long n;} __Pyx_StringTabEntry; /*proto*/

static PyObject *__pyx_m;
static PyObject *__pyx_b;
static int __pyx_lineno;
static char *__pyx_filename;
static char **__pyx_f;

static int __Pyx_ArgTypeTest(PyObject *obj, PyTypeObject *type, int none_allowed, char *name); /*proto*/

static PyObject *__Pyx_Import(PyObject *name, PyObject *from_list); /*proto*/

static void __Pyx_Raise(PyObject *type, PyObject *value, PyObject *tb); /*proto*/

static PyObject *__Pyx_GetName(PyObject *dict, PyObject *name); /*proto*/

static PyObject *__Pyx_GetItemInt(PyObject *o, Py_ssize_t i); /*proto*/

static PyObject *__Pyx_UnpackItem(PyObject *); /*proto*/
static int __Pyx_EndUnpack(PyObject *); /*proto*/

static int __Pyx_InternStrings(__Pyx_InternTabEntry *t); /*proto*/

static int __Pyx_InitStrings(__Pyx_StringTabEntry *t); /*proto*/

static void __Pyx_AddTraceback(char *funcname); /*proto*/

/* Declarations from sparsevect */

struct __pyx_obj_10sparsevect_SparseVector {
  PyObject_HEAD
  sv_vector data;
};

struct __pyx_obj_10sparsevect__SparseVectorIterator {
  PyObject_HEAD
  long cur_index;
  long vector_dim;
  PyDictObject *elements;
};



static PyTypeObject *__pyx_ptype_10sparsevect_SparseVector = 0;
static PyTypeObject *__pyx_ptype_10sparsevect__SparseVectorIterator = 0;


/* Implementation of sparsevect */


static PyObject *__pyx_n_sys;
static PyObject *__pyx_n_types;
static PyObject *__pyx_n_StringIO;

static int __pyx_f_10sparsevect_12SparseVector___cinit__(PyObject *__pyx_v_self, PyObject *__pyx_args, PyObject *__pyx_kwds); /*proto*/
static int __pyx_f_10sparsevect_12SparseVector___cinit__(PyObject *__pyx_v_self, PyObject *__pyx_args, PyObject *__pyx_kwds) {
  PyObject *__pyx_v_dim = 0;
  int __pyx_r;
  long __pyx_1;
  static char *__pyx_argnames[] = {"dim",0};
  if (!PyArg_ParseTupleAndKeywords(__pyx_args, __pyx_kwds, "O", __pyx_argnames, &__pyx_v_dim)) return -1;
  Py_INCREF(__pyx_v_self);
  Py_INCREF(__pyx_v_dim);
  __pyx_1 = PyInt_AsLong(__pyx_v_dim); if (PyErr_Occurred()) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 52; goto __pyx_L1;}
  ((struct __pyx_obj_10sparsevect_SparseVector *)__pyx_v_self)->data = sv_new(__pyx_1);

  __pyx_r = 0;
  goto __pyx_L0;
  __pyx_L1:;
  __Pyx_AddTraceback("sparsevect.SparseVector.__cinit__");
  __pyx_r = -1;
  __pyx_L0:;
  Py_DECREF(__pyx_v_self);
  Py_DECREF(__pyx_v_dim);
  return __pyx_r;
}

static int __pyx_f_10sparsevect_12SparseVector___init__(PyObject *__pyx_v_self, PyObject *__pyx_args, PyObject *__pyx_kwds); /*proto*/
static int __pyx_f_10sparsevect_12SparseVector___init__(PyObject *__pyx_v_self, PyObject *__pyx_args, PyObject *__pyx_kwds) {
  PyObject *__pyx_v_dim = 0;
  int __pyx_r;
  static char *__pyx_argnames[] = {"dim",0};
  if (!PyArg_ParseTupleAndKeywords(__pyx_args, __pyx_kwds, "O", __pyx_argnames, &__pyx_v_dim)) return -1;
  Py_INCREF(__pyx_v_self);
  Py_INCREF(__pyx_v_dim);

  __pyx_r = 0;
  Py_DECREF(__pyx_v_self);
  Py_DECREF(__pyx_v_dim);
  return __pyx_r;
}

static void __pyx_f_10sparsevect_12SparseVector___dealloc__(PyObject *__pyx_v_self); /*proto*/
static void __pyx_f_10sparsevect_12SparseVector___dealloc__(PyObject *__pyx_v_self) {
  Py_INCREF(__pyx_v_self);
  sv_delete(((struct __pyx_obj_10sparsevect_SparseVector *)__pyx_v_self)->data);

  Py_DECREF(__pyx_v_self);
}

static PyObject *__pyx_n_dimension;
static PyObject *__pyx_n_nonzeros;
static PyObject *__pyx_n_elements;

static PyObject *__pyx_k4p;
static PyObject *__pyx_k5p;
static PyObject *__pyx_k6p;
static PyObject *__pyx_k7p;

static char __pyx_k4[] = "[";
static char __pyx_k5[] = "{dim: %s, nonzeros: %s}\n";
static char __pyx_k6[] = "(%s, %s)\n";
static char __pyx_k7[] = "]";

static PyObject *__pyx_f_10sparsevect_12SparseVector___str__(PyObject *__pyx_v_self); /*proto*/
static PyObject *__pyx_f_10sparsevect_12SparseVector___str__(PyObject *__pyx_v_self) {
  PyObject *__pyx_v_s;
  PyObject *__pyx_v_element;
  PyObject *__pyx_r;
  PyObject *__pyx_1 = 0;
  PyObject *__pyx_2 = 0;
  PyObject *__pyx_3 = 0;
  Py_INCREF(__pyx_v_self);
  __pyx_v_s = Py_None; Py_INCREF(Py_None);
  __pyx_v_element = Py_None; Py_INCREF(Py_None);

  /* "/home/batesbad/trunk/project/adam/sparsevect/python/sparsevect.pyx":61 */
  Py_INCREF(__pyx_k4p);
  Py_DECREF(__pyx_v_s);
  __pyx_v_s = __pyx_k4p;

  /* "/home/batesbad/trunk/project/adam/sparsevect/python/sparsevect.pyx":62 */
  __pyx_1 = PyObject_GetAttr(__pyx_v_self, __pyx_n_dimension); if (!__pyx_1) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 62; goto __pyx_L1;}
  __pyx_2 = PyObject_GetAttr(__pyx_v_self, __pyx_n_nonzeros); if (!__pyx_2) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 62; goto __pyx_L1;}
  __pyx_3 = PyTuple_New(2); if (!__pyx_3) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 62; goto __pyx_L1;}
  PyTuple_SET_ITEM(__pyx_3, 0, __pyx_1);
  PyTuple_SET_ITEM(__pyx_3, 1, __pyx_2);
  __pyx_1 = 0;
  __pyx_2 = 0;
  __pyx_1 = PyNumber_Remainder(__pyx_k5p, __pyx_3); if (!__pyx_1) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 62; goto __pyx_L1;}
  Py_DECREF(__pyx_3); __pyx_3 = 0;
  __pyx_2 = PyNumber_Add(__pyx_v_s, __pyx_1); if (!__pyx_2) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 62; goto __pyx_L1;}
  Py_DECREF(__pyx_1); __pyx_1 = 0;
  Py_DECREF(__pyx_v_s);
  __pyx_v_s = __pyx_2;
  __pyx_2 = 0;

  /* "/home/batesbad/trunk/project/adam/sparsevect/python/sparsevect.pyx":64 */
  __pyx_3 = PyObject_GetAttr(__pyx_v_self, __pyx_n_elements); if (!__pyx_3) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 64; goto __pyx_L1;}
  __pyx_1 = PyObject_GetIter(__pyx_3); if (!__pyx_1) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 64; goto __pyx_L1;}
  Py_DECREF(__pyx_3); __pyx_3 = 0;
  for (;;) {
    __pyx_2 = PyIter_Next(__pyx_1);
    if (!__pyx_2) {
      if (PyErr_Occurred()) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 64; goto __pyx_L1;}
      break;
    }
    Py_DECREF(__pyx_v_element);
    __pyx_v_element = __pyx_2;
    __pyx_2 = 0;
    __pyx_3 = PyNumber_Remainder(__pyx_k6p, __pyx_v_element); if (!__pyx_3) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 65; goto __pyx_L1;}
    __pyx_2 = PyNumber_Add(__pyx_v_s, __pyx_3); if (!__pyx_2) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 65; goto __pyx_L1;}
    Py_DECREF(__pyx_3); __pyx_3 = 0;
    Py_DECREF(__pyx_v_s);
    __pyx_v_s = __pyx_2;
    __pyx_2 = 0;
  }
  Py_DECREF(__pyx_1); __pyx_1 = 0;

  /* "/home/batesbad/trunk/project/adam/sparsevect/python/sparsevect.pyx":66 */
  __pyx_3 = PyNumber_Add(__pyx_v_s, __pyx_k7p); if (!__pyx_3) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 66; goto __pyx_L1;}
  Py_DECREF(__pyx_v_s);
  __pyx_v_s = __pyx_3;
  __pyx_3 = 0;

  /* "/home/batesbad/trunk/project/adam/sparsevect/python/sparsevect.pyx":68 */
  Py_INCREF(__pyx_v_s);
  __pyx_r = __pyx_v_s;
  goto __pyx_L0;

  __pyx_r = Py_None; Py_INCREF(Py_None);
  goto __pyx_L0;
  __pyx_L1:;
  Py_XDECREF(__pyx_1);
  Py_XDECREF(__pyx_2);
  Py_XDECREF(__pyx_3);
  __Pyx_AddTraceback("sparsevect.SparseVector.__str__");
  __pyx_r = 0;
  __pyx_L0:;
  Py_DECREF(__pyx_v_s);
  Py_DECREF(__pyx_v_element);
  Py_DECREF(__pyx_v_self);
  return __pyx_r;
}

static PyObject *__pyx_k8p;

static char __pyx_k8[] = "Dimensions of a and b must match";

static PyObject *__pyx_f_10sparsevect_12SparseVector_inner(PyObject *__pyx_v_a, PyObject *__pyx_args, PyObject *__pyx_kwds); /*proto*/
static PyObject *__pyx_f_10sparsevect_12SparseVector_inner(PyObject *__pyx_v_a, PyObject *__pyx_args, PyObject *__pyx_kwds) {
  struct __pyx_obj_10sparsevect_SparseVector *__pyx_v_b = 0;
  PyObject *__pyx_r;
  PyObject *__pyx_1 = 0;
  PyObject *__pyx_2 = 0;
  int __pyx_3;
  static char *__pyx_argnames[] = {"b",0};
  if (!PyArg_ParseTupleAndKeywords(__pyx_args, __pyx_kwds, "O", __pyx_argnames, &__pyx_v_b)) return 0;
  Py_INCREF(__pyx_v_a);
  Py_INCREF(__pyx_v_b);
  if (!__Pyx_ArgTypeTest(((PyObject *)__pyx_v_b), __pyx_ptype_10sparsevect_SparseVector, 1, "b")) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 70; goto __pyx_L1;}
  __pyx_1 = PyObject_GetAttr(__pyx_v_a, __pyx_n_dimension); if (!__pyx_1) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 71; goto __pyx_L1;}
  __pyx_2 = PyObject_GetAttr(((PyObject *)__pyx_v_b), __pyx_n_dimension); if (!__pyx_2) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 71; goto __pyx_L1;}
  if (PyObject_Cmp(__pyx_1, __pyx_2, &__pyx_3) < 0) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 71; goto __pyx_L1;}
  __pyx_3 = __pyx_3 != 0;
  Py_DECREF(__pyx_1); __pyx_1 = 0;
  Py_DECREF(__pyx_2); __pyx_2 = 0;
  if (__pyx_3) {
    __pyx_1 = PyTuple_New(1); if (!__pyx_1) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 72; goto __pyx_L1;}
    Py_INCREF(__pyx_k8p);
    PyTuple_SET_ITEM(__pyx_1, 0, __pyx_k8p);
    __pyx_2 = PyObject_CallObject(PyExc_ValueError, __pyx_1); if (!__pyx_2) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 72; goto __pyx_L1;}
    Py_DECREF(__pyx_1); __pyx_1 = 0;
    __Pyx_Raise(__pyx_2, 0, 0);
    Py_DECREF(__pyx_2); __pyx_2 = 0;
    {__pyx_filename = __pyx_f[0]; __pyx_lineno = 72; goto __pyx_L1;}
    goto __pyx_L2;
  }
  /*else*/ {
    __pyx_1 = PyFloat_FromDouble(sv_inner(((struct __pyx_obj_10sparsevect_SparseVector *)__pyx_v_a)->data,__pyx_v_b->data)); if (!__pyx_1) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 74; goto __pyx_L1;}
    __pyx_r = __pyx_1;
    __pyx_1 = 0;
    goto __pyx_L0;
  }
  __pyx_L2:;

  __pyx_r = Py_None; Py_INCREF(Py_None);
  goto __pyx_L0;
  __pyx_L1:;
  Py_XDECREF(__pyx_1);
  Py_XDECREF(__pyx_2);
  __Pyx_AddTraceback("sparsevect.SparseVector.inner");
  __pyx_r = 0;
  __pyx_L0:;
  Py_DECREF(__pyx_v_a);
  Py_DECREF(__pyx_v_b);
  return __pyx_r;
}

static PyObject *__pyx_f_10sparsevect_12SparseVector_multiply(PyObject *__pyx_v_a, PyObject *__pyx_args, PyObject *__pyx_kwds); /*proto*/
static PyObject *__pyx_f_10sparsevect_12SparseVector_multiply(PyObject *__pyx_v_a, PyObject *__pyx_args, PyObject *__pyx_kwds) {
  struct __pyx_obj_10sparsevect_SparseVector *__pyx_v_b = 0;
  struct __pyx_obj_10sparsevect_SparseVector *__pyx_v_newvect;
  PyObject *__pyx_r;
  PyObject *__pyx_1 = 0;
  PyObject *__pyx_2 = 0;
  static char *__pyx_argnames[] = {"b",0};
  if (!PyArg_ParseTupleAndKeywords(__pyx_args, __pyx_kwds, "O", __pyx_argnames, &__pyx_v_b)) return 0;
  Py_INCREF(__pyx_v_a);
  Py_INCREF(__pyx_v_b);
  __pyx_v_newvect = ((struct __pyx_obj_10sparsevect_SparseVector *)Py_None); Py_INCREF(Py_None);
  if (!__Pyx_ArgTypeTest(((PyObject *)__pyx_v_b), __pyx_ptype_10sparsevect_SparseVector, 1, "b")) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 76; goto __pyx_L1;}

  /* "/home/batesbad/trunk/project/adam/sparsevect/python/sparsevect.pyx":79 */
  __pyx_1 = PyInt_FromLong(1); if (!__pyx_1) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 79; goto __pyx_L1;}
  __pyx_2 = PyTuple_New(1); if (!__pyx_2) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 79; goto __pyx_L1;}
  PyTuple_SET_ITEM(__pyx_2, 0, __pyx_1);
  __pyx_1 = 0;
  __pyx_1 = PyObject_CallObject(((PyObject *)__pyx_ptype_10sparsevect_SparseVector), __pyx_2); if (!__pyx_1) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 79; goto __pyx_L1;}
  Py_DECREF(__pyx_2); __pyx_2 = 0;
  Py_DECREF(((PyObject *)__pyx_v_newvect));
  __pyx_v_newvect = ((struct __pyx_obj_10sparsevect_SparseVector *)__pyx_1);
  __pyx_1 = 0;

  /* "/home/batesbad/trunk/project/adam/sparsevect/python/sparsevect.pyx":80 */
  sv_delete(__pyx_v_newvect->data);

  /* "/home/batesbad/trunk/project/adam/sparsevect/python/sparsevect.pyx":81 */
  __pyx_v_newvect->data = sv_element_multiply(((struct __pyx_obj_10sparsevect_SparseVector *)__pyx_v_a)->data,__pyx_v_b->data);

  /* "/home/batesbad/trunk/project/adam/sparsevect/python/sparsevect.pyx":83 */
  Py_INCREF(((PyObject *)__pyx_v_newvect));
  __pyx_r = ((PyObject *)__pyx_v_newvect);
  goto __pyx_L0;

  __pyx_r = Py_None; Py_INCREF(Py_None);
  goto __pyx_L0;
  __pyx_L1:;
  Py_XDECREF(__pyx_1);
  Py_XDECREF(__pyx_2);
  __Pyx_AddTraceback("sparsevect.SparseVector.multiply");
  __pyx_r = 0;
  __pyx_L0:;
  Py_DECREF(__pyx_v_newvect);
  Py_DECREF(__pyx_v_a);
  Py_DECREF(__pyx_v_b);
  return __pyx_r;
}

static PyObject *__pyx_k9p;

static char __pyx_k9[] = "vector index out of range";

static int __pyx_f_10sparsevect_12SparseVector___setitem__(PyObject *__pyx_v_self, PyObject *__pyx_v_index, PyObject *__pyx_v_value); /*proto*/
static int __pyx_f_10sparsevect_12SparseVector___setitem__(PyObject *__pyx_v_self, PyObject *__pyx_v_index, PyObject *__pyx_v_value) {
  int __pyx_r;
  PyObject *__pyx_1 = 0;
  int __pyx_2;
  PyObject *__pyx_3 = 0;
  long __pyx_4;
  double __pyx_5;
  Py_INCREF(__pyx_v_self);
  Py_INCREF(__pyx_v_index);
  Py_INCREF(__pyx_v_value);

  /* "/home/batesbad/trunk/project/adam/sparsevect/python/sparsevect.pyx":86 */
  __pyx_1 = PyObject_GetAttr(__pyx_v_self, __pyx_n_dimension); if (!__pyx_1) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 86; goto __pyx_L1;}
  if (PyObject_Cmp(__pyx_v_index, __pyx_1, &__pyx_2) < 0) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 86; goto __pyx_L1;}
  __pyx_2 = __pyx_2 >= 0;
  Py_DECREF(__pyx_1); __pyx_1 = 0;
  if (__pyx_2) {
    __pyx_1 = PyTuple_New(1); if (!__pyx_1) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 87; goto __pyx_L1;}
    Py_INCREF(__pyx_k9p);
    PyTuple_SET_ITEM(__pyx_1, 0, __pyx_k9p);
    __pyx_3 = PyObject_CallObject(PyExc_IndexError, __pyx_1); if (!__pyx_3) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 87; goto __pyx_L1;}
    Py_DECREF(__pyx_1); __pyx_1 = 0;
    __Pyx_Raise(__pyx_3, 0, 0);
    Py_DECREF(__pyx_3); __pyx_3 = 0;
    {__pyx_filename = __pyx_f[0]; __pyx_lineno = 87; goto __pyx_L1;}
    goto __pyx_L2;
  }
  __pyx_L2:;

  /* "/home/batesbad/trunk/project/adam/sparsevect/python/sparsevect.pyx":88 */
  __pyx_4 = PyInt_AsLong(__pyx_v_index); if (PyErr_Occurred()) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 88; goto __pyx_L1;}
  __pyx_5 = PyFloat_AsDouble(__pyx_v_value); if (PyErr_Occurred()) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 88; goto __pyx_L1;}
  sv_set_element(((struct __pyx_obj_10sparsevect_SparseVector *)__pyx_v_self)->data,__pyx_4,__pyx_5);

  __pyx_r = 0;
  goto __pyx_L0;
  __pyx_L1:;
  Py_XDECREF(__pyx_1);
  Py_XDECREF(__pyx_3);
  __Pyx_AddTraceback("sparsevect.SparseVector.__setitem__");
  __pyx_r = -1;
  __pyx_L0:;
  Py_DECREF(__pyx_v_self);
  Py_DECREF(__pyx_v_index);
  Py_DECREF(__pyx_v_value);
  return __pyx_r;
}

static PyObject *__pyx_k10p;

static char __pyx_k10[] = "vector index out of range";

static PyObject *__pyx_f_10sparsevect_12SparseVector___getitem__(PyObject *__pyx_v_self, PyObject *__pyx_v_index); /*proto*/
static PyObject *__pyx_f_10sparsevect_12SparseVector___getitem__(PyObject *__pyx_v_self, PyObject *__pyx_v_index) {
  PyObject *__pyx_r;
  PyObject *__pyx_1 = 0;
  int __pyx_2;
  PyObject *__pyx_3 = 0;
  long __pyx_4;
  Py_INCREF(__pyx_v_self);
  Py_INCREF(__pyx_v_index);

  /* "/home/batesbad/trunk/project/adam/sparsevect/python/sparsevect.pyx":91 */
  __pyx_1 = PyObject_GetAttr(__pyx_v_self, __pyx_n_dimension); if (!__pyx_1) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 91; goto __pyx_L1;}
  if (PyObject_Cmp(__pyx_v_index, __pyx_1, &__pyx_2) < 0) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 91; goto __pyx_L1;}
  __pyx_2 = __pyx_2 >= 0;
  Py_DECREF(__pyx_1); __pyx_1 = 0;
  if (__pyx_2) {
    __pyx_1 = PyTuple_New(1); if (!__pyx_1) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 92; goto __pyx_L1;}
    Py_INCREF(__pyx_k10p);
    PyTuple_SET_ITEM(__pyx_1, 0, __pyx_k10p);
    __pyx_3 = PyObject_CallObject(PyExc_IndexError, __pyx_1); if (!__pyx_3) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 92; goto __pyx_L1;}
    Py_DECREF(__pyx_1); __pyx_1 = 0;
    __Pyx_Raise(__pyx_3, 0, 0);
    Py_DECREF(__pyx_3); __pyx_3 = 0;
    {__pyx_filename = __pyx_f[0]; __pyx_lineno = 92; goto __pyx_L1;}
    goto __pyx_L2;
  }
  __pyx_L2:;

  /* "/home/batesbad/trunk/project/adam/sparsevect/python/sparsevect.pyx":93 */
  __pyx_4 = PyInt_AsLong(__pyx_v_index); if (PyErr_Occurred()) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 93; goto __pyx_L1;}
  __pyx_1 = PyFloat_FromDouble(sv_get_element_value(((struct __pyx_obj_10sparsevect_SparseVector *)__pyx_v_self)->data,__pyx_4)); if (!__pyx_1) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 93; goto __pyx_L1;}
  __pyx_r = __pyx_1;
  __pyx_1 = 0;
  goto __pyx_L0;

  __pyx_r = Py_None; Py_INCREF(Py_None);
  goto __pyx_L0;
  __pyx_L1:;
  Py_XDECREF(__pyx_1);
  Py_XDECREF(__pyx_3);
  __Pyx_AddTraceback("sparsevect.SparseVector.__getitem__");
  __pyx_r = 0;
  __pyx_L0:;
  Py_DECREF(__pyx_v_self);
  Py_DECREF(__pyx_v_index);
  return __pyx_r;
}

static PyObject *__pyx_n_inner;
static PyObject *__pyx_n__scalar_mul;

static PyObject *__pyx_f_10sparsevect_12SparseVector___mul__(PyObject *__pyx_v_a, PyObject *__pyx_v_b); /*proto*/
static PyObject *__pyx_f_10sparsevect_12SparseVector___mul__(PyObject *__pyx_v_a, PyObject *__pyx_v_b) {
  PyObject *__pyx_r;
  int __pyx_1;
  PyObject *__pyx_2 = 0;
  PyObject *__pyx_3 = 0;
  PyObject *__pyx_4 = 0;
  Py_INCREF(__pyx_v_a);
  Py_INCREF(__pyx_v_b);
  __pyx_1 = PyObject_TypeCheck(__pyx_v_a,__pyx_ptype_10sparsevect_SparseVector);
  if (__pyx_1) {
    __pyx_1 = PyObject_TypeCheck(__pyx_v_b,__pyx_ptype_10sparsevect_SparseVector);
    if (__pyx_1) {
      __pyx_2 = PyObject_GetAttr(__pyx_v_a, __pyx_n_inner); if (!__pyx_2) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 104; goto __pyx_L1;}
      __pyx_3 = PyTuple_New(1); if (!__pyx_3) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 104; goto __pyx_L1;}
      Py_INCREF(__pyx_v_b);
      PyTuple_SET_ITEM(__pyx_3, 0, __pyx_v_b);
      __pyx_4 = PyObject_CallObject(__pyx_2, __pyx_3); if (!__pyx_4) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 104; goto __pyx_L1;}
      Py_DECREF(__pyx_2); __pyx_2 = 0;
      Py_DECREF(__pyx_3); __pyx_3 = 0;
      __pyx_r = __pyx_4;
      __pyx_4 = 0;
      goto __pyx_L0;
      goto __pyx_L3;
    }
    /*else*/ {
      __pyx_2 = __Pyx_GetName(__pyx_m, __pyx_n__scalar_mul); if (!__pyx_2) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 106; goto __pyx_L1;}
      __pyx_3 = PyTuple_New(2); if (!__pyx_3) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 106; goto __pyx_L1;}
      Py_INCREF(__pyx_v_a);
      PyTuple_SET_ITEM(__pyx_3, 0, __pyx_v_a);
      Py_INCREF(__pyx_v_b);
      PyTuple_SET_ITEM(__pyx_3, 1, __pyx_v_b);
      __pyx_4 = PyObject_CallObject(__pyx_2, __pyx_3); if (!__pyx_4) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 106; goto __pyx_L1;}
      Py_DECREF(__pyx_2); __pyx_2 = 0;
      Py_DECREF(__pyx_3); __pyx_3 = 0;
      __pyx_r = __pyx_4;
      __pyx_4 = 0;
      goto __pyx_L0;
    }
    __pyx_L3:;
    goto __pyx_L2;
  }
  /*else*/ {
    __pyx_2 = __Pyx_GetName(__pyx_m, __pyx_n__scalar_mul); if (!__pyx_2) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 108; goto __pyx_L1;}
    __pyx_3 = PyTuple_New(2); if (!__pyx_3) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 108; goto __pyx_L1;}
    Py_INCREF(__pyx_v_b);
    PyTuple_SET_ITEM(__pyx_3, 0, __pyx_v_b);
    Py_INCREF(__pyx_v_a);
    PyTuple_SET_ITEM(__pyx_3, 1, __pyx_v_a);
    __pyx_4 = PyObject_CallObject(__pyx_2, __pyx_3); if (!__pyx_4) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 108; goto __pyx_L1;}
    Py_DECREF(__pyx_2); __pyx_2 = 0;
    Py_DECREF(__pyx_3); __pyx_3 = 0;
    __pyx_r = __pyx_4;
    __pyx_4 = 0;
    goto __pyx_L0;
  }
  __pyx_L2:;

  __pyx_r = Py_None; Py_INCREF(Py_None);
  goto __pyx_L0;
  __pyx_L1:;
  Py_XDECREF(__pyx_2);
  Py_XDECREF(__pyx_3);
  Py_XDECREF(__pyx_4);
  __Pyx_AddTraceback("sparsevect.SparseVector.__mul__");
  __pyx_r = 0;
  __pyx_L0:;
  Py_DECREF(__pyx_v_a);
  Py_DECREF(__pyx_v_b);
  return __pyx_r;
}

static PyObject *__pyx_f_10sparsevect_12SparseVector___div__(PyObject *__pyx_v_a, PyObject *__pyx_arg_b); /*proto*/
static PyObject *__pyx_f_10sparsevect_12SparseVector___div__(PyObject *__pyx_v_a, PyObject *__pyx_arg_b) {
  double __pyx_v_b;
  PyObject *__pyx_r;
  PyObject *__pyx_1 = 0;
  PyObject *__pyx_2 = 0;
  PyObject *__pyx_3 = 0;
  Py_INCREF(__pyx_v_a);
  __pyx_v_b = PyFloat_AsDouble(__pyx_arg_b); if (PyErr_Occurred()) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 110; goto __pyx_L1;}
  if (!__Pyx_ArgTypeTest(((PyObject *)__pyx_v_a), __pyx_ptype_10sparsevect_SparseVector, 1, "a")) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 110; goto __pyx_L1;}
  __pyx_1 = __Pyx_GetName(__pyx_m, __pyx_n__scalar_mul); if (!__pyx_1) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 112; goto __pyx_L1;}
  __pyx_2 = PyFloat_FromDouble((1.0 / __pyx_v_b)); if (!__pyx_2) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 112; goto __pyx_L1;}
  __pyx_3 = PyTuple_New(2); if (!__pyx_3) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 112; goto __pyx_L1;}
  Py_INCREF(__pyx_v_a);
  PyTuple_SET_ITEM(__pyx_3, 0, __pyx_v_a);
  PyTuple_SET_ITEM(__pyx_3, 1, __pyx_2);
  __pyx_2 = 0;
  __pyx_2 = PyObject_CallObject(__pyx_1, __pyx_3); if (!__pyx_2) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 112; goto __pyx_L1;}
  Py_DECREF(__pyx_1); __pyx_1 = 0;
  Py_DECREF(__pyx_3); __pyx_3 = 0;
  __pyx_r = __pyx_2;
  __pyx_2 = 0;
  goto __pyx_L0;

  __pyx_r = Py_None; Py_INCREF(Py_None);
  goto __pyx_L0;
  __pyx_L1:;
  Py_XDECREF(__pyx_1);
  Py_XDECREF(__pyx_2);
  Py_XDECREF(__pyx_3);
  __Pyx_AddTraceback("sparsevect.SparseVector.__div__");
  __pyx_r = 0;
  __pyx_L0:;
  Py_DECREF(__pyx_v_a);
  return __pyx_r;
}

static PyObject *__pyx_f_10sparsevect_12SparseVector___add__(PyObject *__pyx_v_self, PyObject *__pyx_v_other); /*proto*/
static PyObject *__pyx_f_10sparsevect_12SparseVector___add__(PyObject *__pyx_v_self, PyObject *__pyx_v_other) {
  struct __pyx_obj_10sparsevect_SparseVector *__pyx_v_newvect;
  PyObject *__pyx_r;
  PyObject *__pyx_1 = 0;
  PyObject *__pyx_2 = 0;
  Py_INCREF(__pyx_v_self);
  Py_INCREF(__pyx_v_other);
  __pyx_v_newvect = ((struct __pyx_obj_10sparsevect_SparseVector *)Py_None); Py_INCREF(Py_None);
  if (!__Pyx_ArgTypeTest(((PyObject *)__pyx_v_self), __pyx_ptype_10sparsevect_SparseVector, 1, "self")) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 114; goto __pyx_L1;}
  if (!__Pyx_ArgTypeTest(((PyObject *)__pyx_v_other), __pyx_ptype_10sparsevect_SparseVector, 1, "other")) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 114; goto __pyx_L1;}

  /* "/home/batesbad/trunk/project/adam/sparsevect/python/sparsevect.pyx":117 */
  __pyx_1 = PyInt_FromLong(1); if (!__pyx_1) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 117; goto __pyx_L1;}
  __pyx_2 = PyTuple_New(1); if (!__pyx_2) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 117; goto __pyx_L1;}
  PyTuple_SET_ITEM(__pyx_2, 0, __pyx_1);
  __pyx_1 = 0;
  __pyx_1 = PyObject_CallObject(((PyObject *)__pyx_ptype_10sparsevect_SparseVector), __pyx_2); if (!__pyx_1) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 117; goto __pyx_L1;}
  Py_DECREF(__pyx_2); __pyx_2 = 0;
  Py_DECREF(((PyObject *)__pyx_v_newvect));
  __pyx_v_newvect = ((struct __pyx_obj_10sparsevect_SparseVector *)__pyx_1);
  __pyx_1 = 0;

  /* "/home/batesbad/trunk/project/adam/sparsevect/python/sparsevect.pyx":118 */
  sv_delete(__pyx_v_newvect->data);

  /* "/home/batesbad/trunk/project/adam/sparsevect/python/sparsevect.pyx":119 */
  __pyx_v_newvect->data = sv_add(((struct __pyx_obj_10sparsevect_SparseVector *)__pyx_v_self)->data,((struct __pyx_obj_10sparsevect_SparseVector *)__pyx_v_other)->data);

  /* "/home/batesbad/trunk/project/adam/sparsevect/python/sparsevect.pyx":121 */
  Py_INCREF(((PyObject *)__pyx_v_newvect));
  __pyx_r = ((PyObject *)__pyx_v_newvect);
  goto __pyx_L0;

  __pyx_r = Py_None; Py_INCREF(Py_None);
  goto __pyx_L0;
  __pyx_L1:;
  Py_XDECREF(__pyx_1);
  Py_XDECREF(__pyx_2);
  __Pyx_AddTraceback("sparsevect.SparseVector.__add__");
  __pyx_r = 0;
  __pyx_L0:;
  Py_DECREF(__pyx_v_newvect);
  Py_DECREF(__pyx_v_self);
  Py_DECREF(__pyx_v_other);
  return __pyx_r;
}

static PyObject *__pyx_f_10sparsevect_12SparseVector___sub__(PyObject *__pyx_v_self, PyObject *__pyx_v_other); /*proto*/
static PyObject *__pyx_f_10sparsevect_12SparseVector___sub__(PyObject *__pyx_v_self, PyObject *__pyx_v_other) {
  struct __pyx_obj_10sparsevect_SparseVector *__pyx_v_newvect;
  PyObject *__pyx_r;
  PyObject *__pyx_1 = 0;
  PyObject *__pyx_2 = 0;
  Py_INCREF(__pyx_v_self);
  Py_INCREF(__pyx_v_other);
  __pyx_v_newvect = ((struct __pyx_obj_10sparsevect_SparseVector *)Py_None); Py_INCREF(Py_None);
  if (!__Pyx_ArgTypeTest(((PyObject *)__pyx_v_self), __pyx_ptype_10sparsevect_SparseVector, 1, "self")) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 123; goto __pyx_L1;}
  if (!__Pyx_ArgTypeTest(((PyObject *)__pyx_v_other), __pyx_ptype_10sparsevect_SparseVector, 1, "other")) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 123; goto __pyx_L1;}

  /* "/home/batesbad/trunk/project/adam/sparsevect/python/sparsevect.pyx":126 */
  __pyx_1 = PyInt_FromLong(1); if (!__pyx_1) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 126; goto __pyx_L1;}
  __pyx_2 = PyTuple_New(1); if (!__pyx_2) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 126; goto __pyx_L1;}
  PyTuple_SET_ITEM(__pyx_2, 0, __pyx_1);
  __pyx_1 = 0;
  __pyx_1 = PyObject_CallObject(((PyObject *)__pyx_ptype_10sparsevect_SparseVector), __pyx_2); if (!__pyx_1) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 126; goto __pyx_L1;}
  Py_DECREF(__pyx_2); __pyx_2 = 0;
  Py_DECREF(((PyObject *)__pyx_v_newvect));
  __pyx_v_newvect = ((struct __pyx_obj_10sparsevect_SparseVector *)__pyx_1);
  __pyx_1 = 0;

  /* "/home/batesbad/trunk/project/adam/sparsevect/python/sparsevect.pyx":127 */
  sv_delete(__pyx_v_newvect->data);

  /* "/home/batesbad/trunk/project/adam/sparsevect/python/sparsevect.pyx":128 */
  __pyx_v_newvect->data = sv_subtract(((struct __pyx_obj_10sparsevect_SparseVector *)__pyx_v_self)->data,((struct __pyx_obj_10sparsevect_SparseVector *)__pyx_v_other)->data);

  /* "/home/batesbad/trunk/project/adam/sparsevect/python/sparsevect.pyx":130 */
  Py_INCREF(((PyObject *)__pyx_v_newvect));
  __pyx_r = ((PyObject *)__pyx_v_newvect);
  goto __pyx_L0;

  __pyx_r = Py_None; Py_INCREF(Py_None);
  goto __pyx_L0;
  __pyx_L1:;
  Py_XDECREF(__pyx_1);
  Py_XDECREF(__pyx_2);
  __Pyx_AddTraceback("sparsevect.SparseVector.__sub__");
  __pyx_r = 0;
  __pyx_L0:;
  Py_DECREF(__pyx_v_newvect);
  Py_DECREF(__pyx_v_self);
  Py_DECREF(__pyx_v_other);
  return __pyx_r;
}

static PyObject *__pyx_f_10sparsevect_12SparseVector___iter__(PyObject *__pyx_v_self); /*proto*/
static PyObject *__pyx_f_10sparsevect_12SparseVector___iter__(PyObject *__pyx_v_self) {
  PyObject *__pyx_r;
  PyObject *__pyx_1 = 0;
  PyObject *__pyx_2 = 0;
  Py_INCREF(__pyx_v_self);
  __pyx_1 = PyTuple_New(1); if (!__pyx_1) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 133; goto __pyx_L1;}
  Py_INCREF(__pyx_v_self);
  PyTuple_SET_ITEM(__pyx_1, 0, __pyx_v_self);
  __pyx_2 = PyObject_CallObject(((PyObject *)__pyx_ptype_10sparsevect__SparseVectorIterator), __pyx_1); if (!__pyx_2) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 133; goto __pyx_L1;}
  Py_DECREF(__pyx_1); __pyx_1 = 0;
  __pyx_r = __pyx_2;
  __pyx_2 = 0;
  goto __pyx_L0;

  __pyx_r = Py_None; Py_INCREF(Py_None);
  goto __pyx_L0;
  __pyx_L1:;
  Py_XDECREF(__pyx_1);
  Py_XDECREF(__pyx_2);
  __Pyx_AddTraceback("sparsevect.SparseVector.__iter__");
  __pyx_r = 0;
  __pyx_L0:;
  Py_DECREF(__pyx_v_self);
  return __pyx_r;
}

static PyObject *__pyx_f_10sparsevect_12SparseVector_9dimension___get__(PyObject *__pyx_v_self); /*proto*/
static PyObject *__pyx_f_10sparsevect_12SparseVector_9dimension___get__(PyObject *__pyx_v_self) {
  PyObject *__pyx_r;
  PyObject *__pyx_1 = 0;
  Py_INCREF(__pyx_v_self);
  __pyx_1 = PyInt_FromLong(sv_dimension(((struct __pyx_obj_10sparsevect_SparseVector *)__pyx_v_self)->data)); if (!__pyx_1) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 137; goto __pyx_L1;}
  __pyx_r = __pyx_1;
  __pyx_1 = 0;
  goto __pyx_L0;

  __pyx_r = Py_None; Py_INCREF(Py_None);
  goto __pyx_L0;
  __pyx_L1:;
  Py_XDECREF(__pyx_1);
  __Pyx_AddTraceback("sparsevect.SparseVector.dimension.__get__");
  __pyx_r = 0;
  __pyx_L0:;
  Py_DECREF(__pyx_v_self);
  return __pyx_r;
}

static PyObject *__pyx_n_append;

static PyObject *__pyx_f_10sparsevect_12SparseVector_7indices___get__(PyObject *__pyx_v_self); /*proto*/
static PyObject *__pyx_f_10sparsevect_12SparseVector_7indices___get__(PyObject *__pyx_v_self) {
  long __pyx_v_i;
  long *__pyx_v_c_indices;
  PyObject *__pyx_v_indices;
  PyObject *__pyx_r;
  PyObject *__pyx_1 = 0;
  long __pyx_2;
  PyObject *__pyx_3 = 0;
  PyObject *__pyx_4 = 0;
  Py_INCREF(__pyx_v_self);
  __pyx_v_indices = Py_None; Py_INCREF(Py_None);

  /* "/home/batesbad/trunk/project/adam/sparsevect/python/sparsevect.pyx":143 */
  __pyx_v_c_indices = sv_indices(((struct __pyx_obj_10sparsevect_SparseVector *)__pyx_v_self)->data);

  /* "/home/batesbad/trunk/project/adam/sparsevect/python/sparsevect.pyx":144 */
  __pyx_1 = PyList_New(0); if (!__pyx_1) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 144; goto __pyx_L1;}
  Py_DECREF(__pyx_v_indices);
  __pyx_v_indices = __pyx_1;
  __pyx_1 = 0;

  /* "/home/batesbad/trunk/project/adam/sparsevect/python/sparsevect.pyx":145 */
  __pyx_1 = PyObject_GetAttr(__pyx_v_self, __pyx_n_nonzeros); if (!__pyx_1) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 145; goto __pyx_L1;}
  __pyx_2 = PyInt_AsLong(__pyx_1); if (PyErr_Occurred()) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 145; goto __pyx_L1;}
  Py_DECREF(__pyx_1); __pyx_1 = 0;
  for (__pyx_v_i = 0; __pyx_v_i < __pyx_2; ++__pyx_v_i) {
    __pyx_1 = PyObject_GetAttr(__pyx_v_indices, __pyx_n_append); if (!__pyx_1) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 146; goto __pyx_L1;}
    __pyx_3 = PyInt_FromLong((__pyx_v_c_indices[__pyx_v_i])); if (!__pyx_3) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 146; goto __pyx_L1;}
    __pyx_4 = PyTuple_New(1); if (!__pyx_4) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 146; goto __pyx_L1;}
    PyTuple_SET_ITEM(__pyx_4, 0, __pyx_3);
    __pyx_3 = 0;
    __pyx_3 = PyObject_CallObject(__pyx_1, __pyx_4); if (!__pyx_3) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 146; goto __pyx_L1;}
    Py_DECREF(__pyx_1); __pyx_1 = 0;
    Py_DECREF(__pyx_4); __pyx_4 = 0;
    Py_DECREF(__pyx_3); __pyx_3 = 0;
  }

  /* "/home/batesbad/trunk/project/adam/sparsevect/python/sparsevect.pyx":147 */
  free(__pyx_v_c_indices);

  /* "/home/batesbad/trunk/project/adam/sparsevect/python/sparsevect.pyx":148 */
  Py_INCREF(__pyx_v_indices);
  __pyx_r = __pyx_v_indices;
  goto __pyx_L0;

  __pyx_r = Py_None; Py_INCREF(Py_None);
  goto __pyx_L0;
  __pyx_L1:;
  Py_XDECREF(__pyx_1);
  Py_XDECREF(__pyx_3);
  Py_XDECREF(__pyx_4);
  __Pyx_AddTraceback("sparsevect.SparseVector.indices.__get__");
  __pyx_r = 0;
  __pyx_L0:;
  Py_DECREF(__pyx_v_indices);
  Py_DECREF(__pyx_v_self);
  return __pyx_r;
}

static PyObject *__pyx_f_10sparsevect_12SparseVector_8nonzeros___get__(PyObject *__pyx_v_self); /*proto*/
static PyObject *__pyx_f_10sparsevect_12SparseVector_8nonzeros___get__(PyObject *__pyx_v_self) {
  PyObject *__pyx_r;
  PyObject *__pyx_1 = 0;
  Py_INCREF(__pyx_v_self);
  __pyx_1 = PyInt_FromLong(sv_nonzeros(((struct __pyx_obj_10sparsevect_SparseVector *)__pyx_v_self)->data)); if (!__pyx_1) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 152; goto __pyx_L1;}
  __pyx_r = __pyx_1;
  __pyx_1 = 0;
  goto __pyx_L0;

  __pyx_r = Py_None; Py_INCREF(Py_None);
  goto __pyx_L0;
  __pyx_L1:;
  Py_XDECREF(__pyx_1);
  __Pyx_AddTraceback("sparsevect.SparseVector.nonzeros.__get__");
  __pyx_r = 0;
  __pyx_L0:;
  Py_DECREF(__pyx_v_self);
  return __pyx_r;
}

static PyObject *__pyx_f_10sparsevect_12SparseVector_8elements___get__(PyObject *__pyx_v_self); /*proto*/
static PyObject *__pyx_f_10sparsevect_12SparseVector_8elements___get__(PyObject *__pyx_v_self) {
  int __pyx_v_i;
  struct sv_element *__pyx_v_sv_elements;
  PyObject *__pyx_v_elements;
  PyObject *__pyx_r;
  PyObject *__pyx_1 = 0;
  long __pyx_2;
  PyObject *__pyx_3 = 0;
  PyObject *__pyx_4 = 0;
  PyObject *__pyx_5 = 0;
  Py_INCREF(__pyx_v_self);
  __pyx_v_elements = Py_None; Py_INCREF(Py_None);

  /* "/home/batesbad/trunk/project/adam/sparsevect/python/sparsevect.pyx":160 */
  __pyx_v_sv_elements = sv_get_elements(((struct __pyx_obj_10sparsevect_SparseVector *)__pyx_v_self)->data);

  /* "/home/batesbad/trunk/project/adam/sparsevect/python/sparsevect.pyx":161 */
  __pyx_1 = PyList_New(0); if (!__pyx_1) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 161; goto __pyx_L1;}
  Py_DECREF(__pyx_v_elements);
  __pyx_v_elements = __pyx_1;
  __pyx_1 = 0;

  /* "/home/batesbad/trunk/project/adam/sparsevect/python/sparsevect.pyx":162 */
  __pyx_1 = PyObject_GetAttr(__pyx_v_self, __pyx_n_nonzeros); if (!__pyx_1) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 162; goto __pyx_L1;}
  __pyx_2 = PyInt_AsLong(__pyx_1); if (PyErr_Occurred()) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 162; goto __pyx_L1;}
  Py_DECREF(__pyx_1); __pyx_1 = 0;
  for (__pyx_v_i = 0; __pyx_v_i < __pyx_2; ++__pyx_v_i) {
    __pyx_1 = PyObject_GetAttr(__pyx_v_elements, __pyx_n_append); if (!__pyx_1) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 163; goto __pyx_L1;}
    __pyx_3 = PyInt_FromLong((__pyx_v_sv_elements[__pyx_v_i]).i); if (!__pyx_3) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 163; goto __pyx_L1;}
    __pyx_4 = PyFloat_FromDouble((__pyx_v_sv_elements[__pyx_v_i]).v); if (!__pyx_4) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 163; goto __pyx_L1;}
    __pyx_5 = PyTuple_New(2); if (!__pyx_5) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 163; goto __pyx_L1;}
    PyTuple_SET_ITEM(__pyx_5, 0, __pyx_3);
    PyTuple_SET_ITEM(__pyx_5, 1, __pyx_4);
    __pyx_3 = 0;
    __pyx_4 = 0;
    __pyx_3 = PyTuple_New(1); if (!__pyx_3) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 163; goto __pyx_L1;}
    PyTuple_SET_ITEM(__pyx_3, 0, __pyx_5);
    __pyx_5 = 0;
    __pyx_4 = PyObject_CallObject(__pyx_1, __pyx_3); if (!__pyx_4) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 163; goto __pyx_L1;}
    Py_DECREF(__pyx_1); __pyx_1 = 0;
    Py_DECREF(__pyx_3); __pyx_3 = 0;
    Py_DECREF(__pyx_4); __pyx_4 = 0;
  }

  /* "/home/batesbad/trunk/project/adam/sparsevect/python/sparsevect.pyx":164 */
  free(__pyx_v_sv_elements);

  /* "/home/batesbad/trunk/project/adam/sparsevect/python/sparsevect.pyx":165 */
  Py_INCREF(__pyx_v_elements);
  __pyx_r = __pyx_v_elements;
  goto __pyx_L0;

  __pyx_r = Py_None; Py_INCREF(Py_None);
  goto __pyx_L0;
  __pyx_L1:;
  Py_XDECREF(__pyx_1);
  Py_XDECREF(__pyx_3);
  Py_XDECREF(__pyx_4);
  Py_XDECREF(__pyx_5);
  __Pyx_AddTraceback("sparsevect.SparseVector.elements.__get__");
  __pyx_r = 0;
  __pyx_L0:;
  Py_DECREF(__pyx_v_elements);
  Py_DECREF(__pyx_v_self);
  return __pyx_r;
}

static PyObject *__pyx_n__newvector;
static PyObject *__pyx_n_write;
static PyObject *__pyx_n_getvalue;
static PyObject *__pyx_n_dump;

static PyObject *__pyx_k11p;

static char __pyx_k11[] = "%s:%r\n";

static PyObject *__pyx_f_10sparsevect_12SparseVector___reduce__(PyObject *__pyx_v_self, PyObject *__pyx_args, PyObject *__pyx_kwds); /*proto*/
static PyObject *__pyx_f_10sparsevect_12SparseVector___reduce__(PyObject *__pyx_v_self, PyObject *__pyx_args, PyObject *__pyx_kwds) {
  PyObject *__pyx_v_constructor;
  PyObject *__pyx_v_args;
  PyObject *__pyx_v_state;
  PyObject *__pyx_v_s;
  PyObject *__pyx_v_i;
  PyObject *__pyx_v_v;
  PyObject *__pyx_r;
  PyObject *__pyx_1 = 0;
  PyObject *__pyx_2 = 0;
  PyObject *__pyx_3 = 0;
  PyObject *__pyx_4 = 0;
  static char *__pyx_argnames[] = {0};
  if (!PyArg_ParseTupleAndKeywords(__pyx_args, __pyx_kwds, "", __pyx_argnames)) return 0;
  Py_INCREF(__pyx_v_self);
  __pyx_v_constructor = Py_None; Py_INCREF(Py_None);
  __pyx_v_args = Py_None; Py_INCREF(Py_None);
  __pyx_v_state = Py_None; Py_INCREF(Py_None);
  __pyx_v_s = Py_None; Py_INCREF(Py_None);
  __pyx_v_i = Py_None; Py_INCREF(Py_None);
  __pyx_v_v = Py_None; Py_INCREF(Py_None);

  /* "/home/batesbad/trunk/project/adam/sparsevect/python/sparsevect.pyx":169 */
  __pyx_1 = __Pyx_GetName(__pyx_m, __pyx_n__newvector); if (!__pyx_1) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 169; goto __pyx_L1;}
  Py_DECREF(__pyx_v_constructor);
  __pyx_v_constructor = __pyx_1;
  __pyx_1 = 0;

  /* "/home/batesbad/trunk/project/adam/sparsevect/python/sparsevect.pyx":170 */
  __pyx_1 = PyObject_GetAttr(__pyx_v_self, __pyx_n_dimension); if (!__pyx_1) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 170; goto __pyx_L1;}
  __pyx_2 = PyTuple_New(1); if (!__pyx_2) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 170; goto __pyx_L1;}
  PyTuple_SET_ITEM(__pyx_2, 0, __pyx_1);
  __pyx_1 = 0;
  Py_DECREF(__pyx_v_args);
  __pyx_v_args = __pyx_2;
  __pyx_2 = 0;

  /* "/home/batesbad/trunk/project/adam/sparsevect/python/sparsevect.pyx":172 */
  __pyx_1 = PyDict_New(); if (!__pyx_1) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 172; goto __pyx_L1;}
  Py_DECREF(__pyx_v_state);
  __pyx_v_state = __pyx_1;
  __pyx_1 = 0;

  /* "/home/batesbad/trunk/project/adam/sparsevect/python/sparsevect.pyx":176 */
  __pyx_2 = __Pyx_GetName(__pyx_m, __pyx_n_StringIO); if (!__pyx_2) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 176; goto __pyx_L1;}
  __pyx_1 = PyObject_GetAttr(__pyx_2, __pyx_n_StringIO); if (!__pyx_1) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 176; goto __pyx_L1;}
  Py_DECREF(__pyx_2); __pyx_2 = 0;
  __pyx_2 = PyObject_CallObject(__pyx_1, 0); if (!__pyx_2) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 176; goto __pyx_L1;}
  Py_DECREF(__pyx_1); __pyx_1 = 0;
  Py_DECREF(__pyx_v_s);
  __pyx_v_s = __pyx_2;
  __pyx_2 = 0;

  /* "/home/batesbad/trunk/project/adam/sparsevect/python/sparsevect.pyx":177 */
  __pyx_1 = PyObject_GetAttr(__pyx_v_self, __pyx_n_elements); if (!__pyx_1) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 177; goto __pyx_L1;}
  __pyx_2 = PyObject_GetIter(__pyx_1); if (!__pyx_2) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 177; goto __pyx_L1;}
  Py_DECREF(__pyx_1); __pyx_1 = 0;
  for (;;) {
    __pyx_1 = PyIter_Next(__pyx_2);
    if (!__pyx_1) {
      if (PyErr_Occurred()) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 177; goto __pyx_L1;}
      break;
    }
    __pyx_3 = PyObject_GetIter(__pyx_1); if (!__pyx_3) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 177; goto __pyx_L1;}
    Py_DECREF(__pyx_1); __pyx_1 = 0;
    __pyx_1 = __Pyx_UnpackItem(__pyx_3); if (!__pyx_1) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 177; goto __pyx_L1;}
    Py_DECREF(__pyx_v_i);
    __pyx_v_i = __pyx_1;
    __pyx_1 = 0;
    __pyx_1 = __Pyx_UnpackItem(__pyx_3); if (!__pyx_1) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 177; goto __pyx_L1;}
    Py_DECREF(__pyx_v_v);
    __pyx_v_v = __pyx_1;
    __pyx_1 = 0;
    if (__Pyx_EndUnpack(__pyx_3) < 0) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 177; goto __pyx_L1;}
    Py_DECREF(__pyx_3); __pyx_3 = 0;
    __pyx_1 = PyObject_GetAttr(__pyx_v_s, __pyx_n_write); if (!__pyx_1) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 178; goto __pyx_L1;}
    __pyx_3 = PyTuple_New(2); if (!__pyx_3) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 178; goto __pyx_L1;}
    Py_INCREF(__pyx_v_i);
    PyTuple_SET_ITEM(__pyx_3, 0, __pyx_v_i);
    Py_INCREF(__pyx_v_v);
    PyTuple_SET_ITEM(__pyx_3, 1, __pyx_v_v);
    __pyx_4 = PyNumber_Remainder(__pyx_k11p, __pyx_3); if (!__pyx_4) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 178; goto __pyx_L1;}
    Py_DECREF(__pyx_3); __pyx_3 = 0;
    __pyx_3 = PyTuple_New(1); if (!__pyx_3) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 178; goto __pyx_L1;}
    PyTuple_SET_ITEM(__pyx_3, 0, __pyx_4);
    __pyx_4 = 0;
    __pyx_4 = PyObject_CallObject(__pyx_1, __pyx_3); if (!__pyx_4) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 178; goto __pyx_L1;}
    Py_DECREF(__pyx_1); __pyx_1 = 0;
    Py_DECREF(__pyx_3); __pyx_3 = 0;
    Py_DECREF(__pyx_4); __pyx_4 = 0;
  }
  Py_DECREF(__pyx_2); __pyx_2 = 0;

  /* "/home/batesbad/trunk/project/adam/sparsevect/python/sparsevect.pyx":179 */
  __pyx_1 = PyObject_GetAttr(__pyx_v_s, __pyx_n_getvalue); if (!__pyx_1) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 179; goto __pyx_L1;}
  __pyx_3 = PyObject_CallObject(__pyx_1, 0); if (!__pyx_3) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 179; goto __pyx_L1;}
  Py_DECREF(__pyx_1); __pyx_1 = 0;
  if (PyObject_SetItem(__pyx_v_state, __pyx_n_dump, __pyx_3) < 0) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 179; goto __pyx_L1;}
  Py_DECREF(__pyx_3); __pyx_3 = 0;

  /* "/home/batesbad/trunk/project/adam/sparsevect/python/sparsevect.pyx":181 */
  __pyx_4 = PyTuple_New(3); if (!__pyx_4) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 181; goto __pyx_L1;}
  Py_INCREF(__pyx_v_constructor);
  PyTuple_SET_ITEM(__pyx_4, 0, __pyx_v_constructor);
  Py_INCREF(__pyx_v_args);
  PyTuple_SET_ITEM(__pyx_4, 1, __pyx_v_args);
  Py_INCREF(__pyx_v_state);
  PyTuple_SET_ITEM(__pyx_4, 2, __pyx_v_state);
  __pyx_r = __pyx_4;
  __pyx_4 = 0;
  goto __pyx_L0;

  __pyx_r = Py_None; Py_INCREF(Py_None);
  goto __pyx_L0;
  __pyx_L1:;
  Py_XDECREF(__pyx_1);
  Py_XDECREF(__pyx_2);
  Py_XDECREF(__pyx_3);
  Py_XDECREF(__pyx_4);
  __Pyx_AddTraceback("sparsevect.SparseVector.__reduce__");
  __pyx_r = 0;
  __pyx_L0:;
  Py_DECREF(__pyx_v_constructor);
  Py_DECREF(__pyx_v_args);
  Py_DECREF(__pyx_v_state);
  Py_DECREF(__pyx_v_s);
  Py_DECREF(__pyx_v_i);
  Py_DECREF(__pyx_v_v);
  Py_DECREF(__pyx_v_self);
  return __pyx_r;
}

static PyObject *__pyx_n_has_key;
static PyObject *__pyx_n_splitlines;
static PyObject *__pyx_n_split;

static PyObject *__pyx_k16p;

static char __pyx_k16[] = ":";

static PyObject *__pyx_f_10sparsevect_12SparseVector___setstate__(PyObject *__pyx_v_self, PyObject *__pyx_args, PyObject *__pyx_kwds); /*proto*/
static PyObject *__pyx_f_10sparsevect_12SparseVector___setstate__(PyObject *__pyx_v_self, PyObject *__pyx_args, PyObject *__pyx_kwds) {
  PyObject *__pyx_v_state = 0;
  PyObject *__pyx_v_elements;
  PyObject *__pyx_v_line;
  PyObject *__pyx_v_index_s;
  PyObject *__pyx_v_value_s;
  PyObject *__pyx_v_index;
  PyObject *__pyx_v_value;
  PyObject *__pyx_v_i;
  PyObject *__pyx_v_v;
  PyObject *__pyx_r;
  PyObject *__pyx_1 = 0;
  PyObject *__pyx_2 = 0;
  PyObject *__pyx_3 = 0;
  int __pyx_4;
  PyObject *__pyx_5 = 0;
  long __pyx_6;
  double __pyx_7;
  static char *__pyx_argnames[] = {"state",0};
  if (!PyArg_ParseTupleAndKeywords(__pyx_args, __pyx_kwds, "O", __pyx_argnames, &__pyx_v_state)) return 0;
  Py_INCREF(__pyx_v_self);
  Py_INCREF(__pyx_v_state);
  __pyx_v_elements = Py_None; Py_INCREF(Py_None);
  __pyx_v_line = Py_None; Py_INCREF(Py_None);
  __pyx_v_index_s = Py_None; Py_INCREF(Py_None);
  __pyx_v_value_s = Py_None; Py_INCREF(Py_None);
  __pyx_v_index = Py_None; Py_INCREF(Py_None);
  __pyx_v_value = Py_None; Py_INCREF(Py_None);
  __pyx_v_i = Py_None; Py_INCREF(Py_None);
  __pyx_v_v = Py_None; Py_INCREF(Py_None);

  /* "/home/batesbad/trunk/project/adam/sparsevect/python/sparsevect.pyx":184 */
  __pyx_1 = PyObject_GetAttr(__pyx_v_state, __pyx_n_has_key); if (!__pyx_1) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 184; goto __pyx_L1;}
  __pyx_2 = PyTuple_New(1); if (!__pyx_2) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 184; goto __pyx_L1;}
  Py_INCREF(__pyx_n_elements);
  PyTuple_SET_ITEM(__pyx_2, 0, __pyx_n_elements);
  __pyx_3 = PyObject_CallObject(__pyx_1, __pyx_2); if (!__pyx_3) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 184; goto __pyx_L1;}
  Py_DECREF(__pyx_1); __pyx_1 = 0;
  Py_DECREF(__pyx_2); __pyx_2 = 0;
  __pyx_4 = PyObject_IsTrue(__pyx_3); if (__pyx_4 < 0) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 184; goto __pyx_L1;}
  Py_DECREF(__pyx_3); __pyx_3 = 0;
  if (__pyx_4) {
    __pyx_1 = PyObject_GetItem(__pyx_v_state, __pyx_n_elements); if (!__pyx_1) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 186; goto __pyx_L1;}
    Py_DECREF(__pyx_v_elements);
    __pyx_v_elements = __pyx_1;
    __pyx_1 = 0;
    goto __pyx_L2;
  }
  /*else*/ {

    /* "/home/batesbad/trunk/project/adam/sparsevect/python/sparsevect.pyx":189 */
    __pyx_2 = PyList_New(0); if (!__pyx_2) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 189; goto __pyx_L1;}
    Py_DECREF(__pyx_v_elements);
    __pyx_v_elements = __pyx_2;
    __pyx_2 = 0;

    /* "/home/batesbad/trunk/project/adam/sparsevect/python/sparsevect.pyx":190 */
    __pyx_3 = PyObject_GetItem(__pyx_v_state, __pyx_n_dump); if (!__pyx_3) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 190; goto __pyx_L1;}
    __pyx_1 = PyObject_GetAttr(__pyx_3, __pyx_n_splitlines); if (!__pyx_1) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 190; goto __pyx_L1;}
    Py_DECREF(__pyx_3); __pyx_3 = 0;
    __pyx_2 = PyObject_CallObject(__pyx_1, 0); if (!__pyx_2) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 190; goto __pyx_L1;}
    Py_DECREF(__pyx_1); __pyx_1 = 0;
    __pyx_3 = PyObject_GetIter(__pyx_2); if (!__pyx_3) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 190; goto __pyx_L1;}
    Py_DECREF(__pyx_2); __pyx_2 = 0;
    for (;;) {
      __pyx_1 = PyIter_Next(__pyx_3);
      if (!__pyx_1) {
        if (PyErr_Occurred()) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 190; goto __pyx_L1;}
        break;
      }
      Py_DECREF(__pyx_v_line);
      __pyx_v_line = __pyx_1;
      __pyx_1 = 0;

      /* "/home/batesbad/trunk/project/adam/sparsevect/python/sparsevect.pyx":191 */
      __pyx_2 = PyObject_GetAttr(__pyx_v_line, __pyx_n_split); if (!__pyx_2) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 191; goto __pyx_L1;}
      __pyx_1 = PyTuple_New(1); if (!__pyx_1) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 191; goto __pyx_L1;}
      Py_INCREF(__pyx_k16p);
      PyTuple_SET_ITEM(__pyx_1, 0, __pyx_k16p);
      __pyx_5 = PyObject_CallObject(__pyx_2, __pyx_1); if (!__pyx_5) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 191; goto __pyx_L1;}
      Py_DECREF(__pyx_2); __pyx_2 = 0;
      Py_DECREF(__pyx_1); __pyx_1 = 0;
      __pyx_2 = PyObject_GetIter(__pyx_5); if (!__pyx_2) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 191; goto __pyx_L1;}
      Py_DECREF(__pyx_5); __pyx_5 = 0;
      __pyx_1 = __Pyx_UnpackItem(__pyx_2); if (!__pyx_1) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 191; goto __pyx_L1;}
      Py_DECREF(__pyx_v_index_s);
      __pyx_v_index_s = __pyx_1;
      __pyx_1 = 0;
      __pyx_5 = __Pyx_UnpackItem(__pyx_2); if (!__pyx_5) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 191; goto __pyx_L1;}
      Py_DECREF(__pyx_v_value_s);
      __pyx_v_value_s = __pyx_5;
      __pyx_5 = 0;
      if (__Pyx_EndUnpack(__pyx_2) < 0) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 191; goto __pyx_L1;}
      Py_DECREF(__pyx_2); __pyx_2 = 0;

      /* "/home/batesbad/trunk/project/adam/sparsevect/python/sparsevect.pyx":192 */
      __pyx_1 = PyTuple_New(1); if (!__pyx_1) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 192; goto __pyx_L1;}
      Py_INCREF(__pyx_v_index_s);
      PyTuple_SET_ITEM(__pyx_1, 0, __pyx_v_index_s);
      __pyx_5 = PyObject_CallObject(((PyObject *)(&PyInt_Type)), __pyx_1); if (!__pyx_5) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 192; goto __pyx_L1;}
      Py_DECREF(__pyx_1); __pyx_1 = 0;
      Py_DECREF(__pyx_v_index);
      __pyx_v_index = __pyx_5;
      __pyx_5 = 0;

      /* "/home/batesbad/trunk/project/adam/sparsevect/python/sparsevect.pyx":193 */
      __pyx_2 = PyTuple_New(1); if (!__pyx_2) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 193; goto __pyx_L1;}
      Py_INCREF(__pyx_v_value_s);
      PyTuple_SET_ITEM(__pyx_2, 0, __pyx_v_value_s);
      __pyx_1 = PyObject_CallObject(((PyObject *)(&PyFloat_Type)), __pyx_2); if (!__pyx_1) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 193; goto __pyx_L1;}
      Py_DECREF(__pyx_2); __pyx_2 = 0;
      Py_DECREF(__pyx_v_value);
      __pyx_v_value = __pyx_1;
      __pyx_1 = 0;

      /* "/home/batesbad/trunk/project/adam/sparsevect/python/sparsevect.pyx":194 */
      __pyx_5 = PyObject_GetAttr(__pyx_v_elements, __pyx_n_append); if (!__pyx_5) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 194; goto __pyx_L1;}
      __pyx_2 = PyTuple_New(2); if (!__pyx_2) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 194; goto __pyx_L1;}
      Py_INCREF(__pyx_v_index);
      PyTuple_SET_ITEM(__pyx_2, 0, __pyx_v_index);
      Py_INCREF(__pyx_v_value);
      PyTuple_SET_ITEM(__pyx_2, 1, __pyx_v_value);
      __pyx_1 = PyTuple_New(1); if (!__pyx_1) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 194; goto __pyx_L1;}
      PyTuple_SET_ITEM(__pyx_1, 0, __pyx_2);
      __pyx_2 = 0;
      __pyx_2 = PyObject_CallObject(__pyx_5, __pyx_1); if (!__pyx_2) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 194; goto __pyx_L1;}
      Py_DECREF(__pyx_5); __pyx_5 = 0;
      Py_DECREF(__pyx_1); __pyx_1 = 0;
      Py_DECREF(__pyx_2); __pyx_2 = 0;
    }
    Py_DECREF(__pyx_3); __pyx_3 = 0;
  }
  __pyx_L2:;

  /* "/home/batesbad/trunk/project/adam/sparsevect/python/sparsevect.pyx":196 */
  __pyx_5 = PyObject_GetIter(__pyx_v_elements); if (!__pyx_5) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 196; goto __pyx_L1;}
  for (;;) {
    __pyx_1 = PyIter_Next(__pyx_5);
    if (!__pyx_1) {
      if (PyErr_Occurred()) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 196; goto __pyx_L1;}
      break;
    }
    __pyx_2 = PyObject_GetIter(__pyx_1); if (!__pyx_2) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 196; goto __pyx_L1;}
    Py_DECREF(__pyx_1); __pyx_1 = 0;
    __pyx_3 = __Pyx_UnpackItem(__pyx_2); if (!__pyx_3) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 196; goto __pyx_L1;}
    Py_DECREF(__pyx_v_i);
    __pyx_v_i = __pyx_3;
    __pyx_3 = 0;
    __pyx_1 = __Pyx_UnpackItem(__pyx_2); if (!__pyx_1) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 196; goto __pyx_L1;}
    Py_DECREF(__pyx_v_v);
    __pyx_v_v = __pyx_1;
    __pyx_1 = 0;
    if (__Pyx_EndUnpack(__pyx_2) < 0) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 196; goto __pyx_L1;}
    Py_DECREF(__pyx_2); __pyx_2 = 0;
    __pyx_6 = PyInt_AsLong(__pyx_v_i); if (PyErr_Occurred()) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 197; goto __pyx_L1;}
    __pyx_7 = PyFloat_AsDouble(__pyx_v_v); if (PyErr_Occurred()) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 197; goto __pyx_L1;}
    sv_set_element(((struct __pyx_obj_10sparsevect_SparseVector *)__pyx_v_self)->data,__pyx_6,__pyx_7);
  }
  Py_DECREF(__pyx_5); __pyx_5 = 0;

  __pyx_r = Py_None; Py_INCREF(Py_None);
  goto __pyx_L0;
  __pyx_L1:;
  Py_XDECREF(__pyx_1);
  Py_XDECREF(__pyx_2);
  Py_XDECREF(__pyx_3);
  Py_XDECREF(__pyx_5);
  __Pyx_AddTraceback("sparsevect.SparseVector.__setstate__");
  __pyx_r = 0;
  __pyx_L0:;
  Py_DECREF(__pyx_v_elements);
  Py_DECREF(__pyx_v_line);
  Py_DECREF(__pyx_v_index_s);
  Py_DECREF(__pyx_v_value_s);
  Py_DECREF(__pyx_v_index);
  Py_DECREF(__pyx_v_value);
  Py_DECREF(__pyx_v_i);
  Py_DECREF(__pyx_v_v);
  Py_DECREF(__pyx_v_self);
  Py_DECREF(__pyx_v_state);
  return __pyx_r;
}

static int __pyx_f_10sparsevect_21_SparseVectorIterator___init__(PyObject *__pyx_v_self, PyObject *__pyx_args, PyObject *__pyx_kwds); /*proto*/
static int __pyx_f_10sparsevect_21_SparseVectorIterator___init__(PyObject *__pyx_v_self, PyObject *__pyx_args, PyObject *__pyx_kwds) {
  struct __pyx_obj_10sparsevect_SparseVector *__pyx_v_vector = 0;
  int __pyx_r;
  PyObject *__pyx_1 = 0;
  long __pyx_2;
  PyObject *__pyx_3 = 0;
  static char *__pyx_argnames[] = {"vector",0};
  if (!PyArg_ParseTupleAndKeywords(__pyx_args, __pyx_kwds, "O", __pyx_argnames, &__pyx_v_vector)) return -1;
  Py_INCREF(__pyx_v_self);
  Py_INCREF(__pyx_v_vector);
  if (!__Pyx_ArgTypeTest(((PyObject *)__pyx_v_vector), __pyx_ptype_10sparsevect_SparseVector, 1, "vector")) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 203; goto __pyx_L1;}

  /* "/home/batesbad/trunk/project/adam/sparsevect/python/sparsevect.pyx":204 */
  ((struct __pyx_obj_10sparsevect__SparseVectorIterator *)__pyx_v_self)->cur_index = 0;

  /* "/home/batesbad/trunk/project/adam/sparsevect/python/sparsevect.pyx":206 */
  __pyx_1 = PyObject_GetAttr(((PyObject *)__pyx_v_vector), __pyx_n_dimension); if (!__pyx_1) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 206; goto __pyx_L1;}
  __pyx_2 = PyInt_AsLong(__pyx_1); if (PyErr_Occurred()) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 206; goto __pyx_L1;}
  Py_DECREF(__pyx_1); __pyx_1 = 0;
  ((struct __pyx_obj_10sparsevect__SparseVectorIterator *)__pyx_v_self)->vector_dim = __pyx_2;

  /* "/home/batesbad/trunk/project/adam/sparsevect/python/sparsevect.pyx":207 */
  __pyx_1 = PyObject_GetAttr(((PyObject *)__pyx_v_vector), __pyx_n_elements); if (!__pyx_1) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 207; goto __pyx_L1;}
  __pyx_3 = PyTuple_New(1); if (!__pyx_3) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 207; goto __pyx_L1;}
  PyTuple_SET_ITEM(__pyx_3, 0, __pyx_1);
  __pyx_1 = 0;
  __pyx_1 = PyObject_CallObject(((PyObject *)(&PyDict_Type)), __pyx_3); if (!__pyx_1) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 207; goto __pyx_L1;}
  Py_DECREF(__pyx_3); __pyx_3 = 0;
  Py_DECREF(((PyObject *)((struct __pyx_obj_10sparsevect__SparseVectorIterator *)__pyx_v_self)->elements));
  ((struct __pyx_obj_10sparsevect__SparseVectorIterator *)__pyx_v_self)->elements = ((PyDictObject *)__pyx_1);
  __pyx_1 = 0;

  __pyx_r = 0;
  goto __pyx_L0;
  __pyx_L1:;
  Py_XDECREF(__pyx_1);
  Py_XDECREF(__pyx_3);
  __Pyx_AddTraceback("sparsevect._SparseVectorIterator.__init__");
  __pyx_r = -1;
  __pyx_L0:;
  Py_DECREF(__pyx_v_self);
  Py_DECREF(__pyx_v_vector);
  return __pyx_r;
}

static PyObject *__pyx_n_get;

static PyObject *__pyx_f_10sparsevect_21_SparseVectorIterator___next__(PyObject *__pyx_v_self); /*proto*/
static PyObject *__pyx_f_10sparsevect_21_SparseVectorIterator___next__(PyObject *__pyx_v_self) {
  PyObject *__pyx_v_value;
  PyObject *__pyx_r;
  int __pyx_1;
  PyObject *__pyx_2 = 0;
  PyObject *__pyx_3 = 0;
  PyObject *__pyx_4 = 0;
  PyObject *__pyx_5 = 0;
  Py_INCREF(__pyx_v_self);
  __pyx_v_value = Py_None; Py_INCREF(Py_None);

  /* "/home/batesbad/trunk/project/adam/sparsevect/python/sparsevect.pyx":210 */
  __pyx_1 = (((struct __pyx_obj_10sparsevect__SparseVectorIterator *)__pyx_v_self)->cur_index >= ((struct __pyx_obj_10sparsevect__SparseVectorIterator *)__pyx_v_self)->vector_dim);
  if (__pyx_1) {
    __Pyx_Raise(PyExc_StopIteration, 0, 0);
    {__pyx_filename = __pyx_f[0]; __pyx_lineno = 211; goto __pyx_L1;}
    goto __pyx_L2;
  }
  __pyx_L2:;

  /* "/home/batesbad/trunk/project/adam/sparsevect/python/sparsevect.pyx":212 */
  __pyx_2 = PyObject_GetAttr(((PyObject *)((struct __pyx_obj_10sparsevect__SparseVectorIterator *)__pyx_v_self)->elements), __pyx_n_get); if (!__pyx_2) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 212; goto __pyx_L1;}
  __pyx_3 = PyInt_FromLong(((struct __pyx_obj_10sparsevect__SparseVectorIterator *)__pyx_v_self)->cur_index); if (!__pyx_3) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 212; goto __pyx_L1;}
  __pyx_4 = PyFloat_FromDouble(0.0); if (!__pyx_4) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 212; goto __pyx_L1;}
  __pyx_5 = PyTuple_New(2); if (!__pyx_5) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 212; goto __pyx_L1;}
  PyTuple_SET_ITEM(__pyx_5, 0, __pyx_3);
  PyTuple_SET_ITEM(__pyx_5, 1, __pyx_4);
  __pyx_3 = 0;
  __pyx_4 = 0;
  __pyx_3 = PyObject_CallObject(__pyx_2, __pyx_5); if (!__pyx_3) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 212; goto __pyx_L1;}
  Py_DECREF(__pyx_2); __pyx_2 = 0;
  Py_DECREF(__pyx_5); __pyx_5 = 0;
  Py_DECREF(__pyx_v_value);
  __pyx_v_value = __pyx_3;
  __pyx_3 = 0;

  /* "/home/batesbad/trunk/project/adam/sparsevect/python/sparsevect.pyx":213 */
  ((struct __pyx_obj_10sparsevect__SparseVectorIterator *)__pyx_v_self)->cur_index = (((struct __pyx_obj_10sparsevect__SparseVectorIterator *)__pyx_v_self)->cur_index + 1);

  /* "/home/batesbad/trunk/project/adam/sparsevect/python/sparsevect.pyx":214 */
  Py_INCREF(__pyx_v_value);
  __pyx_r = __pyx_v_value;
  goto __pyx_L0;

  __pyx_r = Py_None; Py_INCREF(Py_None);
  goto __pyx_L0;
  __pyx_L1:;
  Py_XDECREF(__pyx_2);
  Py_XDECREF(__pyx_3);
  Py_XDECREF(__pyx_4);
  Py_XDECREF(__pyx_5);
  __Pyx_AddTraceback("sparsevect._SparseVectorIterator.__next__");
  __pyx_r = 0;
  __pyx_L0:;
  Py_DECREF(__pyx_v_value);
  Py_DECREF(__pyx_v_self);
  return __pyx_r;
}

static PyObject *__pyx_f_10sparsevect__newvector(PyObject *__pyx_self, PyObject *__pyx_args, PyObject *__pyx_kwds); /*proto*/
static PyObject *__pyx_f_10sparsevect__newvector(PyObject *__pyx_self, PyObject *__pyx_args, PyObject *__pyx_kwds) {
  PyObject *__pyx_v_dim = 0;
  PyObject *__pyx_r;
  PyObject *__pyx_1 = 0;
  PyObject *__pyx_2 = 0;
  static char *__pyx_argnames[] = {"dim",0};
  if (!PyArg_ParseTupleAndKeywords(__pyx_args, __pyx_kwds, "O", __pyx_argnames, &__pyx_v_dim)) return 0;
  Py_INCREF(__pyx_v_dim);
  __pyx_1 = PyTuple_New(1); if (!__pyx_1) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 217; goto __pyx_L1;}
  Py_INCREF(__pyx_v_dim);
  PyTuple_SET_ITEM(__pyx_1, 0, __pyx_v_dim);
  __pyx_2 = PyObject_CallObject(((PyObject *)__pyx_ptype_10sparsevect_SparseVector), __pyx_1); if (!__pyx_2) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 217; goto __pyx_L1;}
  Py_DECREF(__pyx_1); __pyx_1 = 0;
  __pyx_r = __pyx_2;
  __pyx_2 = 0;
  goto __pyx_L0;

  __pyx_r = Py_None; Py_INCREF(Py_None);
  goto __pyx_L0;
  __pyx_L1:;
  Py_XDECREF(__pyx_1);
  Py_XDECREF(__pyx_2);
  __Pyx_AddTraceback("sparsevect._newvector");
  __pyx_r = 0;
  __pyx_L0:;
  Py_DECREF(__pyx_v_dim);
  return __pyx_r;
}

static PyObject *__pyx_f_10sparsevect__scalar_mul(PyObject *__pyx_self, PyObject *__pyx_args, PyObject *__pyx_kwds); /*proto*/
static PyObject *__pyx_f_10sparsevect__scalar_mul(PyObject *__pyx_self, PyObject *__pyx_args, PyObject *__pyx_kwds) {
  struct __pyx_obj_10sparsevect_SparseVector *__pyx_v_vect = 0;
  double __pyx_v_other;
  struct __pyx_obj_10sparsevect_SparseVector *__pyx_v_newvect;
  PyObject *__pyx_r;
  PyObject *__pyx_1 = 0;
  PyObject *__pyx_2 = 0;
  static char *__pyx_argnames[] = {"vect","other",0};
  if (!PyArg_ParseTupleAndKeywords(__pyx_args, __pyx_kwds, "Od", __pyx_argnames, &__pyx_v_vect, &__pyx_v_other)) return 0;
  Py_INCREF(__pyx_v_vect);
  __pyx_v_newvect = ((struct __pyx_obj_10sparsevect_SparseVector *)Py_None); Py_INCREF(Py_None);
  if (!__Pyx_ArgTypeTest(((PyObject *)__pyx_v_vect), __pyx_ptype_10sparsevect_SparseVector, 1, "vect")) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 219; goto __pyx_L1;}

  /* "/home/batesbad/trunk/project/adam/sparsevect/python/sparsevect.pyx":222 */
  __pyx_1 = PyInt_FromLong(1); if (!__pyx_1) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 222; goto __pyx_L1;}
  __pyx_2 = PyTuple_New(1); if (!__pyx_2) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 222; goto __pyx_L1;}
  PyTuple_SET_ITEM(__pyx_2, 0, __pyx_1);
  __pyx_1 = 0;
  __pyx_1 = PyObject_CallObject(((PyObject *)__pyx_ptype_10sparsevect_SparseVector), __pyx_2); if (!__pyx_1) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 222; goto __pyx_L1;}
  Py_DECREF(__pyx_2); __pyx_2 = 0;
  Py_DECREF(((PyObject *)__pyx_v_newvect));
  __pyx_v_newvect = ((struct __pyx_obj_10sparsevect_SparseVector *)__pyx_1);
  __pyx_1 = 0;

  /* "/home/batesbad/trunk/project/adam/sparsevect/python/sparsevect.pyx":223 */
  sv_delete(__pyx_v_newvect->data);

  /* "/home/batesbad/trunk/project/adam/sparsevect/python/sparsevect.pyx":224 */
  __pyx_v_newvect->data = sv_scalar_mult(__pyx_v_vect->data,__pyx_v_other);

  /* "/home/batesbad/trunk/project/adam/sparsevect/python/sparsevect.pyx":225 */
  Py_INCREF(((PyObject *)__pyx_v_newvect));
  __pyx_r = ((PyObject *)__pyx_v_newvect);
  goto __pyx_L0;

  __pyx_r = Py_None; Py_INCREF(Py_None);
  goto __pyx_L0;
  __pyx_L1:;
  Py_XDECREF(__pyx_1);
  Py_XDECREF(__pyx_2);
  __Pyx_AddTraceback("sparsevect._scalar_mul");
  __pyx_r = 0;
  __pyx_L0:;
  Py_DECREF(__pyx_v_newvect);
  Py_DECREF(__pyx_v_vect);
  return __pyx_r;
}

static __Pyx_InternTabEntry __pyx_intern_tab[] = {
  {&__pyx_n_StringIO, "StringIO"},
  {&__pyx_n__newvector, "_newvector"},
  {&__pyx_n__scalar_mul, "_scalar_mul"},
  {&__pyx_n_append, "append"},
  {&__pyx_n_dimension, "dimension"},
  {&__pyx_n_dump, "dump"},
  {&__pyx_n_elements, "elements"},
  {&__pyx_n_get, "get"},
  {&__pyx_n_getvalue, "getvalue"},
  {&__pyx_n_has_key, "has_key"},
  {&__pyx_n_inner, "inner"},
  {&__pyx_n_nonzeros, "nonzeros"},
  {&__pyx_n_split, "split"},
  {&__pyx_n_splitlines, "splitlines"},
  {&__pyx_n_sys, "sys"},
  {&__pyx_n_types, "types"},
  {&__pyx_n_write, "write"},
  {0, 0}
};

static __Pyx_StringTabEntry __pyx_string_tab[] = {
  {&__pyx_k4p, __pyx_k4, sizeof(__pyx_k4)},
  {&__pyx_k5p, __pyx_k5, sizeof(__pyx_k5)},
  {&__pyx_k6p, __pyx_k6, sizeof(__pyx_k6)},
  {&__pyx_k7p, __pyx_k7, sizeof(__pyx_k7)},
  {&__pyx_k8p, __pyx_k8, sizeof(__pyx_k8)},
  {&__pyx_k9p, __pyx_k9, sizeof(__pyx_k9)},
  {&__pyx_k10p, __pyx_k10, sizeof(__pyx_k10)},
  {&__pyx_k11p, __pyx_k11, sizeof(__pyx_k11)},
  {&__pyx_k16p, __pyx_k16, sizeof(__pyx_k16)},
  {0, 0, 0}
};

static PyObject *__pyx_tp_new_10sparsevect_SparseVector(PyTypeObject *t, PyObject *a, PyObject *k) {
  PyObject *o = (*t->tp_alloc)(t, 0);
  if (!o) return 0;
  if (__pyx_f_10sparsevect_12SparseVector___cinit__(o, a, k) < 0) {
    Py_DECREF(o); o = 0;
  }
  return o;
}

static void __pyx_tp_dealloc_10sparsevect_SparseVector(PyObject *o) {
  {
    PyObject *etype, *eval, *etb;
    PyErr_Fetch(&etype, &eval, &etb);
    ++o->ob_refcnt;
    __pyx_f_10sparsevect_12SparseVector___dealloc__(o);
    if (PyErr_Occurred()) PyErr_WriteUnraisable(o);
    --o->ob_refcnt;
    PyErr_Restore(etype, eval, etb);
  }
  (*o->ob_type->tp_free)(o);
}
static PyObject *__pyx_sq_item_10sparsevect_SparseVector(PyObject *o, Py_ssize_t i) {
  PyObject *r;
  PyObject *x = PyInt_FromSsize_t(i); if(!x) return 0;
  r = o->ob_type->tp_as_mapping->mp_subscript(o, x);
  Py_DECREF(x);
  return r;
}

static int __pyx_mp_ass_subscript_10sparsevect_SparseVector(PyObject *o, PyObject *i, PyObject *v) {
  if (v) {
    return __pyx_f_10sparsevect_12SparseVector___setitem__(o, i, v);
  }
  else {
    PyErr_Format(PyExc_NotImplementedError,
      "Subscript deletion not supported by %s", o->ob_type->tp_name);
    return -1;
  }
}

static PyObject *__pyx_getprop_10sparsevect_12SparseVector_dimension(PyObject *o, void *x) {
  return __pyx_f_10sparsevect_12SparseVector_9dimension___get__(o);
}

static PyObject *__pyx_getprop_10sparsevect_12SparseVector_indices(PyObject *o, void *x) {
  return __pyx_f_10sparsevect_12SparseVector_7indices___get__(o);
}

static PyObject *__pyx_getprop_10sparsevect_12SparseVector_nonzeros(PyObject *o, void *x) {
  return __pyx_f_10sparsevect_12SparseVector_8nonzeros___get__(o);
}

static PyObject *__pyx_getprop_10sparsevect_12SparseVector_elements(PyObject *o, void *x) {
  return __pyx_f_10sparsevect_12SparseVector_8elements___get__(o);
}

static struct PyMethodDef __pyx_methods_10sparsevect_SparseVector[] = {
  {"inner", (PyCFunction)__pyx_f_10sparsevect_12SparseVector_inner, METH_VARARGS|METH_KEYWORDS, 0},
  {"multiply", (PyCFunction)__pyx_f_10sparsevect_12SparseVector_multiply, METH_VARARGS|METH_KEYWORDS, 0},
  {"__reduce__", (PyCFunction)__pyx_f_10sparsevect_12SparseVector___reduce__, METH_VARARGS|METH_KEYWORDS, 0},
  {"__setstate__", (PyCFunction)__pyx_f_10sparsevect_12SparseVector___setstate__, METH_VARARGS|METH_KEYWORDS, 0},
  {0, 0, 0, 0}
};

static struct PyGetSetDef __pyx_getsets_10sparsevect_SparseVector[] = {
  {"dimension", __pyx_getprop_10sparsevect_12SparseVector_dimension, 0, 0, 0},
  {"indices", __pyx_getprop_10sparsevect_12SparseVector_indices, 0, 0, 0},
  {"nonzeros", __pyx_getprop_10sparsevect_12SparseVector_nonzeros, 0, 0, 0},
  {"elements", __pyx_getprop_10sparsevect_12SparseVector_elements, 0, 0, 0},
  {0, 0, 0, 0, 0}
};

static PyNumberMethods __pyx_tp_as_number_SparseVector = {
  __pyx_f_10sparsevect_12SparseVector___add__, /*nb_add*/
  __pyx_f_10sparsevect_12SparseVector___sub__, /*nb_subtract*/
  __pyx_f_10sparsevect_12SparseVector___mul__, /*nb_multiply*/
  __pyx_f_10sparsevect_12SparseVector___div__, /*nb_divide*/
  0, /*nb_remainder*/
  0, /*nb_divmod*/
  0, /*nb_power*/
  0, /*nb_negative*/
  0, /*nb_positive*/
  0, /*nb_absolute*/
  0, /*nb_nonzero*/
  0, /*nb_invert*/
  0, /*nb_lshift*/
  0, /*nb_rshift*/
  0, /*nb_and*/
  0, /*nb_xor*/
  0, /*nb_or*/
  0, /*nb_coerce*/
  0, /*nb_int*/
  0, /*nb_long*/
  0, /*nb_float*/
  0, /*nb_oct*/
  0, /*nb_hex*/
  0, /*nb_inplace_add*/
  0, /*nb_inplace_subtract*/
  0, /*nb_inplace_multiply*/
  0, /*nb_inplace_divide*/
  0, /*nb_inplace_remainder*/
  0, /*nb_inplace_power*/
  0, /*nb_inplace_lshift*/
  0, /*nb_inplace_rshift*/
  0, /*nb_inplace_and*/
  0, /*nb_inplace_xor*/
  0, /*nb_inplace_or*/
  0, /*nb_floor_divide*/
  0, /*nb_true_divide*/
  0, /*nb_inplace_floor_divide*/
  0, /*nb_inplace_true_divide*/
  #if Py_TPFLAGS_DEFAULT & Py_TPFLAGS_HAVE_INDEX
  0, /*nb_index*/
  #endif
};

static PySequenceMethods __pyx_tp_as_sequence_SparseVector = {
  0, /*sq_length*/
  0, /*sq_concat*/
  0, /*sq_repeat*/
  __pyx_sq_item_10sparsevect_SparseVector, /*sq_item*/
  0, /*sq_slice*/
  0, /*sq_ass_item*/
  0, /*sq_ass_slice*/
  0, /*sq_contains*/
  0, /*sq_inplace_concat*/
  0, /*sq_inplace_repeat*/
};

static PyMappingMethods __pyx_tp_as_mapping_SparseVector = {
  0, /*mp_length*/
  __pyx_f_10sparsevect_12SparseVector___getitem__, /*mp_subscript*/
  __pyx_mp_ass_subscript_10sparsevect_SparseVector, /*mp_ass_subscript*/
};

static PyBufferProcs __pyx_tp_as_buffer_SparseVector = {
  0, /*bf_getreadbuffer*/
  0, /*bf_getwritebuffer*/
  0, /*bf_getsegcount*/
  0, /*bf_getcharbuffer*/
};

PyTypeObject __pyx_type_10sparsevect_SparseVector = {
  PyObject_HEAD_INIT(0)
  0, /*ob_size*/
  "sparsevect.SparseVector", /*tp_name*/
  sizeof(struct __pyx_obj_10sparsevect_SparseVector), /*tp_basicsize*/
  0, /*tp_itemsize*/
  __pyx_tp_dealloc_10sparsevect_SparseVector, /*tp_dealloc*/
  0, /*tp_print*/
  0, /*tp_getattr*/
  0, /*tp_setattr*/
  0, /*tp_compare*/
  0, /*tp_repr*/
  &__pyx_tp_as_number_SparseVector, /*tp_as_number*/
  &__pyx_tp_as_sequence_SparseVector, /*tp_as_sequence*/
  &__pyx_tp_as_mapping_SparseVector, /*tp_as_mapping*/
  0, /*tp_hash*/
  0, /*tp_call*/
  __pyx_f_10sparsevect_12SparseVector___str__, /*tp_str*/
  0, /*tp_getattro*/
  0, /*tp_setattro*/
  &__pyx_tp_as_buffer_SparseVector, /*tp_as_buffer*/
  Py_TPFLAGS_DEFAULT|Py_TPFLAGS_CHECKTYPES|Py_TPFLAGS_BASETYPE, /*tp_flags*/
  0, /*tp_doc*/
  0, /*tp_traverse*/
  0, /*tp_clear*/
  0, /*tp_richcompare*/
  0, /*tp_weaklistoffset*/
  __pyx_f_10sparsevect_12SparseVector___iter__, /*tp_iter*/
  0, /*tp_iternext*/
  __pyx_methods_10sparsevect_SparseVector, /*tp_methods*/
  0, /*tp_members*/
  __pyx_getsets_10sparsevect_SparseVector, /*tp_getset*/
  0, /*tp_base*/
  0, /*tp_dict*/
  0, /*tp_descr_get*/
  0, /*tp_descr_set*/
  0, /*tp_dictoffset*/
  __pyx_f_10sparsevect_12SparseVector___init__, /*tp_init*/
  0, /*tp_alloc*/
  __pyx_tp_new_10sparsevect_SparseVector, /*tp_new*/
  0, /*tp_free*/
  0, /*tp_is_gc*/
  0, /*tp_bases*/
  0, /*tp_mro*/
  0, /*tp_cache*/
  0, /*tp_subclasses*/
  0, /*tp_weaklist*/
};

static PyObject *__pyx_tp_new_10sparsevect__SparseVectorIterator(PyTypeObject *t, PyObject *a, PyObject *k) {
  struct __pyx_obj_10sparsevect__SparseVectorIterator *p;
  PyObject *o = (*t->tp_alloc)(t, 0);
  if (!o) return 0;
  p = ((struct __pyx_obj_10sparsevect__SparseVectorIterator *)o);
  p->elements = ((PyDictObject *)Py_None); Py_INCREF(Py_None);
  return o;
}

static void __pyx_tp_dealloc_10sparsevect__SparseVectorIterator(PyObject *o) {
  struct __pyx_obj_10sparsevect__SparseVectorIterator *p = (struct __pyx_obj_10sparsevect__SparseVectorIterator *)o;
  Py_XDECREF(((PyObject *)p->elements));
  (*o->ob_type->tp_free)(o);
}

static int __pyx_tp_traverse_10sparsevect__SparseVectorIterator(PyObject *o, visitproc v, void *a) {
  int e;
  struct __pyx_obj_10sparsevect__SparseVectorIterator *p = (struct __pyx_obj_10sparsevect__SparseVectorIterator *)o;
  if (p->elements) {
    e = (*v)(((PyObject*)p->elements), a); if (e) return e;
  }
  return 0;
}

static int __pyx_tp_clear_10sparsevect__SparseVectorIterator(PyObject *o) {
  struct __pyx_obj_10sparsevect__SparseVectorIterator *p = (struct __pyx_obj_10sparsevect__SparseVectorIterator *)o;
  PyObject *t;
  t = ((PyObject *)p->elements); 
  p->elements = ((PyDictObject *)Py_None); Py_INCREF(Py_None);
  Py_XDECREF(t);
  return 0;
}

static struct PyMethodDef __pyx_methods_10sparsevect__SparseVectorIterator[] = {
  {0, 0, 0, 0}
};

static PyNumberMethods __pyx_tp_as_number__SparseVectorIterator = {
  0, /*nb_add*/
  0, /*nb_subtract*/
  0, /*nb_multiply*/
  0, /*nb_divide*/
  0, /*nb_remainder*/
  0, /*nb_divmod*/
  0, /*nb_power*/
  0, /*nb_negative*/
  0, /*nb_positive*/
  0, /*nb_absolute*/
  0, /*nb_nonzero*/
  0, /*nb_invert*/
  0, /*nb_lshift*/
  0, /*nb_rshift*/
  0, /*nb_and*/
  0, /*nb_xor*/
  0, /*nb_or*/
  0, /*nb_coerce*/
  0, /*nb_int*/
  0, /*nb_long*/
  0, /*nb_float*/
  0, /*nb_oct*/
  0, /*nb_hex*/
  0, /*nb_inplace_add*/
  0, /*nb_inplace_subtract*/
  0, /*nb_inplace_multiply*/
  0, /*nb_inplace_divide*/
  0, /*nb_inplace_remainder*/
  0, /*nb_inplace_power*/
  0, /*nb_inplace_lshift*/
  0, /*nb_inplace_rshift*/
  0, /*nb_inplace_and*/
  0, /*nb_inplace_xor*/
  0, /*nb_inplace_or*/
  0, /*nb_floor_divide*/
  0, /*nb_true_divide*/
  0, /*nb_inplace_floor_divide*/
  0, /*nb_inplace_true_divide*/
  #if Py_TPFLAGS_DEFAULT & Py_TPFLAGS_HAVE_INDEX
  0, /*nb_index*/
  #endif
};

static PySequenceMethods __pyx_tp_as_sequence__SparseVectorIterator = {
  0, /*sq_length*/
  0, /*sq_concat*/
  0, /*sq_repeat*/
  0, /*sq_item*/
  0, /*sq_slice*/
  0, /*sq_ass_item*/
  0, /*sq_ass_slice*/
  0, /*sq_contains*/
  0, /*sq_inplace_concat*/
  0, /*sq_inplace_repeat*/
};

static PyMappingMethods __pyx_tp_as_mapping__SparseVectorIterator = {
  0, /*mp_length*/
  0, /*mp_subscript*/
  0, /*mp_ass_subscript*/
};

static PyBufferProcs __pyx_tp_as_buffer__SparseVectorIterator = {
  0, /*bf_getreadbuffer*/
  0, /*bf_getwritebuffer*/
  0, /*bf_getsegcount*/
  0, /*bf_getcharbuffer*/
};

PyTypeObject __pyx_type_10sparsevect__SparseVectorIterator = {
  PyObject_HEAD_INIT(0)
  0, /*ob_size*/
  "sparsevect._SparseVectorIterator", /*tp_name*/
  sizeof(struct __pyx_obj_10sparsevect__SparseVectorIterator), /*tp_basicsize*/
  0, /*tp_itemsize*/
  __pyx_tp_dealloc_10sparsevect__SparseVectorIterator, /*tp_dealloc*/
  0, /*tp_print*/
  0, /*tp_getattr*/
  0, /*tp_setattr*/
  0, /*tp_compare*/
  0, /*tp_repr*/
  &__pyx_tp_as_number__SparseVectorIterator, /*tp_as_number*/
  &__pyx_tp_as_sequence__SparseVectorIterator, /*tp_as_sequence*/
  &__pyx_tp_as_mapping__SparseVectorIterator, /*tp_as_mapping*/
  0, /*tp_hash*/
  0, /*tp_call*/
  0, /*tp_str*/
  0, /*tp_getattro*/
  0, /*tp_setattro*/
  &__pyx_tp_as_buffer__SparseVectorIterator, /*tp_as_buffer*/
  Py_TPFLAGS_DEFAULT|Py_TPFLAGS_CHECKTYPES|Py_TPFLAGS_BASETYPE|Py_TPFLAGS_HAVE_GC, /*tp_flags*/
  0, /*tp_doc*/
  __pyx_tp_traverse_10sparsevect__SparseVectorIterator, /*tp_traverse*/
  __pyx_tp_clear_10sparsevect__SparseVectorIterator, /*tp_clear*/
  0, /*tp_richcompare*/
  0, /*tp_weaklistoffset*/
  0, /*tp_iter*/
  __pyx_f_10sparsevect_21_SparseVectorIterator___next__, /*tp_iternext*/
  __pyx_methods_10sparsevect__SparseVectorIterator, /*tp_methods*/
  0, /*tp_members*/
  0, /*tp_getset*/
  0, /*tp_base*/
  0, /*tp_dict*/
  0, /*tp_descr_get*/
  0, /*tp_descr_set*/
  0, /*tp_dictoffset*/
  __pyx_f_10sparsevect_21_SparseVectorIterator___init__, /*tp_init*/
  0, /*tp_alloc*/
  __pyx_tp_new_10sparsevect__SparseVectorIterator, /*tp_new*/
  0, /*tp_free*/
  0, /*tp_is_gc*/
  0, /*tp_bases*/
  0, /*tp_mro*/
  0, /*tp_cache*/
  0, /*tp_subclasses*/
  0, /*tp_weaklist*/
};

static struct PyMethodDef __pyx_methods[] = {
  {"_newvector", (PyCFunction)__pyx_f_10sparsevect__newvector, METH_VARARGS|METH_KEYWORDS, 0},
  {"_scalar_mul", (PyCFunction)__pyx_f_10sparsevect__scalar_mul, METH_VARARGS|METH_KEYWORDS, 0},
  {0, 0, 0, 0}
};

static void __pyx_init_filenames(void); /*proto*/

PyMODINIT_FUNC initsparsevect(void); /*proto*/
PyMODINIT_FUNC initsparsevect(void) {
  PyObject *__pyx_1 = 0;
  __pyx_init_filenames();
  __pyx_m = Py_InitModule4("sparsevect", __pyx_methods, 0, 0, PYTHON_API_VERSION);
  if (!__pyx_m) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 18; goto __pyx_L1;};
  Py_INCREF(__pyx_m);
  __pyx_b = PyImport_AddModule("__builtin__");
  if (!__pyx_b) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 18; goto __pyx_L1;};
  if (PyObject_SetAttrString(__pyx_m, "__builtins__", __pyx_b) < 0) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 18; goto __pyx_L1;};
  if (__Pyx_InternStrings(__pyx_intern_tab) < 0) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 18; goto __pyx_L1;};
  if (__Pyx_InitStrings(__pyx_string_tab) < 0) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 18; goto __pyx_L1;};
  if (PyType_Ready(&__pyx_type_10sparsevect_SparseVector) < 0) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 48; goto __pyx_L1;}
  if (PyObject_SetAttrString(__pyx_m, "SparseVector", (PyObject *)&__pyx_type_10sparsevect_SparseVector) < 0) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 48; goto __pyx_L1;}
  __pyx_ptype_10sparsevect_SparseVector = &__pyx_type_10sparsevect_SparseVector;
  __pyx_type_10sparsevect__SparseVectorIterator.tp_free = _PyObject_GC_Del;
  if (PyType_Ready(&__pyx_type_10sparsevect__SparseVectorIterator) < 0) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 199; goto __pyx_L1;}
  if (PyObject_SetAttrString(__pyx_m, "_SparseVectorIterator", (PyObject *)&__pyx_type_10sparsevect__SparseVectorIterator) < 0) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 199; goto __pyx_L1;}
  __pyx_ptype_10sparsevect__SparseVectorIterator = &__pyx_type_10sparsevect__SparseVectorIterator;

  /* "/home/batesbad/trunk/project/adam/sparsevect/python/sparsevect.pyx":44 */
  __pyx_1 = __Pyx_Import(__pyx_n_sys, 0); if (!__pyx_1) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 44; goto __pyx_L1;}
  if (PyObject_SetAttr(__pyx_m, __pyx_n_sys, __pyx_1) < 0) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 44; goto __pyx_L1;}
  Py_DECREF(__pyx_1); __pyx_1 = 0;

  /* "/home/batesbad/trunk/project/adam/sparsevect/python/sparsevect.pyx":45 */
  __pyx_1 = __Pyx_Import(__pyx_n_types, 0); if (!__pyx_1) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 45; goto __pyx_L1;}
  if (PyObject_SetAttr(__pyx_m, __pyx_n_types, __pyx_1) < 0) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 45; goto __pyx_L1;}
  Py_DECREF(__pyx_1); __pyx_1 = 0;

  /* "/home/batesbad/trunk/project/adam/sparsevect/python/sparsevect.pyx":46 */
  __pyx_1 = __Pyx_Import(__pyx_n_StringIO, 0); if (!__pyx_1) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 46; goto __pyx_L1;}
  if (PyObject_SetAttr(__pyx_m, __pyx_n_StringIO, __pyx_1) < 0) {__pyx_filename = __pyx_f[0]; __pyx_lineno = 46; goto __pyx_L1;}
  Py_DECREF(__pyx_1); __pyx_1 = 0;

  /* "/home/batesbad/trunk/project/adam/sparsevect/python/sparsevect.pyx":219 */
  return;
  __pyx_L1:;
  Py_XDECREF(__pyx_1);
  __Pyx_AddTraceback("sparsevect");
}

static char *__pyx_filenames[] = {
  "sparsevect.pyx",
};

/* Runtime support code */

static void __pyx_init_filenames(void) {
  __pyx_f = __pyx_filenames;
}

static int __Pyx_ArgTypeTest(PyObject *obj, PyTypeObject *type, int none_allowed, char *name) {
    if (!type) {
        PyErr_Format(PyExc_SystemError, "Missing type object");
        return 0;
    }
    if ((none_allowed && obj == Py_None) || PyObject_TypeCheck(obj, type))
        return 1;
    PyErr_Format(PyExc_TypeError,
        "Argument '%s' has incorrect type (expected %s, got %s)",
        name, type->tp_name, obj->ob_type->tp_name);
    return 0;
}

static PyObject *__Pyx_Import(PyObject *name, PyObject *from_list) {
    PyObject *__import__ = 0;
    PyObject *empty_list = 0;
    PyObject *module = 0;
    PyObject *global_dict = 0;
    PyObject *empty_dict = 0;
    PyObject *list;
    __import__ = PyObject_GetAttrString(__pyx_b, "__import__");
    if (!__import__)
        goto bad;
    if (from_list)
        list = from_list;
    else {
        empty_list = PyList_New(0);
        if (!empty_list)
            goto bad;
        list = empty_list;
    }
    global_dict = PyModule_GetDict(__pyx_m);
    if (!global_dict)
        goto bad;
    empty_dict = PyDict_New();
    if (!empty_dict)
        goto bad;
    module = PyObject_CallFunction(__import__, "OOOO",
        name, global_dict, empty_dict, list);
bad:
    Py_XDECREF(empty_list);
    Py_XDECREF(__import__);
    Py_XDECREF(empty_dict);
    return module;
}

static void __Pyx_Raise(PyObject *type, PyObject *value, PyObject *tb) {
    Py_XINCREF(type);
    Py_XINCREF(value);
    Py_XINCREF(tb);
    /* First, check the traceback argument, replacing None with NULL. */
    if (tb == Py_None) {
        Py_DECREF(tb);
        tb = 0;
    }
    else if (tb != NULL && !PyTraceBack_Check(tb)) {
        PyErr_SetString(PyExc_TypeError,
            "raise: arg 3 must be a traceback or None");
        goto raise_error;
    }
    /* Next, replace a missing value with None */
    if (value == NULL) {
        value = Py_None;
        Py_INCREF(value);
    }
    #if PY_VERSION_HEX < 0x02050000
    if (!PyClass_Check(type))
    #else
    if (!PyType_Check(type))
    #endif
    {
        /* Raising an instance.  The value should be a dummy. */
        if (value != Py_None) {
            PyErr_SetString(PyExc_TypeError,
                "instance exception may not have a separate value");
            goto raise_error;
        }
        /* Normalize to raise <class>, <instance> */
        Py_DECREF(value);
        value = type;
        #if PY_VERSION_HEX < 0x02050000
            if (PyInstance_Check(type)) {
                type = (PyObject*) ((PyInstanceObject*)type)->in_class;
                Py_INCREF(type);
            }
            else {
                PyErr_SetString(PyExc_TypeError,
                    "raise: exception must be an old-style class or instance");
                goto raise_error;
            }
        #else
            type = (PyObject*) type->ob_type;
            Py_INCREF(type);
            if (!PyType_IsSubtype((PyTypeObject *)type, (PyTypeObject *)PyExc_BaseException)) {
                PyErr_SetString(PyExc_TypeError,
                    "raise: exception class must be a subclass of BaseException");
                goto raise_error;
            }
        #endif
    }
    PyErr_Restore(type, value, tb);
    return;
raise_error:
    Py_XDECREF(value);
    Py_XDECREF(type);
    Py_XDECREF(tb);
    return;
}

static PyObject *__Pyx_GetName(PyObject *dict, PyObject *name) {
    PyObject *result;
    result = PyObject_GetAttr(dict, name);
    if (!result)
        PyErr_SetObject(PyExc_NameError, name);
    return result;
}

static PyObject *__Pyx_GetItemInt(PyObject *o, Py_ssize_t i) {
    PyTypeObject *t = o->ob_type;
    PyObject *r;
    if (t->tp_as_sequence && t->tp_as_sequence->sq_item)
        r = PySequence_GetItem(o, i);
    else {
        PyObject *j = PyInt_FromLong(i);
        if (!j)
            return 0;
        r = PyObject_GetItem(o, j);
        Py_DECREF(j);
    }
    return r;
}

static void __Pyx_UnpackError(void) {
    PyErr_SetString(PyExc_ValueError, "unpack sequence of wrong size");
}

static PyObject *__Pyx_UnpackItem(PyObject *iter) {
    PyObject *item;
    if (!(item = PyIter_Next(iter))) {
        if (!PyErr_Occurred())
            __Pyx_UnpackError();
    }
    return item;
}

static int __Pyx_EndUnpack(PyObject *iter) {
    PyObject *item;
    if ((item = PyIter_Next(iter))) {
        Py_DECREF(item);
        __Pyx_UnpackError();
        return -1;
    }
    else if (!PyErr_Occurred())
        return 0;
    else
        return -1;
}

static int __Pyx_InternStrings(__Pyx_InternTabEntry *t) {
    while (t->p) {
        *t->p = PyString_InternFromString(t->s);
        if (!*t->p)
            return -1;
        ++t;
    }
    return 0;
}

static int __Pyx_InitStrings(__Pyx_StringTabEntry *t) {
    while (t->p) {
        *t->p = PyString_FromStringAndSize(t->s, t->n - 1);
        if (!*t->p)
            return -1;
        ++t;
    }
    return 0;
}

#include "compile.h"
#include "frameobject.h"
#include "traceback.h"

static void __Pyx_AddTraceback(char *funcname) {
    PyObject *py_srcfile = 0;
    PyObject *py_funcname = 0;
    PyObject *py_globals = 0;
    PyObject *empty_tuple = 0;
    PyObject *empty_string = 0;
    PyCodeObject *py_code = 0;
    PyFrameObject *py_frame = 0;
    
    py_srcfile = PyString_FromString(__pyx_filename);
    if (!py_srcfile) goto bad;
    py_funcname = PyString_FromString(funcname);
    if (!py_funcname) goto bad;
    py_globals = PyModule_GetDict(__pyx_m);
    if (!py_globals) goto bad;
    empty_tuple = PyTuple_New(0);
    if (!empty_tuple) goto bad;
    empty_string = PyString_FromString("");
    if (!empty_string) goto bad;
    py_code = PyCode_New(
        0,            /*int argcount,*/
        0,            /*int nlocals,*/
        0,            /*int stacksize,*/
        0,            /*int flags,*/
        empty_string, /*PyObject *code,*/
        empty_tuple,  /*PyObject *consts,*/
        empty_tuple,  /*PyObject *names,*/
        empty_tuple,  /*PyObject *varnames,*/
        empty_tuple,  /*PyObject *freevars,*/
        empty_tuple,  /*PyObject *cellvars,*/
        py_srcfile,   /*PyObject *filename,*/
        py_funcname,  /*PyObject *name,*/
        __pyx_lineno,   /*int firstlineno,*/
        empty_string  /*PyObject *lnotab*/
    );
    if (!py_code) goto bad;
    py_frame = PyFrame_New(
        PyThreadState_Get(), /*PyThreadState *tstate,*/
        py_code,             /*PyCodeObject *code,*/
        py_globals,          /*PyObject *globals,*/
        0                    /*PyObject *locals*/
    );
    if (!py_frame) goto bad;
    py_frame->f_lineno = __pyx_lineno;
    PyTraceBack_Here(py_frame);
bad:
    Py_XDECREF(py_srcfile);
    Py_XDECREF(py_funcname);
    Py_XDECREF(empty_tuple);
    Py_XDECREF(empty_string);
    Py_XDECREF(py_code);
    Py_XDECREF(py_frame);
}
