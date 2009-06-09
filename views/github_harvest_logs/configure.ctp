<?php
	echo $html->css('forms', false, false, false);
	echo $javascript->link('jquery.selectboxes', false);
	echo $javascript->codeBlock('
		$(function() {
			$("#GithubToken").change(loadGithubProjects);	
			$("#HarvestPassword").change(loadHarvestProjectsAndTasks);
			$("#refreshGithub").click(function() {
				loadGithubProjects();
				return false;
			})
			$("#refreshHarvest, #refreshHarvest2").click(function() {
				loadHarvestProjectsAndTasks();
				return false;
			})
		});
		
		function loadGithubProjects() {
			var data = {
				username: $("#GithubUsername").val(),
				token: $("#GithubToken").val()
			};
			
			$("#GithubGithubProject").html("<option>loading...</option>");
			$("#GithubGithubProject").ajaxAddOption("' . $html->url(array('action' => 'github_projects', 'ext' => 'json')) . '", data);
		}
		
		function loadHarvestProjectsAndTasks() {
			var data = {
				domain: $("#HarvestDomain").val(),
				email: $("#HarvestEmail").val(),
				password: $("#HarvestPassword").val()
			};
			
			$("#HarvestHarvestProject").html("<option>loading...</option>");
			$("#HarvestTask").html("<option>loading...</option>");
			$.getJSON("' . $html->url(array('action' => 'harvest_projects_tasks', 'ext' => 'json')) . '", data, function(j) {
				$("#HarvestHarvestProject").addOption(j.projects);
				$("#HarvestTask").addOption(j.tasks);
			});
		}
	', array('inline' => false));
	
	echo $advform->create('UserBridge', array('url' => $this->here, 'class' => 'grid'));
?>
<h1>Give this bridge a name</h1>
<?php echo $advform->input('name'); ?>

<h1>Github</h1>
<?php echo $advform->input('Github.username'); ?>
<?php echo $advform->input('Github.token'); ?>
<?php echo $advform->input('Github.github_project', array('after' => $html->link('Refresh', '#', array('id' => 'refreshGithub', 'class' => 'refresh')))); ?>

<h1>Harvest</h1>
<?php echo $advform->input('Harvest.domain'); ?>
<?php echo $advform->input('Harvest.email'); ?>
<?php echo $advform->input('Harvest.password'); ?>
<?php echo $advform->input('Harvest.harvest_project', array('after' => $html->link('Refresh', '#', array('id' => 'refreshHarvest', 'class' => 'refresh')))); ?>
<?php echo $advform->input('Harvest.task', array('after' => $html->link('Refresh', '#', array('id' => 'refreshHarvest2', 'class' => 'refresh')))); ?>

<div class="submit">
	<?php echo $advform->submit('Submit', array('div' => false)); ?>
	or
	<?php echo $html->link('return to my bridges', array('plugin' => null, 'controller' => 'user_bridges', 'action' => 'select')); ?>
</div>
<?php echo $advform->end(); ?>