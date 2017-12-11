<?php

class InvoicePosition{
	static function table(){	
		return [
			'invoice_id'	=> ['INTEGER','NOT NULL'],
			'pos'		=> ['INTEGER','NOT NULL'],
			'item_code'	=> ['VARCHAR'=>50],
			'amount'	=> ['INTEGER','NOT NULL','DEFAULT'=>1],
			'unit'		=> ['VARCHAR'=> 12],
			'title'		=> ['VARCHAR'=>255],
			'description'	=> 'TEXT',
			'single_price'	=> 'INTEGER',
			'tax'		=> 'INTEGER',
			'time_id'	=> 'INTEGER',
		];
	}

	function __construct(Invoice $invoice){
		$db = get_or_create_db();

		$query = $db->prepare('SELECT max(pos) AS pos FROM invoice_positions WHERE invoice_id = :iid');
		assert($query->execute([':iid'=>$invoice->id]),'Was not able to read invoice position table');
		$this->pos = reset($query->fetch(PDO::FETCH_ASSOC)) +1;
		$this->invoice_id = $invoice->id;
		
	}
	
	function patch($data = array(),$set_dirty = true){
		if (!isset($this->dirty)) $this->dirty = [];
		foreach ($data as $key => $val){
			if (!isset($this->{$key}) || $this->{$key} != $val) $this->dirty[] = $key;
			$this->{$key} = $val;
		}
		return $this;
	}
	
	public function save(){
		$db = get_or_create_db();
		$query = $db->prepare('SELECT count(*) AS count FROM invoice_positions WHERE invoice_id = :iid AND pos = :pos');
		assert($query->execute([':iid'=>$this->invoice_id,':pos'=>$this->pos]),'Was not able to read from invoice positions table!');
		$count = reset($query->fetch(PDO::FETCH_ASSOC));
		if ($count == 0){ // new!
			$known_fields = array_keys(InvoicePosition::table());
			$fields = [];
			$args = [];
			foreach ($known_fields as $f){
				if (isset($this->{$f})){
					$fields[]=$f;
					$args[':'.$f] = $this->{$f};
				}
			}
			$sql = 'INSERT INTO invoice_positions ( '.implode(', ',$fields).' ) VALUES ( :'.implode(', :',$fields).' )';
			$query = $db->prepare($sql);
			assert($query->execute($args),'Was not able to insert new row into invoice_positions');
		} else {
			if (!empty($this->dirty)){
				$sql = 'UPDATE invoice_positions SET';
				$args = [':iid'=>$this->invoice_id,':pos'=>$this->pos];
				foreach ($this->dirty as $field){
					$sql .= ' '.$field.'=:'.$field.',';
					$args[':'.$field] = $this->{$field};
				}
				$this->dirty = [];
				$sql = rtrim($sql,',').' WHERE invoice_id = :iid AND pos = :pos';
				$query = $db->prepare($sql);
				assert($query->execute($args),'Was no able to update invoice_positions in database!');
			}
		}
		return $this;
	}
	
	static function load($invoice){
		$db = get_or_create_db();
		$sql = 'SELECT pos,* FROM invoice_positions WHERE invoice_id = :iid ORDER BY pos';
		$args = [':iid'=>$invoice->id];
		$query = $db->prepare($sql);
		assert($query->execute($args),'Was not able to load invoie positions.');
		$rows = $query->fetchAll(INDEX_FETCH);
		$result = [];
		foreach ($rows as $pos => $row){
			$invoicePosition = new InvoicePosition($invoice);
			$invoicePosition->patch($row);
			$invoicePosition->dirty = [];
			$result[$pos] = $invoicePosition;
		}
		return $result;
	}
	
	public function delete(){
		global $services;
		$db = get_or_create_db();
		$query = $db->prepare('DELETE FROM invoice_positions WHERE invoice_id = :iid AND pos = :pos');
		assert($query->execute([':iid'=>$this->invoice_id,':pos'=>$this->pos]),'Was not able to remove entry from invoice positions table!');
		
		$query = $db->prepare('UPDATE invoice_positions SET pos = pos-1 WHERE invoice_id = :iid AND pos > :pos');
		assert($query->execute([':iid'=>$this->invoice_id,':pos'=>$this->pos]));
		if (isset($this->time_id) && $this->time_id !== null && isset($services['time'])){
			request('time','update_state',['OPEN'=>$this->time_id]);
		}

		return $this;
	}
	
}

function get_or_create_db(){
	if (!file_exists('db')) assert(mkdir('db'),'Failed to create invoice/db directory!');
	assert(is_writable('db'),'Directory invoice/db not writable!');
	if (!file_exists('db/invoices.db')){
		$db = new PDO('sqlite:db/invoices.db');
		
		$tables = [
			'invoices'=>Invoice::table(),
			'invoice_positions'=>InvoicePosition::table(),
			'company_settings'=>CompanySettings::table(),
			'templates'=>Template::table(),
		];
		
		foreach ($tables as $table => $fields){		
			$sql = 'CREATE TABLE '.$table.' ( ';
			foreach ($fields as $field => $props){
				$sql .= $field . ' ';
				if (is_array($props)){
					foreach ($props as $prop_k => $prop_v){
						switch (true){
							case $prop_k==='VARCHAR':
								$sql.= 'VARCHAR('.$prop_v.') '; break;
							case $prop_k==='DEFAULT':
								$sql.= 'DEFAULT '.($prop_v === null)?'NULL ':('"'.$prop_v.'" '); break;
							case $prop_k==='KEY':
								assert($prop_v === 'PRIMARY','Non-primary keys not implemented in invoice/controller.php!');
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
		}
	} else {
		$db = new PDO('sqlite:db/invoices.db');
	}
	return $db;

}

class CompanySettings{
	function __construct($company_id){
		$this->company_id = $company_id;
		$this->invoice_prefix = 'R';
		$this->invoice_suffix = '';
		$this->invoice_number = 1;
		$this->default_invoice_header = 'We allow us to charge the following items:';
		$this->default_invoice_footer = 'Due and payable without discounts within 30 days of the invoice date.';
	}
	
	static function load($company){
		$company_id = is_array($company) ? $company['id'] : $company;
		$companySettings = new CompanySettings($company_id);
		$db = get_or_create_db();
		$sql = 'SELECT * FROM company_settings WHERE company_id = :cid';
		$args = [':cid'=>$company_id];
		$query = $db->prepare($sql);
		assert($query->execute($args),'Was not able to load settings for the selected company.');
		$rows = $query->fetchAll(PDO::FETCH_ASSOC);
		foreach ($rows as $row) $companySettings->patch($row);
		$companySettings->dirty = [];
		return $companySettings;		
	}
	
	function patch($data = array()){
		if (!isset($this->dirty)) $this->dirty = [];
		foreach ($data as $key => $val){
			if (!isset($this->{$key}) || $this->{$key} != $val) $this->dirty[] = $key;
			$this->{$key} = $val;
		}
	}
	
	function applyTo(Invoice $invoice){
		$invoice->company_id = $this->company_id;
		$invoice->number = $this->invoice_prefix.$this->invoice_number.$this->invoice_suffix;
		$this->patch(['invoice_number'=>$this->invoice_number+1]);
		$invoice->head = $this->default_invoice_header;
		$invoice->footer = $this->default_invoice_footer;
	}
	
	static function table(){
		return [
			'company_id'				=> ['INTEGER','KEY'=>'PRIMARY'],
			'default_invoice_header' 	=> 'TEXT',
			'default_invoice_footer'	=> 'TEXT',
			'invoice_prefix'			=> ['TEXT','DEFAULT'=>'R'],
			'invoice_suffix'			=> ['TEXT','DEFAULT'=>null],
			'invoice_number'			=> ['INT','NOT NULL'],
		];
	}
	
	public function save(){
		$db = get_or_create_db();
		$query = $db->prepare('SELECT count(*) AS count FROM company_settings WHERE company_id = :cid');
		assert($query->execute([':cid'=>$this->company_id]),'Was not able to count settings for company!');
		$count = reset($query->fetch(PDO::FETCH_ASSOC));
		if ($count == 0){ // new!
			$known_fields = array_keys(CompanySettings::table());
			$fields = [];
			$args = [];
			foreach ($known_fields as $f){
				if (isset($this->{$f})){
					$fields[]=$f;
					$args[':'.$f] = $this->{$f};
				}
			}
			$sql = 'INSERT INTO company_settings ( '.implode(', ',$fields).' ) VALUES ( :'.implode(', :',$fields).' )';
			$query = $db->prepare($sql);
			assert($query->execute($args),'Was not able to insert new row into company_settings');
		} else {
			if (!empty($this->dirty)){
				$sql = 'UPDATE company_settings SET';
				$args = [':cid'=>$this->company_id];
				foreach ($this->dirty as $field){
					$sql .= ' '.$field.'=:'.$field.',';
					$args[':'.$field] = $this->{$field};
				}
				$sql = rtrim($sql,',').' WHERE company_id = :cid';
				$query = $db->prepare($sql);
				assert($query->execute($args),'Was no able to update company_settings in database!');
			}
		}
	}
	
	function updateFrom(Invoice $invoice){
		$data = [
			'default_invoice_header' => $invoice->head,
			'default_invoice_footer' => $invoice->footer,
			'invoice_prefix' => preg_replace('/[1-9]+\w*$/', '', $invoice->number),
			'invoice_suffix' => preg_replace('/^\w*\d+/', '', $invoice->number),
		];
		$this->patch($data);
		$this->save();
	}
}

class Invoice {
	const STATE_NEW = 1;
	const STATE_SENT = 2;
	const STATE_DELAYED = 3;
	const STATE_PAYED = 4;
	const STATE_ERROR = 99;
	
	function __construct(array $company = []){
		if (isset($company['id'])) $this->company_id = $company['id'];
		if (isset($company['currency'])) $this->currency = $company['currency'];
		$this->state = static::STATE_NEW;
	}

	static function states(){
		return [
			static::STATE_NEW => 'new',
			static::STATE_SENT => 'sent',
			static::STATE_DELAYED => 'delayed',
			static::STATE_PAYED => 'payed',
			static::STATE_ERROR => 'error',
		];
	}
	
	static function table(){
		return [
			'id'				=> ['INTEGER','KEY'=>'PRIMARY'],
			'date'				=> ['TIMESTAMP','NOT NULL'],
			'number'			=> ['TEXT','NOT NULL'],
			'delivery_date'		=> ['VARCHAR'=>100],
			'head'				=> 'TEXT',
			'footer'			=> 'TEXT',
			'company_id'		=> ['INT','NOT NULL'],
			'currency'			=> ['VARCHAR'=>10,'NOT NULL'],
			'template_id'		=> ['INT','NOT NULL'],
			'state'				=> ['INT','NOT NULL','DEFAULT'=>1],
			'sender'			=> ['TEXT','NOT NULL'],
			'tax_number'		=> ['VARCHAR'=>255],
			'bank_account'		=> 'TEXT',
			'court'				=> 'TEXT',
			'customer'			=> 'TEXT',
			'customer_number'	=> 'INT',
			'customer_email'	=> ['VARCHAR'=>255]
		];
	}

	function patch($data = array()){
		if (!isset($this->dirty)) $this->dirty = [];
		foreach ($data as $key => $val){
			if ($key === 'id' && isset($this->id)) continue;
			if (!isset($this->{$key}) || $this->{$key} != $val) $this->dirty[] = $key;
			$this->{$key} = $val;
		}
	}
	
	static function load($ids = null){
		$db = get_or_create_db();
		
		$user_companies = request('company','json_list');
		$user_company_ids = array_keys($user_companies);

		$args = [];
		if ($user_company_ids !== null){
			if (!is_array($user_company_ids)) $user_company_ids = [ $user_company_ids ];
			$qmarks = str_repeat('?,', count($user_company_ids) - 1) . '?';
			$args = $user_company_ids;			
		}
		
		$sql = 'SELECT * FROM invoices WHERE company_id IN ('.$qmarks.')';
		
		if ($ids !== null){
			if (!is_array($ids)) $ids = [ $ids ];
			$qmarks = str_repeat('?,', count($ids) - 1) . '?';
			$args = array_merge($args, $ids);
			$sql .= ' AND id IN ('.$qmarks.')';
		}		
		
		$query = $db->prepare($sql);
		assert($query->execute($args),'Was not able to load invoices!');
		$rows = $query->fetchAll(PDO::FETCH_ASSOC);
		$invoices = [];
		foreach ($rows as $row){
			$invoice = new Invoice();
			$invoice->patch($row,false);
			$invoice->dirty = [];
			$invoices[$row['id']] = $invoice;
		}
		return $invoices;
	}
	
	public function save(){
		global $user,$services;
		$db = get_or_create_db();
		if (isset($this->id)){
			if (!empty($this->dirty)){
				$sql = 'UPDATE invoices SET';
				$args = [':id'=>$this->id];
				foreach ($this->dirty as $field){
					$sql .= ' '.$field.'=:'.$field.',';
					$args[':'.$field] = $this->{$field};
				}
				$sql = rtrim($sql,',').' WHERE id = :id';
				$query = $db->prepare($sql);
				assert($query->execute($args),'Was no able to update invoice in database!');
				if (in_array('state',$this->dirty) && isset($services['time'])){
					$time_ids = [];
					foreach ($this->positions() as $position){
						if (isset($position->time_id) && $position->time_id !== null) $time_ids[] = $position->time_id;
					}
					if (!empty($time_ids)){
						$state = null;
						switch ($this->state){
							case static::STATE_NEW:
							case static::STATE_SENT:
							case static::STATE_DELAYED:
								$state = 'PENDING';
								break;
							case static::STATE_PAYED:
								$state = 'COMPLETED';
								break;
							default:
								$state = 'OPEN';
						}
						request('time','update_state',[$state=>implode(',',$time_ids)]);
					}
				}
				$this->dirty = [];
			}
		} else {
			$this->date = time();
			$known_fields = array_keys(Invoice::table());
			$fields = [];
			$args = [];
			foreach ($known_fields as $f){
				if (isset($this->{$f})){
					$fields[]=$f;
					$args[':'.$f] = $this->{$f};
				}
			}			
			$sql = 'INSERT INTO invoices ( '.implode(', ',$fields).' ) VALUES ( :'.implode(', :',$fields).' )';
			$query = $db->prepare($sql);
			assert($query->execute($args),'Was not able to insert new invoice');	
			$this->id = $db->lastInsertId();
		}
	}
	
	public function date(){
		return date('d.m.Y',$this->date);
	}
	
	public function delivery_date(){
		if (!isset($this->delivery_date) || $this->delivery_date === null) return '';
		return $this->delivery_date;
	}
	
	
	public function state(){
		if (array_key_exists($this->state, Invoice::states())) return Invoice::states()[$this->state];
		return t('unknown state');
	}
	
	public function customer_short(){
		return reset(explode("\n",$this->customer));
	}
	
	public function positions(){
		if (!isset($this->positions)) $this->positions = InvoicePosition::load($this);
		return $this->positions;
	}
	
	function add_position($code,$title,$description,$amount,$unit,$price,$tax){
		$db = get_or_create_db();
	
		$query = $db->prepare('SELECT MAX(pos) FROM invoice_positions WHERE invoice_id = :id');
		assert($query->execute(array(':id'=>$invoice_id)),'Was not able to get last invoice position!');
		$row = $query->fetch(PDO::FETCH_COLUMN);
		$pos = ($row === null)?1:$row+1;
	
		$query = $db->prepare('INSERT INTO invoice_positions (invoice_id, pos, item_code, amount, unit, title, description, single_price, tax) VALUES (:id, :pos, :code, :amt, :unit, :ttl, :desc, :price, :tax)');
		$args = array(':id'=>$invoice_id,':pos'=>$pos,':code'=>$code,':amt'=>$amount,':unit'=>$unit,':ttl'=>$title,':desc'=>$description,':price'=>$price,':tax'=>$tax);
		assert($query->execute($args),'Was not able to store new postion for invoice '.$invoice_id.'!');
	}
	
	function elevate($position_number){
		if ($position_number<2) return;
		$positions = $this->positions();
		$a = $this->positions[$position_number]->delete();
		$b = $this->positions[$position_number-1]->delete();
		$a->patch(['pos'=>$position_number-1])->save();
		$b->patch(['pos'=>$position_number])->save();
	}
	
	function sum(){
		$sum = 0;
		foreach ($this->positions() as $position){
			$pos = $position->amount * $position->single_price;
			$sum += $pos + ($pos*$position->tax/100.0);
		}
		return round($sum/100.0,2);
	}
	
	function template(){
		if (!isset($this->template_id) || $this->template_id === null || $this->template_id < 1) return null;
		$templates = Template::load($this->company_id);
		if (!isset($templates[$this->template_id])) return null;
		return $templates[$this->template_id];
	}
}

class Template{
	static function table(){
		return [
			'id'						=> ['INTEGER','KEY'=>'PRIMARY'],
			'company_id'			 	=> ['INT','NOT NULL'],
			'name'						=> ['VARCHAR'=>255,'NOT NULL'],
			'template'					=> 'BLOB',
		];
	}
	
	static function load($company_id){
		$templates = [];
		$db = get_or_create_db();
		$sql = 'SELECT * FROM templates WHERE company_id = :cid';
		$args = [':cid'=>$company_id];
		$query = $db->prepare($sql);
		assert($query->execute($args),'Was not able to templates for the selected company.');
		$rows = $query->fetchAll(PDO::FETCH_ASSOC);
		foreach ($rows as $row) {
			$template = new Template();
			$template->patch($row);
			$template->dirty = [];			
			$templates[$template->id] = $template;
		}
		return $templates;
	}
	
	function __construct($file_path = null){
		if ($file_path) $this->template = request('files','download?file='.$file_path,null,false,NO_CONVERSSION);
	}
		
	function patch($data = array()){
		if (!isset($this->dirty)) $this->dirty = [];
		foreach ($data as $key => $val){
			if ($key === 'id' && isset($this->id)) continue;
			if (!isset($this->{$key}) || $this->{$key} != $val) $this->dirty[] = $key;
			$this->{$key} = $val;
		}
	}
	
	public function save(){
		global $user;
		$db = get_or_create_db();
		if (isset($this->id)){
			if (!empty($this->dirty)){
				$sql = 'UPDATE templates SET';
				$args = [':id'=>$this->id];
				foreach ($this->dirty as $field){
					$sql .= ' '.$field.'=:'.$field.',';
					$args[':'.$field] = $this->{$field};
				}
				$sql = rtrim($sql,',').' WHERE id = :id';
				$query = $db->prepare($sql);
				assert($query->execute($args),'Was no able to update template in database!');
			}
		} else {
			$known_fields = array_keys(Template::table());
			$fields = [];
			$args = [];
			foreach ($known_fields as $f){
				if (isset($this->{$f})){
					$fields[]=$f;
					$args[':'.$f] = $this->{$f};
				}
			}
			$sql = 'INSERT INTO templates ( '.implode(', ',$fields).' ) VALUES ( :'.implode(', :',$fields).' )';
			$query = $db->prepare($sql);
			assert($query->execute($args),'Was not able to insert new template');
			$this->id = $db->lastInsertId();
		}
	}
	
	public function file(){
		$tempfile = tempnam('/tmp','template_');
		$f = fopen($tempfile,'w');
		fwrite($f,$this->template);
		fclose($f);
		return $tempfile;
	}
}
?>
