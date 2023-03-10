<?php error_reporting(-1);

//$DBG = true;

require_once "test.php";

//---------------------------------------------------------------------------
define('_SAVE' , '_');	// Capture source for current rule. Usage: [_SAVE _OR X Y]
define('_SAVE_Q' , '_Q');
define('_SAVE_R' , '_R');
Parser::$OP[_SAVE] = function(Parser $p, $pos, $rule)
{
	$len = $p->match($pos, $rule);
	if ($len !== false) echo " [".substr($p->text, $pos, $len) . "] ";
	return $len;
};
Parser::$OP[_SAVE_Q] = function(Parser $p, $pos, $rule)
{
	$len = $p->match($pos, $rule);
	if ($len !== false) echo " \"".substr($p->text, $pos, $len) . "\" ";
	return $len;
};
Parser::$OP[_SAVE_R] = function(Parser $p, $pos, $rule)
{
	$len = $p->match($pos, $rule);
	if ($len !== false) echo " ~".substr($p->text, $pos, $len) . "~ ";
	return $len;
};

//---------------------------------------------------------------------------
// Constructions...
//$word =	[_MANY, 'LETTER'];
//$word =	['WORD'];
//$optional_space = [_OR, 'SPACE', 'EMPTY'];
//$word_maybe_in_spaces =	[$optional_space, 'WORD', $optional_space];
/*
$wordlist = [_MANY,
		[_OR,
			'WORD',
			['WHITESPACE', 'WORD'],
		], 
            ];
*/
//---------------------------------------------------------------------------
//$WORD = 'LETTERS';
$WORD = '/[^\\s\\"\\/]+/';
//$REGEXLIKE = ['SLASH', [Parser::_ANY, [Parser::_OR, 'LETTERS', 'WHITESPACES']], [Parser::_ANY, 'SLASH']];
$REGEXLIKE = ['SLASH', [_SAVE_R, Parser::_ANY, [Parser::_OR, $WORD, 'WHITESPACES']], [Parser::_ANY, 'SLASH']];
//!!WHY IS THIS NOT DOING WHAT I THINK? :)
//!!$REGEXLIKE = ['SLASH', [Parser::_OR, 'LETTERS', 'WHITESPACES', 'EMPTY'], [Parser::_ANY, 'SLASH']];
//$QUOTED = ['QUOTE', [Parser::_ANY, [Parser::_OR, 'LETTERS', 'WHITESPACES']], [Parser::_ANY, 'QUOTE']];
$QUOTED = ['QUOTE', [_SAVE_Q, Parser::_ANY, [Parser::_OR, $WORD, 'WHITESPACES']], [Parser::_ANY, 'QUOTE']];
//!!WHY IS THIS NOT DOING WHAT I THINK? :)
//!!$QUOTED = ['QUOTE', [Parser::_OR, 'LETTERS', 'WHITESPACES', 'EMPTY']], [Parser::_ANY, 'QUOTE']];
$TERM = [Parser::_OR, [_SAVE, $WORD], $QUOTED, $REGEXLIKE];
//$TERM = [Parser::_OR, $WORD, $REGEXLIKE];
//$TERM = [Parser::_OR, $REGEXLIKE, $QUOTED];
$QUERY = $TERM;
$QUERY = [Parser::_MANY, [$TERM, [Parser::_ANY, 'WHITESPACES']]];
	
//$s = $word;
//$s = $word_maybe_in_spaces;
//$s = $wordlist;
$s = $WORD;
$s = $REGEXLIKE;
$s = $QUOTED;
$s = $TERM;
$s = $QUERY;

if (!empty($_GET)) {
	$syntaxname = $_GET['s'];
	if (!isset($$syntaxname)) { echo "-- Unknown syntax: '$syntaxname'!"; die; }
	$text = $_GET['t'];
	test($$syntaxname, $text);
	die;
}

//test($s, "a");

test($s, "");
test($s, " ");
test($s, "egy");
test($s, " egy ");
test($s, " egy  ");
test($s, "  x  ");
test($s, "  egy  ");

echo "<h1>PHRASE...</h1>";

test($s, "egy kett??");
test($s, "egy k??t h??");
test($s, " egy k??t ");
test($s, "a ! b");

echo "<h1>REX...</h1>";

test($s, "/");
test($s, "//");
test($s, "/x/");
test($s, " /x/");
test($s, "/x");
test($s, '/ket t??/');
test($s, '/kett??/ "h??rom"');
test($s, '/k ett??/ "h ??rom"');

echo "<h1>QUOTED...</h1>";

test($s, '"');
test($s, '""');
test($s, '"x"');
test($s, ' "x"');
test($s, '"x');
test($s, '"ket t??"');
test($s, '"kett??" "h??rom"');
test($s, '"k ett??" "h ??rom"');

echo "<h1>MIXED...</h1>";

test($s, "egy /ket t??/");
test($s, "egy /kett??/ \"h??rom\"");
test($s, 'a "b" c');
test($s, 'a b "c');
test($s, '/k/ e');
test($s, 'e /k/');

test($s, '/k/ "q"');
test($s, '"q" /k/');

test($s, 'e /k/ e');
test($s, '/a/ b /c/');
test($s, '/a/ b /c/ d');
test($s, 'e /k/ "h"');
test($s, 'w /r/ w "q"');
test($s, 'w w /r r/ w w "q q"');

echo "<h1>REAL-WORLD EXAMPLES...</h1>";

test($s, 'egy /k ett??/ k??t "h ??rom"');	// recursion depth 105 with array model, and 74 with the string model! :-o
test($s, 'qqqqqqq egy /k ett??/ ddd"ddd dd"ddk??t "h ??rom"');

test($s, 'egy /k??t/ "h /??rom"');
test($s, 'egy /k ett?? k??t "h ??rom');
test($s, 'egy kett?? h??rom');
test($s, 'egy kett?? h??rom n??gy');
test($s, 'egy kett?? "h?? rom" n??gy');

echo "<h1>MORE ACCENTED...</h1>";

test($s, 'n??gy /k ett??/ k??t "h ??rom"');	// recursion depth 105 with array model, and 74 with the string model! :-o
test($s, '??qqqq?? egy /k ett??/ ddd"ddd dd"ddk??t "h ??rom"');

test($s, 'n??gy /k??t/ "h /??rom"');
test($s, 'n??gy /k ett?? ket "h ??rom');
test($s, 'n??gy kett?? h??rom');
test($s, 'n??gy kett?? h??rom n??gy');
test($s, '??t n??gy "h?? rom" kett?? "egy');
