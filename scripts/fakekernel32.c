/*
 * Tiny kernel32.dll shim for Mono dllmap.
 * Implements GetPrivateProfileStringA/W — enough to let .NET 4.0 apps that
 * use Bussiness.IniReader (P/Invoke "kernel32") read their .ini files on
 * Linux.
 */
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <ctype.h>
#include <stdint.h>
#include <wchar.h>

static char *trim(char *s) {
    while (*s && isspace((unsigned char)*s)) s++;
    if (!*s) return s;
    char *e = s + strlen(s) - 1;
    while (e > s && isspace((unsigned char)*e)) *e-- = '\0';
    return s;
}

static int strieq(const char *a, const char *b) {
    if (!a || !b) return 0;
    while (*a && *b) {
        if (tolower((unsigned char)*a) != tolower((unsigned char)*b)) return 0;
        a++; b++;
    }
    return *a == *b;
}

/* ANSI (StringBuilder marshals as ANSI by default for kernel32 entry points) */
uint32_t GetPrivateProfileStringA(const char *section,
                                  const char *key,
                                  const char *def,
                                  char *retBuf,
                                  uint32_t size,
                                  const char *filePath) {
    if (!retBuf || size == 0) return 0;
    retBuf[0] = '\0';
    if (!filePath) goto fallback;

    FILE *f = fopen(filePath, "r");
    if (!f) goto fallback;

    char line[4096];
    char current_section[256] = "";
    int found = 0;
    while (fgets(line, sizeof(line), f)) {
        char *p = trim(line);
        if (!*p || *p == ';' || *p == '#') continue;
        size_t L = strlen(p);
        if (p[0] == '[' && p[L-1] == ']') {
            p[L-1] = '\0';
            snprintf(current_section, sizeof(current_section), "%s", trim(p+1));
            continue;
        }
        if (!strieq(current_section, section ? section : "")) continue;
        char *eq = strchr(p, '=');
        if (!eq) continue;
        *eq = '\0';
        char *k = trim(p);
        char *v = trim(eq+1);
        if (strieq(k, key ? key : "")) {
            snprintf(retBuf, size, "%s", v);
            found = 1;
            break;
        }
    }
    fclose(f);
    if (found) return (uint32_t)strlen(retBuf);

fallback:
    if (def) snprintf(retBuf, size, "%s", def);
    return (uint32_t)strlen(retBuf);
}

/* Unicode version — Mono's P/Invoke CharSet defaults to Ansi for kernel32,
 * but provide W too in case some assemblies opt in. */
uint32_t GetPrivateProfileStringW(const wchar_t *section,
                                  const wchar_t *key,
                                  const wchar_t *def,
                                  wchar_t *retBuf,
                                  uint32_t size,
                                  const wchar_t *filePath) {
    char s[256], k[256], d[1024], fp[1024];
    char tmp[4096];
    if (section) wcstombs(s, section, sizeof(s)); else s[0]=0;
    if (key)     wcstombs(k, key, sizeof(k));     else k[0]=0;
    if (def)     wcstombs(d, def, sizeof(d));     else d[0]=0;
    if (filePath) wcstombs(fp, filePath, sizeof(fp)); else fp[0]=0;
    uint32_t n = GetPrivateProfileStringA(s, k, d, tmp, sizeof(tmp), fp);
    if (retBuf && size > 0) {
        mbstowcs(retBuf, tmp, size);
        retBuf[size-1] = 0;
        return (uint32_t)wcslen(retBuf);
    }
    return n;
}

/* Some Bussiness flavours may call GetPrivateProfileInt; cheap impl. */
uint32_t GetPrivateProfileIntA(const char *section, const char *key,
                               int defaultValue, const char *filePath) {
    char buf[64];
    char defstr[32];
    snprintf(defstr, sizeof(defstr), "%d", defaultValue);
    GetPrivateProfileStringA(section, key, defstr, buf, sizeof(buf), filePath);
    return (uint32_t)atoi(buf);
}

/* No-ops for common Win32 entry points Mono cannot satisfy, to avoid further
 * DllNotFoundException stalls. Add more as we encounter them. */
int SetConsoleCtrlHandler(void *handler, int add) { (void)handler; (void)add; return 1; }
uint32_t GetConsoleOutputCP(void) { return 65001; /* UTF-8 */ }
uint32_t GetConsoleCP(void) { return 65001; }
int SetConsoleOutputCP(uint32_t cp) { (void)cp; return 1; }
int SetConsoleCP(uint32_t cp) { (void)cp; return 1; }
