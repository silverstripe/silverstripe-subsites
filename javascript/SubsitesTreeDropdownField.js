(function($) {
	$.entwine('ss', function($) {
		/**
		 * Choose a subsite from which to select pages.
		 * Needs to clear tree dropdowns in case selection is changed.
		 */
        $('select.subsitestreedropdownfield-chooser').entwine({
			onchange: function() {
				// TODO Data binding between two fields
                const name = this.attr('name').replace('_SubsiteID', '');
                let field = $('#Form_EditForm_' + name).first();
                field.setValue(0);
                field.refresh();
                field.trigger('change');
			}
		});

		/**
		 * Add selected subsite from separate dropdown to the request parameters
		 * before asking for the tree.
		 */
		$('.TreeDropdownField.SubsitesTreeDropdownField').entwine({
            getAttributes() {
                const fieldName = this.attr('id').replace('Form_EditForm_', '');
                const subsiteID = $('#Form_EditForm_' + fieldName + '_SubsiteID option:selected').val();

                let attributes = this._super();
                attributes.data.urlTree += "?" + fieldName + "_SubsiteID=" + subsiteID;
                attributes.data.cacheKey = attributes.data.cacheKey.substring(0, 19) + "_" + subsiteID;
                return attributes;
            }
		});
	});
})(jQuery);
