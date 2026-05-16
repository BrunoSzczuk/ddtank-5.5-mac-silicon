<?php
#------------------------------------------------#
#-----------------Admin Painel v1.0--------------#
#--Fix By PauloDev & Jean Carlos & Julio Cezar--=#
#------------------------------------------------#
include('global.php');
if(!isset($_SESSION['UserId'])) die();
if(isset($_GET['ty']))
{
	co();
	switch ($_GET['ty'])
	{
		case 'tooltip':
			gettooltip();
			qc();
			break;
		case 'item':
			getitem();
			qc();
			break;
		case 'teste':
			getmoney();
			qc();
			break;
		case 'event_top':
			GetTopWin();
			qc();
			break;
		case 'gnp':
			getpage();
			qc();
			break;
		case 'form':
			include('module.php');
			buildform();
			qc();
			break;
		case 'buy':
			buyitem();
			qc();
			break;
		case 'bagweb':
			GetinfoBag();
			qc();
			break;
		case 'gitem':
			GetItemToGame();
			qc();
			break;
		case 'ctime':
			ChangeTimeOnlineToCash();
			qc();
			break;
		case 'cpass':
			ChangePassUser();
			qc();
			break;
		case 'glogin':
			GetLinkLogin();
			qc();
			break;
		case 'cnick':
			ChangeNickName();
			qa();
			break;
		case 'pet':
			getpet();
			qc();
			break;
		case 'del':
			delPet();
			qc();
			break;
		case 'delpet':
			delPet();
			qc();
			break;
		case 'gcoin':
			echo loadCoin($_SESSION['UserID']);
			qc();
			break;
		case 'gvip':
			echo IsVipUser($_SESSION['UserId']);
			qc();
			break;
		default:
			echo 'error '.__LINE__;
			break;
	}
} else echo 'error '.__LINE__;
function gettooltip()
{
	$TemplateID = (int)$_POST['id'];
	$q = q("Select TOP 1 CategoryID,Quality,Name,NeedSex,Attack,Defence,Description,Agility,Luck from Shop_Goods Where TemplateID = '$TemplateID'");
	$query = qa($q);
	if(Count($query) > 0)
	{
		if($query['NeedSex'] != 0)
		{
			if($query['NeedSex'] == 1)
				$sexInfo = 'Masculino';
			else
				$sexInfo = 'Feminino';
		}
		else
		{
			$sexInfo = 'UNISEX';
		}
		echo '<div class="tooltip-content" id="tooltipcontent"><div class="ui-tooltip wiki-tooltip">
		<div class="tooltips"><div><div><span style="color:#FFFFFF">'.$query['Name'].'</span><br><div class="pham-chat"><span style="color:'.getQualityColor($query['Quality']).'">'.getQualityName($query['Quality']).'</span></div><div class="loai">'.GetNameItem($query['CategoryID']).'</div></div></div><img class="hr" src="./images/hr.png">';
		if($query['Attack'] > 0)
		{
			echo '<span class="stats">Ataque: '.$query['Attack'].'</span><br><span class="stats">Defesa: '.$query['Defence'].'</span><br><span class="stats">Agilidade: '.$query['Agility'].'</span><br><span class="stats">Sorte: '.$query['Luck'].'</span><br>';
		}
		echo  '<img class="hr" src="./images/hr.png"><span class="des">'.$query['Description'].'</span><img class="hr" src="./images/hr.png"><div><span class="note">Sexo: '.$sexInfo.'</span></div><div><span class="note">Efeito Permanente</span></div></div></div></div>';
	}
	else
	{
		echo 'No infomatin';
	}
}
function getitem()
{
	if(!isset($_POST['id'],$_POST['p'])) die('error '.__LINE__);
	$id = (int)$_POST['id'];
	$page = (int)$_POST['p'];
	if($page < 1) die();
	$where = $id == 0 ? '' : "Where A.CategoryID = '{$id}'";
	$p_off = ($page-1)*12;
	$where .= ' Order by A.id offset '.$p_off.' rows FETCH NEXT 12 ROWS ONLY';
	$query = q('SELECT A.TemplateID, A.Price, B.MaxCount, B.NeedSex, B.CategoryID, B.Pic, B.Name FROM WebShop_Item A LEFT JOIN Shop_Goods B ON A.TemplateID = B.TemplateID '.$where);
	if(qn($query) == 0) die();
	$info  = array();
	while($r = qa($query)) {
		$r['img'] = loadimage($r['Pic'],$r['CategoryID'],$r['NeedSex']);
		unset($r['Pic'],$r['CategoryID'],$r['NeedSex']);
		$info[] = $r;
	}
	echo json_encode($info);
}
function getpage()
{
	if(!isset($_POST['id'])) die('error '.__LINE__);
	$id = (int)$_POST['id'];
	$where = $id == 0 ? '' : "Where B.CategoryID = '{$id}'";
	$query = q('Select Count(A.TemplateID) as count from Webshop_Item A LEFT JOIN Shop_Goods B ON A.TemplateID = B.TemplateID '.$where);
	$info = qa($query);
	echo (int)floor($info['count']/10);
	return false;
}
function buyitem()
{
	if(!isset($_POST['id'],$_POST['c'])) die('eror '.__LINE__);
	$ItemID    = $_POST['id'];
	$Count     = $_POST['c'];
	$return    = null;
	if($ItemID == null || $Count == null)
		$return .= 'Please enter full information. <br>';
	if(!is_numeric($ItemID))
		$return .= 'Itemid invalid .<br>';
	if(!is_numeric($Count))
		$Count = 1;
	$qcheck = q("SELECT TOP 1 A.Price, B.MaxCount FROM WebShop_Item A LEFT JOIN Shop_Goods B ON A.TemplateID = B.TemplateID Where A.TemplateID = '{$ItemID}'");
	if(qn($qcheck) == 0)
		$return .= 'Server does not sell this item';
	$CheckItem = qa($qcheck);
	if($Count > $CheckItem['MaxCount'])
		$Count = $CheckItem['MaxCount'];
	$Price 	   = $Count*$CheckItem['Price'];
	if(loadCoin($_SESSION['UserId']) < $Price)
		$return .= 'Not enough Coin to buy this item. <br>';
	if(strlen($return) != 0)
	{
		echo $return;
		return false;
	}
	else
	{
		q("Update Webshop_Account Set Coin -= '{$Price}' Where UserId = '".$_SESSION['UserId']."'");
		q("Update WebShop_Item Set CountBuy += 1, LastBuy = '".date("Y-m-d H:i:s", time())."' Where TemplateID = '".$ItemID."'");
		q("INSERT INTO Webshop_Bag (UserID,TemplateID,Count,TimeAdd,IsGet) VALUES ('".$_SESSION['UserId']."','{$ItemID}','{$Count}','".date("Y-m-d H:i:s", time())."','False')");
		LogShop($ItemID,3);
		echo 'Item comprado com sucesso, agora va para sua mochila virtual <a href="index.php?page=bagweb"  >Clicando Aqui</a> e em seguida envie os itens comprados para dentro do jogo';
		return false;
	}
}
function GetItemToGame()
{
	if(!isset($_POST['id']) || $_POST['id'] == '') {
		echo 'Please select at least one rows';
		return false;
	}
	$ArrayID = explode(',',$_POST['id']);
	$IdTrue = array();
	foreach($ArrayID as $val) {
		$q = q("SELECT TOP 1 TemplateID,Count From Webshop_Bag Where ID = '".(int)$val."' AND IsGet = 'False' AND UserId='".$_SESSION['UserId']."'");
		if(qn($q) == 1) {
			$r= qa($q);
			$r['ID'] = $val;
			$IdTrue[] = $r;
		}
	}
	$Apara = array();
	$num = count($IdTrue);
	$i = 1;$dem = 0;$para = null;
	$Count = ceil($num/5);
	$array_send = array();
	while($i <= $Count)
	{
		if(($num-$dem) >= 5)
		{
			for($j = 0; $j <= 4; $j++)
			{
				$para .= $IdTrue[$dem]['TemplateID'].','.$IdTrue[$dem]['Count'].',0,0,0,0,0,0,true|';
				$dem++;
			}
		}
		else
		{
			$two = 5-$num+$dem;
			$check = $num-$dem;
			for($j = 0; $j < $check; $j++)
			{
				$para .= $IdTrue[$dem]['TemplateID'].','.$IdTrue[$dem]['Count'].',0,0,0,0,0,0,true|';
				$dem++;
			}
			for($j = 0; $j < $two; $j++)
			{
				$para .= ',1,7,0,0,0,0,0,true|';
			}
		}
		$array_send[] = $para;$para='';
		$i++;
	}
	foreach($IdTrue as $val) {
		q("UPDATE Webshop_Bag SET IsGet = 'True',TimeGet = '".date("Y-m-d H:i:s", time())."' Where ID = '".$val['ID']."'");
	}
	foreach($array_send as $param) {
		$CodeTran = md5(time().$_SESSION['UserId']);
		$link_admin_send = 'http://s2.skytank.net/PainelWebShop/Admin/mainRequest.ashx/SendMailByAdmin';
		$data = array(
			"title"    => "Itens do Shop",
			"content"  => "Você comprou este item no webshop, o codigo da sua compra é : {$CodeTran}                             Obrigado por jogar nosso servidor!",
			"UserName" => $_SESSION['UserName'],
			"param"    => $param
		);
		$data_string = json_encode($data);
		$ch          = curl_init($link_admin_send);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			'Content-Type: application/json',
			'Content-Length: ' . strlen($data_string))
		);
		$ketqua = curl_exec($ch);
		curl_close($ch);
	}
	echo 'Item enviado com sucesso, Por favor verifique seu correio dentro do jogo!';
	return false;
}
function GetinfoBag()
{
	$rep = array();
	$q = q("Select A.TemplateID,A.Count,A.ID,Server='DDTank Ultimate',B.Name from Webshop_Bag A left join Shop_Goods B on A.TemplateID = B.TemplateID Where A.IsGet = 'False' AND A.UserID = ".$_SESSION['UserId']);
	if(qn($q) > 0)
	{
		while($r = qa($q))
		{
			$rep[] = $r;
		}
	}
	echo json_encode($rep);
	return false;
}
function ChangePassUser()
{
	global $conn;
	if(!isset($_POST)) die('Error '.__LINE__);
	$pass = $_POST['p'];
	$newpass = $_POST['np'];
	$renpass = $_POST['rnp'];
	$user = $_SESSION['UserName'];
	$return = '';
	if($pass == null || $newpass == null || $renpass == null)
	{
		$return .= 'Please enter full form <br>';
	}
	if(strlen($pass) < 6 || strlen($pass) > 30 || strlen($newpass) < 6 || strlen($newpass) > 30)
	{
		$return .= 'A senha deve ter entre 6 e 30 caracteres <br>';
	}
	if($newpass == $pass) $return .= 'A nova senha deve ser diferente da senha antiga <br>';
	if($newpass != $renpass) $return .= 'Confirmar senha não correspondem<br>';
	if(strlen($return) != 0)
	{
		echo $return;
		return false;
	}
	else
	{
		$app     = 'DanDanTang';
		$error   = 0;
		$pass    = strtoupper(md5($pass));
		$newpass = strtoupper(md5($newpass));
		// Inline EXEC + SELECT instead of CALL-with-OUTPUT (the odbc shim
		// cannot bind real OUT parameters).
		$cpQ = "DECLARE @err INT = 0; "
		     . "EXEC Webshop_Changepass @ApplicationName=N'$app',@UserName=N'$user',"
		     . "@Password=N'$pass',@Newpass=N'$newpass',@Error=@err OUTPUT; "
		     . "SELECT @err AS err";
		$cpR = sqlsrv_query($conn, $cpQ);
		if ($cpR) {
			$cpRow = sqlsrv_fetch_array($cpR, SQLSRV_FETCH_ASSOC);
			if ($cpRow && isset($cpRow['err'])) {
				$error = (int)$cpRow['err'];
			}
		}
		if($error <= 0)
		{
			$return .= 'A informação não está correcta <br>';
			echo $return;
			return false;
		}
		if($error == 1)
		{
			session_destroy();
			echo '<script type="text/javascript">window.location="login.php";<script>';
			return false;
		} else
		{
			echo 'Error '.__LINE__;
			return false;
		}
	}
}
function Email()	
{
	global $LinkLogin,$Play;
	if(!isset($_POST['name'])) die();
	echo $LinkLogin[$_POST['name']].$Play[$_SESSION['IsVip']].'?u='.$_SESSION['UserName'].'&p='.$_SESSION['PassWord'];
	exit();
}
function GetLinkLogin()
{
	global $LinkLogin,$Play;
	if(!isset($_POST['name'])) die();
	echo $LinkLogin[$_POST['name']].$Play[$_SESSION['IsVip']].'?u='.$_SESSION['UserName'].'&p='.$_SESSION['PassWord'];
	exit();
}
function getpet()
{
	global $dbtank;
	$q = q("Select A.ID,A.Level,B.Name,B.StarLevel,B.Pic From {$dbtank}.dbo.Sys_Users_Pet A Left Join {$dbtank}.dbo.Pet_Template_Info B ON A.TemplateID = B.TemplateID WHERE A.Place > -1 AND A.IsExit = 'True' And A.UserId = (Select Top 1 UserId From {$dbtank}.dbo.Sys_Users_Detail Where UserName = '".$_SESSION['UserName']."')");
	$info = array();
	while($r = qa($q)) {
		$info[] = $r;
	}
	echo json_encode($info);
}
function delPet()
{
	global $dbtank;
	if(!isset($_POST['pid']) || !is_numeric($_POST['pid'])) die('Error '.__LINE__);
	$Pid = $_POST['pid'];
	$q = q("Select TOP 1 ID From {$dbtank}.dbo.Sys_Users_Pet Where UserID = (Select UserID From {$dbtank}.dbo.Sys_Users_Detail Where UserName = '".$_SESSION['UserName']."') And ID = '{$Pid}'");
	if(qn($q) != 1) die('PET you selected does not exist.');
	q("Delete From {$dbtank}.dbo.Sys_Users_Pet Where ID = '{$Pid}'");
	#LogShop($Pid,4);
	echo 'Delete Pet successfull.';
	return false;
}
function ChangeTimeOnlineToCash()
{
	global $dbtank,$RateTimeToCoin;
	$timeonline = GetTimeOnline();
	if($timeonline > 0) {
		$Coin = $timeonline*$RateTimeToCoin;
		q("Update Webshop_Account Set Coin += '{$Coin}' Where UserName = '".$_SESSION['UserName']."'");
		q("Update {$dbtank}.dbo.Sys_Users_Detail Set OnlineTime -= '".($timeonline*60)."' Where UserName = '".$_SESSION['UserName']."' AND IsExist = 'True'");
		LogShop($timeonline,2);
		echo 'true';
	} else echo 'false';
}
function GetTopWin()
{
	global $dbtank;
	
	$query = q("Select TOP 500 Grade,FightPower,NickName,Win,Lost=Total-Win from {$dbtank}.dbo.Sys_Users_Detail Where IsExist='True' Order by FightPower DESC");
	$info  = array();
	$stt = 1;
	$text = '{"data":[';
	while($r = qa($query)) {
		$text .= '{"Grade":'.$r['Grade'].',"FightPower":'.$r['FightPower'].',"NickName":"'.$r['NickName'].'","Win":'.$r['Win'].',"Lost":'.$r['Lost'].',"Top":'.$stt.'},';
		$stt++;
	}
	$text = rtrim($text,',');
	$text .= ']}';
	echo $text;
	exit();
}
function ChangeNickName()
{
	global $dbtank;
	$NickName = EscapeString($_POST['nn']);
	$ReNickName = $_POST['rnn'];
	$return = '';
	if($NickName != $ReNickName)
		$return .= 'Os novos nicks não conferem, por favor cheque';
	if(stripos($NickName,'gm') !== false || stripos($NickName,'adm') !== false || stripos($NickName,'mod') !== false || stripos($NickName,',') !== false || stripos($NickName,'.') !== false)
		$return .= 'Caracters Invalidos';
	$q = q("SELECT TOP 1 UserID From {$dbtank}.dbo.Sys_Users_Detail Where NickName = '{$NickName}'");
	if(qn($q) == 1)
		$return .= 'Este nome ja esta sendo usado, por favor escolha outro';
	$q = q("SELECT TOP 1 UserID From {$dbtank}.dbo.Sys_Users_Detail Where State = '1' AND IsExist = 'True' AND UserName = '".$_SESSION['UserName']."'");
	$q = q("SELECT Money From {$dbtank}.dbo.Sys_Users_Detail Where Money > 0 = '1' AND IsExist = 'True' AND Money = '".$_SESSION['Money']."'");
	if(qn($q) == 1)
		$return .= 'Por favor, saia do jogo antes de executar esta operação';
	
	//if(loadCoin($_SESSION['UserId']) < 5)
	//if(loadCoin($_SESSION['Money']) > 0)	
		//$return .= 'Not engough coin !';
	
	if(strlen($return) != 0) {
		echo $return;
		return false;
	}
	q("UPDATE {$dbtank}.dbo.Sys_Users_Detail SET NickName = '{$NickName}' Where UserName = '".$_SESSION['UserName']."'");
	//q("UPDATE Webshop_Account SET Coin -= '5' Where UserName = '".$_SESSION['UserName']."'"); 
	session_destroy();
	echo '<script type="text/javascript">alert("Mudança de nome Concluida, voce será redirecionado para fazer o login novamente!");window.location="login.php";</script>';
	return false;
}