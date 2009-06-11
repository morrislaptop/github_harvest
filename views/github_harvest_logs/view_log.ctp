<h1>Github Harvest Logs</h1>

<p>
<?php
echo $paginator->counter(array(
'format' => __('Page %page% of %pages%, showing %current% records out of %count% total, starting on record %start%, ending on %end%', true)
));
?></p>
<table cellpadding="0" cellspacing="0" class="log">
	<tr>
		<th class="headerLeft"><?php echo $paginator->sort('commit_id');?></th>
		<th><?php echo $paginator->sort('entry_id');?></th>
		<th class="headerRight"><?php echo $paginator->sort('created');?></th>
	</tr>
	<?php
		$odd = true;
		foreach ($logs as $log)
		{
			$log['GitHub'] = unserialize($log['GithubHarvestLog']['app1data']);
			$log['Harvest'] = unserialize($log['GithubHarvestLog']['app2data']);
			?>
			<tr class="<?php echo $odd ? 'odd' : 'even'; ?>">
				<td class="rowLeft">
					<?php
						$url = 'http://github.com/' . $userBridge['GitHub']['username'] . '/' . $userBridge['GitHub']['github_project'] . '/commit/' . $log['GithubHarvestLog']['app1_id'];
						echo $html->link($log['GithubHarvestLog']['app1_id'], $url, array('target' => '_blank'));
					?>
				</td>
				<td>
					<?php
						$timestamp = strtotime($log['GitHub']->committed_date);
						$url = 'http://' . $userBridge['Harvest']['domain'] . '.harvestapp.com/daily/' . (date('z', $timestamp) + 1) . '/' . date('Y', $timestamp);
						echo $html->link($log['GithubHarvestLog']['app2_id'], $url, array('target' => '_blank'));
					?>
				</td>
				<td class="rowRight">
					<?php echo $time->nice($log['GithubHarvestLog']['created']); ?>
				</td>
			</tr>
			<?php
			$odd = !$odd;
		}
	?>
</table>
<div class="paging">
	<?php echo $paginator->prev('<< '.__('previous', true), array(), null, array('class'=>'disabled'));?>
 | 	<?php echo $paginator->numbers();?>
	<?php echo $paginator->next(__('next', true).' >>', array(), null, array('class'=>'disabled'));?>
</div>
<p style="text-align: right;"><?php echo $html->link('return to my bridges', array('plugin' => null, 'controller' => 'user_bridges', 'action' => 'select')); ?></p>