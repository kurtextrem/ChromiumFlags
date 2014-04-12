+function($) {
	var App = function() {
		this.buildTable()
	}

	App.prototype = {
		construct: App,

		classToAdd: {
			0: 'success',
			1: 'active',
			2: 'fade',
			3: 'fade',
			4: 'fade',
			5: 'active fade',
			6: 'info',
			7: 'success fade',
			8: 'info',
			9: 'fade',
			10: 'fade',
			11: 'fade',
			12: 'fade'
		},

		buildTable: function() {
			var html = '', title, add = ''
			$.getJSON('flags.json', function(content) {
				$.each(content.flags, function(i, v) {
					title = this.textParseCondition(v.condition, content.constants)
					if (title !== '')
						title = ' data-toggle="tootltip" data-placement="left" data-trigger="manual" data-show="true" title="' + title + '"'
					html += '<tr class="switch' + this.parseCondition(v.condition) + '"' + title + '>'
					if (v.new)
						add = ' <span class="label label-primary">new</span>'
					html += '<td class="switch" id="' + i + '">' + i + add + ' <a href="#' + i + '" class="anchor">#</a></td>'
					html += '<td>' + v.comment + '</td>'
					html += '</tr>'
				}.bind(this))
				$('tbody').append(html)
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
			if (condition === null || condition === undefined) return ''
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
			window.setTimeout(function() {
				$('[data-toggle="tooltip"]').tooltip()
				$('[data-show="true"]').tooltip('show')
			}, 800) // hurts performance so much
			if (location.hash !== '') {
				$('html').animate({
				        scrollTop: $(location.hash).offset().top
				}, 0) // workaround for hash not working
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
