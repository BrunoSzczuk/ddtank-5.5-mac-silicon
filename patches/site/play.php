<?php
#----------------------------------------#
#------------Admin Painel v1.0-----------#
#--------Create by bachugacon122----=----#
#----------------------------------------#
include ('global.php');
$rd = rand(0,9999999).rand(0,9999999).uniqid();
session_start();

if(!isset($_SESSION['UserId'])) exit('<script type="text/javascript">window.location="login.php";</script>');

// Build the Loading.swf URL using the currently-logged-in user. Each player
// gets their own user/key — no hard-coded jogador1.
$host    = $_SERVER['HTTP_HOST'];                 // e.g. 192.168.0.11:8080
$wsHost  = (strpos($host, ':') !== false) ? substr($host, 0, strpos($host, ':')) : $host;
$user    = $_SESSION['UserName'];
$gameKey = 'k' . bin2hex(random_bytes(16));
$swfUrl  = 'http://'.$host.'/Flash/Loading.swf?user='.urlencode($user).'&key='.$gameKey.'&config=http://'.$host.'/config-lan.xml';

if(isset($_GET['key'])) {
	// Legacy code path: when the original JS AJAX chain calls play.php?key=...
	// it expects a bare <embed> snippet to insert into the page. Keep that
	// behaviour so the existing flow still works in browsers with a real
	// Flash plugin.
	$k = $_GET['key'];
	echo '
	<embed flashvars="editby=" src="http://'.$host.'/Flash/Loading.swf?user='.urlencode($_SESSION['UserName']).'&key='.htmlspecialchars($k, ENT_QUOTES).'&config=http://'.$host.'/config-lan.xml"
		width="1000" height="600" align="middle" quality="high" name="Main" allowscriptaccess="always"
		type="application/x-shockwave-flash" />';
	exit();
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
	<title><?php echo $jogando ?></title>
	<?php echo $icons ?>
	<script>
	// Ruffle config (only matters when this page is opened in a regular Chrome
	// without a Flash plugin — Ruffle then polyfills the <embed> below).
	// Real Flash plugins (Pale Moon, Waterfox, Flash Projector) ignore this.
	window.RufflePlayer = window.RufflePlayer || {};
	window.RufflePlayer.config = {
		publicPath: "/ruffle/",
		autoplay: "on",
		unmuteOverlay: "hidden",
		socketProxy: [
			{host:"gameserver",                port:9202, proxyUrl:"ws://<?php echo $wsHost; ?>:9302/"},
			{host:"gameserver",                port:9208, proxyUrl:"ws://<?php echo $wsHost; ?>:9308/"},
			{host:"gameserver",                port:9200, proxyUrl:"ws://<?php echo $wsHost; ?>:9300/"},
			{host:"<?php echo $wsHost; ?>",    port:9202, proxyUrl:"ws://<?php echo $wsHost; ?>:9302/"},
			{host:"<?php echo $wsHost; ?>",    port:9208, proxyUrl:"ws://<?php echo $wsHost; ?>:9308/"},
			{host:"<?php echo $wsHost; ?>",    port:9200, proxyUrl:"ws://<?php echo $wsHost; ?>:9300/"},
		],
	};
	</script>
	<script src="/ruffle/ruffle.js"></script>
	<style>
		html,body { margin:0; height:100%; background:#000; color:#ddd; font-family:Verdana,Helvetica,sans-serif; }
		.frame    { display:flex; justify-content:center; align-items:center; min-height:100vh; flex-direction:column; padding:8px; }
		.game     { width:1000px; height:600px; background:#000; }
		.tip      { max-width:1000px; margin-top:16px; background:#111; padding:14px 18px; border-radius:6px; font-size:13px; line-height:1.5; }
		.tip code { background:#222; padding:2px 6px; border-radius:3px; word-break:break-all; }
		.tip a    { color:#7cd; }
	</style>
</head>
<body>
<div class="frame">
	<embed flashvars="editby=" src="<?php echo htmlspecialchars($swfUrl, ENT_QUOTES); ?>"
		class="game"
		quality="high" name="Main" allowscriptaccess="always" bgcolor="#000000"
		type="application/x-shockwave-flash" />
	<div class="tip">
		<strong>Logado como <?php echo htmlspecialchars($_SESSION['UserName'], ENT_QUOTES); ?>.</strong>
		Se o jogo não carregou acima, seu browser não tem Flash. Opções:
		<ol>
			<li>Abra esta página em um browser com plugin Flash (Pale Moon ou Waterfox).</li>
			<li>Ou abra o <em>Flash Player Projector</em> e cole esta URL em <strong>File → Open URL</strong>:
				<br><code id="swfUrl"><?php echo htmlspecialchars($swfUrl, ENT_QUOTES); ?></code>
				<button onclick="navigator.clipboard.writeText(document.getElementById('swfUrl').textContent);this.textContent='copiado!'">copiar</button>
			</li>
		</ol>
		<a href="index.php">voltar</a>
	</div>
</div>
</body>
</html>

