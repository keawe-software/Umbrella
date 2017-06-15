<?php

	const TASK_PERMISSION_OWNER = 1;
	const TASK_PERMISSION_PARTICIPANT = 2;
	
	const TASK_STATUS_CANCELED = 0;
	const TASK_STATUS_OPEN = 1;
	const TASK_STATUS_CLOSED = 2;

	function get_or_create_db(){
		if (!file_exists('db')){
			assert(mkdir('db'),'Failed to create task/db directory!');
		}
		assert(is_writable('db'),'Directory task/db not writable!');
		if (!file_exists('db/tasks.db')){
			$db = new PDO('sqlite:db/tasks.db');
			$db->query('CREATE TABLE tasks (id INTEGER PRIMARY KEY, project_id INTEGER NOT NULL, parent_task_id INTEGER DEFAULT NULL, name VARCHAR(255) NOT NULL, description TEXT, status INT DEFAULT 1);');
			$db->query('CREATE TABLE tasks_users (task_id INT NOT NULL, user_id INT NOT NULL, permissions INT DEFAULT 1, PRIMARY KEY(task_id, user_id));');
		} else {
			$db = new PDO('sqlite:db/tasks.db');
		}
		return $db;
	}

	function get_task_list($order = null){
		global $user;
		debug($order);
		$db = get_or_create_db();
		$sql = 'SELECT * FROM tasks WHERE id IN (SELECT task_id FROM tasks_users WHERE user_id = :uid)';
		$args = array(':uid'=>$user->id);		
		if ($order !== null) {
			$sql.= ' ORDER BY name';
			//$args[':order'] = $order;
		}
		debug($sql);
		$query = $db->prepare($sql);		
		assert($query->execute($args),'Was not able to request project list!');
		$results = $query->fetchAll(PDO::FETCH_ASSOC);
		return $results;
	}

	function add_task($name,$description = null,$project_id = null,$parent_task_id = null){
		global $user;
		$db = get_or_create_db();
		assert($name !== null && trim($name) != '','Task name must not be empty or null!');
		assert(is_numeric($project_id),'Task must reference project!');
		$query = $db->prepare('INSERT INTO tasks (name, project_id, parent_task_id, description, status) VALUES (:name, :pid, :parent, :desc, :state);');		
		assert($query->execute(array(':name'=>$name,':pid'=>$project_id, ':parent'=>$parent_task_id,':desc'=>$description,':state'=>TASK_STATUS_OPEN)),'Was not able to create new task entry in database');
		$task_id = $db->lastInsertId();
		add_user_to_task($task_id,$user->id,TASK_PERMISSION_OWNER);
	}
	
	function load_task($id = null){
		assert($id !== null,'No task id passed to load_task!');
		assert(is_numeric($id),'Invalid task id passed to load_task!');
		$db = get_or_create_db();
		$query = $db->prepare('SELECT * FROM tasks WHERE id = :id');
		assert($query->execute(array(':id'=>$id)));
		$results = $query->fetchAll(PDO::FETCH_ASSOC);
		return $results[0];
	}
	
	function load_users($id = null){
		assert(is_numeric($id),'Invalid task id passed to load_users!');
		$db = get_or_create_db();
		$query = $db->prepare('SELECT * FROM tasks_users WHERE task_id = :id');
		assert($query->execute(array(':id'=>$id)));
		$results = $query->fetchAll(PDO::FETCH_ASSOC);
		return $results;		
	}
	
	function add_user_to_task($task_id = null,$user_id = null,$permission = null){
		assert(is_numeric($task_id),'task id must be numeric, is '.$task_id);
		assert(is_numeric($user_id),'user id must be numeric, is '.$user_id);
		assert(is_numeric($permission),'permission must be numeric, is '.$permission);
		$db = get_or_create_db();
		$query = $db->prepare('INSERT INTO tasks_users (task_id, user_id, permissions) VALUES (:tid, :uid, :perm);');
		assert($query->execute(array(':tid'=>$task_id,':uid'=>$user_id, ':perm'=>$permission)),'Was not able to assign task to user!');
	}
	
	function find_project($task_id){
		$db = get_or_create_db();
		$query = $db->prepare('SELECT project_id, parent_task_id FROM tasks WHERE id = :id;');
		assert($query->execute(array(':id'=>$task_id)),'Was not able to read tasks parent or project');
		$data = reset($query->fetchAll(PDO::FETCH_ASSOC));
		if (isset($data['project_id'])) return $data['project_id'];
		if (isset($data['parent_task_id'])) return find_project($data['parent_task_id']);
		return null;
	}
?>
