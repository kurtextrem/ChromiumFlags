<?php
error_reporting(E_ALL);
require_once 'simple_html_dom.php';

class Flags {
	protected $urls = [
		'http://src.chromium.org/viewvc/chrome/trunk/src/chrome/common/chrome_switches.cc',
		'http://src.chromium.org/viewvc/chrome/trunk/src/base/base_switches.cc'
	];

	protected $publicOutput = '../flags.json';

	protected $parseActions = array(
	                // 1 char
		'/' => 'comment',

		'#' => 'hash',

		'n' => 'namespace',

		'c' => 'constant',

		'}' => 'endBracket',

		'"' => 'constantName',

		// 3 chars
		'#in' => 'include',

		'#if' => 'condition',

		'#en' => 'endCondition',

		'#el' => 'elseCondition'

	);

	private $preString = '';
	private $openCondition = null;
	private $switches = array('time' => null, 'flags' => array(), 'constants' => array());
	private $oldFile;

	public function __construct() {
		//@unlink($this->publicOutput);
		$this->oldFile = @file_get_contents($this->publicOutput);
		$this->switches['time'] = $now = time();
		if (!$this->oldFile) {
			$this->update();
		} else {
			$this->oldFile = json_decode($this->oldFile);
			if ($now - $this->oldFile->time > 60 * 60 * 24) {
				$this->update();
			} else {
				$this->switches = $this->oldFile;
			}
		}

		$this->output();
	}

	private function output() {
		Header('Content-Type: text/json');
		echo json_encode($this->switches, JSON_FORCE_OBJECT);
	}

	private function update() {
		foreach($this->urls as $url) {
			$this->parse($this->get($url));
		}
		file_put_contents($this->publicOutput, json_encode($this->switches, JSON_FORCE_OBJECT));
	}

	private function get($url) {
		//file_put_contents('test.html', file_get_contents($url));
		return file_get_html($url); //file_get_html($url); //file_get_html('test.html');
	}

	private function parse($html) {
		foreach ($html->find('.vc_file_line_text') as $content) {
			$content = preg_replace('/\n/', ' ', (html_entity_decode($content->plaintext)));

			if(!empty($content))
				$this->call($content);
		}
	}

	private function call($content, $length = 1) {
		$string = substr($content, 0, $length);
		if (isset($this->parseActions[$string])) {
			$name = 'handle'  . ucfirst($this->parseActions[$string]);
			if (method_exists($this, $name)) {
				$this->{$name}($content);
			}
		}
	}

	protected function handleComment($content) {
		if (strpos($content, '------')) return $this->preString = ''; // workaround for the first lines in chrome_switches
		$this->preString .= ltrim(str_replace('//', '', $content));
	}

	protected function handleHash($content) {
		$this->call($content, 3);
	}

	protected function handleCondition($content) {
		// workaround for #ifndef NDEBUG
		$content = preg_replace('/#ifndef (.+)/', '#if !defined($1)', $content);
		// workaround for the future
		$content = preg_replace('/#ifdef (.+)/', '#if defined($1)', $content);

		$content = str_replace('#if ', '', rtrim($content));
		$this->openCondition = $this->parseCondition($content);
	}

	protected function handleElseCondition($content) {
		$this->openCondition = preg_replace('/([ ]?)(\d+)/', '$1!$2', $this->openCondition);
		$this->openCondition = str_replace('!', '', $this->openCondition);
	}

	protected function parseCondition($condition) {
		if (strpos ($condition, '||') !== false || strpos ($condition, '&&') !== false) { // "real" condition
			preg_match_all('/(&&|\|\|)?[ ]?(!?defined\([^\)]+\))/', $condition, $matches);
			$string = '';
			foreach($matches[2] as $pos => $match) {
				$string .= $matches[1][$pos] . ' ' . $this->parseCondition($match) . ' ';
			}
			return rtrim($string);
		}

		$flag = '';
		if (substr ($condition, 0, 7) == 'defined') {
			$const = substr ($condition, 8, -1);
		}

		if (substr ($condition, 0, 8) == '!defined') {
			$const = substr ($condition, 9, -1);
			$flag = '!';
		}

		$search = array_search($const, $this->switches['constants']);
		$int = true;
		if ($search === false) {
			$search = '';
			$this->switches['constants'][] = $const;
			if ($flag) {
				$search = $flag;
				$int = false;
			}
			$search .= array_search($const, $this->switches['constants']);
		}
		if ($int)
			$search = intval($search);
		return $search;
	}

	protected function handleEndCondition($condition) {
		$this->openCondition = null;
	}

	protected function handleConstant($content) {
		$name = trim(strstr($content, '"'));
		if (!$name) return;
		$name = preg_replace('/"(.*)" ;/', '$1', $name);
		preg_match('/k([^ ]+)/', $content, $matches);
		if (strlen($name) < 3) { // for values like ProfilerTimingDisabledValue from base_switches
			$name = $matches[1] . ': "' . $name . '"';
		}
		$this->switches['flags'][$name] = array(
		                'original' => $matches[0],
			'comment' => trim($this->preString),
			'condition' => $this->openCondition,
			'new' => !isset($this->oldFile->flags->{$name})
		);
		$this->preString = '';
	}

	protected function handleConstantName($content) {
		$this->handleConstant($content);
	}

	protected function handleNamespace ($content) {
		$this->preString = '';
	}

	protected function handleInclude ($content) {
		$this->preString = '';
	}
}

new Flags();
