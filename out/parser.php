<?php
/* 
  A simplistic recursive descent LL parser for simple, ad-hoc tasks
  v0.3
*/


//---------------------------------------------------------------------------
class Parser
{
	const DEFAULT_RECURSION_LIMIT = 500;

	//---------------------------------------------------------------------------
	// Grammar operators...
	//
	// Can be referred to either as Parser::_SOME_OP, or as the 'literal' names.
	// Can be freely extended by users (in sync with the ::$OP map below).
	const _TERMINAL = '#';  // Implicit internal operation, defined here only for a more uniform match()
	const _SEQ	= ',';	// Default for just listing rules.
	const _OR	= '|';
	const _MANY	= '+';	// 1 or more (greedy); must be followed by exactly 1 rule
	const _ANY	= '*';	// 0 or more (greedy); shortcut to [_OR [_MANY X] EMPTY]; must be followed by exactly 1 rule
	                        // "greedy" above means that [A...]A will never match! Be careful!
	const _OPT      = '?';  // 0 or 1; must be followed by exactly 1 rule

	// Operator implementations...
	// 
	// Will be populated later below, according to:
	// ::$OP[Parser::_SOME_OP] = function(Parser $p, $input_pos, $rule) { ... return match-length or false; }
	// Can be freely extended by users (in sync with the keyword list above).
	static $OP = [];

	// Atoms (terminal pattens) - just "metasyntactic sugar", as they could 
	// as well be just literal patterns. But fancy random regex literals could
	// confuse the parsing, so these "officially" nicely behaving ones are just 
	// quarantined and named here.
	// (BTW, a user pattern that's not anchored to the left is guaranteed to 
	// fail, as the relevant preg_match() call only returns the match length.
	// It could be extended though, but I'm not sure about multibyte support,
	// apart from my suspicion that mbstring *doesn't* have a position-capturing
	// preg_match (only ereg_... crap).)
	// NOTE: PCRE *is* UNICODE-aware! --> http://pcre.org/original/doc/html/pcreunicode.html
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

		$this->loopguard = $this->depth_reached = $maxnest;
		$this->rules_tried = 0;
		$this->terminals_tried = 0;

		return $this->match(0, $syntax);
	}

	//-------------------------------------------------------------------
	public function match($pos, $rule)
	// $pos is the source position
	// $rule is a syntax rule (tree node)
	// If matches, returns the length of the matched input, otherwise false.
	{
		++$this->rules_tried;
		if ($this->depth_reached > --$this->loopguard)
		    $this->depth_reached =   $this->loopguard;
		if (!$this->loopguard) {
			throw new Exception("--WTF? Infinite loop (in 'match()')!<br>\n");
		}

		if (self::term($rule)) // Terminal rule: atom or literal pattern.
		{
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
	assert(is_string($rule));
	++$p->terminals_tried;

	$str = mb_substr($p->text, $pos);
	if (Parser::atom($rule)) // atom pattern?
	{	
		$m = [];
                if (preg_match(Parser::$ATOM[$rule], $str, $m)) {
			return mb_strlen($m[1]);
		} else	return false;

	}
	else if (!empty($rule) && $rule[0] == '/' && $rule[-1] == '/') // direct regex?
	{
                if (preg_match($rule, $str, $m)) {
			return mb_strlen($m[1]);
		} else	return false;
	}
	else // literal non-regex pattern
	{
		$l = strlen($rule);	//! Not not mb_... because strncasecmp() will be used below! (I couldn't find a practical mb_strncasecmp(). :-o )
		if (strncasecmp($str, $rule, $l) == 0) { //! might fail to ignore case for UNICODE, but nothing worse, hopefully!
			return mb_strlen($rule); //!! Need to be consistent with the other positions (returned above)!
		} else	return false;
	}
};

//---------------------------------------------------------------------------
Parser::$OP[Parser::_SEQ] = function(Parser $p, $pos, $rule)
{
	$len = 0;
	foreach ($rule as $r)
	{
		if (($l = $p->match($pos + $len, $r)) !== false) {
			$len += $l;
		} else	return false;
	}
	return $len;
};

//---------------------------------------------------------------------------
Parser::$OP[Parser::_OR]  = function(Parser $p, $pos, $rule)
{
	assert(count($rule) > 1);

	foreach ($rule as $r)
	{
		if (($len = $p->match($pos, $r)) !== false) {
			return $len;
		} else {
			continue;
		}
	}
	return false;
};

//---------------------------------------------------------------------------
// Could just be _ANY{1}
Parser::$OP[Parser::_OPT] = function(Parser $p, $pos, $rule)
{
	assert(count($rule) == 1);

	if (($len = $p->match($pos, $rule)) !== false) {
		return $len;
	} else {
		return 0;
	}
};

//---------------------------------------------------------------------------
Parser::$OP[Parser::_ANY] = function(Parser $p, $pos, $rule)
{
	assert(count($rule) == 1);

	$len = 0;
	$r = $rule[0];
	do {
		if (($l = $p->match($pos + $len, $r)) === false) {
			break;
		} else {
			if ($l == 0) {
				throw new Exception("--WTF? Infinite loop (in _ANY)!");
			}
			$len += $l;
		}
	} while ($pos + $len < $p->text_length);

	return $len;
};

//---------------------------------------------------------------------------
Parser::$OP[Parser::_MANY] = function(Parser $p, $pos, $rule)
{
	assert(count($rule) == 1);

	$len = 0;
	$r = $rule[0];
	$at_least_one_match = false;
	do {
		if (($l = $p->match($pos + $len, $r)) === false) {
			break;
		} else {
			$at_least_one_match = true;
			// We'd get stuck in an infinite loop if not progressing! :-o
			if ($l == 0) {
				throw new Exception("--WTF? Infinite loop (in _MANY)!");
			}
			$len += $l;
		}
	} while ($pos + $len < $p->text_length);

	return $at_least_one_match ? $len : false;
};
