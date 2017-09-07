<div class="cms-subsites" data-pjax-fragment="SubsiteList">
	<div class="field dropdown">
		<select id="SubsitesSelect">
			<% loop $ListSubsites %>
				<option value="$ID" $CurrentState>$Title</option>
			<% end_loop %>
		</select>
	</div>
</div>
