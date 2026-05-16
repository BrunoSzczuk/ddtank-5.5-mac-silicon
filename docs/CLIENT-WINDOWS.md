# Cliente Windows — Como jogar conectando no Mac

O backend (SQL Server, Center, Fighting, Road, site PHP, painel) roda no Mac via `docker compose up` (veja [README](../README.md)). O cliente Flash do DDTank é Windows-nativo: precisa de **Flash Player real** e de um pequeno trecho de **ASP.NET** (a app `Request/`) que o Mono não consegue executar. Esse guia descreve como subir só essa parte num PC Windows na mesma rede do Mac.

## Arquitetura

```
   Mac (server side)                          Windows (client side)
   ──────────────────                         ─────────────────────
   192.168.0.11                               192.168.0.??  (qualquer PC da mesma rede)
                                              
   docker compose up                          IIS Express
   ├── SQL Server     :1433  ◀──── SQL Server ───── Request app (CreateLogin.aspx, etc.)
   ├── Center         :9202  ◀──── socket TCP ──── Cliente Flash (Loading.swf → ddthall.swf)
   ├── Center WCF     :2009  ◀──── net.tcp ─────── Request app
   ├── Fighting       :9208  ◀──── socket TCP ──── Cliente Flash
   ├── Road           :9200  ◀──── socket TCP ──── Cliente Flash
   ├── Site PHP       :8080  ──── HTTP ──── login/signup pelo browser
   └── Painel admin   :8081  ──── HTTP ──── admin pelo browser
```

A LAN IP do Mac aparece no `setup.sh` ou rodando `ifconfig | grep "inet "` no Mac. Nas instruções abaixo substitua `192.168.0.11` pelo IP real do seu Mac.

## Pré-requisitos no Windows

1. **Windows 10 ou 11** (qualquer edição). Pode ser VM Parallels/UTM/VMware no Mac, ou PC físico.
2. **.NET Framework 4.0** ou 4.8 (Windows 10/11 já vêm com 4.8 instalado)
3. **IIS Express** (vem grátis com Visual Studio Community ou instala pelo Web Platform Installer)
   - Alternativa mais simples: **IIS** dos "Recursos do Windows" (Painel de Controle → Programas → Ativar/desativar recursos)
4. **Cópia do `Servidor5.5/Request/`** (a pasta que está no Mac dentro do arquivo `Servidor5.5.rar`)
5. **Flash Player Projector** — Adobe removeu o download oficial, mas as builds pré-2021 estão preservadas:
   - https://archive.org/details/flashplayer32_0r0_363_win_sa (Internet Archive — versão Windows 32.0.0.363, abril/2020)
   - https://github.com/Grubsic/Adobe-Flash-Player-Debug-Downloads-Archive (mirror no GitHub com todos os debug builds)
   - Baixe o `flashplayer_*_sa.exe` (standalone, sem instalador, roda direto).

## Passo a passo

### 1. Copiar a pasta `Request/` pro Windows

Do Mac, copie a pasta `Servidor5.5/Request/` (~250 MB) pro PC Windows. Opções:

- **Pen drive / AirDrop / Google Drive**, ou
- **Compartilhar via SMB** ativando "Compartilhamento de arquivos" no Mac (Ajustes → Geral → Compartilhamento), ou
- **Clone o repo no Windows** e descompacte `Servidor5.5.rar` lá também (mais simples se for repetir).

Coloque a pasta em algo como `C:\inetpub\wwwroot\Request\` (caminho padrão do IIS).

### 2. Patchar o `Web.config` da `Request/` pro IP do Mac

Edite `C:\inetpub\wwwroot\Request\Web.config` e troque os hosts:

```diff
- <add name="Db_TankConnectionString" connectionString="Data Source=WIN-IJR04FJ3H42;..." />
+ <add name="Db_TankConnectionString" connectionString="Data Source=192.168.0.11,1433;Initial Catalog=Db_Tank;Persist Security Info=True;User ID=sa;Password=ddtank@2016" />

- <add key="conString" value="Data Source=WIN-IJR04FJ3H42;..." />
+ <add key="conString" value="Data Source=192.168.0.11,1433;Initial Catalog=Db_Tank;Persist Security Info=True;User ID=sa;Password=ddtank@2016" />

- <add key="countDb" value="Data Source=WIN-IJR04FJ3H42;..." />
+ <add key="countDb" value="Data Source=192.168.0.11,1433;Initial Catalog=Db_Count;Persist Security Info=True;User ID=sa;Password=ddtank@2016" />
```

E no `<system.serviceModel>` (no fim do arquivo) troque o IP do `net.tcp` endpoint:
```diff
- <endpoint address="net.tcp://192.99.235.98:2009/" ...
+ <endpoint address="net.tcp://192.168.0.11:2009/" ...
```

### 3. Subir a Request app no IIS Express

Abra um **PowerShell** ou **Prompt de Comando** e rode:

```cmd
cd "C:\Program Files\IIS Express"
iisexpress.exe /path:"C:\inetpub\wwwroot\Request" /port:80
```

Deixe essa janela aberta enquanto for jogar. Confirme abrindo `http://localhost/CreateLogin.aspx` no browser — você deve ver uma resposta (não 404 ou 500 vermelho).

> Se quiser usar IIS "completo" em vez do IIS Express: no IIS Manager, crie uma Application chamada `Request` apontando pra `C:\inetpub\wwwroot\Request` no Default Web Site. Confirme que o Application Pool está em ".NET CLR Version v4.0".

### 4. Servir os SWFs e o `config.xml` localmente também (opcional mas recomendado)

O Loading.swf busca o `config.xml` e os assets em `/Flash/`. Se você apontar tudo pra um único host (o Windows local), evita CORS chato.

Copie do Mac (do `Servidor5.5/`):
- `Servidor5.5/br/flash/` → `C:\inetpub\wwwroot\Flash\`
- `Servidor5.5/Novo Site/config.xml` → `C:\inetpub\wwwroot\config.xml`
- `Servidor5.5/Novo Site/crossdomain.xml` → `C:\inetpub\wwwroot\crossdomain.xml`

E edite `C:\inetpub\wwwroot\config.xml`:
```diff
- <FLASHSITE value="http://cybertank.ml/Flash/" />
+ <FLASHSITE value="http://localhost/Flash/" />
- <SITE value="http://ddt-a.akamaihd.net/" />
+ <SITE value="http://localhost/" />
- <FIRSTPAGE value="http://cybertank.ml/" />
+ <FIRSTPAGE value="http://localhost/" />
- <LOGIN_PATH value="http://cybertank.ml/" />
+ <LOGIN_PATH value="http://localhost/" />
- <REQUEST_PATH value="http://cybertank.ml/Request/" />
+ <REQUEST_PATH value="http://localhost/Request/" />
- <FILL_PATH value="http://cybertank.ml/" />
+ <FILL_PATH value="http://localhost/" />
- <COUNT_PATH value="http://assayerhandler.7road.com/" />
+ <COUNT_PATH value="http://localhost/" />
- <file value="http://cybertank.ml/crossdomain.xml" />
+ <file value="http://localhost/crossdomain.xml" />
```

### 5. Registrar / pegar um login

Pelo browser do Windows (ou do Mac mesmo), acesse `http://192.168.0.11:8080/login.php` (substituindo pelo IP do seu Mac) e crie uma conta — exatamente como já validamos no setup do Mac. Anote usuário e senha.

### 6. Patchar o IP de servidor no cliente

Edite os arquivos:
- `C:\inetpub\wwwroot\Flash\config.xml` — não precisa, já fez no passo 4
- Verifique se nenhum `192.99.235.98` sobrou em arquivos `.xml` dentro de `C:\inetpub\wwwroot\Flash\` (procure com `findstr /S /M "192.99" *.xml`). Se achar, troque por `192.168.0.11`.

### 7. Abrir o jogo no Flash Projector

1. Execute o `flashplayer_*_sa.exe` (Flash Projector standalone que você baixou).
2. Menu **File → Open** e cole a URL:

   ```
   http://localhost/Flash/Loading.swf?user=SEU_USUARIO&key=qualquercoisa&config=http://localhost/config.xml
   ```

   Onde `SEU_USUARIO` é o usuário que você cadastrou no passo 5. O `key` é uma string qualquer — o `CreateLogin.aspx` vai gerar o token real e devolver pro Flash.

3. Loading.swf vai:
   - Baixar `config.xml`
   - Chamar `http://localhost/Request/CreateLogin.aspx?content=...` → IIS responde
   - Receber o token
   - Conectar via TCP em `192.168.0.11:9202` (Center, login)
   - Você cai na tela de criação de personagem / hall do jogo

## Troubleshooting

**"Cannot connect to server" / loading infinito**
- Cheque que o **firewall do Windows** não está bloqueando o `flashplayer_sa.exe`. Quando ele tenta abrir socket em `192.168.0.11:9202`, o Windows pode pedir permissão — clique "Permitir".
- Cheque que o **firewall do Mac** permite conexões inbound (Ajustes → Rede → Firewall → adicione `Docker.app` à lista de permissões).
- Confirme do Windows que dá pra conectar nas portas:
  ```cmd
  Test-NetConnection 192.168.0.11 -Port 1433
  Test-NetConnection 192.168.0.11 -Port 2009
  Test-NetConnection 192.168.0.11 -Port 9202
  ```

**`CreateLogin.aspx` retorna 500**
- Provavelmente o IIS Express não tem permissão de leitura na pasta `Request/`. Click direito → Properties → Security → adiciona `IIS_IUSRS` com leitura.
- Ou o `Web.config` ficou com algum URL inválido depois das substituições — abra o response do navegador, o Mono/IIS imprime o stack.

**SQL Server "Login failed for user 'sa'"**
- Verifique que o `MSSQL_SA_PASSWORD` no `docker-compose.yml` do Mac bate com o `Password=ddtank@2016` nos Web.configs do Windows (default é `ddtank@2016`).

**Game socket conecta mas logo cai**
- Os `.exe.config` dos emuladores no Mac têm `<add key="IP" value="0.0.0.0"/>` (bind em todas interfaces). O cliente Flash envia handshake esperando o servidor responder a partir de um endereço alcançável. Em rede LAN simples isso costuma funcionar. Se cair em loop, troque no Mac `runtime/Center/Center.Service.exe.config` o IP de `0.0.0.0` para o IP LAN do Mac (`192.168.0.11`) e `docker compose restart center`.

## E se eu não quiser instalar IIS?

Alternativa rápida: rodar o **HTTP File Server (HFS)** ou **Nginx for Windows** apenas pra `/Flash/` e `/config.xml` estáticos, e rodar `Request/` num **IIS Express** standalone (não precisa de instalação, é um único `.exe`). Mas IIS Express precisa do Visual Studio ou de um download direto da Microsoft (https://www.microsoft.com/en-us/download/details.aspx?id=48264 — esse link de 2014 ainda funciona).
