<?PHP include '../bootstrap.php';

	const TIME_PERMISSION_OWNER = 1;
	const TIME_PERMISSION_PARTICIPANT = 2;

	const MODULE = 'Time';

	const TIME_STATUS_STARTED = 10; // time tracking started
	const TIME_STATUS_OPEN = 20; // time tracking concluded. not invoiced
	const TIME_STATUS_PENDING = 40; // time track used in unsent invoice
	const TIME_STATUS_COMPLETE = 60; // time track used in sent invoice
	const TIME_STATUS_CANCELED = 100;

	const TIME_STATES = [TIME_STATUS_CANCELED => 'canceled',
						 TIME_STATUS_PENDING => 'pending',
						 TIME_STATUS_OPEN => 'open',
						 TIME_STATUS_COMPLETE => 'completed',
						 TIME_STATUS_STARTED => 'started'
						];
	/** @var mixed $dummy used in loops */
	/** @var mixed $parsedown used in importing classes */
	/** @var mixed $services used in importing classes */
	/** @var mixed $TIME_PERMISSIONS */
	/** @var mixed $title used in importing classes */
	/** @var mixed $user used in importing classes */

	$TIME_PERMISSIONS = [TIME_PERMISSION_OWNER=>'owener',TIME_PERMISSION_PARTICIPANT=>'participant'];
	$title = t('Umbrella Timetracking');

	function get_or_create_db(){
		$table_filename = 'times.db';
		if (!file_exists('.db') && !mkdir('.db')) throw new Exception('Failed to create time/.db directory!');
		if (!is_writable('.db')) throw new Exception('Directory time/.db not writable!');
		if (!file_exists('.db/'.$table_filename)){
			$db = new PDO('sqlite:.db/'.$table_filename);

			$tables = [
				'times'=>Timetrack::table(),
				'task_times'=>Timetrack::task_table(),
			];

			foreach ($tables as $table => $fields){
				$sql = 'CREATE TABLE '.$table.' ( ';
				foreach ($fields as $field => $props){
					if ($field == 'UNIQUE') {
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
									if ($prop_v != 'PRIMARY') throw new Exception('Non-primary keys not implemented in time/controller.php!');
									$sql.= 'PRIMARY KEY '; break;
								default:
									$sql .= $prop_v.' ';
							}
						}
						$sql .= ", ";
					} else $sql .= $props.", ";
				}
				$sql = str_replace([' ,',', )'],[',',')'],$sql.')');
				if (!$db->query($sql)) throw new Exception('Was not able to create '.$table.' table in '.$table_filename.'!');
			}
		} else {
			$db = new PDO('sqlite:.db/'.$table_filename);
		}
		return $db;
	}

	class Timetrack extends UmbrellaObjectWithId{
		static function startNew(){
			global $user;
			return (new Timetrack())->patch(['user_id'=>$user->id,'subject'=>t('started timetrack'),'start_time'=>time(),'state'=>TIME_STATUS_STARTED])->save();
		}

		static function table(){
			return [
				'id'				=> ['INTEGER','KEY'=>'PRIMARY'],
				'user_id'			=> ['INT','NOT NULL'],
				'subject'			=> ['VARCHAR'=>255,'NOT NULL'],
				'description'		=> 'TEXT',
				'start_time'		=> 'TIMESTAMP',
				'end_time'			=> 'TIMESTAMP',
				'state'				=> ['INT','NOT NULL','DEFAULT 10'],
			];
		}

		static function task_table(){
			return [
				'task_id'		=> ['INT','NOT NULL'],
				'time_id'		=> ['INT','NOT NULL'],
				'PRIMARY KEY'	=> '(task_id, time_id)',
			];
		}

		static function load($options){
			global $parsedown, $user;

			$sql = 'SELECT id,* FROM times';
			$where = [];
			$args = [];
			$single = false;

			if (empty($options['task_ids'])) {
				$where[] = 'user_id = ?';
				$args[] = $user->id;
			}

			if (isset($options['ids'])){
				if (!is_array($options['ids'])) {
					$single = true;
					$options['ids'] = [$options['ids']];
				}
				$qMarks = str_repeat('?,', count($options['ids'])-1).'?';
				$where[] = 'id IN ('.$qMarks.')';
				$args = array_merge($args, $options['ids']);
			}

			if (isset($options['search'])){
				$key = '%'.$options['search'].'%';
				$where[] = '(subject LIKE ? OR description LIKE ?)';
				$args = array_merge($args,[$key,$key]);
			}

			if (isset($options['open']) && $options['open']){
				$where[] = 'end_time IS NULL';
			}

			if (!empty($options['task_ids'])){ // select times belonging to given tasks
				$ids = $options['task_ids'];
				if (!is_array($ids)) $ids = [$ids];
				$qMarks = str_repeat('?,', count($ids)-1).'?';
				$where[] = 'id IN (SELECT time_id FROM task_times WHERE task_id IN ('.$qMarks.'))';
				$args = array_merge($args, $ids);
			}

			if (!empty($where)) $sql .= ' WHERE '.implode(' AND ', $where);

			if (isset($options['order'])){
				switch ($options['order']){
					case 'description':
					case 'end_time':
					case 'start_time':
					case 'state':
					case 'subject':
						$sql .= ' ORDER BY '.$options['order'];
				}
			} else {
				$sql .= ' ORDER BY state ASC, end_time DESC';
			}

			/* Fetch times from time db. Collect task ids of times */
			$db = get_or_create_db();
			//debug(query_insert($sql, $args),1);
			$query = $db->prepare($sql);
			//debug($query,1);
			if (!$query->execute($args)) throw new Exception('Was not able to load times!');
			$rows = $query->fetchAll(INDEX_FETCH);

			$all_times = [];
			$task_ids = [];
			foreach ($rows as $row){
				$time = new Timetrack();
				$time->patch($row);
				unset($time->dirty);
				$task_ids = array_merge($task_ids,$time->task_ids());
				$all_times[$time->id] = $time;
			}
			$task_ids = array_unique($task_ids);

			$filter_by_project = !empty($options['project_id']);

			/* Fetch tasks referenced by times */
			$task_filter = ['ids'=>$task_ids];
			if ($filter_by_project) $task_filter['project_ids'] = $options['project_id'];
			$tasks_referenced_by_times = request('task','json',$task_filter);

			/* Fetch all projects and filter by referencing takss */
			$projects = $filter_by_project ? request('project','json',['id'=>$options['project_id']]) : request('project','json');

			$filtered_times = [];
			foreach ($all_times as $tid => $time){
				foreach ($time->tasks as $task_id => $dummy){
					if (!empty($tasks_referenced_by_times[$task_id])){
						$task = $tasks_referenced_by_times[$task_id];

						/* add project information to task */
						$task['project'] = $projects[$task['project_id']];

						/* add task to time */
						$time->tasks[$task_id] = $task;

						if ($single) return $time;
						/* add time to filtered list */
						$filtered_times[$tid] = $time;
					}
				}
				if (!$filter_by_project) {
					if ($single) return $time;
					$filtered_times[$tid] = $time;
				}
			}

			if ($single) return null;
			return $filtered_times;
		}

		function assign_task($task = null){
			if (isset($this->tasks[$task['id']])){
				info('The task "◊" already was assigned to this timetrack.',$task['name']);
			} else {
				$new_description = (empty($this->description) ? '':$this->description."\n\n") . $task['description'];
				$this->patch(['description'=>$new_description,'subject'=>$task['name']]);
				$this->tasks[$task['id']] = $task;
			}
			return $this;
		}

		function delete(){
			$db = get_or_create_db();
			$query = $db->prepare('DELETE FROM times WHERE id = :tid');
			if (!$query->execute([':tid'=>$this->id])) throw new Exception('Was not able to drop time entry!');
			$query = $db->prepare('DELETE FROM task_times WHERE time_id = :tid');
			if (!$query->execute([':tid'=>$this->id])) throw new Exception('Was not able to drop task_time entry!');
			unset($this->id);
		}

		function save(){
			global $user,$services;
			$db = get_or_create_db();
			if (isset($this->id)){
				if (!empty($this->dirty)){
					$sql = 'UPDATE times SET';
					$args = [':id'=>$this->id];
					foreach ($this->dirty as $field){
						$sql .= ' '.$field.'=:'.$field.',';
						$args[':'.$field] = $this->{$field};
					}
					$sql = rtrim($sql,',').' WHERE id = :id';
					$query = $db->prepare($sql);
					//debug(query_insert($query, $args),1);
					if (!$query->execute($args)) throw new Exception('Was no able to update time in database!');
					$this->dirty = [];
				}
			} else {
				$known_fields = array_keys(Timetrack::table());
				$fields = [];
				$args = [];
				foreach ($known_fields as $f){
					if (isset($this->{$f})){
						$fields[]=$f;
						$args[':'.$f] = $this->{$f};
					}
				}
				$sql = 'INSERT INTO times ( '.implode(', ',$fields).' ) VALUES ( :'.implode(', :',$fields).' )';
				$query = $db->prepare($sql);
				//debug(query_insert($query, $args),1);
				if (!$query->execute($args)) throw new Exception('Was not able to insert new timetrack');
				$this->id = $db->lastInsertId();
			}

			$query = $db->prepare('INSERT OR IGNORE INTO task_times (task_id, time_id) VALUES (:task, :time)');
			foreach ($this->tasks as $task_id => $dummy) if (!$query->execute([':task'=>$task_id,':time'=>$this->id])) throw new Exception('Was not able to assign task to timetrack.');

			return $this;
		}

		function state(){
			$t = TIME_STATES;
			return $t[$this->state];
		}

		function tasks(){
			if (empty($this->tasks)) $this->task_ids();
			$ids_of_missing_tasks = [];
			foreach ($this->tasks as $task_id => $task) {
				if (empty($task)) $ids_of_missing_tasks[] = $task_id;
			}
			if (!empty($ids_of_missing_tasks)){
				$tasks = request('task','json',['ids'=>$ids_of_missing_tasks]);
				foreach ($tasks as $task_id => $task) $this->tasks[$task_id] = $task;
			}
			return $this->tasks;
		}

		function task_ids(){
			if (empty($this->tasks)) {
				$sql = 'SELECT task_id FROM task_times WHERE time_id = :tid';
				$args = [':tid'=>$this->id];
				$db = get_or_create_db();
				$query = $db->prepare($sql);
				if (!$query->execute($args)) throw new Exception('Was not able to load task ids!');
				$this->tasks = $query->fetchAll(INDEX_FETCH);
			}
			return array_keys($this->tasks);
		}

		function update($subject = null,$description = null,$start = null,$end = null,$state = TIME_STATUS_OPEN){
			if ($subject == null) throw new Exception('Subject must not be null!');
			$start_time = strtotime($start);
			if ($start_time == false) throw new Exception('Invalid start time passed to time.update!');

			$end_time = strtotime($end);
			if (!$end_time) $end_time = null;
			if ($end_time === null) $state = TIME_STATUS_STARTED;
			$this->patch(compact(['subject','description','start_time','end_time','state']));
			return $this;
		}
	}

	class Formatter{
		function parse($text){
			return str_replace("\n", '<br/>', $text);
		}
	}

	$parsedown = null;
	if (file_exists('../lib/parsedown/Parsedown.php')){
		include '../lib/parsedown/Parsedown.php';
		$parsedown = Parsedown::instance();
	} else {
		$parsedown = new Formatter();
	}
?>
