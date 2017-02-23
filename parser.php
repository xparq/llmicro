<?php error_reporting(-1);
/*
NOTES:
	Currently only testing the leftmost portion of the input against 
	the current rule, ignoring any leftover text on the right, so a
	successful match may not be a "full" match.

	I.e. the first "deep match" wins, regardless of consuming all the
	text or only some left-side chunk or if; the priority is satisfying
	the rules, not eating all the input.

	A simple test after-the-fact for any residual text left is enough 
	to check for a full match anyway.
*/

// Operators...
define('_SAVE'	, '_');	// "Emit code" for current rule. Usage: [_SAVE _OR X Y]
define('_SEQ'	, ',');	// Default for just listing rules.
define('_OR'	, '|');
define('_SOME'	, '...');// 1 or more, not greedy!
define('_MANY'	, '+');	// 1 or more, greedy!
define('_ANY'	, '*');	// 0 or more; shortcut to [_OR [_MANY X] EMPTY]

$OP[_SAVE]	= _SAVE;
$OP[_SEQ]	= _SEQ;
$OP[_OR]	= _OR;
$OP[_SOME]	= _SOME;// must be followed by exactly 1 rule
$OP[_MANY]	= _MANY;// must be followed by exactly 1 rule
$OP[_ANY]	= _ANY;	// must be followed by exactly 1 rule

// Atoms ("terminal token pattens")
// They could as well be used as literals, but too fancy regex literals 
// might confuse the parser, so these nicely behaving patterns are just 
// quarantined and named here...
$ATOM['EMPTY'] = '/^()/';
$ATOM['SPACE'] = '/^(\\s)/';
$ATOM['TAB'] = '/^(\\t)/';
$ATOM['WHITESPACE'] = '/^([\\s]+)/';
$ATOM['QUOTE'] = '/^(\\")/';
$ATOM['LETTER'] = '/^([\\w])/'; // more like ALPHANUM or IDCHAR...
$ATOM['WORD'] = '/^([\\w]+)/';
$ATOM['SLASH'] = '~^(\\/)~';
$ATOM['REGEX_DELIM'] = $ATOM['SLASH'];

//---------------------------------------------------------------------------
function tokenize($str)
{
	return preg_split('//', $str, null, PREG_SPLIT_NO_EMPTY);
}

function stringize($seq)
{
	//return implode($seq);
	return $seq;
}

function dump($mixed)
{
	return "<pre style='margin:0 0 0 2em; background:#ffff90;'>" . print_r($mixed, true) . "</pre>";
}

function DBG($msg)
{	global $DBG;
	if (!$DBG) return;
	echo $msg ."<br>\n";
}

//---------------------------------------------------------------------------
function atom($rule)	{ global $ATOM; return isset($ATOM[$rule]); }
function op($rule)	{ global $OP;   return isset($OP[$rule]); }
function term($rule)	{ return is_string($rule); }
function constr($rule)	{ return is_array($rule) && !empty($rule); }


//---------------------------------------------------------------------------
function match_term($seq, $rule)
{
	global $ATOM;
//	assert(is_array($seq));
	assert(is_string($seq));
	assert(is_string($rule));

//	$str = stringize($seq);
	$str = $seq;

	if (atom($rule)) { // atom pattern
		$m = [];
DBG(" -- match_term(): matching atom '$rule' ($ATOM[$rule]) against input: '$str'");
                if (preg_match($ATOM[$rule], $str, $m)) {
DBG(" -- match_term(): MATCH! [$m[1]]");
                	return strlen($m[1]);
		} else {
			return false;
		}
	} else if (!empty($rule) && $rule[0] == '/' && $rule[-1] == '/') {
		// Direct regex literals in the syntax!
DBG(" -- match_term(): matching direct regex '$rule' against input: '$str'");
                if (preg_match($rule, $str, $m)) {
DBG(" -- match_term(): MATCH! [$m[1]]");
                	return strlen($m[1]);
//			return $m[1];
		} else {
			return false;
		}
	} else { // literal
DBG(" -- match_term(): matching literal '$rule' against input: '$str'");
               	if (strcasecmp($str, $rule) < 0) {
			return false;
		} else {
	               	return strlen($rule);
		}
	}
}

function match_seq($seq, $rule)
{
DBG(" -- match_seq() ");
	assert(is_string($seq));
	assert(is_array($rule));

	$pos = 0;
	foreach ($rule as $r)
	{
		$chunk = substr($seq, $pos);
		if (($len = match($chunk, $r)) === false) {
			return false;
		} else {
			$pos += $len;
		}
	}

	return $pos;
}

function match_or($seq, $rule)
{
DBG(" -- match_or() ");
	assert(is_string($seq));
	assert(is_array($rule));

	foreach ($rule as $r)
	{
DBG(" -- match_or(): matching rule: ". dump($r));
		if (($pos = match($seq, $r)) !== false) {
DBG(" -- match_or(): MATCH! (pos=$pos)");
			return $pos;
		} else {
DBG(" -- match_or(): failed, skipping to next, if any...");
			continue;
		}
	}

DBG(" -- match_or(): returning false");
	return false;
}


function match_any($seq, $rule)
{
DBG(" -- match_any() ");
	assert(is_string($seq));
	assert(is_array($rule));
	assert(count($rule) == 1);

	$pos = 0;
	$r = $rule[0];
	do {
		$chunk = substr($seq, $pos);
DBG(" -- match_any(): iteration for '".stringize($chunk) ."'");
		if (($len = match($chunk, $r)) === false) {
DBG(" -- match_any(): received false!");
			break;
		} else {
DBG(" -- match_any(): received len: $len");
			if ($len == 0) {
echo("--WTF? Infinite loop (in _ANY)!");
die;
			}
			$pos += $len;
		}
	} while (!empty($chunk));

DBG(" -- match_any(): returning pos=$pos");
	return $pos;
}


//---------------------------------------------------------------------------
function match_many($seq, $rule)
{
DBG(" -- match_many() entered...");
	assert(is_string($seq));
	assert(is_array($rule));
	assert(count($rule) == 1);

	$pos = 0;
	$r = $rule[0];
	$at_least_one_match = false;
	do {
		$chunk = substr($seq, $pos);
DBG(" -- match_many(): iteration for '".stringize($chunk) ."'");
		if (($len = match($chunk, $r)) === false) {
DBG(" -- match_many(): received false!");
			break;
		} else {
DBG(" -- match_many(): received len: $len");
			$at_least_one_match = true;
			$pos += $len;
			break;
		}
	} while (!empty($chunk));

$res = $at_least_one_match ? $pos : false;
if ($res == false) DBG(" -- match_many(): returning false");
else               DBG(" -- match_many(): returning pos=$pos");
	return $at_least_one_match ? $pos : false;
}

//---------------------------------------------------------------------------
function match_many_greedy($seq, $rule)
{
DBG(" -- match_many_greedy() entered...");
	assert(is_string($seq));
	assert(is_array($rule));
	assert(count($rule) == 1);

	$pos = 0;
	$r = $rule[0];
	$at_least_one_match = false;
	do {
		$chunk = substr($seq, $pos);
DBG(" -- match_many_greedy(): iteration for '".stringize($chunk) ."'");
		if (($len = match($chunk, $r)) === false) {
DBG(" -- match_many_greedy(): received false!");
			break;
		} else {
DBG(" -- match_many_greedy(): received len: $len");
			$at_least_one_match = true;
			// We'd get stuck in an infinite loop if not progressing! :-o
			if ($len == 0) {
echo("--WTF? Infinite loop (in _MANY)!");
die;
			}
			$pos += $len;
		}
	} while (!empty($chunk));

$res = $at_least_one_match ? $pos : false;
if ($res == false) DBG(" -- match_many_greedy(): returning false");
else               DBG(" -- match_many_greedy(): returning pos=$pos");
	return $at_least_one_match ? $pos : false;
}

//---------------------------------------------------------------------------
function match($seq, $rule)
// $seq is a SEQUENCE of syntaxt constructs.
// If $seq matches $rule, it returns the position true, otherwise false.
{
	global $ATOM, $OP, $loopguard;

	if (!--$loopguard) {
echo ("--WTF? Infinite loop (in 'match()')!<br>\n");
die;
	}

DBG("match(): input '".stringize($seq)."' against rule: ".dump($rule));
	// Terminal rule! Atom pattern or literal.
	if (term($rule))
	{
DBG(" --> terminal rule: ".dump($rule));
		return match_term($seq, $rule);
	}
	else if (constr($rule))
	{
		$save_match = 0;
		if ($rule[0] == _SAVE) {
			$save_match = 1;
			array_shift($rule); // eat it
		}
		
		// First item is the op. of the rule, or else _SEQ is assumed:
		if (!is_array($rule[0]) //!! Needed to silence Warning: Illegal offset type in isset or empty in parsing.php on line 52
			&& op($rule[0])) {
			$op = $rule[0];
			array_shift($rule); // eat it
		} else {
			$op = _SEQ;
		}

DBG(" --> complex rule: type '$op'"
//	.dump($rule)
);
	  if (!$save_match) {

		switch ($op)
		{
		case _SEQ:
			return match_seq($seq, $rule);
			break;
		case _OR:
			return match_or($seq, $rule);
			break;
		case _ANY:
			return match_any($seq, $rule);
		case _SOME:
			return match_many($seq, $rule);
		case _MANY:
			return match_many_greedy($seq, $rule);
			break;
		default:
			echo("--WTF? Unknown operator: '$op'!");
			die;
		}

	  } else {

		switch ($op)
		{
		case _SEQ:
			$res = match_seq($seq, $rule);
echo " [".substr(stringize($seq), 0, $res) . "] ";
			return $res;
		case _OR:
			$res = match_or($seq, $rule);
echo " [".substr(stringize($seq), 0, $res) . "] ";
			return $res;
		case _ANY:
			$res = match_any($seq, $rule);
echo " [".substr(stringize($seq), 0, $res) . "] ";
			return $res;
		case _SOME:
			$res = match_many($seq, $rule);
echo " [".substr(stringize($seq), 0, $res) . "] ";
			return $res;
		case _MANY:
			$res = match_many_greedy($seq, $rule);
echo " [".substr(stringize($seq), 0, $res) . "] ";
			return $res;
		default:
			echo("--WTF? Unknown operator: '$op'!");
			die;
		}
	  }
	}
	else
	{
		etc("--WTF? Broken syntax: " . dump($rule));
		die;
	}
}
