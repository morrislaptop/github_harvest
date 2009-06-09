<?php
class GithubHarvestLogsController extends AppController
{
	var $name = 'GithubHarvestLogs';
	var $uses = array('UserBridge', 'GithubHarvest.GithubHarvestLog');
	var $helpers = array('Advform.Advform');
	
	function beforeFilter() {
		parent::beforeFilter();
		$this->Auth->allow('sync');
	}
	
	function sync()
	{
		// Get all users subscribed to this bridge.
		$conditions = array(
			'bridge_id' => 1
		);
		$bridges = $this->UserBridge->find('all', compact('conditions'));
		
		// Go through each user ;)
		foreach ($bridges as $bridge)
		{
			// Get github details.
			$github = unserialize($bridge['UserBridge']['app1data']);
			
			// Get harvest details
			$harvest = unserialize($bridge['UserBridge']['app2data']);
			
			// Get commits from github.
			$url = 'http://github.com/api/v2/json/commits/list/' . $github['username'] . '/' . $github['github_project'] . '/master';
			$params = array(
				'login' => $github['username'],
				'token' => $github['token']
			);
			$url .= '?' . http_build_query($params);
			$json = file_get_contents($url);
			$commits = json_decode($json);
			
			foreach ($commits->commits as $commit)
			{
				// extract the time out of the message
				$regexp = '/\[time:([^\]]+)\]/';
				preg_match($regexp, $commit->message, $matches);
				if ( empty($matches[1]) ) {
					continue;
				}
				
				// check if there has been a log made already.
				$conditions = array(
					'commit_id' => $commit->id,
					'entry_id NOT' => null
				);
				if ( $this->GithubHarvestLog->find('count', compact('conditions')) ) {
					continue;
				}
				
				$url = 'http://' . $harvest['domain'] . '.harvestapp.com/daily/add';
				$ch = curl_init($url);
				
				// construct data to send to harvest
				$data = array(
					'notes' => $commit->message,
					'hours' => $matches[1],
					'project_id' => $harvest['harvest_project'],
					'task_id' => $harvest['task'],
					'spent_at' => date('D, j M Y', strtotime($commit->committed_date))
				);
				
				$headers = array(
					'Content-Type: application/json',
					'Accept: application/json',
					'Authorization: Basic ' . base64_encode($harvest['email'] . ':' . $harvest['password'])
				);
				
				curl_setopt( $ch, CURLOPT_POST, 1 );
		        curl_setopt( $ch, CURLOPT_POSTFIELDS, json_encode($data) );
		        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
		        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
		        curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers); 
		        
		        $response = curl_exec($ch);
		        $response = json_decode($response);
		        
		        // chuck a log
		        $github_harvest_log = array(
		        	'user_bridge_id' => $bridge['UserBridge']['id'],
		        	'commit_id' => $commit->id,
		        	'entry_id' => $response->id
		        );
		        $this->GithubHarvestLog->save($github_harvest_log);
			}
		}
	}
	
	function view_log($user_bridge_id)
	{
		$conditions = compact('user_bridge_id');
		$this->paginate['order'] = 'created DESC';
		$logs = $this->paginate('GithubHarvestLog', $conditions);
		$this->helpers[] = 'Time';
		$this->set(compact('logs'));
	}
	
	function configure($user_bridge_id) 
	{
		if ( !empty($this->data) ) {
			$data = array(
				'name' => $this->data['UserBridge']['name'],
				'app1data' => serialize($this->data['Github']),
				'app2data' => serialize($this->data['Harvest']),
				'id' => $user_bridge_id
			);
			$this->UserBridge->id = $user_bridge_id;
			if ( $this->UserBridge->save($data) ) {
				$this->Session->setFlash('Configuration saved', 'default', array('class' => 'success'));
			}
			else {
				$this->Session->setFlash('Error saving configuration');
			}
		}
		
		// Get details.
		$this->UserBridge->contain();
		$user_bridge = $this->UserBridge->read(null, $user_bridge_id);
		$user_bridge['Github'] = unserialize($user_bridge['UserBridge']['app1data']);
		$user_bridge['Harvest'] = unserialize($user_bridge['UserBridge']['app2data']);
		
		// Set defaults.
		if ( empty($this->data) ) {
			$this->data = $user_bridge;
		}
		
		$this->_setFormData($user_bridge);
	}
	
	function _setFormData($user_bridge) {
		$github_projects = array();
		if ( $user_bridge['Github'] ) {
			$github_projects = $this->GithubHarvestLog->getGithubProjects($user_bridge['Github']['username'], $user_bridge['Github']['token']);
		}
		$harvest_projects = array();
		$tasks = array();
		if ( $user_bridge['Harvest'] ) {
			$harvest_projects = $this->GithubHarvestLog->getHarvestProjects($user_bridge['Harvest']['domain'], $user_bridge['Harvest']['email'], $user_bridge['Harvest']['password']);
			$tasks = $this->GithubHarvestLog->getHarvestTasks($user_bridge['Harvest']['domain'], $user_bridge['Harvest']['email'], $user_bridge['Harvest']['password']);
		}
		$this->set(compact('harvest_projects', 'github_projects', 'tasks'));
	}
	
	function github_projects() {
		$username = $this->params['url']['username'];
		$token = $this->params['url']['token'];
		$projects = $this->GithubHarvestLog->getGithubProjects($username, $token);
		$this->set(compact('projects'));
	}
	
	function harvest_projects_tasks() {
		$domain = $this->params['url']['domain'];
		$email = $this->params['url']['email'];
		$password = $this->params['url']['password'];
		$projects_tasks = array(
			'projects' => $this->GithubHarvestLog->getHarvestProjects($domain, $email, $password),
			'tasks' => $this->GithubHarvestLog->getHarvestTasks($domain, $email, $password)
		);
		$this->set(compact('projects_tasks'));
	}
}
?>