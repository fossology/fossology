#ifndef __HASH_H__
#define __HASH_H__

static unsigned long sdbm(char *str) {
    unsigned long hash = 0;
    int c;

    while (c = *str++)
        hash = c + (hash << 6) + (hash << 16) - hash;

    return hash;
}

#if defined(__cplusplus)
static unsigned long sdbm_string(string s) {
    unsigned long hash = 0;
    int c;
    int pos;

    for (pos = 0; pos < s.length(); ++pos) {
        c = s.at(pos);
        hash = c + (hash << 6) + (hash << 16) - hash;
    }

    return hash;
}
#endif
#endif
