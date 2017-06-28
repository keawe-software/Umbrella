<?php
	function perform_login($login = null, $pass = null){
		assert($login !== null && $pass !== null,'Missing username or password!');
		$db = get_or_create_db();
		$query = $db->prepare('SELECT * FROM users WHERE login = :login;');
		assert($query->execute(array(':login'=>$login)),'Was not able to request users from database!');
		$results = $query->fetchAll(PDO::FETCH_ASSOC);
		foreach ($results as $user){
			if (sha1($pass) == $user['pass']){
				set_token_cookie($user);
				if ($user['id'] == 1){
					header('Location: index');
				} else {
					header('Location: '.$user['id'].'/view');
				}
				die();				
			}
		}
		error('The provided username/password combination is not valid!');
	}

	function set_token_cookie($user = null){
		assert(is_array($user) && !empty($user),'Parameter "user" null or empty!');
		$db = get_or_create_db();
		$query = $db->prepare('SELECT * FROM tokens WHERE user_id = :userid');
		assert($query->execute(array(':userid'=>$user['id'])),'Was not able to execute SELECT statement.');
		$results = $query->fetchAll(PDO::FETCH_ASSOC);
		$token = null;
		foreach ($results as $row){
			$token = $row['token'];
		}
		if ($token === null) $token = generateRandomString();
		$expiration = time()+3600; // now + one hour
		$query = $db->prepare('INSERT OR REPLACE INTO tokens (user_id, token, expiration) VALUES (:uid, :token, :expiration);');
		assert($query->execute(array(':uid'=>$user['id'],':token'=>$token,':expiration'=>$expiration)),'Was not able to update token expiration date!');
		setcookie('UmbrellaToken',$token,time()+3600,'/');
	}

	function generateRandomString(){
		return bin2hex(openssl_random_pseudo_bytes(40));
	}
	
	function load_user($id = null){
		assert($id !== null,'No user id passed to load_user!');
		assert(is_numeric($id),'Invalid user id passed to load_user!');
		$db = get_or_create_db();
		$query = $db->prepare('SELECT * FROM users WHERE id = :id');
		assert($query->execute(array(':id'=>$id)));
		$results = $query->fetchAll(PDO::FETCH_ASSOC);
		return $results[0];		
	}

	function add_user($db,$login,$pass){
		$hash = sha1($pass); // TODO: better hashing

		$query = $db->prepare('SELECT count(*) AS count FROM users WHERE login = :login');
		assert($query->execute(array(':login'=>$login)),'Was not able to assure non-existance of user!');
		$results = $query->fetchAll(PDO::FETCH_ASSOC);
		assert($results[0]['count'] == 0,'User with this login name already existing!');
		$query = $db->prepare('INSERT INTO users (login, pass) VALUES (:login, :pass);');
		assert ($query->execute(array(':login'=>$login,':pass'=>$hash)),'Was not able to add user '.$login);
	}

	function get_or_create_db(){
		if (!file_exists('db')){
			assert(mkdir('db'),'Failed to create user/db directory!');
		}
		assert(is_writable('db'),'Directory user/db not writable!');
		if (!file_exists('db/users.db')){
			$db = new PDO('sqlite:db/users.db');
			$db->query('CREATE TABLE users (id INTEGER PRIMARY KEY, login VARCHAR(255) NOT NULL, pass VARCHAR(255) NOT NULL);');
			$db->query('CREATE TABLE tokens (user_id INT NOT NULL PRIMARY KEY, token VARCHAR(255), expiration INTEGER NOT NULL)');
			add_user($db,'admin','admin');
		} else {
			$db = new PDO('sqlite:db/users.db');
		}
		return $db;
	}

	function get_userlist($ids = null,$include_passwords = false){
		$db = get_or_create_db();
		$columns = array('id', 'login');
		if ($include_passwords) $columns[]='pass';
		$sql = 'SELECT '.implode(', ', $columns).' FROM users';
		$args = array();
		
		if (is_array($ids) && !empty($ids)){
			$qMarks = str_repeat('?,', count($ids) - 1) . '?';
			$sql .= ' WHERE id IN ('.$qMarks.')';
			$args = $ids;
		}
		$query = $db->prepare($sql);
		assert($query->execute($args),'Was not able to request user list!');
		$results = $query->fetchAll(INDEX_FETCH);
		return $results;
	}
	
	function alter_password($user,$new_pass){
		$db = get_or_create_db();
		$hash = sha1($new_pass); // TODO: better hashing
		$query = $db->prepare('UPDATE users SET pass = :pass WHERE id = :id;');
		assert ($query->execute(array(':pass'=>$hash,':id'=>$user['id'])),'Was not able to update user '.$user['login']);
		
	}
?>