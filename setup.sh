#!/usr/bin/env bash
# One-shot setup for the DDTank/Mortaltank private server on macOS (Apple Silicon).
# Run this from the project root. Idempotent — re-runs only do what's needed.
set -euo pipefail

cd "$(dirname "$0")"

require() {
  command -v "$1" >/dev/null 2>&1 || { echo "missing dependency: $1" >&2; exit 1; }
}

require docker

echo "==> Step 1/4: extracting Servidor5.5.rar (if needed)"
if [ ! -d Servidor5.5 ]; then
  if [ ! -f Servidor5.5.rar ]; then
    echo "  Servidor5.5.rar not found in $(pwd)" >&2
    exit 1
  fi
  require unar
  unar -force-overwrite Servidor5.5.rar >/dev/null
fi
echo "    Servidor5.5/ ready"

echo "==> Step 2/4: preparing runtime/ workdir with patched configs (if needed)"
if [ ! -d runtime/Center ]; then
  cp -R Servidor5.5/Emulador runtime
  # Patch emulator configs: SQL host + WCF endpoints + bind IP
  for f in runtime/Center/Center.Service.exe.config \
           runtime/Fighting/Fighting.Service.exe.config \
           runtime/Road/Road.Service.exe.config; do
    sed -i.bak \
      -e 's|Data Source=WIN-IJR04FJ3H42|Data Source=mssql,1433|g' \
      -e 's|192\.99\.235\.98|gameserver|g' \
      -e 's|"IP" value="gameserver"|"IP" value="0.0.0.0"|g' \
      -e 's|Languages\\Language-vn.txt|Languages/Language-vn.txt|g' \
      -e 's|Languages\\SystemNotice.xml|Languages/Systemnotice.xml|g' \
      "$f"
  done
  # Road sockets use IPAddress.Parse (need literal IP, not hostname)
  sed -i.bak \
    -e 's|"LoginServerIp" value="gameserver"|"LoginServerIp" value="127.0.0.1"|g' \
    -e 's|"FightServerIp" value="gameserver"|"FightServerIp" value="127.0.0.1"|g' \
    runtime/Road/Road.Service.exe.config
fi

if [ ! -d runtime/request ]; then
  cp -R Servidor5.5/Request runtime/request
  sed -i.bak \
    -e 's|Data Source=WIN-IJR04FJ3H42|Data Source=mssql,1433|g' \
    -e 's|net\.tcp://192\.99\.235\.98|net.tcp://gameserver|g' \
    -e 's|http://192\.99\.235\.98:9001|http://localhost:9001|g' \
    -e 's|http://192\.99\.235\.98:840|http://localhost:840|g' \
    -e 's|http://192\.99\.235\.98|http://localhost:8080|g' \
    -e 's|bin\\Languages\\Language-vn.txt|bin/Languages/Language-vn.txt|g' \
    -e 's|<compilation debug="true" targetFramework="4.0">|<compilation debug="false" batch="false" targetFramework="4.0">|' \
    runtime/request/Web.config
  # Drop the codebehind Global.asax that tries to wire Tank.Request.Global —
  # we use a minimal inline one to skip the Application_Start chain.
  rm -f runtime/request/Global.asax.cs
  cat > runtime/request/Global.asax <<'EOG'
<%@ Application Language="C#" %>
<script runat="server">
  void Application_Start(object sender, EventArgs e) { }
</script>
EOG
  # The Language file isn't shipped in Bin/Languages/ in the original tree —
  # copy it from obj/ where the publisher left it.
  if [ -f runtime/request/obj/Release/Package/PackageTmp/bin/Languages/Language-vn.txt ]; then
    mkdir -p runtime/request/Bin/Languages
    cp runtime/request/obj/Release/Package/PackageTmp/bin/Languages/Language-vn.txt runtime/request/Bin/Languages/
  fi
fi

if [ ! -d runtime/site ]; then
  cp -R "Servidor5.5/Novo Site" runtime/site
  sed -i.bak \
    -e "s|'WIN-IJR04FJ3H42'|'mssql'|g" \
    -e "s|WIN-IJR04FJ3H42|mssql|g" \
    -e "s|cybertank\\.ml|localhost:8080|g" \
    -e "s|Driver={SQL Server}|Driver=FreeTDS;Port=1433;TDS_Version=7.4|g" \
    runtime/site/global.php runtime/site/function.php runtime/site/login.php runtime/site/Web.config 2>/dev/null || true
  # Also rewrite the Flash client config.xml URLs so the SWF loader points
  # back at our nginx instead of the original cybertank.ml/akamaihd/7road CDNs.
  if [ -f runtime/site/config.xml ]; then
    sed -i.bak \
      -e 's|http://cybertank\.ml/|http://localhost:8080/|g' \
      -e 's|http://ddt-a\.akamaihd\.net/|http://localhost:8080/|g' \
      -e 's|http://assayerhandler\.7road\.com/|http://localhost:8080/|g' \
      runtime/site/config.xml
  fi
  if ! grep -q "_sqlsrv_shim" runtime/site/global.php; then
    awk '/@session_start/ { print; print "require_once __DIR__ . '\''/_sqlsrv_shim.php'\'';"; next } { print }' \
        runtime/site/global.php > runtime/site/global.php.tmp \
      && mv runtime/site/global.php.tmp runtime/site/global.php
  fi
  # Apply our PHP patches over the originals (signup capturing UID, login
  # using inline EXEC for Mem_Users_Accede, ajax for Webshop_Changepass,
  # and the _sqlsrv_shim.php itself). These come from patches/site/.
  for f in patches/site/_sqlsrv_shim.php \
           patches/site/login.php \
           patches/site/ajax.php \
           patches/site/play.php \
           patches/site/config.xml \
           patches/site/md5.xml; do
    [ -f "$f" ] && cp "$f" "runtime/site/$(basename "$f")"
  done

  # Download self-hosted Ruffle nightly (the released 0.2.0 has worse AS3
  # support than the nightlies; we want the latest). Skipped if the user
  # already vendored a copy.
  if [ ! -d runtime/site/ruffle ]; then
    require /usr/bin/curl
    mkdir -p runtime/site/ruffle
    /usr/bin/curl -L -s -o /tmp/ruffle.zip \
      "https://github.com/ruffle-rs/ruffle/releases/download/nightly-2026-05-16/ruffle-nightly-2026_05_16-web-selfhosted.zip"
    (cd runtime/site/ruffle && unzip -oq /tmp/ruffle.zip)
    rm -f /tmp/ruffle.zip
  fi
fi

if [ ! -d runtime/painel ]; then
  cp -R Servidor5.5/Painel runtime/painel
  sed -i.bak \
    -e 's|Data Source=WIN-IJR04FJ3H42|Data Source=mssql,1433|g' \
    -e 's|http://192\.99\.235\.98|http://localhost|g' \
    -e 's|net\.tcp://192\.99\.235\.98|net.tcp://gameserver|g' \
    -e 's|"ServerIP" value="192\.99\.235\.98"|"ServerIP" value="127.0.0.1"|g' \
    runtime/painel/Web.config
  # Replace the broken nested HTML comment block in the three master pages
  for f in runtime/painel/Site.Master runtime/painel/DDT.Master runtime/painel/Homen.Master; do
    awk '
      /<!--<SCRIPT LANGUAGE="JavaScript">/ { skipping=1; print "<%-- legacy anti-selection JS block removed --%>"; next }
      skipping && /<\/script>-->/ { skipping=0; next }
      !skipping { print }
    ' "$f" > "$f.tmp" && mv "$f.tmp" "$f"
  done
  for f in runtime/painel/Site.Master runtime/painel/DDT.Master runtime/painel/Homen.Master \
           runtime/painel/Site.aspx runtime/painel/Account/Login.aspx; do
    [ -f "$f" ] || continue
    sed -i.bak -E 's|\.\./Scripts/|Scripts/|g; s|\.\./DDTank/|DDTank/|g' "$f"
  done

  # Site.aspx (no <%@ Page %> directive) crashes the Mono ASP.NET batch
  # compiler with CS1576 #line 0 and brings down EVERY Admin page along
  # with it. Disable it.
  [ -f runtime/painel/Site.aspx ] && mv runtime/painel/Site.aspx runtime/painel/Site.aspx.disabled

  # Login.aspx's <asp:Button CommandName="Login"> triggers Mono's broken
  # SqliteMembershipProvider before the user-defined click handler runs.
  # Strip CommandName so only the click handler authenticates.
  sed -i.bak -E 's| CommandName="Login"||' runtime/painel/Account/Login.aspx 2>/dev/null || true
fi
echo "    runtime/ ready (Center, Fighting, Road, site, painel)"

echo "==> Step 3/4: bringing up SQL Server and restoring databases"
docker compose up -d mssql >/dev/null
bash scripts/restore-dbs.sh >/tmp/restore.log 2>&1 || { tail -50 /tmp/restore.log; exit 1; }
echo "    databases restored"

echo "==> Step 4/4: building and starting emulator + site"
docker compose build >/dev/null 2>&1
docker compose up -d
echo ""
echo "    Waiting for the emulator to bind its ports..."
for _ in $(seq 1 30); do
  ports=$(docker exec ddt-center awk 'NR>1 && $4=="0A" {print $2}' /proc/net/tcp 2>/dev/null | wc -l)
  if [ "$ports" -ge 3 ]; then break; fi
  sleep 2
done

echo ""
LAN_IP=$(ifconfig 2>/dev/null | awk '/inet / && $2 != "127.0.0.1" {print $2; exit}')
LAN_IP=${LAN_IP:-<seu-ip-lan>}
echo "==========================================="
echo "Backend pronto. Endpoints (do Mac):"
echo "  Site (PHP login/signup):  http://localhost:8080/   (LAN: http://$LAN_IP:8080/)"
echo "  Painel admin:             http://localhost:8081/   (LAN: http://$LAN_IP:8081/)"
echo "  SQL Server:               localhost:1433           (LAN: $LAN_IP:1433)   sa / ddtank@2016"
echo "  Game sockets:             9200 (Road) 9202 (Center) 9208 (Fighting)"
echo ""
echo "Para jogar precisa de um PC Windows na mesma rede:"
echo "  docs/CLIENT-WINDOWS.md  — IIS + Request app + Flash Projector"
echo "==========================================="
echo "Logs:   docker compose logs -f <service>"
echo "Stop:   docker compose down"
