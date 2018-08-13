<?php
namespace u2flib_server;

if(empty($_SERVER['HTTPS']) or strtolower($_SERVER['HTTPS']) !== 'on') {
	header('Content-Type: text/plain');
	exit('Only HTTPS protocol is supported');
}

if(
	!function_exists('session_start') or
	!function_exists('json_decode')
) {
	header('Content-Type: text/plain');
	exit('Session and JSON supports are required');
}

session_start();

require_once 'U2F.php';

class App {

	private $libraryURL = false;
	private $u2f = false;
	public $registrations = false;

	// public $lastUserId = '';

	function __construct($url='', $debug=false) {

		$this->debug = $debug;
		if(!array_key_exists('u2f', $_SESSION)) {
			$_SESSION['u2f'] = null;
		}

		if($debug) {
			echo "<!--\nAt beginning of this script :\n\n\$_SESSION['u2f'] = ".print_r($_SESSION['u2f'], true)."\n".
				"\$_POST = ".print_r($_POST, true)."-->\n";
		}

		$this->libraryURL = $url;
		$this->loadRegistrations();

		$appId = "https://{$_SERVER['HTTP_HOST']}";
		// $this->u2f = new u2flib_server\U2F($appId);
		$this->u2f = new U2F($appId);
	}

	protected function loadRegistrations() {
	}

	function getUserIds() {
	}

	protected function getRegistrations($userId) {
	}

	protected function saveRegistration($userId, &$registration) {
	}

	protected function updateCounter($userId, $keyHandle, $value) {
	}

	private function sessionCleanup($fieldsListArr) {
		foreach($fieldsListArr as $field) {
			if(array_key_exists($field, $_SESSION['u2f'])) {
				unset($_SESSION['u2f'][$field]);
			}
		}
	}

	private function alertJS($message, $userId='') {
		if($this->debug) {
			echo <<< EOT
				alert("Ok for $message\\nLook at response in the JS console\\nof the userId : $userId");\n
EOT;
		}
	}

	protected function printRegistrationRequestJS($callback) {
		self::sessionCleanup(array('userId', 'authChallenge'));
		list($regAppId, $regRequest, $regRegisteredKeys) = $this->u2f->getRegisterData(); // $signs is an empty array;
		$_SESSION['u2f']['regRequest'] = json_encode($regRequest, JSON_UNESCAPED_SLASHES);
		$regRequestJSON = json_encode(array($regRequest), JSON_UNESCAPED_SLASHES);
		$regRegisteredKeysJSON = json_encode($regRegisteredKeys, JSON_UNESCAPED_SLASHES);
		echo <<< REGISTER
				setTimeout(function () {
					u2f.register(
						'$regAppId',
						$regRequestJSON,
						$regRegisteredKeysJSON,
						$callback
					)
				}, 1000);\n
REGISTER;
	}

	protected function printAuthenticationRequestJS($callback) {
		self::sessionCleanup(array('newUserId', 'regRequest'));

		$registrationObj = $this->getRegistrations($_SESSION['u2f']['userId']); // Object is required by $u2f->getAuthenticateData
		list($signAppId, $signChallenge, $signRegisteredKeys) = $this->u2f->getAuthenticateData($registrationObj); // returns an array of one object
		$_SESSION['u2f']['authChallenge'] = $signChallenge;
		$signRegisteredKeysJSON = json_encode($signRegisteredKeys, JSON_UNESCAPED_SLASHES);
		echo <<< AUTHENTIFICATION
			setTimeout(function () {
					u2f.sign(
						'$signAppId',
						'$signChallenge',
						$signRegisteredKeysJSON,
						$callback
					);
			}, 1000);\n
AUTHENTIFICATION;
	}

	function printScriptJS($formId, $fieldname, $registerButtonId=false) {
		$sessionKey = (empty($registerButtonId)) ? 'userId' : 'newUserId';
		$actionMsg = (empty($registerButtonId)) ? 'authentification' : 'registration';
?>
	<script type="text/javascript">
	(function () {
		'use strict';

		const errorMsgs = [
		    'Success',
		    'An error otherwise not enumerated',
		    'The request cannot be processed',
		    'Client configuration is not supported',
		    'The presented device is not eligible for this request.\nFor a registration request this may mean that the token is already registered,\nand for a sign request it may mean the token does not know the presented key handle.',
		    'Timeout reached before request could be satisfied'
		];

		const actionMsg = '<?php echo (empty($registerButtonId)) ? 'authentification' : 'registration'; ?>';

		function u2fCallback(response) {
			console.log('Response from the token :', response);
			if(typeof response.errorCode === 'undefined' || response.errorCode === 0) {
<?php self::alertJS($actionMsg, $_SESSION['u2f'][$sessionKey]); ?>
				const form1 = document.getElementById('<?= $formId ?>');
				form1.elements['<?= $fieldname ?>'].value = JSON.stringify(response);
				form1.submit();
			} else {
				alert('U2F error #' + response.errorCode + ' for <?= $actionMsg ?> :\n\n' + errorMsgs[response.errorCode]);
			}
		}

		function setup() {
<?php
if(!empty($registerButtonId)) { /* ------ Registration ------ */
?>
			document.getElementById('<?= $registerButtonId ?>').addEventListener('click', function (event) {
				event.preventDefault();
				console.log('Starts registration');
<?php $this->printRegistrationRequestJS('u2fCallback'); ?>
			});
<?php
} else { /* -------- Authentication ----------- */
?>
			console.log('Starts authentification');
<?php $this->printAuthenticationRequestJS('u2fCallback'); ?>
<?php
}
?>
		}

		if(typeof u2f === 'undefined') {
			const script1 = document.createElement('SCRIPT');
			script1.src = '<?= $this->libraryURL ?>';
			script1.onload = function(event) {
				console.log('API loaded');
				setup();
			};
			document.head.appendChild(script1);
		} else {
			setup();
		}
	})();
	</script>
<?php
	}

	function doRegistration() {
		try {
			/*
			 * returns a u2flib_server\Registration object
			 *   with publicKey, keyHandle, certificate properties
			 * */
			$newRegistration = $this->u2f->doRegister(
				json_decode($_SESSION['u2f']['regRequest']),
				json_decode($_POST['registrationResp'])
			);
			$this->saveRegistration(
				$_SESSION['u2f']['newUserId'],
				$newRegistration
			);
			// $this->lastUserId = $lastUserId;
		} catch( Exception $e ) {
			header('Content-Type: text/plain');
			echo "\$_SESSION['u2f']['regRequest'] = ";
			print_r($_SESSION['u2f']['regRequest']);
			echo "\njson_decode(\$_POST['registrationResp']) = ";
			print_r(json_decode($_POST['registrationResp']));
			die("Error for registration :".$e->getMessage());
		} finally {
			unset($_SESSION['u2f']['newUserId']);
			unset($_SESSION['u2f']['regRequest']);
		}
	}

	function doAuthentication() {
		try {
			$newRegistration = $this->u2f->doAuthenticate(
				$_SESSION['u2f']['authChallenge'],
				//$this->registrations[$_SESSION['u2f']['userId']],
				$this->getRegistrations($_SESSION['u2f']['userId']),
				json_decode($_POST['authentificationResp'])
			);
			/*
			foreach($this->registrations[$_SESSION['u2f']['userId']] as &$registration) {
				if($registration->keyHandle === $newRegistration->keyHandle) {
					$registration->counter = $newRegistration->counter;
					$this->updateCounter($registration->keyHandle, $newRegistration->counter);
					break;
				}
			}
			 * */
			$this->updateCounter(
				$_SESSION['u2f']['userId'],
				$newRegistration->keyHandle,
				$newRegistration->counter
			);
			// $this->lastUserId = $_SESSION['u2f']['userId'];
			header('Content-Type: text/plain');
			$hr = str_repeat('=', 45);
			echo "\n$hr\n  Successful authentification for userId: {$_SESSION['u2f']['userId']}\n$hr\n\n";
			echo '$newRegistration = ';
			print_r($newRegistration);
			echo "\n";
		} catch  (Exception $e) {
			die("Error for authentication :\n".$e->getMessage());
		} finally {
			unset($_SESSION['u2f']['authChallenge']);
			unset($_SESSION['u2f']['userId']);
		}
	}

	function getNewUserId() {
		$userIds = $this->getUserIds();
		do {
			$newUserId = rand(1, 50);
		} while(!empty($userIds) and array_key_exists($newUserId, $userIds));
		return $newUserId;
	}

	protected function userExists($userId) {
		return false;
	}

	function getUserId() {
		if(
			!empty($_SESSION['u2f']['newUserId']) and
			!empty($_SESSION['u2f']['regRequest']) and
			!empty($_POST['registrationResp'])
		) { /* ------------- Do registration ---------------- */
			$this->doRegistration();
		} elseif(
			!empty($_POST['userId']) and
			$this->userExists($_POST['userId'])
		) {
			if(!empty($_POST['newToken'])) { /*  --------- Add a new token ---------- */
				$_SESSION['u2f']['newUserId'] = $_POST['userId'];
			} else { /* --------- Request for authentification -------------- */
				$authUserId = $_POST['userId'];
				$_SESSION['u2f']['userId'] = $authUserId;
				return $authUserId;
			}
		} elseif(
			!empty($_SESSION['u2f']['userId']) and
			!empty($_SESSION['u2f']['authChallenge']) and
			!empty($_POST['authentificationResp'])
		) {	/* ---------- Check authentification ------------ */
			$this->doAuthentication();
			echo '$_SESSION[\'u2f\'] = ';
			print_r($_SESSION['u2f']);
			exit;
		}

		return false;
	}
}

/* End of class App */
?>
