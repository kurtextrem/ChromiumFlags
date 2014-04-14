<?php
error_reporting(E_ALL);
require_once 'simple_html_dom.php';

class Flags {
	protected $urls = [
		'http://src.chromium.org/viewvc/chrome/trunk/src/chrome/common/chrome_switches.cc', // largest first to build the base constants
		'http://src.chromium.org/viewvc/chrome/trunk/src/base/base_switches.cc',
		'http://src.chromium.org/viewvc/chrome/trunk/src/apps/switches.cc',
		'http://src.chromium.org/viewvc/chrome/trunk/src/ash/ash_switches.cc',
		'http://src.chromium.org/viewvc/chrome/trunk/src/ipc/ipc_switches.cc',
		'http://src.chromium.org/viewvc/chrome/trunk/src/chromeos/chromeos_switches.cc'
	];

	protected $publicOutput = '../flags.json';

	protected $parseActions = array(
	                // class parse
		'k' => 'determination',

		'cp' => 'determination',

		's' => 'constantName',

		'kt' => 'boolConst', // ash_switches

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

	private $preString = '';
	private $original = '';
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
		Header('Content-Type: application/json');
		echo json_encode($this->switches, JSON_FORCE_OBJECT);
	}

	private function update() {
		foreach($this->urls as $url) {
			$comment = $condition = '';
			if ($url === 'http://src.chromium.org/viewvc/chrome/trunk/src/chromeos/chromeos_switches.cc') {
				$comment = '<b>The switches below only work with Chrome OS</b>';
				$condition = 6;
			}
			$this->switches['flags']['..' . substr($url, 47, -3)]  = array(
		              		'original' => $url,
				'comment' => $comment,
				'condition' => $condition,
				'new' => false
			);
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
			$content = $content->childNodes();
			if(isset($content[0]))
				$this->call($content);
		}
	}

	private function call($spans, $step2 = false, $length = 3) {
		if (!$step2)
			$string = str_replace('pygments-', '', $spans[0]->class);
		else
			$string = substr($step2, 0, $length);
		if (isset($this->parseActions[$string])) {
			$name = 'handle'  . ucfirst($this->parseActions[$string]);
			if (method_exists($this, $name)) {
				$this->{$name}($spans);
			}
		}
	}

	private function handleDetermination($spans) {
		$this->call($spans, html_entity_decode($spans[0]->innertext));
	}

	protected function handleComment($spans) {
		$content = html_entity_decode($spans[0]->innertext);
		if (strpos($content, '------')) return $this->preString = ''; // workaround for the first lines in chrome_switches
		$this->preString .= preg_replace('~^// ~', '', $content) . ' ';
	}

	protected function handleCondition($spans) {
		$content = html_entity_decode($spans[0]->innertext);
		// workaround for #ifndef NDEBUG
		$content = preg_replace('/#ifndef (.+)/', '#if !defined($1)', $content);
		// workaround for the future
		$content = preg_replace('/#ifdef (.+)/', '#if defined($1)', $content);

		$content = str_replace('#if ', '', $content);
		$this->openCondition = $this->parseCondition($content);
	}

	protected function handleElseCondition($spans) {
		$this->openCondition = preg_replace('/([ ]?)(\d+)/', '$1!$2', $this->openCondition);
		$this->openCondition = str_replace('!', '', $this->openCondition);
	}

	protected function parseCondition($condition) {
		if (strpos ($condition, '||') !== false || strpos ($condition, '&&') !== false) { // "real" condition
			preg_match_all('/(&&|\|\|)?[ ]?(!?defined\([^)]+\))/', $condition, $matches);
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

	protected function handleEndCondition($spans) {
		$this->openCondition = null;
	}

	protected function handleConstant($spans) {
		if (!isset($spans[5]->innertext)) return $this->original = $spans[2];
		$name = str_replace('"', '', html_entity_decode($spans[5]->innertext));
		if (strlen($name) < 3) { // for values like ProfilerTimingDisabledValue from base_switches
			$name = $spans[2] . ': "' . $name . '"';
		}
		$this->switches['flags'][$name] = array(
		                'original' => html_entity_decode($spans[2]->innertext),
			'comment' => $this->preString,
			'condition' => $this->openCondition,
			'new' => !isset($this->oldFile->flags->{$name})
		);
		$this->preString = '';
	}

	protected function handleConstantName($spans) {
		$spans[2] = $this->original;
		$spans[5] = $spans[0];
		$this->handleConstant($spans);
		$this->original = '';
	}

	protected function handleNamespace ($spans) {
		$this->preString = '';
	}

	protected function handleInclude ($spans) {
		$this->preString = '';
	}
}

new Flags();
