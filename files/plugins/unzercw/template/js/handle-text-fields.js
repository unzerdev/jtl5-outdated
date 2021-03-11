jQuery( document ).ready(function( $ ) {

	var listOfElements = [];
	$.each(listOfElements, function(index, value) {
		var textElement = $('input[name="' + value + '"]');
		var elementHtml = textElement.parent().html();
		var contentInclEndTag = elementHtml.substring(elementHtml.indexOf('value="') + 'value="'.length);
		var content = contentInclEndTag.substring(0, contentInclEndTag.indexOf('"'));
		textElement.replaceWith('<textarea cols="100" rows="10" name="' + value + '">' + content + '</textarea>');
	});
});