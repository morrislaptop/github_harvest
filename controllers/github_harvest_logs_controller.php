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

	function view_log($user_bridge_id)
	{
		// get the user bridge.
		$this->UserBridge->contain();
		$user_bridge = $this->UserBridge->read(null, $user_bridge_id);
		$user_bridge['GitHub'] = unserialize($user_bridge['UserBridge']['app1data']);
		$user_bridge['Harvest'] = unserialize($user_bridge['UserBridge']['app2data']);

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