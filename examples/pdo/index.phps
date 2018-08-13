<?php
const TITLE_PAGE = 'Test of U2F with SQLite storage';

const DEBUG = true;

require_once '../../src/u2flib_server/App.php';

const FIRSTNAMES = 'Donald Melania, Arnold Barack Georges John Boris';

class MyRegistration {
	public $keyHandle;
	public $publicKey;
	public $certificate;
	public $counter;
}

class MyApp extends u2flib_server\App {

	const DATABASE = '/u2f-pdo.sqlite';
	const CREATION_DATABASE = <<< CREATION_DATABASE
create table if not exists registrations(
	keyHandle varchar(255) primary key,
	publicKey varchar(255),
	certificate text,
	counter integer,
	user_id integer not null
);
CREATION_DATABASE;

	const ADD_REGISTRATION = <<< ADD_REGISTRATION
insert into registrations(keyHandle, publicKey, certificate, counter, user_id)
	values(?, ?, ?, ?, ?);
ADD_REGISTRATION;

	const GET_REGISTRATIONS = <<< GET_REGISTRATIONS
select keyHandle, publicKey, certificate, counter from registrations
	where user_id = ?;
GET_REGISTRATIONS;

	private $dbh = false;

	protected function loadRegistrations() {
		parent::loadRegistrations();

		// vérifier si droits en écriture dans le dossier
		$this->dbh = new PDO('sqlite:'. __DIR__ .'/'.self::DATABASE);
		// $this->dbh->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ);
		$this->dbh->exec(self::CREATION_DATABASE);
	}

	protected function getRegistrations($userId) {
		$stmt = $this->dbh->prepare(self::GET_REGISTRATIONS);
		$stmt->execute(array($userId));
		return $stmt->fetchAll(PDO::FETCH_CLASS, 'MyRegistration');
	}

	protected function updateCounter($userId, $keyHandle, $value) {
		$stmt = $this->dbh->prepare('update registrations set counter=? where keyHandle=?;');
		$stmt->execute(array($value, $keyHandle));
	}

	protected function saveRegistration($userId, &$registration) {
		$stmt = $this->dbh->prepare(self::ADD_REGISTRATION);
		$stmt->execute(array(
			$registration->keyHandle,
			$registration->publicKey,
			$registration->certificate,
			$registration->counter,
			$userId
		));
	}

	function getUserIds() {
		$stmt = $this->dbh->query('select distinct user_id from registrations order by 1;');
		return $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
	}

	protected function userExists($userId) {
		$stmt = $this->dbh->prepare('select count(*) from registrations where user_id = ?;');
		$stmt->execute(array($userId));
		$rows = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
		return !empty($rows) and $rows[0] > 0;
	}

}

$myApp = new MyApp('../assets/u2f-api.js', DEBUG);
$authUserId = $myApp->getUserId(); // for authentication

include '../common.inc';
