<?php include '../bootstrap.php';

	$title = 'Umbrella User Management';
	const MODULE = 'User';

	function get_or_create_db(){
		$table_filename = 'users.db';
		if (!file_exists('db')) assert(mkdir('db'),'Failed to create user/db directory!');
		assert(is_writable('db'),'Directory user/db not writable!');
		if (!file_exists('db/'.$table_filename)){
			$db = new PDO('sqlite:db/'.$table_filename);

			$tables = [
					'users'=>User::table(),
					'tokens'=>Token::table(),
					'token_uses'=>Token::uses(),
					'login_services'=>LoginService::table(),
					'service_ids_users'=>LoginService::users(),
			];

			foreach ($tables as $table => $fields){
				$sql = 'CREATE TABLE '.$table.' ( ';
				foreach ($fields as $field => $props){
					if ($field == 'UNIQUE'||$field == 'PRIMARY KEY') {
						$field .='('.implode(',',$props).')';
						$props = null;
					}
					$sql .= $field . ' ';
					if (is_array($props)){
						foreach ($props as $prop_k => $prop_v){
							switch (true){
								case $prop_k==='VARCHAR':
									$sql.= 'VARCHAR('.$prop_v.') '; break;
								case $prop_k==='DEFAULT':
									$sql.= 'DEFAULT '.($prop_v === null?'NULL ':'"'.$prop_v.'" '); break;
								case $prop_k==='KEY':
									assert($prop_v === 'PRIMARY','Non-primary keys not implemented in user/controller.php!');
									$sql.= 'PRIMARY KEY '; break;
								default:
									$sql .= $prop_v.' ';
							}
						}
						$sql .= ", ";
					} else $sql .= $props.", ";
				}
				$sql = str_replace([' ,',', )'],[',',')'],$sql.')');
				$query = $db->prepare($sql);
				assert($query->execute(),'Was not able to create '.$table.' table in '.$table_filename.'!');
			}

			User::createAdmin();
		} else {
			$db = new PDO('sqlite:db/'.$table_filename);
		}
		return $db;
	}

	function perform_id_login($id){
		global $services;

		$user = User::load(['ids'=>$id]);
		if (empty($user)){
			error('No user found for id',$id);
			return;
		}
		$token = Token::getOrCreate($user);
		$redirect = param('returnTo');
		if ($redirect) {
			if (strpos($redirect, '?') === false){
				$redirect.='?token='.$token;
			} else $redirect.='&token='.$token;
		}
		if (!$redirect && $user['id'] == 1) $redirect='index';
		if (!$redirect)	{
			$tests = ['task','project','bookmarks','files'];
			foreach ($tests as $test){
				if (isset($services[$test])) {
					$redirect = getUrl($test);
					break;
				}
			}

		}
		if (!$redirect)	$redirect = $user['id'].'/view';
		redirect($redirect);
	}

	function user_revoke_token(){
		global $user;
		$token = $_SESSION['token'];
		unset($_SESSION['token']);
		$db = get_or_create_db();
		$query = $db->prepare('DELETE FROM tokens WHERE token = :token');
		assert($query->execute(array(':token'=>$token)),'Was not able to execute DELETE statement.');

		$query = $db->prepare('SELECT domain FROM token_uses WHERE token = :token;');
		if ($query->execute([':token'=>$token])){
			$rows = $query->fetchAll(PDO::FETCH_ASSOC);
			foreach ($rows as $row) file_get_contents($row['domain'].'?revoke='.$token);
		}
		$query = $db->prepare('DELETE FROM token_uses WHERE token = :token');
		assert($query->execute(array(':token'=>$token)),'Was not able to execute DELETE statement.');
	}

	function generateRandomString(){
		return bin2hex(openssl_random_pseudo_bytes(40));
	}

	function get_assigned_logins($foreign_id = null){
		global $user;
		$db = get_or_create_db();

		$sql = 'SELECT * FROM service_ids_users ';
		if ($foreign_id !== null) {
			$sql .= 'WHERE service_id = :id';
			$args = [':id'=>$foreign_id];
		} else {
			$sql .= 'WHERE user_id = :id';
			$args = [':id'=>$user->id];
		}
		$query = $db->prepare($sql);
		assert($query->execute($args),'Was not able to read list of assigned logins.');
		$rows = $query->fetchAll(INDEX_FETCH);
		if ($foreign_id !== null) return $rows[$foreign_id];
		return $rows;
	}

	function get_themes(){
		$entries = scandir('common_templates/css');
		$results = [];
		foreach ($entries as $entry){
			if (in_array($entry,['.','..'])) continue;
			if (is_dir('common_templates/css/'.$entry)) $results[] = $entry;
		}
		return $results;
	}

	function add_login_service($login_service){
		assert(is_array($login_service),'Argument passed to user/controller::add_login_service is not an array!');
		$db = get_or_create_db();
		$args = [];
		foreach ($login_service as $k => $v){
			$args[':'.$k] = $v;
		}
		$query = $db->prepare('INSERT INTO login_services ('.implode(',',array_keys($login_service)).') VALUES ('.implode(',',array_keys($args)).')');
		assert($query->execute($args),'Was not able to add login service!');
	}

	function drop_login_service($name){
		$db = get_or_create_db();
		$query = $db->prepare('DELETE FROM login_services WHERE name = :name');
		assert($query->execute([':name'=>$name]),'Was not able to delete login_service "'.$name.'"!');
	}

	class LoginService extends UmbrellaObjectWithId{
		function assign($foreign_id){
			global $user;
			$db = get_or_create_db();

			$query = $db->prepare('INSERT INTO service_ids_users (service_id, user_id) VALUES (:service, :user);');
			assert($query->execute([':service'=>$this->id.':'.$foreign_id,':user'=>$user->id]),t('Was not able to assign service id (?) with your user account!',$foreign_id));
			info('Your account has been assigned with ? / id ?',[$this->id,$foreign_id]);
		}

		function deassign($foreign_id){
			$db = get_or_create_db();
			$query = $db->prepare('DELETE FROM service_ids_users WHERE service_id = :service;');
			assert($query->execute([':service'=>$foreign_id]),t('Was not able to de-assign service id (?) from your user account!',$foreign_id));
			info('? has been de-assigned.',$foreign_id);
		}

		static function load($name = null){
			$db = get_or_create_db();

			$sql = 'SELECT * FROM login_services';
			$args = [];
			if ($name) {
				$sql .= ' WHERE name = :name';
				$args[':name'] = $name;
			}
			$query = $db->prepare($sql);
			assert($query->execute($args),'Was not able to read login services list.');
			$rows = $query->fetchAll(INDEX_FETCH);
			$services = [];
			foreach ($rows as $id => $row){
				$service = new LoginService();
				$service->patch($row);
				$service->id = $id;
				unset($service->dirty);
				if ($name) return $service;
				$services[$id] = $service;
			}
			if ($name) return null;
			return $services;
		}

		static function table(){
			return [
					'name'=>['VARCHAR'=>255,'KEY'=>'PRIMARY'],
					'url'=>'TEXT',
					'client_id'=>['VARCHAR'=>255],
					'client_secret'=>['VARCHAR'=>255],
					'user_info_field'=>['VARCHAR'=>255],
			];
		}

		static function users(){
			return [
					'service_id'=>['VARCHAR'=>255,'NOT NULL','KEY'=>'PRIMARY'],
					'user_id'=>['INT','NOT NULL']
			];
		}
	}

	class Token extends UmbrellaObjectWithId{
		static function  drop_expired(){
			$db = get_or_create_db();
			$db->exec('DELETE FROM tokens WHERE expiration < '.time());
			return $db;
		}

		static function getOrCreate($user = null){
			assert(!empty($user->id),'Parameter "user" null or empty!');
			$db = get_or_create_db();
			$query = $db->prepare('SELECT * FROM tokens WHERE user_id = :userid');
			assert($query->execute([':userid'=>$user->id]),'Was not able to execute SELECT statement.');
			$results = $query->fetchAll(PDO::FETCH_ASSOC);
			$token = null;
			foreach ($results as $row) $token = $row['token'];
			if ($token === null) $token = generateRandomString();
			$expiration = time()+3600; // now + one hour
			$query = $db->prepare('INSERT OR REPLACE INTO tokens (user_id, token, expiration) VALUES (:uid, :token, :expiration);');
			assert($query->execute([':uid'=>$user->id,':token'=>$token,':expiration'=>$expiration]),'Was not able to update token expiration date!');
			$_SESSION['token'] = $token;
			return $token;
		}

		static function load($key = null){
			$db = Token::drop_expired();

			$sql = 'SELECT '.implode(', ', array_keys(Token::table())).' FROM tokens';
			$where = [];
			$args = [];

			if ($key != null){
				$where[] = 'token = :token';
				$args[':token'] = $key;
			}

			if (!empty($where)) $sql .= ' WHERE '.implode(' AND ', $where).' ';
			$query = $db->prepare($sql);
			assert($query->execute($args),'Was not able to request token table.');
			$rows = $query->fetchAll(INDEX_FETCH);

			$tokens = [];
			foreach ($rows as $id => $row){
				$token = new Token();
				$token->patch($row);
				$token->token = $id;
				unset($token->dirty);
				if (!empty($key)) return $token;
				$tokens[$id] = $token;

			}
			if (!empty($key)) return null;
			return $tokens;
		}

		static function table(){
			return [
					'token'=>['VARCHAR'=>255,'NOT NULL','KEY'=>'PRIMARY'],
					'user_id'=>['INTEGER','NOT NULL'],
					'expiration'=>['INT','NOT NULL']
			];
		}

		function useWith($domain){
			$db = get_or_create_db();

			// stretch expiration time
			$this->expiration = time()+300; // this value will be delivered to cliet apps
			$query = $db->prepare('UPDATE tokens SET expiration = :exp WHERE token = :token');
			$query->execute([':exp'=>($this->expiration+3000),':token'=>$this->token]); // the expiration period in the user app is way longer, so clients can revalidate from time to time

			if ($domain){
				$query = $db->prepare('INSERT OR IGNORE INTO token_uses (token, domain) VALUES (:token, :domain)');
				$query->execute([':token'=>$this->token,':domain'=>$domain]);
			}
			return $this;
		}

		function user(){
			$u = User::load(['ids'=>$this->user_id]);
			$u->token=$this;
			return $u;
		}

		static function uses(){
			return [
					'token'=>['VARCHAR'=>255],
					'domain'=>'TEXT',
					'PRIMARY KEY'=>['token','domain']
			];
		}
	}

	class User extends UmbrellaObjectWithId{
		function assigned_logins(){
			if (empty($this->assigned_logins)){
				global $user;
				$db = get_or_create_db();

				$sql = 'SELECT * FROM service_ids_users WHERE user_id = :id';
				$args = [':id'=>$user->id];
				$query = $db->prepare($sql);
				assert($query->execute($args),'Was not able to read list of assigned logins.');
				$rows = $query->fetchAll(INDEX_FETCH);
				$this->assigned_logins = array_keys($rows);
			}
			return $this->assigned_logins;
		}

		function correct($pass = null){
			if ($pass == null) return false;
			return ($this->pass == sha1($pass));
		}

		static function createAdmin(){
			$user = new User();
			$user->patch(['login'=>'admin','pass'=>'admin'])->save();
		}

		function exists(){
			$db = get_or_create_db();
			$query = $db->prepare('SELECT count(*) AS count FROM users WHERE login = :login');
			assert($query->execute([':login'=>$this->login]),'Was not able to check existance of user!');
			$results = $query->fetchAll(PDO::FETCH_ASSOC);
			if (empty($results)) return false;
			return $results[0]['count'] > 0;
		}

		function invite(){
			global $user;
			$db = get_or_create_db();
			$query = $db->prepare('DELETE FROM tokens WHERE user_id = :uid');
			$query->execute([':uid'=>$this->id]);
			$query = $db->prepare('INSERT INTO tokens (user_id, token, expiration) VALUES (:uid, :tok, :exp)');
			$token = generateRandomString();
			$args = [':uid'=>$this->id,':tok'=>$token,':exp'=>(time()+60*60*240)];
			assert($query->execute($args),'Was not able to set token for user.'); // token valid for 10 days
			$subject = t('? invited you to Umbrella',$user->login);
			$url = getUrl('user',$this->id.'/edit?token='.$token);
			$text = t('Umbrella is an online project management system developed by Stephan Richter.')."\n".
					t("Click the following link and set a password to join:\n?",$url)."\n".
					t('Note: this link can only be used once!');
			send_mail($user->email, $this->email, $subject, $text);
			info('Email has been sent to ?',$this->email);
		}

		static function load($options = []){
			$db = get_or_create_db();

			$fields = User::table();
			if (empty($options['passwords']) || $options['passwords']!='load') unset($fields['pass']);
			$sql = 'SELECT '.implode(', ', array_keys($fields)).' FROM users';
			$where = [];
			$args = [];

			$single = false;
			if (isset($options['ids'])){
				$ids = $options['ids'];
				if (!is_array($ids) && $ids = [$ids]) $single = true;
				$qMarks = str_repeat('?,', count($ids)-1).'?';
				$where[] = 'id IN ('.$qMarks.')';
				$args = array_merge($args, $ids);
			}

			if (!empty($options['login'])){
				$where[] = '(login = ? OR email = ?)';
				$args[] = $options['login'];
				$args[] = $options['login'];
			}

			if (!empty($where)) $sql .= ' WHERE '.implode(' AND ', $where);

			$query = $db->prepare($sql);

			assert($query->execute($args),'Was not able to load tasks!');
			$rows = $query->fetchAll(INDEX_FETCH);

			$users = [];
			foreach ($rows as $id => $row){
				$user = new User();
				$user->patch($row);
				$user->id = $id;
				unset($user->dirty);
				if ($single) return $user;
				$users[$user->id] = $user;
			}
			if ($single) return null;
			return $users;
		}

		function lock(){
			$db = get_or_create_db();
			$query = $db->prepare('UPDATE users SET pass="" WHERE id = :id');
			assert($query->execute([':id'=>$this->id]));
			$query = $db->prepare('DELETE FROM service_ids_users WHERE user_id = :id');
			assert($query->execute([':id'=>$this->id]));
		}



		function login(){
			Token::getOrCreate($this);
		}

		static function require_login(){
			$url = getUrl('user','login?returnTo='.urlencode(location()));
			if (!isset($_SESSION['token']) || $_SESSION['token'] === null) redirect($url);

			$token = Token::load($_SESSION['token']);
			if ($token->user_id == null) redirect($url);
			if ($token != null) $user = User::load(['ids'=>$token->user_id]);
			if ($user == null) redirect($url);
			return $user;
		}

		function save(){
			if (!empty($this->id)) return $this->update();

			$db = get_or_create_db();

			if ($this->exists()) {
				error('User with this login name already existing!');
				return false;
			}

			$this->pass = sha1($this->pass); // TODO: better hashing
			$args = [];
			foreach (User::table() as $key => $definition){
				if (!isset($this->{$key})) continue;
				$args[$key] = $this->{$key};
			}

			$sql = 'INSERT INTO users ('.implode(', ', array_keys($args)).') VALUES (:'.implode(', :',array_keys($args)).' )';
			$query = $db->prepare($sql);
			assert ($query->execute($args),'Was not able to add user '.$this->login);
			info('User ? has been added',$this->login);
			return true;
		}

		static function table(){
			return [
					'id' => ['INTEGER','KEY'=>'PRIMARY'],
					'login' => ['VARCHAR'=>255,'NOT NULL'],
					'pass' => ['VARCHAR'=>255, 'NOT NULL'],
					'email' => ['VARCHAR'=>255],
					'theme'=> ['VARCHAR'=>50]
			];
		}

		function update(){
			if (!empty($this->new_pass)) $this->patch(['pass'=>sha1($this->new_pass)]);
			if (in_array('login', $this->dirty) && User::exists($this->login)){
				error('User with this login name already existing!');
				return $this;
			}

			$db = get_or_create_db();
			$sql = 'UPDATE users SET ';
			$args = [];
			foreach (array_keys(User::table()) as $key){
				if ($key == 'id' || !in_array($key, $this->dirty)) continue;
				$args[':'.$key] = $this->{$key};
				$sql .= $key.' = :'.$key.', ';
			}
			if (empty($args)) {
				info('Nothing changed in your account!');
				return $this;
			}

			$sql=rtrim($sql,', ').' WHERE id = :id';
			$args[':id'] = $this->id;
			$query = $db->prepare($sql);
			assert ($query->execute($args),'Was not able to update user '.$this->login);
			info('User data has been updated.');
			warn('If you changed your theme, you will have to log off an in again.');
			return $this;
		}
	}
?>
