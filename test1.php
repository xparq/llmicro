<?php error_reporting(-1);

//$DBG = true;

require_once "test.php";


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
define('_SAVE' , '_');	// Capture source for current rule. Usage: [_SAVE _OR X Y]
Parser::$OP[_SAVE] = function(Parser $p, $pos, $rule)
{
	$len = $p->match($pos, $rule);
	if ($len !== false) echo " [".substr($p->text, $pos, $len) . "] ";
	return $len;
};

//---------------------------------------------------------------------------
//$WORD = 'LETTERS';
$WORD = '/[^\\s\\"\\/]+/';
//OK:
//$REGEXLIKE = ['SLASH', [_SAVE, Parser::_ANY, [Parser::_OR, $WORD, 'WHITESPACES'], ], [Parser::_ANY, 'SLASH']];
//wrong:
//$REGEXLIKE = ['SLASH', [_SAVE, [[Parser::_ANY, [Parser::_OR, $WORD, 'WHITESPACES']], $WORD]], 'SLASH'];
//?
$REGEXLIKE = ['SLASH', [_SAVE,
		[Parser::_ANY, [Parser::_OR, $WORD, 'WHITESPACES']],
//		$WORD,
], 'SLASH'];

//$TERM = [Parser::_OR, $WORD, $REGEXLIKE];
//$QUERY = [Parser::_MANY, [$TERM, [Parser::_ANY, 'WHITESPACE']]];

//$s = $word;
//$s = $word_maybe_in_spaces;
//$s = $wordlist;
$s = $WORD;
$s = $REGEXLIKE;
//$s = $TERM;
//$s = $QUERY;

if (!empty($_GET)) {
	$syntaxname = $_GET['s'];
	if (!isset($$syntaxname)) { echo "-- Unknown syntax: '$syntaxname'!"; die; }
	$text = $_GET['t'];
	test($$syntaxname, $text);
	die;
}

echo "<h1>OPT/OR...</h1>";

$o = ['one', ['?', ' '], ['?', 'two']];
echo "<h2>All these should match:</h2>";
test($o, "one");
test($o, "one ");
test($o, "one xxx");
test($o, "onexxx");
test($o, "onetwo");
test($o, "one two");
test($o, "one twoxxx");

echo "<h2>OK OK FAIL! FAIL! OK OK FAIL!</h2>";
$o = ['one', ['?', ' '], ['?', 'kettő'], ['?', ' '], 'vége'];
test($o, "one vége");	// OK
test($o, "onevége");	// OK
test($o, "one xxx vége");	// FAIL!
test($o, "onexxx vége");	// FAIL!
test($o, "onekettő vége");	// OK
test($o, "one kettő vége");	// OK
test($o, "one kettőxxx vége");	// FAIL!

echo "<h1>REX...</h1>";
echo "<h2>All but the first should match:</h2>";
test($s, "/");
test($s, "//");
test($s, "/x/");
test($s, "/ x/");
test($s, '/x /');
test($s, '/a b/');
