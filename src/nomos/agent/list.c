/***************************************************************
 Copyright (C) 2006-2011 Hewlett-Packard Development Company, L.P.

 This program is free software; you can redistribute it and/or
 modify it under the terms of the GNU General Public License
 version 2 as published by the Free Software Foundation.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License along
 with this program; if not, write to the Free Software Foundation, Inc.,
 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.

 ***************************************************************/

/* Equivalent to core nomos v1.10 */

/**
 * \file list.c
 * \brief list manipulation functions and str/val Compare functions
 *
 * list.c supplies all of the functions needed to manipulate the list and
 * listitem strunctures defined in nomos.h.  It also supplies the
 * str*Compare* and valCompare* functions.
 *
 * @version "$Id: list.c 3676 2010-11-15 23:10:52Z bobgo $"
 *
 */

#include "nomos.h"
#include "list.h"
#include "util.h"

#define DFL_STARTSIZE 100

static int strCompare(item_t *, item_t *);
static int strIcaseCompare(item_t *, item_t *);
static int strCompareBasename(item_t *, item_t *);
static int valCompareDsc(item_t *, item_t *);
static int valCompareAsc(item_t *, item_t *);
static int bufCompare(item_t *, item_t *);
static void listDoubleSize(list_t *);
static void listValidate(list_t *, int);

#if defined(PROC_TRACE) || defined(LIST_DEBUG)
static void listDebugDetails();
#endif /* PROC_TRACE || LIST_DEBUG */

/**
 * \brief intialize a list, if the list is not empty, empty it (initialize it to
 * zero's).
 *
 * sets:\n
 *    l->name to label\n
 *    l->size to DFL_STARTSIZE\n
 *    l->used = 0\n
 *    l->ix = -1\n
 *    l->sorted = UNSORTED\n
 *
 * \note label can't be longer than l->name (64)
 *
 */
void listInit(list_t *l, int size, char *label) {

#ifdef PROC_TRACE
  traceFunc("== listInit(%p, %d, \"%s\")\n", l, size, label);
#endif /* PROC_TRACE */

  if (l == NULL_LIST) {
    LOG_FATAL("listInit: List @ %p is NULL", l)
                Bail(-__LINE__);
  }
  if (label == NULL_STR) {
    LOG_FATAL("no name for list @ %p", l)
                Bail(-__LINE__);
  }
  if (strlen(label) > sizeof(l->name)) {
    LOG_FATAL("List name \"%s\" too long", label)
                Bail(-__LINE__);
  }
  if (l->name != label) (void) strcpy(l->name, label);
  if (size == 0) {
#ifdef LIST_DEBUG
    printf("LIST: (%p) initialize %s to %d elements\n", l,
        l->name, DFL_STARTSIZE);
#endif /* LIST_DEBUG */
    l->size = DFL_STARTSIZE; /* default start */
    l->items = (item_t *)memAlloc(l->size*(int)sizeof(item_t),
        l->name);
  }
#ifdef QA_CHECKS
  else if (size != l->size) {
    Assert(NO, "%s: specified reset size %d != list size %d",
        l->name, size, l->size);
  }
#endif /* QA_CHECKS */
  else {
#ifdef LIST_DEBUG
    printf("LIST: reset %d elements in \"%s\" (%d bytes)\n",
        l->size, l->name, l->size*sizeof(item_t));
#endif /* LIST_DEBUG */
    memset(l->items, 0, l->size*sizeof(item_t));
  }
  l->used = 0;
  l->ix = -1;
  l->sorted = UNSORTED;
  return;
}

void listClear(list_t *l, int deallocFlag) {
  item_t *p;
  int i;

#if defined(PROC_TRACE) /* || defined(UNPACK_DEBUG) */
  traceFunc("== listClear(%p, %s)\n", l,
      deallocFlag ? "DEALLOC" : "NOTOUCH");
  listDebugDetails(l);
#endif /* PROC_TRACE || UNPACK_DEBUG */

  if (l == NULL_LIST) {
#ifdef LIST_DEBUG
    printf("%% clear NULL list\n");
#endif /* LIST_DEBUG */
    return;
  }

  if (l->size == 0) {
#ifdef LIST_DEBUG
    printf("%% clear empty list \"%s\"\n", l->name);
#endif /* LIST_DEBUG */
    return;
  }
#ifdef LIST_DEBUG
  listDump(l, YES)
#endif /* LIST_DEBUG */

#ifdef GLOBAL_DEBUG
  if (gl.MEM_DEEBUG) {
    printf("... used %d size %d ix %d sorted %d items %p\n",
        l->used, l->size, l->ix, l->sorted, l->items);
  }
#endif /* GLOBAL_DEBUG */
  if (l->used) {
    if (l->items == NULL_ITEM) {
      Assert(NO, "%s: used/size %d/%d with null data",
          l->name, l->used, l->size);
    }
#ifdef LIST_DEBUG
    printf("LIST: clearing %s, used entries == %d\n", l->name,
        l->used);
#endif /* LIST_DEBUG */
#ifdef GLOBAL_DEBUG
    if (gl.MEM_DEEBUG) {
      printf("LIST: clearing %s, used entries == %d\n",
          l->name, l->used);
    }
#endif /* GLOBAL_DEBUG */
    for (p = l->items, i = 0; i < l->used; i++, p++) {
      if (p->str != NULL_STR) {
#ifdef GLOBAL_DEBUG
        if (gl.MEM_DEEBUG) {
          printf("FREE %p items[%d].str %p\n",
              l, i, p->str);
        }
#endif /* GLOBAL_DEBUG */
        memFree((void *) p->str, MTAG_LISTKEY);
        p->str = NULL_STR;
      }
      if (p->buf != NULL_STR) {
#ifdef GLOBAL_DEBUG
        if (gl.MEM_DEEBUG) {
          printf("... FREE %p items[%d].buf %p\n",
              l, i, p->buf);
        }
#endif /* GLOBAL_DEBUG */
        memFree((void *) p->buf, MTAG_LISTBUF);
        p->buf = NULL_STR;
      }
      p->buf = NULL_STR;
      p->val = p->val2 = p->val3 = 0;
    }
#ifdef GLOBAL_DEBUG
    if (gl.MEM_DEEBUG) {
      printf("INIT %s...\n", l->name);
    }
#endif /* GLOBAL_DEBUG */
    listInit(l, l->size, l->name);
  }
#ifdef GLOBAL_DEBUG
  if (gl.MEM_DEEBUG) {
    printf("... dealloc %p \"items\" %p\n", l, l->items);
  }
#endif /* GLOBAL_DEBUG */
  if (deallocFlag && l->size) {
    memFree(l->items, "list items");
    l->size = 0;
  }
#ifdef GLOBAL_DEBUG
  if (gl.MEM_DEEBUG) {
    printf("LIST: %s is cleared!\n", l->name);
  }
#endif /* GLOBAL_DEBUG */
  return;
}

void listValidate(list_t *l, int appendFlag) {
  if (l == NULL_LIST) {
    LOG_FATAL("listValidate: null list!")
                Bail(-__LINE__);
  }
  /*
   * Question: do we want to initialize the list here, instead of aborting?
   * It would mean we don't have to initialize EVERY list EVERYWHERE -- we
   * could just set the size to zero and start adding/inserting.
   */
  if (l->size == 0) {
    LOG_FATAL("List (%s) @ %p not initialized", l->name, l)
                Bail(-__LINE__);
  }
  if (l->items == NULL_ITEM) {
    Assert(NO, "List (%s) @ %p has no data", l->name, l);
  }
  if (appendFlag) {
    if (l->size == l->used) {
      listDoubleSize(l);
    }
  }
  return;
}


/**
 * \brief get an item from the itemlist.  If the item is not in the itemlist,
 * then add it to the itemlist.
 *
 * This function searches the str member in the listitem structure, if found,
 * a pointer to that item is returned.  If not found, the item is added to the
 * list of items 'in the middle' of the list.
 *
 * @param list_t *list the list to search/update
 * @param *s pointer to the string to search for.
 *
 * @return pointer to the item
 */
item_t *listGetItem(list_t *l, char *s) {
  item_t *p;
  int i;
  int x;

#ifdef PROC_TRACE
  traceFunc("== listGetItem(%p, \"%s\")\n", l, s);
  listDebugDetails(l);
#endif /* PROC_TRACE */

  listValidate(l, YES); /* assume/setup for an 'add' */
  if (s == NULL_STR) {
    Assert(NO, "listGetItem: Null string to insert!");
  }
  if (l->sorted && l->sorted != SORT_BY_NAME) {
    LOG_FATAL("%s is sorted other than by-name (%d)", l->name, l->sorted)
                Bail(-__LINE__);
  }
  else if (l->used == 0) {
    l->sorted = SORT_BY_NAME;
  }
  /*
   * Now we KNOW we have at least one opening in the list; see if the
   * requested string already exists in the list
   */
  /**
\todo CDB -- Change so that there is only one loop variable in the
 for loop
   */
  for (p = l->items, i = 0; i < l->used; i++, p++) {
#ifdef LIST_DEBUG
    printf("%p: check i = %d, used = %d, size = %d\n", l, i, l->used,
        l->size);
#endif /* LIST_DEBUG */
    if ((x = strcmp(s, p->str)) == 0) {
      return (p);
    }
    else if (x < 0) { /* e.g., not in list */
      break; /* add new list entry */
    }
  } /* for */
#ifdef LIST_DEBUG
  printf("listGetItem: new entry @%d (size %d, max %d)\n", i,
      l->used, l->size);
#endif /* LIST_DEBUG */
  if (i != l->used) { /* make room in 'middle' of list */
    (void) memmove(l->items+i+1, l->items+i, (l->used-i)*sizeof(*p));
  }
  (l->used)++;
  p->str = copyString(s, MTAG_SORTKEY);
  p->buf = NULL_STR;
  p->val = 0;
  p->val2 = 0;
  p->val3 = 0;
#ifdef LIST_DEBUG
  printf("ADDING: insert %s @%d, \"used\" now == %d, Cache (listDump):\n",
      p->str, i, l->used);
  listDump(l, NO);
#endif /* LIST_DEBUG */
  return (p);
}

/**
 * \brief Utility list that isn't sorted - faster for unpacking archives and
 * maintaining lists of files (we only need to sort that list at the
 * end of the unpacking process - this just appends an entry at the
 * bottom/end of the list.
 */
item_t *listAppend(list_t *l, char *s) {
  item_t *p; /* computed return value */

#ifdef PROC_TRACE
  traceFunc("== listAppend(%p, \"%s\")\n", l, s);
  listDebugDetails(l);
#endif /* PROC_TRACE */

  listValidate(l, YES);
  if (s == NULL_STR) {
    Assert(NO, "listAppend: Null string to insert!");
  }
  /*
   * Now we know we have a valid list with enough room to add one more
   * element; simply insert it at the end, increment the 'used' counter
   * and get outta Dodge.
   */
  p = &l->items[l->used++];
  p->str = copyString(s, MTAG_UNSORTKEY);
  p->buf = NULL_STR;
  p->val = p->val2 = p->val3 = 0;
  return (p);
}

#ifdef notdef
/**
 * \brief Look up an element in a list based on it's name (str) value and
 * return NULL if not found.
 * \note the list MUST be of sort-type SORT_BY_NAME for this to be valid.
 * This is a specific-purpose routine, necessitated by needing to change
 * the name of a DOS-format package on-the-fly.  This function should
 * probably be used sparingly -- as little as needed, actually. :(
 */
/*
 CDB -- From comment above, we could probably get rid of this. #ifdef'd
 out for now.
 */
item_t *listLookupName(list_t *l, char *s)
{
  item_t *p; /* computed return value */
  int i;
  int match;

#if defined(PROC_TRACE)
#ifdef PROC_TRACE_SWITCH
  if (gl.ptswitch) {
#endif /* PROC_TRACE_SWITCH */
    printf("== listLookupName(%p, \"%s\")\n", l, s);
    listDebugDetails(l);
#ifdef PROC_TRACE_SWITCH
  }
#endif /* PROC_TRACE_SWITCH */
#endif /* PROC_TRACE */

#if defined(QA_CHECKS) || defined(LIST_DEBUG)
  listValidate(l, NO);
  if (s == NULL_STR) {
    Assert(NO, "lookupName: Null key for lookup!");
    return(NULL_ITEM);
  }
#endif /* QA_CHECKS || LIST_DEBUG */
  /*
   * Now we know we have a valid list with enough room to add one more
   * element; simply insert it at the end, increment the 'used' counter
   * and get outta Dodge.
   */
  if (l->sorted != SORT_BY_NAME && l->sorted != 0) {
    LOG_FATAL("Improper sort-type %d for %s name-lookup", l->sorted, l->name)
                Bail(-__LINE__);
  }
  /*
   * Walk through the sorted-by-name list and exit when we're done.
   * This function could be called during a loop (while(listIterate(&list)))
   * and we DON'T want to mess up the 'ix' field for this list!
   */
  for (i = 0; i < l->used; i++) {
    if (l->items[i].str == NULL_STR) {
      Assert(NO, "%s[%d] is NULL!", l->name, i);
      continue;
    }
    match = strcmp(s, l->items[i].str);
    if (match == 0) {
      return(&(l->items[i]));
    }
    else if (match < 0) { /* e.g., cannnot be in list */
      break;
    }
  }
  return(NULL_ITEM);
}
#endif /* notdef */

#ifdef notdef
/*
 * Look up an element in a list based on it's alias (buf) value and
 * return NULL if not found.
 *****
 * NOTE: the list MUST be of sort-type SORT_BY_ALIAS for this to be valid.
 *****
 * This is a general-purpose utility; often it's required to look up a
 * specific value based on an item's alias.
 */
item_t *listLookupAlias(list_t *l, char *s)
{
  item_t *p; /* computed return value */
  int i;
  int x;

#if defined(PROC_TRACE)
#ifdef PROC_TRACE_SWITCH
  if (gl.ptswitch) {
#endif /* PROC_TRACE_SWITCH */
    printf("== listLookupAlias(%p, \"%s\")\n", l, s);
    listDebugDetails(l);
#ifdef PROC_TRACE_SWITCH
  }
#endif /* PROC_TRACE_SWITCH */
#endif /* PROC_TRACE */
#if defined(QA_CHECKS) || defined(LIST_DEBUG)
  listValidate(l, NO);
  if (s == NULL_STR) {
    Assert(NO, "lookupAlias: Null key for lookup!");
  }
#endif /* QA_CHECKS || LIST_DEBUG */
  /*
   * Now we know we have a valid list with enough room to add one more
   * element; simply insert it at the end, increment the 'used' counter
   * and get outta Dodge.
   */
  if (l->sorted != SORT_BY_ALIAS) {
    LOG_FATAL("Improper sort-type %d for %s alias-lookup", l->sorted, l->name)
                Bail(-__LINE__);
  }
  /*
   * Walk through the sorted-by-alias list and exit when we're done.
   * Do NOT use listIterate() or we could mess up the 'ix' field!
   */
  for (i = 0, p = l->items; i < l->used; i++, p++) {
    if (p->buf == NULL_STR) {
      Assert(NO, "%s[%d] is NULL!", l->name, i);
      continue;
    }
    if ((x = strcmp(s, p->buf)) == 0) {
      return(p);
    }
    else if (x < 0) { /* e.g., not in list */
      break;
    }
  }
  return(NULL_ITEM);
}
#endif /* notdef */

/**
 * listIterate
 * \brief return a pointer to listitem, returns a NULL_ITEM when no more items
 * to return.
 *
 * @param list_t *list a point to a list
 *
 * NOTE: this routine increments the ix member! (bad boy)
 *
 * \todo remove/fix the fact that this routine increments ix.
 */
item_t *listIterate(list_t *l) {

  item_t *p;

#ifdef LIST_DEBUG /* was PROC_TRACE */
  traceFunc("== listIterate(%p) -- %s (ix %d, used %d)\n", l, l->name,
      l->ix, l->used);
  listDebugDetails(l);
#endif /* LIST_DEBUG, oh-so-formerly-PROC_TRACE */

#if defined(QA_CHECKS) || defined(LIST_DEBUG)
  listValidate(l, NO);
  if (l->used == 0) { /* empty list? */
    return(NULL_ITEM);
  }
#endif /* QA_CHECKS || LIST_DEBUG */
  l->ix++;

  if (l->ix == l->used) {
#ifdef LIST_DEBUG
    Assert(NO, "End-of-list: %s", l->name);
#endif /* LIST_DEBUG */
    l->ix = -1;
    return (NULL_ITEM);
  } else if ((l->ix > l->used) || (l->ix < 0)) {
    LOG_FATAL("Index %d out of bounds (%d) on %s", l->ix, l->used, l->name)
                Bail(-__LINE__);
  }
  p = l->items+(l->ix);
  return (p);
}

void listIterationReset(list_t *l) {

#ifdef LIST_DEBUG /* was PROC_TRACE */
  traceFunc("== listIterationReset(%p) -- %s (ix %d, used %d)\n", l, l->name,
      l->ix, l->used);
  listDebugDetails(l);
#endif /* LIST_DEBUG, oh-so-formerly-PROC_TRACE */

#if defined(QA_CHECKS) || defined(LIST_DEBUG)
  listValidate(l, NO);
#endif /* QA_CHECKS || LIST_DEBUG */

  l->ix = -1; /* reset index for listIterate() */
  return;
}

int listDelete(list_t *l, item_t *p) {
  int index;
  item_t *base;

#if defined(PROC_TRACE)
  traceFunc("== listDelete(%p, %p)\n", l, p);
  listDebugDetails(l);
#endif /* PROC_TRACE */

#if defined(QA_CHECKS) || defined(LIST_DEBUG)
  listValidate(l, NO);
#endif /* QA_CHECKS || LIST_DEBUG */
  if ((base = l->items) == NULL_ITEM) {
    Assert(NO, "%s: empty list", l->name);
    return (0);
  }
  if ((index = p-base) >= l->used) {
    Assert(NO, "%s[%d] is out of range", l->name, p-(l->items));
    return (0);
  }
#ifdef LIST_DEBUG
  printf("DEBUG: listDelete: delete index %d (used %d, size %d)\n",
      index, l->used, l->size);
#endif /* LIST_DEBUG */
  /*
   * If anything was allocated, delete it.
   */
  if (p->str != NULL_STR) {
    memFree(p->str, MTAG_LISTKEY);
  }
  if (p->buf != NULL_STR) {
    memFree(p->buf, MTAG_LISTBUF);
  }
  /*
   * move everything up (e.g., from index+1 to index, index+2 to index+1)
   */
  if (index+1 < l->used) {
    (void) memmove(l->items+index, l->items+index+1, (size_t)((l->used
        -index)*sizeof(item_t)));
  }
  l->used--; /* ... then just ignore it now */
  return (1);
}

static void listDoubleSize(list_t *l) {
  int sz;
  item_t *newptr;

#if defined(PROC_TRACE)
  traceFunc("== listDoubleSize(%p) -- %s\n", l, l->name);
  listDebugDetails(l);
#endif /* PROC_TRACE */

  sz = (size_t) (l->used * (int)sizeof(item_t));
#ifdef LIST_DEBUG
  printf("LIST: %s FULL (%d) @ addr %p! -- %d -> %d\n", l->name,
      l->used, l, sz, sz * 2);
#endif /* LIST_DEBUG */
#ifdef MEMSTATS
  printf("... DOUBLE \"%s\" (%d -> %d) => %d slots\n", l->name, sz, sz * 2,
      (sz * 2)/sizeof(item_t));
#endif /* MEMSTATS */

  newptr = (item_t *)memAlloc(sz * 2, MTAG_DOUBLED);
  memcpy((void *) newptr, (void *) l->items, sz);
  if (l->items != newptr) {
#ifdef LIST_DEBUG
    printf("LIST: old %p new %p\n", l->items, newptr);
#endif /* LIST_DEBUG */
    memFree(l->items, MTAG_TOOSMALL);
    l->items = newptr;
  }
  l->size *= 2;
  return;
}

void listSort(list_t *l, int sortType) {

  int (*f)() = 0;

#ifdef PROC_TRACE
  char *fName;
#ifdef PROC_TRACE_SWITCH
  if (gl.ptswitch) {
#endif /* PROC_TRACE_SWITCH */
    printf("== listSort(%p, %d", l, sortType);
    switch (sortType) {
      case SORT_BY_NAME:
        printf("(NAME)");
        break;
      case SORT_BY_COUNT_DSC:
        printf("(COUNT_DSC)");
        break;
      case SORT_BY_COUNT_ASC:
        printf("(COUNT_ASC)");
        break;
      case SORT_BY_ALIAS:
        printf("(ALIAS)");
        break;
      case SORT_BY_BASENAME:
        printf("(BASENAME)");
        break;
      default:
        printf("(***)");
        break;
    }
    printf(")\n");
    listDebugDetails(l);
#ifdef PROC_TRACE_SWITCH
  }
#endif /* PROC_TRACE_SWITCH */
#endif /* PROC_TRACE */

  if (sortType == SORT_BY_BASENAME) { /* special case */
    l->sorted = SORT_BY_NAME;
  } else {
    l->sorted = sortType;
  }

  if (l->used == 0) {
#ifdef LIST_DEBUG
    Warn("\"%s\" is empty", l->name);
#endif /* LIST_DEBUG */
    return;
  }

  switch (sortType) {
#ifdef QA_CHECKS
    case UNSORTED:
      LOG_FATAL("Sort-spec == UNSORTED")
      Bail(-__LINE__);
      break;
#endif /* QA_CHECKS */
    case SORT_BY_NAME_ICASE:
      f = strIcaseCompare;
#ifdef PROC_TRACE
      fName = "strIcaseCompare";
#endif /* PROC_TRACE */
      break;
    case SORT_BY_NAME:
      f = strCompare;
#ifdef PROC_TRACE
      fName = "strCompare";
#endif /* PROC_TRACE */
      break;
    case SORT_BY_COUNT_DSC:
      f = valCompareDsc;
#ifdef PROC_TRACE
      fName = "valCompareDsc";
#endif /* PROC_TRACE */
      break;
    case SORT_BY_COUNT_ASC:
      f = valCompareAsc;
#ifdef PROC_TRACE
      fName = "valCompareAsc";
#endif /* PROC_TRACE */
      break;
    case SORT_BY_ALIAS:
#ifdef PROC_TRACE
      fName = "bufCompare";
#endif /* PROC_TRACE */
      f = bufCompare;
      break;
    case SORT_BY_BASENAME:
#ifdef PROC_TRACE
      fName = "strCompareBasename";
#endif /* PROC_TRACE */
      f = strCompareBasename;
      sortType = SORT_BY_NAME;
      break;
    default:
      LOG_FATAL("Invalid sort-spec %d", sortType)
      Bail(-__LINE__);
  }

#ifdef PROC_TRACE
  traceFunc("=> invoking qsort(): callback is %s()\n", fName);
#endif /* PROC_TRACE */

  qsort(l->items, (size_t) l->used, sizeof(item_t), f);
  return;
}

/*
 * qsort utility-function to create an alphabetically sorted (ASCENDING)
 * [case-insensitive] list based on the string value in the item_t 'str' field
 */
static int strIcaseCompare(item_t *p1, item_t *p2) {
  int ret;

  ret = strcasecmp(p1->str, p2->str);
  return (ret ? ret : valCompareDsc(p1, p2));
}

/*
 * qsort utility-function to create an alphabetically sorted (ASCENDING)
 * list based on the string value in the item_t 'str' field
 */
static int strCompare(item_t *p1, item_t *p2) {
  int ret;

  ret = strcmp(p1->str, p2->str);
  return (ret ? ret : valCompareDsc(p1, p2));
}

/*
 * qsort utility-function to create an alphabetically sorted (ASCENDING)
 * list based on the path-basename of string value in the item_t 'str' field
 */
static int strCompareBasename(item_t *p1, item_t *p2) {
  int ret;

  ret = strcmp(pathBasename(p1->str), pathBasename(p2->str));
  return (ret ? ret : strCompare(p1, p2));
}

/*
 * qsort utility-function to create a numerically sorted (ASCENDING)
 * list based on the integer value in the item_t 'val' field
 */
static int valCompareAsc(item_t *p1, item_t *p2) {
  return (p1->val - p2->val);
}

/*
 * qsort utility-function to create a numerically sorted (DESCENDING)
 * list based on the integer value in the item_t 'val' field
 */
static int valCompareDsc(item_t *p1, item_t *p2) {
  return (p2->val - p1->val);
}

/*
 * qsort utility-function to create an alphabetically sorted (ASCENDING)
 * list based on the string value in the item_t 'buf' field
 */
static int bufCompare(item_t *p1, item_t *p2) {
  int ret = strcmp(p1->buf, p2->buf);
  return (ret ? ret : valCompareDsc(p1, p2));
}

/*
 * Be careful about calling this function; some lists use the 'val'
 * field as a flag, others use it as a count!
 */
int listCount(list_t *l) {
  int i;
  int total;

#ifdef PROC_TRACE
  traceFunc("== listCount(%p)\n", l);
  listDebugDetails(l);
#endif /* PROC_TRACE */

#if defined(QA_CHECKS) || defined(LIST_DEBUG)
  listValidate(l, NO);
#endif /* QA_CHECKS || LIST_DEBUG */
  total = 0;
  for (i = 0; i < l->used; i++) {
    if (l->items[i].val > 0) {
      total += l->items[i].val; /* sum POSITIVE 'val' values */
    }
  }
  return (total);
}

/**
 * \brief print the passed in list
 *
 * @param *l the list to dump
 * @param verbose flag, print more
 *
 * \callgraph
 */
void listDump(list_t *l, int verbose) {
  item_t *p;
  int i;
  int max = (verbose ? l->size : l->used);

#ifdef PROC_TRACE
  traceFunc("== listDump(%p, %d)\n", l, verbose);
  listDebugDetails(l);
#endif /* PROC_TRACE */

  /*MD: why should an empty list be fatal?  Just return....? */
  if (l == NULL_LIST) {
    LOG_FATAL("NULL list passed to listDump()")
                Bail(-__LINE__);
  }
  if (l->used == 0) {
#if defined(LIST_DEBUG) || defined(UNPACK_DEBUG) || defined(REPORT_DEBUG)
    Warn("%s is empty", l->name);
#endif /* LIST_DEBUG || UNPACK_DEBUG || REPORT_DEBUG */
    return;
  }
  if (verbose < 0) {
    printf("** %s (size %d, used %d, ix %d, sort %d desc %d) == %lu\n",
        l->name, l->size, l->used, l->ix, l->sorted, l->desc,
        (unsigned long)sizeof(item_t));
    return;
  }
  if (verbose || max) {
    printf("Contents of %s:\n", l->name);
    printf("    ... @%p (size %d, used %d, ix %d, sort %d desc %d)\n", l,
        l->size, l->used, l->ix, l->sorted, l->desc);
  }
  /*
   * Brute-force a walk through the list contents.  This function could
   * be called during a loop (while(listIterate(&list))) and we CANNOT
   * mess with the 'ix' field for this list as listIterate() does!
   */
  for (i = 0; i < l->used; i++) {
    p = &(l->items[i]);
    if (verbose) {
      printf("[%c] ", (i < l->used ? 'x' : ' '));
    }
    printf("#%03d: str %p buf %p (val %d, val2 %d val3 %d)\n", i, p->str,
        p->buf, p->val, p->val2, p->val3);
    if (i < l->used) {
      printf("    str: \"%s\"\n", p->str);
      if (p->buf != NULL_STR) {
        printf("    ... buf: \"%s\"\n", (char *)p->buf);
      }
    }
  }
  return;
} /* listDump */

#if defined(PROC_TRACE) || defined(LIST_DEBUG)
void listDebugDetails(list_t *l)
{
  if (l != NULL_LIST && l->size) {
    printf("... %p is %s\n", l, l->name ? l->name : "No-name");
  }
  return;
}
#endif /* PROC_TRACE || LIST_DEBUG */
