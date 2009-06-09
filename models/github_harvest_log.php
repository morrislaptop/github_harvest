<?php
class GithubHarvestLog extends AppModel
{
	var $name = 'GithubHarvestLog';

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
