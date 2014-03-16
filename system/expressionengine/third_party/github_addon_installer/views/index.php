<div id="filterMenu">
	<fieldset>
		<legend><?=lang('filter')?></legend>
		<form>
			<div class="group">
				<select id="addonFilter">
					<option value=""><?=lang('no_filter')?></option>
					<option value="keyword" selected="selected"><?=lang('filter_by_keyword')?></option>
					<optgroup data-filter="addon_status" label="<?=lang('filter_by_status')?>">
					</optgroup>
					<optgroup data-filter="addon_author" label="<?=lang('filter_by_author')?>">
					</optgroup>
				</select>
			</div>

			<div>
				<label for="addonKeyword" class="js_hide"><?=lang('keyword')?></label>
				<input type="text" id="addonKeyword" maxlength="200" class="field shun" placeholder="<?=lang('keyword')?>">
			</div>

		</form>
	</fieldset>
</div>

<table class="mainTable" id="addons" border="0" cellspacing="0" cellpadding="0">
	<thead>
		<tr>
			<th class="addon_name"><?=lang('addon_name')?></th>
			<th class="addon_github_url"><?=lang('addon_github_url')?></th>
			<th class="addon_branch"><?=lang('addon_branch')?></th>
			<th class="addon_author"><?=lang('addon_author')?></th>
			<th class="addon_stars"><?=lang('addon_stars')?></th>
			<th class="addon_status"><?=lang('addon_status')?></th>
			<th>&nbsp;</th>
		</tr>
	</thead>
	<tbody>
		<?php foreach ($addons as $addon) : ?>
		<tr class="<?=alternator('even', 'odd')?>">
			<td class="addon_name"><?=element('name', $addon)?></td>
			<td class="addon_github_url"><?=element('github_url', $addon)?></td>
			<td class="addon_branch"><?=element('branch', $addon)?></td>
			<td class="addon_author"><?=element('author', $addon)?></td>
			<td class="addon_stars"><?=element('stars', $addon)?></td>
			<td class="addon_status"><?=element('status', $addon)?></td>
			<td class="addon_install"><?=element('install', $addon)?></td>
		</tr>
		<?php endforeach; ?>
</table>