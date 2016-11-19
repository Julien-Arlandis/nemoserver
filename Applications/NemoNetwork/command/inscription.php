<?php

// Insère un nouvel utilisateur
function insertUser($email, $password, $privilege = 1)
{
	global $jntp;
	$error = array();
	$code = "200";
	$userid = "";
	$check = "";
	if(strlen($password) < 4)
	{
		array_push($error, "Password trop court");
		$code = "400";
	}
	if (!preg_match('#^[\w.-]+@[\w.-]+\.[a-z]{2,6}$#i', $email))
	{
		array_push($error, "Email invalide");
		$code = "400";
	}

	$total = $jntp->mongo->user->find(array('email' => strtolower($email)))->count();
	$total = ($total > 0 ) ? $total : 0;

	if ( $total > 0)
	{
		$obj = $jntp->mongo->user->findOne(array('email' => strtolower($email)));
		if($obj{'check'} != '')
		{
			array_push($error, "Un nouveau mail d'activation a été envoyé");
			$code = "300";
		}
		else
		{
			array_push($error, "Email déjà pris");
			$code = "400";
		}
	}
	if($code == "200")
	{
		$check = (string)rand(100000000000, 99999999999999);
		$hashkey = sha1(rand(0, 9e16).uniqid());
		$checksum = sha1(uniqid());
		$password_crypt = sha1($checksum.$password);
		$date = date("Y-m-d").'T'.date("H:i:s").'Z';

		$res = $jntp->mongo->counters->findAndModify(
			array("_id"=>"UserID"),
			array('$inc'=>array("seq"=>1)),
			null,
			array("new" => true, "upsert"=>true)
		);
		$userid = $res['seq'];
		$user = array('UserID' => $userid, 'email' => $email, 'password' => $password_crypt, 'privilege' => $privilege, 'hashkey' => $hashkey, 'check' => $check, date => $date, 'checksum' => $checksum);

		$jntp->mongo->user->save($user);
	}
	return(array("code" => $code, "info" => $error, "userid" => $userid, "check" => $check));
}

function mailInscription($email, $password, $userid, $check)
{
	global $jntp;
	require_once(__DIR__.'/../../core/lib/class.phpmailer.php');
	require_once(__DIR__.'/../../core/lib/class.smtp.php');
	
	$url = "http://".$jntp->config{'domain'}."/jntp/Applications/NemoNetwork/account.php?action=inscription&amp;userid=".$userid."&amp;check=".$check;
	$message = "
Bonjour, bienvenue sur <a href=\"http://".$jntp->config{'domain'}."\">".$jntp->config{'organization'}."</a>.
<br><br>
Votre inscription a bien été enregistrée avec l'email ".$email.".<br>
Votre mot de passe est : <strong>".$password."</strong>
<br><br>
Merci de valider votre adresse mail en cliquant sur ce lien : <br>
<a href=\"".$url."\">".$url."</a><br>
ou en le recopiant dans votre barre d'adresse.";
	
	$mail = new PHPMailer();
	$mail->isSMTP();
	$mail->Host = $jntp->config{'smtpHost'};
	if($mail->SMTPAuth = $jntp->config{'smtpAuth'};)
	{
		$mail->Username = $jntp->config{'smtpLogin'};
		$mail->Password = $jntp->config{'smtpPassword'};  
	}	  
	$mail->SMTPSecure = $jntp->config{'smtpSecure'};
	$mail->Port = $jntp->config{'smtpPort'};
	$mail->setFrom($jntp->config{'administrator'}, $jntp->config{'organization'});
	$mail->AddAddress($email);
	$mail->Subject = "Bienvenue sur ".$jntp->config{'organization'};
	$mail->isHTML(true);
	$mail->Body = $message;
	$mail->CharSet = "UTF-8";
	
	if(!$mail->Send())
	{
		return array("code" =>"400", "info" => "L'email n'a pas pu être envoyé\n" );
	}
	return true;
}

if($jntp->config{'activeInscription'} || $jntp->privilege == 'admin')
{
	$res = insertUser($jntp->param{'email'}, $jntp->param{'password'});
	if($res['code'] == "200" || $res['code'] == "300")
	{
		mailInscription($jntp->param{'email'}, $jntp->param{'password'}, $res['userid'], $res['check']);
		$res['code'] = "200";
	}
	$jntp->reponse{'code'} = $res['code'];
	$jntp->reponse{'info'} = $res['info'];
}
else
{
	$jntp->reponse{'code'} = "400";
	$jntp->reponse{'info'} = "L'inscription en ligne est désactivée sur ce serveur, veuillez adresser un mail à ".$jntp->config{'administrator'}." en spécifiant le mot de passe souhaité, votre compte sera ouvert dans les plus brefs délais.";
}
