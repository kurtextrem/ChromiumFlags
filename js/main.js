+function($) {
	var App = function() {
		this.buildTable()
	}

	App.prototype = {
		construct: App,

		buildTable: function() {
			var $table = $('tbody'), $tr, $switch, $comment, $cond
			$.getJSON('flags.json', function(content) {
				$.each(content.flags, function(i, v) {
					$switch = $('<td>').text(i)
					$comment = $('<td>').text(v.comment)
					$cond = $('<td>').text(v.condition)
					$tr = $('<tr>').append($switch, $comment, $cond).attr('id', i)
					$table.append($tr)
				})
				$('.time').text(new Date( +(content.time + '000')))
			})
		}
	}

	window.App = App
}(jQuery);

$.fn.ready(function() {
	new App()
})
