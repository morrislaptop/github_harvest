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
		$this->GithubHarvestLog->sync();
	}
	
	function refresh()
	{
		$this->GithubHarvestLog->refreshRemoteData();
	}

	function view_log($user_bridge_id)
	{
		// get the user bridge.
		$this->UserBridge->contain();
		$user_bridge = $this->UserBridge->read(null, $user_bridge_id);
		$user_bridge['GitHub'] = $user_bridge['UserBridge']['app1data'];
		$user_bridge['Harvest'] = $user_bridge['UserBridge']['app2data'];

		// get logs
		$conditions = compact('user_bridge_id');
		$this->paginate['order'] = 'GithubHarvestLog.created DESC';
		$this->paginate['contain'] = array();
		$logs = $this->paginate('GithubHarvestLog', $conditions);

		// add Time helper for friendliness
		$this->helpers[] = 'Time';

		// render
		$this->set(compact('logs', 'user_bridge'));
	}

	function configure($user_bridge_id)
	{
		if ( !empty($this->data) ) {
			$data = array(
				'name' => $this->data['UserBridge']['name'],
				'app1data' => $this->data['Github'],
				'app2data' => $this->data['Harvest'],
				'id' => $user_bridge_id
			);
			$this->UserBridge->id = $user_bridge_id;
			if ( $this->UserBridge->save($data) ) {
				$this->Session->setFlash('Configuration saved', 'default', array('class' => 'success'));
				$this->redirect('/');
			}
			else {
				$this->Session->setFlash('Error saving configuration');
			}
		}

		// Get details.
		$this->UserBridge->contain();
		$user_bridge = $this->UserBridge->read(null, $user_bridge_id);
		$user_bridge['Github'] = $user_bridge['UserBridge']['app1data'];
		$user_bridge['Harvest'] = $user_bridge['UserBridge']['app2data'];

		// Set defaults.
		if ( empty($this->data) ) {
			$this->data = $user_bridge;
		}

		$this->_setFormData($user_bridge);
	}

	function _setFormData($user_bridge) {
		// GITHUB
		$github_projects = array();
		if ( $user_bridge['Github'] ) {
			$projects = $this->GithubHarvestLog->getGithubProjects($user_bridge['Github']['username'], $user_bridge['Github']['token']);	
			foreach ($projects as $repo) {
				$github_projects[$repo->name] = $repo->name;
			}
		}
		
		// HARVEST
		$harvest_projects = array();
		$harvest_tasks = array();
		if ( $user_bridge['Harvest'] ) 
		{
			$projects = $this->GithubHarvestLog->getHarvestProjects($user_bridge['Harvest']['domain'], $user_bridge['Harvest']['email'], $user_bridge['Harvest']['password']);	
			foreach ($projects as $project) {
				$label = !get_class($project->code) ? $project->code . ' - ' . $project->name : $project->name;
				$harvest_projects[intval($project->id)] = $label;
			}
			
			$tasks = $this->GithubHarvestLog->getHarvestTasks($user_bridge['Harvest']['domain'], $user_bridge['Harvest']['email'], $user_bridge['Harvest']['password']);
			foreach ($tasks as $task) {
				$harvest_tasks[intval($task->id)] = (string) $task->name;
			}
		}
		$this->set(compact('harvest_projects', 'github_projects', 'harvest_tasks'));
	}

	function github_projects($user_bridge_id) {
		$username = $this->params['url']['username'];
		$token = $this->params['url']['token'];
		$projects = $this->GithubHarvestLog->getGithubProjects($username, $token);
		$this->set(compact('projects'));
	}

	function harvest_projects_tasks($user_bridge_id) {
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