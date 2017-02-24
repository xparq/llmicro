<head>
<meta charset="utf-8">
</head>
<?php error_reporting(-1);

define('MY_RECURSION_DEPTH', 150);

$t1 = microtime(true);

$DBG = true;
$DBG = false;

$PARSER_SCRIPT = "out/parser.php";
if (!file_exists($PARSER_SCRIPT)) {
	echo "Run `build` to create '$PARSER_SCRIPT', or just copy over the original!";
	die;
}
require $PARSER_SCRIPT;

//---------------------------------------------------------------------------
function test($syntax, $text)
{
	echo("<hr><pre>Testing: \"$text\"...</pre>");

	Parser::$loopguard = MY_RECURSION_DEPTH;

	$src = $text;
	$res = Parser::match($src, $syntax);
	if ($res !== false) {
		echo("<p style='color:green;'><b>MATCHED: '"
				. substr($text, 0, $res) ."'"
				."</b></p>\n");
	} else {
		echo("<p style='color:red;'>FAILED.</p>");
	}
	echo "<p>Recursion depth: " . (MY_RECURSION_DEPTH - Parser::$loopguard) . "</p>";
}

//---------------------------------------------------------------------------
// "Userland" demo:
define('_SAVE' , '_');	// Capture source for current rule. Usage: [_SAVE _OR X Y]
Parser::$OP[_SAVE] = function($seq, $rule)
{
	$res = Parser::match($seq, $rule);
echo " [".substr($seq, 0, $res) . "] ";
	return $res;
};


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
//$WORD = 'WORD';
$WORD = '/^([^\\s\\"\\/]+)/';
$REGEXLIKE = ['SLASH', [_ANY, [_OR, 'LETTER', 'WHITESPACE']], [_ANY, 'SLASH']];
//$REGEXLIKE = ['REGEX_DELIM', [_MANY, [_OR, 'LETTER', 'WHITESPACE']], 'REGEX_DELIM'];
$QUOTED = ['QUOTE', [_ANY, [_OR, 'LETTER', 'WHITESPACE']], [_ANY, 'QUOTE']];
//$QUOTED = ['QUOTE', [_MANY, [_OR, 'LETTER', 'WHITESPACE']], 'QUOTE'];
$TERM = [_SAVE, _OR, $WORD, $QUOTED, $REGEXLIKE];
//$TERM = [_OR, $WORD, $REGEXLIKE];
//$TERM = [_OR, $REGEXLIKE, $QUOTED];
$QUERY = $TERM;
$QUERY = [_MANY, [$TERM, [_ANY, 'WHITESPACE']]];
	
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

echo "PHRASE...<br>";

test($s, "egy ketto");
test($s, "egy ket ha");
test($s, " egy ket ");
test($s, "a ! b");

echo "REX...<br>";

test($s, "/");
test($s, "//");
test($s, "/x/");
test($s, " /x/");
test($s, "/x");
test($s, '/ket to/');
test($s, '/ketto/ "harom"');
test($s, '/k etto/ "h arom"');

echo "QUOTED...<br>";

test($s, '"');
test($s, '""');
test($s, '"x"');
test($s, ' "x"');
test($s, '"x');
test($s, '"ket to"');
test($s, '"ketto" "harom"');
test($s, '"k etto" "h arom"');

echo "PHRASE...<br>";

test($s, "egy /ket to/");
test($s, "egy /ketto/ \"harom\"");
test($s, "a ! b");
test($s, 'a b');
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

echo "REAL-WORLD EXAMPLES...<br>";

test($s, 'egy /k etto/ ket "h arom"');	// recursion depth 105 with array model, and 74 with the string model! :-o
test($s, 'qqqqqqq egy /k etto/ ddd"ddd dd"ddket "h arom"');

test($s, 'egy /ket/ "h /arom"');
test($s, 'egy /k etto ket "h arom');
test($s, 'egy ketto harom');
test($s, 'egy ketto harom negy');
test($s, 'egy ketto "ha rom" negy');

echo "ACCENTED...<br>";

test($s, 'egy /k ettő/ ket "h árom"');	// recursion depth 105 with array model, and 74 with the string model! :-o
test($s, 'qqqqqqq egy /k etto/ ddd"ddd dd"ddket "h arom"');

test($s, 'egy /ket/ "h /árom"');
test($s, 'egy /k ettő ket "h árom');
test($s, 'egy kettő három');
test($s, 'egy kettő három nágy');
test($s, 'öt négy "há rom" kettő "egy');

echo "Timing: " . (microtime(true) - $t1);
