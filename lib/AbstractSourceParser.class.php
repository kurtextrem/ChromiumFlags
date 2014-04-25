<?php
namespace crflags;
require_once 'AbstractParser.class.php';
require_once 'simple_html_dom.php';

/**
 * Includes the default implementation for chromium source parsers.
 *
 * @author		Jacob Groß (kurtextrem)
 * @copyright		2014 Jacob Groß
 * @license		"Do whatever you want"
 * @package		ChromiumFlags
 */
abstract class AbstractSourceParser extends AbstractParser {
	// Pre
	/** @var 	array 	URLs to parse */
	public $urls = array();

	// Mid parsing
	/** @var 	array 	Parse actions */
	public $parseActions  = array(
	                // class parse
		'k' => 'determination',

		'kd' => 'javaDetermination',

		'cp' => 'determination',

		's' => 'constantName',

		'kt' => 'boolConstant', // ash_switches

		'kn' => 'package',

		'p' => 'endBracket',

		'c1' => 'comment',

		// 3 chars
		'con' => 'constant',

		'sta' => 'javaConstant',

		'abs' => 'javaNamespace',

		'pri' => 'private',

		'nam' => 'namespace',

		'ext' => 'externConstant',

		'#in' => 'include',

		'#if' => 'condition',

		'#en' => 'endCondition',

		'#el' => 'elseCondition'

	);

	// Output
	/** @var 	int 	Cache life in seconds */
	public $cacheLife = 0;
	/** @var 	string 	Contains the output file */
	public $publicOutputFile = '';
	/** @var 	array 	Contains the output array */
	protected $output = array();

	/**
	 * Outputs the current output to the browser.
	 *
	 * @author 	Jacob Groß (kurtextrem)
	 * @date   	2014-04-16
	 */
	public function output() {
		Header('Content-Type: application/json');
		echo json_encode($this->output, JSON_FORCE_OBJECT);
	}

	/**
	 * Writes the output into the cache file.
	 *
	 * @author 	Jacob Groß (kurtextrem)
	 * @date   	2014-04-16
	 */
	public function update() {
		file_put_contents($this->publicOutputFile, json_encode($this->output, JSON_FORCE_OBJECT));
	}

	/**
	 * Downloads a specific url.
	 *
	 * @author 	Jacob Groß (kurtextrem)
	 * @date   	2014-04-16
	 * @param  	string     		$url 	The url.
	 * @return 	simple_html_dom
	 */
	protected function get($url) {
		if ($this->development === 3 || $this->development === 5) {
			file_put_contents('test.html', file_get_contents($url));
		}
		return !$this->development || $this->development === 4 ? file_get_html($url) : file_get_html('test.html');
	}

	/**
	 * Loops through the lines and parses them. Also calls the action handler.
	 *
	 * @author 	Jacob Groß (kurtextrem)
	 * @date   	2014-04-16
	 * @param  	simple_html_dom     	$html
	 */
	protected function parse($html) {
		foreach ($html->find('.vc_file_line_text') as $content) {
			$content = $content->childNodes();
			if(isset($content[0]))
				$this->call($content);
		}
	}

	/**
	 * Calls the apropriate action handler.
	 *
	 * @author 	Jacob Groß (kurtextrem)
	 * @date   	2014-04-17
	 * @param  	array     			$spans
	 * @param  	boolean    		$step2
	 * @param  	integer    		$length 	Substr length parameter
	 */
	protected function call($spans, $step2 = false, $length = 3) {
		if (!$step2)
			$string = str_replace('pygments-', '', $spans[0]->class);
		else
			$string = substr($step2, 0, $length);
		if (isset($this->parseActions[$string]) && $handle = $this->parseActions[$string]) {
			$this->{'handle'  . ucfirst($handle)}($spans);
		}
	}

	/** Handler for bool constants. */
	protected function handleBoolConstant($spans) { }
	/** Handler for conditions. */
	protected function handleCondition($spans) { }
	/** Handler for comments. */
	protected function handleComment($spans) { }
	/** Handler for constants (switches). */
	protected function handleConstant($spans) { }
	/** Handler for java constants (switches). */
	protected function handleJavaConstant($spans) { }
	/** Handler for constant names (original switch names). */
	protected function handleConstantName($spans) { }
	/** Handler for java constants (switches). */
	protected function handlePrivate($spans) { }
	/** Determines which handler to call. */
	protected function handleDetermination($spans) { }
	/** Determines which (java) handler to call. */
	protected function handleJavaDetermination($spans) { }
	/** Handler for conditions' ends. */
	protected function handleEndCondition($spans) { }
	/** Handler for brackets' ends. */
	protected function handleEndBracket($spans) { }
	/** Handler for 'else' conditions. */
	protected function handleElseCondition($spans) { }
	/** Handler for 'extern' constants. */
	protected function handleExternConstant($spans) { }
	/** Handler for 'include'. */
	protected function handleInclude($spans) { }
	/** Handler for 'package' (java). */
	protected function handlePackage($spans) { }
	/** Handler for 'namespace'. */
	protected function handleNamespace($spans) { }
	/** Handler for Java abstract classes  */
	protected function handleJavaNamespace($spans) { }
}
