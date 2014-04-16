<?php
namespace crflags;
require_once 'AbstractParser.class.php';
require_once 'simple_html_dom.php';

abstract class AbstractSourceParser extends AbstractParser {
	// Pre
	public $urls = array();

	// Mid parsing
	public $parseActions  = array(
	                // class parse
		'k' => 'determination',

		'cp' => 'determination',

		's' => 'constantName',

		'kt' => 'boolConstant', // ash_switches

		'p' => 'endBracket',

		'c1' => 'comment',

		// 3 chars
		'con' => 'constant',

		'nam' => 'namespace',

		'#in' => 'include',

		'#if' => 'condition',

		'#en' => 'endCondition',

		'#el' => 'elseCondition'

	);

	// Output
	public $cacheLife = 0;
	public $publicOutputFile = '';
	protected $output = array();

	public function output() {
		Header('Content-Type: application/json');
		echo json_encode($this->output, JSON_FORCE_OBJECT);
	}

	public function update() {
		file_put_contents($this->publicOutputFile, json_encode($this->output, JSON_FORCE_OBJECT));
	}

	protected function get($url) {
		if ($this->development === 3) {
			file_put_contents('test.html', file_get_contents($url));
		}
		return !$this->development ? file_get_html($url) : file_get_html('test.html');
	}

	protected function parse($html) {
		foreach ($html->find('.vc_file_line_text') as $content) {
			$content = $content->childNodes();
			if(isset($content[0]))
				$this->call($content);
		}
	}

	protected function call($spans, $step2 = false, $length = 3) {
		if (!$step2)
			$string = str_replace('pygments-', '', $spans[0]->class);
		else
			$string = substr($step2, 0, $length);
		if (isset($this->parseActions[$string]) && $handle = $this->parseActions[$string]) {
			$this->{'handle'  . ucfirst($handle)}($spans);
		}
	}

	protected function handleBoolConstant($spans) { }
	protected function handleCondition($spans) { }
	protected function handleComment($spans) {}
	protected function handleConstant($spans) {}
	protected function handleConstantName($spans) { }
	protected function handleDetermination($spans) { }
	protected function handleEndCondition($spans) { }
	protected function handleEndBracket($spans) { }
	protected function handleElseCondition($spans) { }
	protected function handleInclude($spans) { }
	protected function handleNamespace($spans) { }
}
