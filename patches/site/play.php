<?php
#----------------------------------------#
#------------Admin Painel v1.0-----------#
#--------Create by bachugacon122----=----#
#----------------------------------------#
include ('global.php');
$rd = rand(0,9999999).rand(0,9999999).uniqid();
session_start();

if(isset($_GET['key'])) {
	$k = $_GET['key'];
	// Just the <embed> fragment — Ruffle.js loaded by the parent page (below)
	// will polyfill it. The original code used <embed>; sticking with that
	// because Ruffle handles it more reliably than <object data=...>.
	echo '
	<embed flashvars="editby=" src="'.$LinkFlash.'Loading.swf?user='.$_SESSION['UserName'].'&key='.$k.'&config='.$LinkLogin.'config.xml"
		width="1000" height="600" align="middle" quality="high" name="Main" allowscriptaccess="always"
		type="application/x-shockwave-flash" />';
	exit();
}
$wsHost = $_SERVER['HTTP_HOST'];
if (strpos($wsHost, ':') !== false) { $wsHost = substr($wsHost, 0, strpos($wsHost, ':')); }
if(!isset($_SESSION['UserId'])) exit('<script type="text/javascript">window.location="login.php";</script>');
?>
<head>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
		<title><?php echo $jogando ?></title>
		<?php echo $icons ?>
		<script>
		// Ruffle config must exist BEFORE ruffle.js loads. Routes Flash sockets
		// through the websockify bridges running on the Mac.
		window.RufflePlayer = window.RufflePlayer || {};
		window.RufflePlayer.config = {
			publicPath: "/ruffle/",
			autoplay: "on",
			unmuteOverlay: "hidden",
			socketProxy: [
				{host:"gameserver", port:9202, proxyUrl:"ws://<?php echo $wsHost; ?>:9302/"},
				{host:"gameserver", port:9208, proxyUrl:"ws://<?php echo $wsHost; ?>:9308/"},
				{host:"gameserver", port:9200, proxyUrl:"ws://<?php echo $wsHost; ?>:9300/"},
				{host:"<?php echo $wsHost; ?>", port:9202, proxyUrl:"ws://<?php echo $wsHost; ?>:9302/"},
				{host:"<?php echo $wsHost; ?>", port:9208, proxyUrl:"ws://<?php echo $wsHost; ?>:9308/"},
				{host:"<?php echo $wsHost; ?>", port:9200, proxyUrl:"ws://<?php echo $wsHost; ?>:9300/"},
			],
		};
		// Stubs so legacy onclick handlers and the disabled adblock detector
		// do not throw.
		window.showGame = function(){};
		window.fuckAdBlock = { setOptions:function(){}, onDetected:function(){}, onNotDetected:function(){}, on:function(){} };
		</script>
		<script src="/ruffle/ruffle.js"></script>
		<script src="./js/jquery-1.11.1.min.js"></script>
 
<style>
      html, body	{ height:100%; }
      body
        {
        margin: 0px auto;
        padding: 0px;
        background-image: url(4.jpg);
	    background-repeat: no-repeat;
        background-position: center center;
        overflow:auto; text-align:center;
        }
        *,html,body,embed{cursor:url('images/cursors/default.cur'), default;}
	    a:hover{cursor:url('images/cursors/link.cur'), pointer;}
	    input{cursor:url('images/cursors/input.cur'), text;}
		#playgane {position: relative;};
    </style>	
</head>
<body scroll="no" >
	<table width="100%" height="100%" border="0" cellspacing="0" cellpadding="0">
        <tr>
            <td valign="middle">
                <table border="0" align="center" cellpadding="0" cellspacing="0">
                    <tr>
                        <td align="center">
						<div id="gameOuterContainer"  style="cursor:pointer;width:1000px;height:600px;overflow:hidden;background-image:url('images/gameBackGround.jpg');" onclick="showGame();">
                            <div id="gameContainer"  style="width:1000px;height:600px;overflow:hidden;" >							
                            <div id="playgame" ></div>
							 </div>							 
                        </div>							
                        </td>
<center><script async src="//pagead2.googlesyndication.com/pagead/js/adsbygoogle.js"></script><!-- webshop --><ins class="adsbygoogle"     style="display:inline-block;width:970px;height:90px"     data-ad-client="ca-pub-5910538190471692"     data-ad-slot="2238805761"></ins><script>(adsbygoogle = window.adsbygoogle || []).push({});</script></center>						
                    </tr>					
                </table>
<center><script async src="//pagead2.googlesyndication.com/pagead/js/adsbygoogle.js"></script><!-- webshop --><ins class="adsbygoogle"     style="display:inline-block;width:970px;height:90px"     data-ad-client="ca-pub-5910538190471692"     data-ad-slot="2238805761"></ins><script>(adsbygoogle = window.adsbygoogle || []).push({});</script></center>				
            </td>
        </tr>
<div id="loading"><center><img src='./images/gif-load.gif'/></center></div>
<script type="text/javascript">
$.ajax({
    type: 'GET',
    url: "./checkuser.ashx",
    data: "username=<?php echo $_SESSION['UserName'];?>&password=<?php echo $_SESSION['PassWord']; ?>",
    success: function (data_revert) {
        if (data_revert == "ok") {
			$.ajax({
                type: 'GET',
                url: './logingame.aspx',
                success: function (data_revert) {
                    if (data_revert != "0") {
						$.ajax({
							type: 'GET',
							url: 'play.php',
							data: 'key='+data_revert,
							success: function (data) {
								$('#loading').slideUp(function() {
									$('#playgame').html(data).slideDown();
								});
							}
						});
                    }
                    else window.location="index.php?logout=true";
                }
            });
        }
        else window.location="index.php?logout=true";
    }
});
</script>

</body>

