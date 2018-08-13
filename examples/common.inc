<!DOCTYPE html>
<html lang="en">
<head>
	<meta http-equiv="X-UA-Compatible" content="IE=Edge" />
	<meta charset="utf-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<title><?php echo TITLE_PAGE; ?></title>
</head><body>
<?php
if(empty($authUserId)) { /* ---------- Prepare a new registration -------- */
	if(empty($_SESSION['u2f']['newUserId'])) {
		// new userId
		$_SESSION['u2f']['newUserId'] = $myApp->getNewUserId();
	}
	$newToken = (!empty($myApp->registrations) and array_key_exists($_SESSION['u2f']['newUserId'], $myApp->registrations));
	$caption = ($newToken) ? 'New token for userId' : 'New userId';
?>
	<form id="reg-form" method="post">
		<input type="hidden" name="registrationResp" value="" />
		<span class="user-id"><?= $caption ?></span> : <?php echo $_SESSION['u2f']['newUserId']; ?>
		<button id="regBtn">Registration</button>
	</form>
<?php
	$userIds = $myApp->getUserIds();
	if(!empty($userIds) and !$newToken) {
?>
	<form id="auth-form" method="post">
		<label for="id_user-id">UserId</label>
		<select id="id_user-id" name="userId" required>
			<option value="">-------</option>
<?php
	// $lastUserId = $_SESSION['lastUserId'];
	$lastUserId = '';
	foreach($userIds as $userId) {
		$selected = ($userId == $lastUserId) ? ' selected' : '';
		echo <<< OPTION
			<option value="$userId"$selected>$userId</option>\n
OPTION;
		}
?>
		</select>
		<input type="submit" value="New token" name="newToken" />
		<input type="submit" value="Authentification" />
	</form>
<?php
	}
	$myApp->printScriptJS('reg-form', 'registrationResp', 'regBtn');
} else { /* ---------- Request only for authentification -------- */
?>
	<form id="auth-form" method="post">
		<input type="hidden" name="authentificationResp" />
		Request authentication for UserId : <?php echo $authUserId; ?>
	</form>
<?php
	$myApp->printScriptJS('auth-form', 'authentificationResp');
}

if(DEBUG) {
?>
	<div style="position: fixed; bottom: 0; left:0; width: 100vw; max-height: 75vh; overflow: auto;">
		<pre style="margin: 0; padding: 0.3rem 0.8rem; width: 300vw; background: #666; color: greenyellow;">
<?php
echo '$_SESSION[\'u2f\'] = '.print_r($_SESSION['u2f'], true).
	'$registrations = '.print_r($myApp->registrations, true);
?>
		</pre>
	</div>
<?php
}
?>
</body></html>
