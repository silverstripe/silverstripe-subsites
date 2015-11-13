(function($) {
	$.entwine('ss', function($) {
		/**
		 * Choose a subsite from which to select pages.
		 * Needs to clear tree dropdowns in case selection is changed.
		 */
		$('.subsitestreedropdownfield-chooser').entwine({
			onchange: function() {
				// TODO Data binding between two fields
				// TODO create resetField method on API instead
				var fields = $('.SubsitesTreeDropdownField');
				fields.setValue(null);
				fields.setTitle(null);
				fields.find('.tree-holder').empty();
			}
		});

		/**
		 * Add selected subsite from separate dropdown to the request parameters
		 * before asking for the tree.
		 */
		$('.TreeDropdownField.SubsitesTreeDropdownField').entwine({
			getRequestParams: function() {
				var name = this.find(':input[type=hidden]:first').attr('name') + '_SubsiteID',
					source = $('[name=' + name + ']'), params = {};
				params[name] = source.length ? source.val() : null;
				return params;
			}
		});
	});
})(jQuery);
