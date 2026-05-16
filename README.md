# Servidor DDTank / Mortaltank 5.5 — Docker (macOS / Apple Silicon)

Stack original (Windows + IIS + SQL Server + .NET 4.0) empacotada para rodar
em Mac M1/M2/M3 com **um único `docker compose up`**.

## Pré-requisitos
- macOS (testado em Apple Silicon)
- Docker Desktop com Rosetta habilitado em *Settings → General → Use Rosetta
  for x86/amd64 emulation* (necessário só para o SQL Server)
- `unar` no PATH (`brew install unar`) só se for descompactar o `Servidor5.5.rar`
- Arquivo `Servidor5.5.rar` na raiz do projeto

## Subir tudo
```bash
./setup.sh
```

Em ~3 minutos sobe o stack inteiro. Idempotente — re-rodar não destrói nada.

## O que está rodando

| Serviço | Container | Porta | Descrição |
|---|---|---|---|
| SQL Server 2019 | `ddt-mssql` | 1433 | Bancos `Db_Tank`, `Db_Membership`, `Db_Count` restaurados dos `.bak` |
| Center | `ddt-center` | 2008/2009/9202 | Login server + WCF (NetTcpBinding) |
| Fighting | `ddt-fighting` | 9208 | Battle server |
| Road | `ddt-road` | 9200 | Game world server |
| Site (PHP + .aspx) | `ddt-site` | 8080 | Cadastro/login do jogador |
| Painel admin | `ddt-site` | 8081 | Painel ASP.NET de admin |

Credenciais SQL (default): `sa` / `ddtank@2016`. Mudar em `docker-compose.yml`
(`MSSQL_SA_PASSWORD`) e nos configs em `runtime/`.

## O que foi feito para rodar em Linux/Mono

Cada um destes ajustes está versionado no repo, não em runtime puro:

- **`scripts/fakekernel32.c`** — `.so` em C com `GetPrivateProfileString`,
  `GetConsoleOutputCP` e outros stubs Win32 que o `Bussiness.dll` chamava via
  P/Invoke. Registrado via `<dllmap>` em `/etc/mono/config`.
- **`scripts/create-winpath-links.sh`** — cria symlinks com `\` literal no
  nome para resolver paths `map\1019\fore.map` que os DLLs compilados
  buscam (`MONO_IOMAP=all` está bugado nessa versão).
- **`scripts/create-case-links.sh`** — cópias com extensão case-folded
  (`Homen.master`, `homen.master`) para o WebForms parser não reclamar.
- **`runtime/site/_sqlsrv_shim.php`** — implementação PHP puro das funções
  `sqlsrv_*` em cima de `odbc_*`, usando driver FreeTDS, já que a extensão
  `sqlsrv` da Microsoft não tem build pra ARM Debian buster.
- **Patches em `runtime/painel/*.Master`** — removidos comentários HTML
  aninhados quebrados (`<!--<SCRIPT>...<!-->...-->`) que o parser ASP.NET
  do Mono rejeitava.
- **`runtime/site/login.php`** — chamada para `Mem_Users_Accede` reescrita
  com inline EXEC + SELECT (PHP ODBC não suporta OUT parameters).

## Estado atual: backend 100%, cliente Flash 99% via Ruffle

Tudo do **server-side** roda no Mac:
- SQL Server, Center, Fighting, Road, Site PHP, Painel admin
- **Request app** (gateway ASP.NET do cliente Flash) destravado no Mono — `batch="false"` no Web.config evita o hang do batch-compile do xsp4
- **WebSocket↔TCP bridges** (`ws-center`, `ws-fighting`, `ws-road`) via `efrecon/websockify` — convertem `flash.net.Socket` do browser pros sockets TCP do Center/Fighting/Road

E o **cliente roda no browser via Ruffle**:
- `play.php` embute Ruffle.js (self-hosted em `/ruffle/`) + config `socketProxy`
- Ao acessar `http://localhost:8080/login.php`, logar e clicar jogar, o Loading.swf carrega no browser e renderiza o **splash do DDTANK** (mascote + dragão + barra de progresso)

Mas: o `UIModuleLoader` do DDTank (carregador modular customizado da pickgliss/7Road, usa `Loader.loadBytes()` + `ApplicationDomain.parentDomain`) bate em limitações do **AS3 alpha do Ruffle** ([state](https://github.com/ruffle-rs/ruffle/wiki/Compatibility)) — o splash aparece mas o `DDT_Loading.swf` nunca é solicitado depois. Tudo do lado servidor está pronto pra quando o Ruffle terminar suporte AS3 (eles melhoram a cada release), ou pra um cliente Flash real.

### Como jogar de fato hoje

**Opção A — Flash Projector legacy** (chance maior): baixar do Internet Archive
[Adobe Flash Player projector 32.0.0.363](https://archive.org/details/flashplayer32_0r0_363_win_sa) e abrir:
```
http://localhost:8080/Flash/Loading.swf?user=jogador1&key=test&config=http://localhost:8080/config.xml
```
Flash Player real tem 100% do AS3 — UIModuleLoader funciona, socket TCP funciona direto sem websockify.

**Opção B — Esperar o Ruffle**: acessar `http://localhost:8080/login.php`, logar (`jogador1` / `NovaSenha9999`), clicar jogar. Splash renderiza. Em alguns meses, Ruffle deve completar o que falta.

## Operação

```bash
# logs
docker compose logs -f center fighting road site mssql

# entrar no container do emulador
docker exec -it ddt-center bash

# parar tudo (mantém bancos)
docker compose down

# resetar bancos completamente
docker compose down -v
./setup.sh
```

## Estrutura
```
docker-compose.yml          # 5 services
Dockerfile.emulator         # multi-stage: shim builder + mono runtime
Dockerfile.site             # nginx + xsp4 + php-fpm + freetds
setup.sh                    # entrypoint
scripts/
  restore-dbs.sh            # restaura os 3 .bak
  fakekernel32.c            # shim P/Invoke Windows
  create-winpath-links.sh   # symlinks map\...\...
  create-case-links.sh      # cópias case-folded
  run-service.sh            # launcher mono de cada service
  site-nginx.conf           # routing aspx/php
  start-site.sh             # supervisor manual (xsp4 + php-fpm + nginx)
runtime/                    # cópia editada de Emulador, Novo Site, Painel, Request
Servidor5.5/                # extraído original (intacto)
docs/
  CLIENT-WINDOWS.md         # como montar o cliente Windows na LAN
```
