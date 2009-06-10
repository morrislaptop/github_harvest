<?php
class GithubHarvestLog extends AppModel
{
	var $name = 'GithubHarvestLog';
	
	var $belongsTo = array(
		'UserBridge'
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
		$url .= '?' . http_build_query($params);
		$json = file_get_contents($url);
		$json = json_decode($json);
		
		$projects = array();
		foreach ($json->repositories as $repo) {
			$projects[$repo->name] = $repo->name;
		}
		
		return $projects;
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
		$projects = array();
		foreach ($xml->project as $project) {
			$projects[intval($project->id)] = $project->code . ' - ' . $project->name;
		}
		
		return $projects;
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
		$tasks = array();
		foreach ($xml->task as $task) {
			$tasks[intval($task->id)] = (string) $task->name;
		}
		
		return $tasks;
	}
}
?>
