#!/usr/bin/env bash
# Patch Bussiness.dll to replace P/Invoke kernel32!GetPrivateProfileString
# with a managed implementation, allowing the .NET 4.0 server to run under Mono.
set -euo pipefail

WORKDIR=/tmp/patch-bussiness
SRC_DLL=$1     # absolute path to source Bussiness.dll
OUT_DLL=$2     # absolute path for patched Bussiness.dll

rm -rf "$WORKDIR"
mkdir -p "$WORKDIR"
cd "$WORKDIR"

# 1. Compile the managed INI reader helper
cat > Helper.cs <<'EOF'
using System;
using System.IO;
using System.Text;

namespace Bussiness {
  public static class IniReaderHelper {
    public static int Read(string section, string key, string def, StringBuilder retVal, int size, string filePath) {
      try {
        if (retVal == null) return 0;
        retVal.Length = 0;
        if (filePath == null || !File.Exists(filePath)) {
          if (def != null) retVal.Append(def);
          return retVal.Length;
        }
        string current = "";
        foreach (string raw in File.ReadAllLines(filePath)) {
          string line = raw.Trim();
          if (line.Length == 0 || line[0] == ';' || line[0] == '#') continue;
          if (line.Length >= 2 && line[0] == '[' && line[line.Length-1] == ']') {
            current = line.Substring(1, line.Length-2).Trim();
            continue;
          }
          if (string.Equals(current, section, StringComparison.OrdinalIgnoreCase)) {
            int eq = line.IndexOf('=');
            if (eq <= 0) continue;
            string k = line.Substring(0, eq).Trim();
            if (string.Equals(k, key, StringComparison.OrdinalIgnoreCase)) {
              retVal.Append(line.Substring(eq+1).Trim());
              return retVal.Length;
            }
          }
        }
        if (def != null) retVal.Append(def);
        return retVal.Length;
      } catch {
        try { if (def != null) retVal.Append(def); } catch { }
        return retVal == null ? 0 : retVal.Length;
      }
    }
  }
}
EOF

mcs -target:library -out:Helper.dll Helper.cs

# 2. Dump IL of Helper and Bussiness
ikdasm Helper.dll > Helper.il
ikdasm "$SRC_DLL" > Bussiness.il

# 3. Extract just the IniReaderHelper class IL
awk '
  /^\.class.*Bussiness\.IniReaderHelper/ { in_class = 1 }
  in_class { print }
  in_class && /^\} \/\/ end of class Bussiness\.IniReaderHelper/ { in_class = 0 }
' Helper.il > Helper.class.il

if [ ! -s Helper.class.il ]; then
  echo "Failed to extract IniReaderHelper class from Helper.il" >&2
  head -20 Helper.il >&2
  exit 1
fi

# 4. Patch Bussiness.il:
#    (a) Replace pinvokeimpl GetPrivateProfileString with a managed body
#        delegating to Bussiness.IniReaderHelper::Read
#    (b) Insert IniReaderHelper class right after the IniReader class
awk -v helper_file=Helper.class.il '
  BEGIN {
    new_method = ""              \
      "  .method private hidebysig static \n" \
      "          int32  GetPrivateProfileString(string section,\n"                 \
      "                                         string key,\n"                     \
      "                                         string def,\n"                     \
      "                                         [mscorlib]System.Text.StringBuilder retVal,\n" \
      "                                         int32 size,\n"                     \
      "                                         string filePath) cil managed\n"    \
      "  {\n"                                                                      \
      "    .maxstack 8\n"                                                          \
      "    ldarg.0\n"                                                              \
      "    ldarg.1\n"                                                              \
      "    ldarg.2\n"                                                              \
      "    ldarg.3\n"                                                              \
      "    ldarg.s 4\n"                                                            \
      "    ldarg.s 5\n"                                                            \
      "    call int32 Bussiness.IniReaderHelper::Read(string, string, string, class [mscorlib]System.Text.StringBuilder, int32, string)\n" \
      "    ret\n"                                                                  \
      "  }"
    in_pinvoke = 0
  }

  # Detect the start of the pinvokeimpl GetPrivateProfileString method
  /^[[:space:]]*\.method.*pinvokeimpl.*kernel32/ {
    in_pinvoke = 1
    next
  }
  in_pinvoke {
    # Consume lines until we hit the closing "}" of the empty body
    if ($0 ~ /^[[:space:]]*\}[[:space:]]*$/) {
      print new_method
      in_pinvoke = 0
    }
    next
  }

  # Inject IniReaderHelper class after the closing brace of IniReader
  /^\} \/\/ end of class Bussiness\.IniReader$/ {
    print
    print ""
    while ((getline hline < helper_file) > 0) {
      print hline
    }
    close(helper_file)
    next
  }

  { print }
' Bussiness.il > Bussiness.patched.il

if ! grep -q 'IniReaderHelper::Read' Bussiness.patched.il; then
  echo "ERROR: patch did not inject helper call" >&2
  exit 1
fi
echo "Patched Bussiness.il -> Bussiness.patched.il ($(wc -l < Bussiness.patched.il) lines)"

# 5. Re-assemble
ilasm /dll /quiet /output:Bussiness.dll Bussiness.patched.il 2>&1 | tail -10

# 6. Output
cp Bussiness.dll "$OUT_DLL"
echo "Patched Bussiness.dll written to: $OUT_DLL"
shasum "$OUT_DLL"
