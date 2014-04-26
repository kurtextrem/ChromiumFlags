+function($) {
	var App = function() {
		this.buildTable()
	}

	App.prototype = {
		construct: App,

		classToAdd: {
			0: 'fade',
			1: 'success',
			2: 'fade',
			3: 'fade',
			4: 'active',
			5: 'info fade',
			6: 'success fade',
			7: 'info',
			8: 'fade',
			9: 'fade',
			10: 'fade',
			11: 'fade',
			12: 'fade',
			13: 'active fade'
		},

		buildTable: function() {
			var html = '', title
			$.getJSON('http://kurtextrem.de/chromium/flags.php?callback=?', function(content) {
				$.each(content.switches, function(i, v) {
					title = this.textParseCondition(v.condition, content.constants)
					if (title !== '')
						title = ' data-toggle="tootltip" data-placement="left" data-trigger="manual" data-show="true" data-title="' + title + '"'
					html += '<tr class="switch' + this.parseCondition(v.condition) + '"' + title + '>'
					var add = ''
					if (v.new)
						add = ' <span class="label label-primary" data-toggle="tooltip" data-title="' + new Date( +(v.new + '000')) + '">new</span>'
					if (v.deleted)
						add = ' <span class="label label-danger" data-toggle="tooltip" data-title="' + new Date( +(v.deleted + '000')) + '">deleted</span>'
					html += '<td class="switch" id="' + encodeURIComponent(i) + '" tabindex="0">' + i + add + ' <a  class="anchor" href="#' + encodeURIComponent(i) + '">#</a></td>'
					html += '<td>' + v.comment.replace(/[^:](\/\/.+)/g, '<br><span class="text-muted">$1</span>').replace(/(\. |:(?!\/| |[A-Z0-9:]))/g, '$1<br>').replace(/  /g, '<br>&nbsp;&nbsp;').replace(/(<br>&nbsp;&nbsp;){3}/g, '').replace(/--((\w|-)+)/g, '<a href="#$1">--$1</a>') + '</td>' // only break after ":char", as ": " probably tells there is something in the same line after it
					html += '</tr>'
				}.bind(this))
				$('tbody').append(html)
				content.urls = $.map(content.urls, function(v, i) {
					return '<a href="' + v + '">' + v.match(/\/([^\/]+)\.(cc|java)$/)[1] + '</a>'
				})
				$('.urls').append(content.urls.join(' &middot; '))
				$('.time').text(new Date( +(content.time + '000')))
				$('.table').tablesort()
				this.registerEvents()
			}.bind(this))
		},

		parseCondition: function(condition) {
			if (condition === null) return ''
			if ($.isNumeric(condition))
				return ' ' + this.classToAdd[condition]
			if (condition.search('!') === 0)
				return ' warning'

			return ' purple'
		},

		textParseCondition: function(condition, constants) {
			if (condition === null || condition === '' || condition === undefined) return ''
			condition = $.trim(condition)
			if ($.isNumeric(condition))
				return constants[condition].replace('OS_', '').replace(/_/g, ' ')
			if (condition.search('!') === 0)
				return 'not ' + constants[condition.replace('!', '')].replace('OS_', '').replace(/_/g, ' ')

			var matches = condition.match(/(&&|\|\|)?[ ]?!?\d+/g), text = '', split
			$.each(matches, function(i, v) {
				if (/(&&|\|\|)/.test(v)) {
					split = v.split(' ')
					text += ' ' + split[0] + ' '
					v = split[1]
				}
				text +=  this.textParseCondition(v, constants)
			}.bind(this))
			return text.replace(/&&/g, 'and').replace(/\|\|/g, 'or')
		},

		registerEvents: function() {
			var $small = $('<small>')
			$('.page-header > h1').append($small.text(' Adding tooltips, page unresponsive for a second...'))
			window.setTimeout(function() {
				$('[data-toggle="tooltip"]').tooltip()
				$('[data-show="true"]').tooltip('show')
				$small.text(' Done').delay(500).fadeOut('slow')
			}, 200) // hurts performance so much
			if (location.hash !== '') {
				var elem = $(location.hash)
				$('html').animate({
				        scrollTop: elem.offset().top
				}, 0, function() {
					elem.focus()
				}) // workaround for hash not working
				//elem.parent().addClass('target')
			}
			// http://www.jitbit.com/alexblog/230-javascript-injecting-extra-info-to-copypasted-text/
			$('body').on('copy', function (e) {
				var body_element = document.getElementsByTagName('body')[0],
				selection = window.getSelection()

				//create a div outside of the visible area
				//and fill it with the selected text
				var newdiv = document.createElement('div')
				newdiv.style.position = 'absolute'
				newdiv.style.left = '-99999px'
				body_element.appendChild(newdiv)
				newdiv.appendChild(selection.getRangeAt(0).cloneContents())

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
