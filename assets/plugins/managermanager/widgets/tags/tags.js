// If we haven't yet got the function
if 	(typeof(TagCompleter) != 'function') {
	function TagCompleter(tagEntryField, tagIndicatorList, delimiter) {

		var theEntry = jQuery('#'+tagEntryField);
		var theList = jQuery('#'+tagIndicatorList);

		// Make sure the elements that have been supplied exist
		if (!theEntry.length) {
			return;
		}

		// Attach events
		// Add hilights every time the tag field changes
		jQuery(theEntry).change(function(e) { addHilights(); });

		// Add tag every click on a tag in the list
		jQuery('#'+tagIndicatorList + ' li').click( function(e) { addTag(e); } );

		// Get an array of the current tags in the field
		var getTags = function() {
			// Get the contents of the field
			// Split is by commas
			// Trim each item of whitespace at the beginning and end
			var theTags = jQuery(theEntry).val().split(delimiter);
			jQuery.each(theTags, function(i,v) {
				theTags[i] = jQuery.trim(v);
					if (theTags[i] == '') {theTags.splice(i, 1); } // Remove any empty values
				});
			return theTags;
		};

		// Add the tag that has been clicked to the field
		var addTag = function (e) {
			var newTag = jQuery(e.target).text();
			newTag = newTag.replace(/\([0-9]+\)$/,'');
			newTag = jQuery.trim(newTag);
			var oldTags = getTags();
			// Mark the document as dirty for Modx by triggering a "change" event
			jQuery(theEntry).trigger("change");

			// Is the tag already in the list? If so, remove it
			var thePos = jQuery.inArray(newTag, oldTags);
			if (thePos != -1) {
				oldTags.splice(thePos, 1);
			} else { // Not in the list, so add it
				oldTags.push(newTag);
			}
				jQuery(theEntry).val(oldTags.join(delimiter));
			addHilights();
		};

		// Highlight any tags in the tag list which are already in the field
		var addHilights = function() {

			var tagsInField = getTags();
			var tag;

			jQuery('#'+tagIndicatorList + ' li').each( function() {
				tag = jQuery(this).text().replace(/\([0-9]+\)$/,'');
				tag = jQuery.trim(tag);
				if (jQuery.inArray(tag , tagsInField) != -1) {
					jQuery(this).addClass('tagSelected');
				} else {
					jQuery(this).removeClass('tagSelected');
				}
			});

		};

		addHilights();

	}

}