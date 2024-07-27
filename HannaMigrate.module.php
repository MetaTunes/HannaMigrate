<?php namespace ProcessWire;

class HannaMigrate extends WireData implements Module {
	public static function getModuleInfo() {
		return array(
			'title' => 'Hanna Migrate',
			'summary' => 'Migrate Hanna codes between sites',
			'version' => '0.0.1',
			'author' => 'Mark Evens',
			'icon' => 'medium',
			'requires' => ['ProcessWire>=3.0.200'],
			'singular' => true,
		);
	}

	public function init() {

	}

	public function exportAll($migrationName = null) {
		$modules = $this->wire('modules');
		$hm = $modules->get('ProcessHannaCode');
		$ha = $hm->hannaCodes()->getAll();
		$exportAll = [];
		foreach($ha as $h) {
			$exportData = array(
				'name' => $h->name,
				'type' => $h->type,
				'code' => $hm->hannaCodes()->packCode($h->code, $h->attrs),
			);
			$exportAll[$h->name] = "!HannaCode:$h->name:" . base64_encode(json_encode($exportData)) . "/!HannaCode";
		}
		$directory = $this->migrationDirectory($migrationName);
		if(!is_dir($directory)) if(!wireMkdir($directory, true, "0777")) {          // wireMkDir recursive
			throw new WireException("Unable to create migration directory: $directory");
		}
		$exportJson = json_encode($exportAll);
		file_put_contents($directory . 'data.json', $exportJson);
	}

	public function importAll($migrationName = null) {
		$modules = $this->wire('modules');
		$session = $this->wire('session');
		$hm = $modules->get('ProcessHannaCode');

		// Get the migration .json file for this migration
		$directory = $this->migrationDirectory($migrationName);
		$dataFile = (file_exists($directory . 'data.json'))
			? file_get_contents($directory . 'data.json') : null;

		$dataArray = json_decode($dataFile);
		if(!$dataArray) {
			throw new WireException("Failed to json decode import file");
		}

		foreach($dataArray as $nameKey => $data) {
			bd([$nameKey, $data], 'nameKey, data');
			if(!preg_match('{!HannaCode:([^:]+):(.*?)/!HannaCode}s', $data, $matches)) {
				$session->error("Unrecognized Hanna Code format for $nameKey");
			}
			$name = $matches[1];
			$data = $matches[2];
			$data = base64_decode($data);
			if($data === false) {
				$session->error("Failed to base64 decode import data item $nameKey");
			}

			$data = json_decode($data, true);
			if($data === false) {
				$session->error("Failed to json decode import data item $nameKey");
			}

			if(empty($data['name']) || empty($data['code'])) {
				$session->error("Import data for $nameKey does not contain all required fields");
			}

			$h = $hm->hannaCodes()->get($name);
			if($h->id) {
				$session->error($this->_("Hanna Code with  name $nameKey already exists"));
			}

			$data['type'] = (int)$data['type'];
			if($data['type'] & HannaCode::typePHP && !$hm->hasPermission('hanna-code-php')) {
				throw new WireException("You don't have permission to add/edit PHP Hanna Codes");
			}

			$h = new HannaCode();
			$hm->wire($h);
			$h->name = $name;
			$h->type = $data['type'];
			$h->code = $data['code'];
			$h->modified = time();

			if($hm->hannaCodes()->save($h)) {
				$this->message($this->_('Imported Hanna Code:') . " $name");
			} else {
				$session->error("Error importing Hanna code for $nameKey");
			}
		}
		return '';

	}

	public function migrationDirectory($migrationName) {
		$modules = $this->wire('modules');
		$dbm = $modules->get('ProcessDbMigrate');
		if($dbm) {
			$migrationPage = ($migrationName) ? $dbm->migrations->get("template=$dbm->migrationTemplate, name=$migrationName") : new nullPage();
			/* @var $migrationPage DbMigrationPage */
			if($migrationPage->id > 0) {
				$directory = $dbm->migrationsPath;
				$migrationPath = $directory . $migrationPage->name . '/hanna-codes/';
			} else {
				$migrationPath = $dbm->migrationsPath . '/hanna-codes/';
			}
		} else {
			$migrationPath = $this->wire()->config->paths->assets . 'migrations/hanna-codes/';
		}
		return $migrationPath;
	}

}