// If we haven't yet got the function
if (typeof (TagCompleter) != 'function') {
    // tagEntryField・tagIndicatorList は要素の ID（# は付けない）
    function TagCompleter(tagEntryField, tagIndicatorList, delimiter) {
        var trimValue = function (value) {
            return String(value == null ? '' : value).trim();
        };

        var theEntry = document.getElementById(tagEntryField);
        var theList = document.getElementById(tagIndicatorList);

        // Make sure the elements that have been supplied exist
        if (!theEntry) {
            return;
        }

        var listItems = function () {
            return theList ? Array.from(theList.querySelectorAll('li')) : [];
        };

        // 一覧の文字から件数の「(3)」を外したタグ
        var tagOf = function (li) {
            return trimValue(li.textContent.replace(/\([0-9]+\)$/, ''));
        };

        // Get an array of the current tags in the field
        var getTags = function () {
            // Get the contents of the field
            // Split is by commas
            // Trim each item of whitespace at the beginning and end
            return theEntry.value.split(delimiter).map(trimValue).filter(function (v) {
                return v !== ''; // Remove any empty values
            });
        };

        // Highlight any tags in the tag list which are already in the field
        var addHilights = function () {
            var tagsInField = getTags();
            listItems().forEach(function (li) {
                li.classList.toggle('tagSelected', tagsInField.indexOf(tagOf(li)) !== -1);
            });
        };

        // Add the tag that has been clicked to the field
        var addTag = function (li) {
            var newTag = tagOf(li);
            var oldTags = getTags();
            // Mark the document as dirty for Modx by triggering a "change" event
            // 管理画面の未保存の検知に届くよう、素の change を起こす
            theEntry.dispatchEvent(new Event("change", {bubbles: true}));

            // Is the tag already in the list? If so, remove it
            var thePos = oldTags.indexOf(newTag);
            if (thePos != -1) {
                oldTags.splice(thePos, 1);
            } else { // Not in the list, so add it
                oldTags.push(newTag);
            }
            theEntry.value = oldTags.join(delimiter);
            addHilights();
        };

        // Attach events
        // Add hilights every time the tag field changes
        theEntry.addEventListener('change', function () {
            addHilights();
        });

        // Add tag every click on a tag in the list
        listItems().forEach(function (li) {
            li.addEventListener('click', function () {
                addTag(li);
            });
        });

        addHilights();

    }

}
