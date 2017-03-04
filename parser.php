<?php
/* 
  A simplistic recursive descent LL parser for simple, ad-hoc tasks
*/

//---------------------------------------------------------------------------//DBG()
// Throwaway utilities... (filtered out by the build script)                 //DBG()
//---------------------------------------------------------------------------//DBG()
function dump($mixed) { return "<pre style='margin:0 0 0 2em; background:#ffff90;'>" . print_r($mixed, true) . "</pre>"; }//DBG()
function DBG($msg) { global $DBG; if (!$DBG) return; echo $msg ."<br>\n"; }//DBG()

//---------------------------------------------------------------------------
class Parser
{
	const DEFAULT_RECURSION_LIMIT = 500;

	//---------------------------------------------------------------------------
	// Operators/rules...
	//
	// Can be extended by users at will (in sync with the ::$OP map below).
	const _TERMINAL = '#';  // Implicit internal operation, defined here only for a more uniform match()
	const _SEQ	= ',';	// Default when just listing rules.
	const _OR	= '|';
	const _MANY	= '+';	// 1 or more (greedy); must be followed by exactly 1 rule
	const _ANY	= '*';	// 0 or more (greedy); shortcut to [_OR [_MANY X] EMPTY]; must be followed by exactly 1 rule
	                        // "greedy" (above) means that [A...]A will never match!
//!!	const _OPT      = '?';  // 0 or 1; must be followed by exactly 1 rule

	// Operator functions...
	// Populated later below, as:
	//    Parser::$OP[Parser::_SOME-OP] = function($parser, $input_pos, $rule) { ... return false or match-length; }
	// Can be extended by clients at will (in sync with the keyword list above).
	static $OP = [];

	// Atoms ("terminal pattens") - just "metasyntactic sugar", as they could 
	// as well be just literal patterns. But fancy random regex literals would
	// confuse the parsing, so these "officially" nicely behaving ones are just 
	// quarantined and named here.
	// (BTW, a user pattern that's not anchored to the left is guaranteed to 
	// fail, as the relevant preg_match() call only returns the match length.
	// It could be extended though, but I'm not sure about multibyte support,
	// apart from my suspicion that mbstring *doesn't* have a position-capturing
	// preg_match (or any, for that matter, only ereg_...!).)
	// NOTE: PCRE is UNICODE-aware! --> http://pcre.org/original/doc/html/pcreunicode.html
	static $ATOM = [
		'EMPTY'      => '/^()/',
		'SPACE'      => '/^(\\s)/',
		'TAB'        => '/^(\\t)/',
		'QUOTE'      => '/^(\\")/',
		'APOSTROPHE' => "/^(')/",
		'SLASH'      => '/^(\\/)/',
		'IDCHAR'     => '/^([\\w])/', // [a-zA-Z0-9_], I guess
		'ID'         => '/^([\\w]+)/',
		'HEX'        => '/^([\\0-9a-fA-F])/',
		// UNICODE-safe:
		'DIGIT'      => '/^([\\p{N}])/u',
		'DIGITS'     => '/^([\\p{N}]+)/u',
		'LETTER'     => '/^([\\p{L}])/u',
		'LETTERS'    => '/^([\\p{L}]+)/u',
		'ALNUM'      => '/^([[:alnum:]])/u',
		'ALNUMS'     => '/^([[:alnum:]]+)/u',
		'WHITESPACE' => '/^([\\p{Z}])/u',
		'WHITESPACES'=> '/^([\\p{Z}]+)/u',
	];

	//-------------------------------------------------------------------
	static function atom($rule)	{ return isset(self::$ATOM[$rule]) ? self::$ATOM[$rule] : false; }
	static function op($rule)	{ return isset(self::$OP[$rule])   ? self::$OP[$rule]   : false; }
	static function term($rule)	{ return is_string($rule); }
	static function constr($rule)	{ return is_array($rule) && !empty($rule); }


	//-------------------------------------------------------------------
	// Parser state...
	//
	// input:
	public $text;
	public $text_length;
	// diagnostics:
	public $loopguard;
	public $depth_reached;
	public $rules_tried;
	public $terminals_tried;

	//-------------------------------------------------------------------
	public function parse($text, $syntax, $maxnest = self::DEFAULT_RECURSION_LIMIT)
	{
		$this->text = $text;
		$this->text_length = mb_strlen($text);
		// diagnostics
		$this->loopguard = $this->depth_reached = $maxnest;
		$this->rules_tried = 0;
		$this->terminals_tried = 0;

		return $this->match(0, $syntax);
	}

	//-------------------------------------------------------------------
	public function match($pos, $rule)
	// $seq is the source text (a string).
	// $rule is a syntax (tree) rule
	// If $seq matches $rule, it returns the length of the match, otherwise false.
	{
		++$this->rules_tried;
		if ($this->depth_reached > --$this->loopguard)
		    $this->depth_reached =   $this->loopguard;
		if (!$this->loopguard) {
			throw new Exception("--WTF? Infinite loop (in 'match()')!<br>\n");
		}

//DBG("match(): input '".mb_substr($this->text, $pos)."' against rule: ".dump($rule));

		if (self::term($rule)) // Terminal rule: atom or literal pattern.
		{
DBG(" --> terminal rule: ".dump($rule));
			$f = self::op(self::_TERMINAL);
		}
		else if (self::constr($rule))
		{
			// First item is the op. of the rule, or else _SEQ is assumed:
			if (!is_array($rule[0]) //!! Needed to silence Warning: Illegal offset type in isset or empty in parsing.php on line 52
				&& self::op($rule[0])) {
				$op = $rule[0];
				array_shift($rule); // eat it
			} else {
				$op = self::_SEQ;
			}

			$f = self::op($op);
DBG(" --> complex rule: type '$op', for rule: "
	.dump($rule) //DBG()
); //DBG()
			if (!$f) {
				throw new Exception("--WTF? Unknown operator: '$op'!");
			}
		}
		else
		{
			throw new Exception("--WTF? Broken syntax: " . print_r($rule, true));
		}

		$res = $f($this, $pos, $rule);
		++$this->loopguard;
		return $res;
	}
}

//---------------------------------------------------------------------------
Parser::$OP[Parser::_TERMINAL] = function(Parser $p, $pos, $rule)
{
//	assert(is_string($rule));
	++$p->terminals_tried;

	$str = mb_substr($p->text, $pos);
	if (Parser::atom($rule)) // atom pattern?
	{	
		$m = [];
//DBG(" -- match_term(): matching atom '$rule' (".Parser::$ATOM[$rule].") against input: '$str'");
                if (preg_match(Parser::$ATOM[$rule], $str, $m)) {
//DBG(" -- match_term(): MATCH! [$m[1]]");
			return mb_strlen($m[1]);
		} else	return false;

	}
	else if (!empty($rule) && $rule[0] == '/' && $rule[-1] == '/') // direct regex?
	{
//DBG(" -- match_term(): matching direct regex '$rule' against input: '$str'");
                if (preg_match($rule, $str, $m)) {
//DBG(" -- match_term(): MATCH! [$m[1]]");
			return mb_strlen($m[1]);
		} else	return false;
	}
	else // literal non-pattern
	{
//DBG(" -- match_term(): matching literal '$rule' against input: '$str'");
               	if (strcasecmp($str, $rule) >= 0) {
			return mb_strlen($rule);
		} else	return false;
	}
};

//---------------------------------------------------------------------------
Parser::$OP[Parser::_SEQ] = function(Parser $p, $pos, $rule)
{
//DBG(" -- match_seq() ");
//	assert(is_array($rule));
	$len = 0;
	foreach ($rule as $r)
	{
//DBG(" -- match_seq($p->text, rule): matching chunk '".mb_substr($p->text, $pos)."' against rule: ".dump($rule));
		if (($l = $p->match($pos + $len, $r)) !== false) {
			$len += $l;
		} else	return false;
	}
	return $len;
};

//---------------------------------------------------------------------------
Parser::$OP[Parser::_OR] = function(Parser $p, $pos, $rule)
{
DBG(" -- match_or() ");
//	assert(is_array($rule));
	foreach ($rule as $r)
	{
DBG(" -- match_or(): matching rule: ". dump($r));
		if (($len = $p->match($pos, $r)) !== false) {
DBG(" -- match_or(): MATCH! (pos=$pos)");
			return $len;
		} else {
DBG(" -- match_or(): failed, skipping to next, if any...");
			continue;
		}
	}
DBG(" -- match_or(): returning false");
	return false;
};

//---------------------------------------------------------------------------
Parser::$OP[Parser::_ANY] = function(Parser $p, $pos, $rule)
{
DBG(" -- match_any() ");
	assert(is_array($rule));
	assert(count($rule) == 1);

	$len = 0;
	$r = $rule[0];
	do {
$chunk = mb_substr($p->text, $pos + $len);//DBG()
DBG(" -- match_any(): iteration for '". $chunk ."'");
		if (($l = $p->match($pos + $len, $r)) === false) {
DBG(" -- match_any(): received false!");
			break;
		} else {
DBG(" -- match_any(): received len: $l");
			if ($l == 0) {
				throw new Exception("--WTF? Infinite loop (in _ANY)!");
			}
			$len += $l;
		}
	} while ($pos + $len < $p->text_length);

DBG(" -- match_any(): returning pos=$pos");
	return $len;
};

//---------------------------------------------------------------------------
Parser::$OP[Parser::_MANY] = function(Parser $p, $pos, $rule)
{
//DBG(" -- match_many_greedy() entered...");
//	assert(is_array($rule));
	assert(count($rule) == 1);

	$len = 0;
	$r = $rule[0];
	$at_least_one_match = false;
	do {
//$chunk = mb_substr($p->text, $pos + $len);//DBG()
//DBG(" -- match_many_greedy(): iteration for '". $chunk ."'");
		if (($l = $p->match($pos + $len, $r)) === false) {
//DBG(" -- match_many_greedy(): received false!");
			break;
		} else {
//DBG(" -- match_many_greedy(): received len: $l");
			$at_least_one_match = true;
			// We'd get stuck in an infinite loop if not progressing! :-o
			if ($l == 0) {
				throw new Exception("--WTF? Infinite loop (in _MANY)!");
			}
			$len += $l;
		}
	} while ($pos + $len < $p->text_length);

//$res = $at_least_one_match ? $pos : false; //DBG();
//if ($res == false) DBG(" -- match_many_greedy(): returning false");
//else               DBG(" -- match_many_greedy(): returning pos=$pos");
	return $at_least_one_match ? $len : false;
};
