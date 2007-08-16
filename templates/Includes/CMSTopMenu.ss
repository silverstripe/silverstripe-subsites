	<ul id="MainMenu">
	<% control MainMenu %>
		<li class="$LinkingMode" id="Menu-$Code"><a href="$Link">$Title</a></li>
	<% end_control %>
	</ul>
	<form id="SubsiteActions">
		<fieldset>
			<span style="float: right">$ApplicationLogoText</span>
			<% if CanAddSubsites %><a id="AddSubsiteLink" href="admin/addsubsite">Add a site</a> <% end_if %>
			$SubsiteList
			<!-- <img src="../images/mainmenu/help.gif" alt="Get Help"> -->
		</fieldset>
	</form>	
