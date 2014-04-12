+function($) {
	var App = function() {
		this.buildTable()
	}

	App.prototype = {
		construct: App,

		classToAdd: {

		},

		buildTable: function() {
			var $table = $('tbody'), $tr, $switch, $comment, $cond
			$.getJSON('flags.json', function(content) {
				$.each(content.flags, function(i, v) {
					$switch = $('<td class="switch">').text(i).append(' <a href="#' + i + '" class="anchor">#</a>')
					$comment = $('<td>').html(v.comment)
					$cond = $('<td>').text(v.condition)
					$tr = $('<tr class="switch">').append($switch, $comment, $cond).attr('id', i)
					$table.append($tr)
				})
				$('.time').text(new Date( +(content.time + '000')))
				this.registerEvents()
			}.bind(this))
		},

		registerEvents: function() {
			// http://www.jitbit.com/alexblog/230-javascript-injecting-extra-info-to-copypasted-text/
			$('body').on('copy', function (e) {
				var body_element = document.getElementsByTagName('body')[0],
				selection = window.getSelection()

				//create a div outside of the visible area
				//and fill it with the selected text
				var newdiv = document.createElement('div');
				newdiv.style.position = 'absolute';
				newdiv.style.left = '-99999px';
				body_element.appendChild(newdiv);
				newdiv.appendChild(selection.getRangeAt(0).cloneContents());

				//we need a <pre> tag workaround
				//otherwise the text inside "pre" loses all the line breaks!
				if (selection.getRangeAt(0).commonAncestorContainer.nodeName == 'PRE') {
					newdiv.innerHTML = '--<pre>' + newdiv.innerHTML + '</pre>'
				}
				else
					newdiv.innerHTML = '--' + newdiv.innerHTML

				selection.selectAllChildren(newdiv)
				window.setTimeout(function () { body_element.removeChild(newdiv) }, 200)
			})
		}
	}

	window.App = App
}(jQuery);

$.fn.ready(function() {
	new App()
})
