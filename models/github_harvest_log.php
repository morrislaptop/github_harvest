<?php
class GithubHarvestLog extends AppModel
{
	var $name = 'GithubHarvestLog';
	var $useTable = 'sync_logs';

	var $belongsTo = array(
		'UserBridge'
	);
	var $actsAs = array(
		'SyncLog'
	);

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
			$github = $bridge['UserBridge']['app1data'];

			// Get harvest details
			$harvest = $bridge['UserBridge']['app2data'];

			// Get commits from github.
			$url = 'http://github.com/api/v2/json/commits/list/' . $github['username'] . '/' . $github['github_project'] . '/master';
			$params = array(
				'login' => $github['username'],
				'token' => $github['token']
			);
			$params = array_filter($params);
			$url .= '?' . http_build_query($params);
			$ch = curl_init($url);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
			$json = curl_exec($ch);
			$commits = json_decode($json);

			foreach ($commits->commits as $commit)
			{
				// extract the time out of the message
				$regexp = '/\[time:([^\]]+)\]/';
				preg_match($regexp, $commit->message, $matches);
				if ( empty($matches[1]) ) {
					continue;
				}
				$hours = $matches[1];

				// check if there has been a log made already.
				$conditions = array(
					'app1_id' => $commit->id,
					'app2_id NOT' => null
				);
				if ( $this->find('count', compact('conditions')) ) {
					continue;
				}
				
				// extract the task out of the message
				$regexp = '/\[task:([^\]]+)\]/';
				preg_match($regexp, $commit->message, $matches);
				$task = null;
				if ( $matches[1] ) {
					// try and find the task id.
					foreach ($harvest['harvest_tasks'] as $task) {
						if ( $matches[1] == $task->name ) {
							$task = $task->id;
						}
					}
				}
				// if we didnt find a task, use the defualt
				if ( !$task ) {
					$task = $harvest['harvest_task'];
				}

				$url = 'http://' . $harvest['domain'] . '.harvestapp.com/daily/add';
				$ch = curl_init($url);

				// construct data to send to harvest
				$data = array(
					'notes' => $commit->message,
					'hours' => $hours,
					'project_id' => $harvest['harvest_project'],
					'task_id' => $task,
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
		        	'app1_id' => $commit->id,
		        	'app2_id' => $response->id,
		        	'app1data' => $commit,
		        	'app2data' => $response
		        );
		        $this->save($github_harvest_log);
			}
		}
	}
	
	/**
	*/
	function refreshRemoteData() 
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
			$github = $bridge['UserBridge']['app1data'];
			$github_projects = $this->getGithubProjects($github['username'], $github['token']);
			
			// Get harvest details
			$harvest = $bridge['UserBridge']['app2data'];
			$harvest_projects = $this->getHarvestProjects($harvest['domain'], $harvest['email'], $harvest['password']);
			$harvest_tasks = $this->getHarvestTasks($harvest['domain'], $harvest['email'], $harvest['password']);
			
			// save into bridge.
			$bridge['UserBridge']['app1data']['github_projects' ] = $github_projects;
			$bridge['UserBridge']['app2data']['harvest_projects' ] = $harvest_projects;
			$bridge['UserBridge']['app2data']['harvest_tasks' ] = $harvest_tasks;
			$this->UserBridge->save($bridge);
		}
	}

	function getGithubProjects($username, $token)
	{
		if ( !$username ) {
			return array();
		}

		// Get repos from github.
		$url = 'http://github.com/api/v2/json/repos/show/' . $username;
		$params = array(
			'login' => $username,
			'token' => $token
		);
		$params = array_filter($params);
		$url .= '?' . http_build_query($params);
		$json = file_get_contents($url);
		$json = json_decode($json);
		return $json->repositories;
	}

	function getHarvestProjects($domain, $email, $password)
	{
		if ( !$domain || !$email || !$password ) {
			return array();
		}

		$url = 'http://' . $domain . '.harvestapp.com/projects';
		$ch = curl_init($url);
		$headers = array(
			'Content-Type: application/xml',
			'Accept: application/xml',
			'Authorization: Basic ' . base64_encode($email . ':' . $password)
		);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1 );
		#curl_setopt($ch, CURLOPT_HEADER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		$response = curl_exec($ch);
		$xml = simplexml_load_string($response);
		
		// convert into json and back so we have a permanent instance of the data.
		$json = json_encode($xml);
		$projects = json_decode($json);
		
		return $projects->project;
	}

	function getHarvestTasks($domain, $email, $password)
	{
		if ( !$domain || !$email || !$password ) {
			return array();
		}

		$url = 'http://' . $domain . '.harvestapp.com/tasks';
		$ch = curl_init($url);
		$headers = array(
			'Content-Type: application/xml',
			'Accept: application/xml',
			'Authorization: Basic ' . base64_encode($email . ':' . $password)
		);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1 );
		#curl_setopt($ch, CURLOPT_HEADER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		$response = curl_exec($ch);
		$xml = simplexml_load_string($response);
		
		// convert into json and back so we have a permanent instance of the data.
		$json = json_encode($xml);
		$tasks = json_decode($json);
		
		return $tasks->task;
	}
}
?>
