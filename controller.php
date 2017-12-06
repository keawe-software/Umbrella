<?php

function get_or_create_db(){
	if (!file_exists('db')) assert(mkdir('db'),'Failed to create company/db directory!');
	assert(is_writable('db'),'Directory company/db not writable!');
	if (!file_exists('db/companies.db')){
		$db = new PDO('sqlite:db/companies.db');
		$sql = 'CREATE TABLE companies ( ';
		foreach (Company::fields() as $field => $props){
			$sql .= $field . ' ';
			if (is_array($props)){
				foreach ($props as $prop_k => $prop_v){
					switch (true){
						case $prop_k==='VARCHAR':
							$sql.= 'VARCHAR('.$prop_v.') '; break;
						case $prop_k==='DEFAULT':
							$sql.= 'DEFAULT "'.$prop_v.'" '; break;
						case $prop_k==='KEY':
							assert($prop_v === 'PRIMARY','Non-primary keys not implemented in company/controller.php!');
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
		assert($db->query($sql),'Was not able to create companies table in companies.db!');
		assert($db->query('CREATE TABLE companies_users (company_id INT NOT NULL, user_id INT NOT NULL)'),
			'Was not able to create table companies_users.');
	} else {
		$db = new PDO('sqlite:db/companies.db');
	}
	return $db;

}

class Company {
	function __construct($name = null){
		assert($name !== null,'Company name must not be empty');
		$this->name = $name;
	}
	
	static function fields(){
		return [
			'id'					=> ['INTEGER','KEY'=>'PRIMARY'],
			'address'				=> 'TEXT',
			'bank_account'			=> 'TEXT',
			'court'					=> 'TEXT',
			'currency'				=> ['VARCHAR'=>10,'DEFAULT'=>'€'],
			'decimals'				=> ['INT','NOT NULL','DEFAULT'=>'2'],
			'decimal_separator'		=> ['VARCHAR'=>10,'DEFAULT'=>','],
			'logo'					=> 'TEXT',
			'name'					=> ['VARCHAR'=>255, 'NOT NULL'],
			'tax_number'			=> ['VARCHAR'=>255],
			'thousands_separator'	=> ['VARCHAR'=>10,'DEFAULT'=>'.'],
		];
	}

	function patch($data = array()){
		if (!isset($this->dirty)) $this->dirty = [];
		foreach ($data as $key => $val){
			if ($key === 'id' && isset($this->id)) continue;
			if (isset($this->{$key}) && $this->{$key} != $val) $this->dirty[] = $key;
			$this->{$key} = $val;
		}
	}

	public function save(){
		global $user;
		$db = get_or_create_db();
		if (isset($this->id)){
			if (!empty($this->dirty)){
				$sql = 'UPDATE companies SET';
				$args = [':id'=>$this->id];
				foreach ($this->dirty as $field){
					$sql .= ' '.$field.'=:'.$field.',';
					$args[':'.$field] = $this->{$field};
				}
				$sql = rtrim($sql,',').' WHERE id = :id';
				$query = $db->prepare($sql); 
				assert($query->execute($args),'Was no able to update company in database!');
				redirect('../index');
			}
		} else {
			$known_fields = array_keys(Company::fields());
			$fields = [];
			$args = [];
			foreach ($known_fields as $f){
				if (isset($this->{$f})){
					$fields[]=$f;
					$args[':'.$f] = $this->{$f};		
				}
			}
			$query = $db->prepare('INSERT INTO companies ( '.implode(', ',$fields).' ) VALUES ( :'.implode(', :',$fields).' )');
			assert($query->execute($args),'Was not able to insert new company');

			$this->id = $db->lastInsertId();
			$query = $db->prepare('INSERT INTO companies_users (company_id, user_id) VALUES (:cid, :uid);');
			assert($query->execute([':cid'=>$this->id, ':uid'=>$user->id]),'Was no able to assign you to the new company!');
			redirect('index');
		}
	}

	static function load($ids = null){
		global $user;
		$db = get_or_create_db();

		$sql = 'SELECT * FROM companies WHERE id IN (SELECT company_id FROM companies_users WHERE user_id = ?)';
		$args = [];
		if ($ids !== null){
			if (!is_array($ids)) $ids = [ $ids ];
			$qmarks = str_repeat('?,', count($ids) - 1) . '?';
			$sql .= ' AND id IN ('.$qmarks.')';
			$args = $ids;			
		}
		array_unshift($args,$user->id);
		$query = $db->prepare($sql);
		assert($query->execute($args),'Was not able to load companies!');
		$rows = $query->fetchAll(PDO::FETCH_ASSOC);
		$companies = [];
		foreach ($rows as $row){
			$company = new Company($row['name']);
			$company->patch($row);
			$companies[$row['id']] = $company;
		}
		return $companies;
	}
	
	public function users(){
		if (!isset($this->users)){
			$db = get_or_create_db();
			$query = $db->prepare('SELECT user_id FROM companies_users WHERE company_id = :id');
			assert($query->execute([':id'=>$this->id]),'Was not able to load list of associated users!');
			$this->users = array_keys($query->fetchAll(INDEX_FETCH));			
		}
		return $this->users;
	}
	
	public function add_user($user_id = null){
		assert($user_id !== null,'Trying to assign "null" as user to company! Aborting');
		$db = get_or_create_db();
		$query = $db->prepare('INSERT INTO companies_users (company_id, user_id) VALUES (:cid, :uid)');
		assert($query->execute([':cid'=>$this->id,':uid'=>$user_id]),'Was not able to assign user in database!');
	}
}

?>