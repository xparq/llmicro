<style>
.input { background: #efffff; padding: 2px; border: 1px solid lightblue; }
</style>
<?php error_reporting(-1);

//Not needed for my test env:
//header('Content-Type: text/html; charset=utf-8');
//ini_set("mbstring.language", "Neutral");
//ini_set("internal_encoding", "UTF-8");

define('MY_RECURSION_DEPTH', 150);

$PARSER_SCRIPT = ($DBG?? false) ? "parser.php" : "out/parser.php";
if (!file_exists($PARSER_SCRIPT)) {
	echo "Run `build` to create '$PARSER_SCRIPT', or just copy over the original!";
	die;
}
require $PARSER_SCRIPT;

if (!isset($DBG)) $DBG = false;

//---------------------------------------------------------------------------
function test($syntax, $text)
{
	static $test_case = 0;

	echo "<hr><b>Test #" . ++$test_case . ":</b>";
	echo '<pre>Input: <span class="input">&gt;'.$text.'&lt;</span></pre>';

	$p = new Parser();

	$res = $p->parse($text, $syntax, MY_RECURSION_DEPTH);
	if ($res !== false) {
		echo("<p style='color:green;'><b>MATCHED: '"
				. substr($text, 0, $res) ."'"
				."</b></p>\n");
	} else {
		echo("<p style='color:red;'>FAILED.</p>");
	}
	echo "<p>"
	   . "Recursion depth: " . (MY_RECURSION_DEPTH - $p->depth_reached) . "<br>";
	echo "Rules tried: " . $p->rules_tried . ". "
	   . "Terminal patterns: " . $p->terminals_tried . "."
	   . "</p>";
}
