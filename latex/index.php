<?php 

// dvisvgm file

// echo "<pre>";

$tmpdir = 'tmp';

$latex = '\displaystyle P_\nu^{-\mu}(z)=\frac{\left(z^2-1\right)^{\frac{\mu}{2}}}{2^\mu \sqrt{\pi}\Gamma\left(\mu+\frac{1}{2}\right)}\int_{-1}^1\frac{\left(1-t^2\right)^{\mu -\frac{1}{2}}}{\left(z+t\sqrt{z^2-1}\right)^{\mu-\nu}}dt';

$latex = trim(@$_GET['latex']);
$debug = @$_GET['debug'];

// replace accidental unicode NO-BREAK SPACE
$latex = str_replace('\u00A0', ' ', $latex);

$cache_db_path = 'cache/latex.sqlite';
if (!file_exists($cache_db_path)) {
	$db = new PDO("sqlite:$cache_db_path");
	$db->exec('CREATE TABLE cache (latex PRIMARY KEY, svg);');
} else {
	$db = new PDO("sqlite:$cache_db_path");
}

if (@$_GET['delinv']) {
	// delete invalid formulas from cache
	$sql = "DELETE FROM cache WHERE svg = ''";
	$stmt = $db->prepare($sql);
	$result = $stmt->execute();
	exit;
}

$latex_key = $latex;

// check cache
$sql		= "SELECT * FROM cache WHERE latex = :latex";
$stmt	 = $db->prepare($sql);
$result = $stmt->execute(array(":latex" => $latex_key));
$cache	= $stmt->fetch(PDO::FETCH_ASSOC);

if (!$debug && $cache !== false) {
	if (substr_count($_SERVER[‘HTTP_ACCEPT_ENCODING’], ‘gzip’)) {
		ob_start(“ob_gzhandler”);
	}
	header("Content-type: image/svg+xml");
	// header('Expires: Thu, 01-Jan-70 00:00:01 GMT');
	header("Cache-Control: public, max-age=2592000"); // 30days
	echo adjustViewBox($cache['svg']);
	exit;
}

/* start wp-latex code segments */

$preamble = "\documentclass[12pt]{article}\n\usepackage[utf8]{inputenc}\n\usepackage{amsmath}\n\usepackage{amsfonts}\n\usepackage{amssymb}\n\usepackage[mathscr]{eucal}\n\usepackage{dsfont}\n\usepackage{color}\n\pagestyle{empty}";

$blacklist = array('^^');
$graylist = array('afterassignment', 'aftergroup', 'batchmode', 'catcode', 'closein', 'closeout', 'command', 'csname', 'document', 'def', 'errhelp', 'errcontextlines', 'errorstopmode', 'every', 'expandafter', 'immediate', 'include', 'input', 'jobname', 'loop', 'lowercase', 'makeat', 'meaning', 'message', 'name', 'newhelp', 'noexpand', 'nonstopmode', 'open', 'output', 'pagestyle', 'package', 'pathname', 'read', 'relax', 'repeat', 'shipout', 'show', 'scrollmode', 'special', 'syscall', 'toks', 'tracing', 'typeout', 'typein', 'uppercase', 'write');

$sizes = array(
	1 => 'large',
	2 => 'Large',
	3 => 'LARGE',
	4 => 'huge',
	-1 => 'small',
	-2 => 'footnotesize',
	-3 => 'scriptsize',
	-4 => 'tiny'
);
$s = @$_GET['s'];
$size = isset($sizes[$s]) ? $sizes[$s] : false;

if (strlen($latex)==0) exit('No formula provided');

// look for invalid code
$latexi = strtolower($latex);
foreach ($blacklist as $bad) {
	if (strpos($latexi, $bad)===false) continue;
}
foreach ($graylist as $bad) {
	if (preg_match( "#\\\\".preg_quote($bad,'#').'#', $latex)) {
		exit('Formula Invalid');
	}
	// add positive followed by negative space, just to be sure.
	// DEBUG: breaks \operatorname
	// $latex = str_replace($bad, $bad[0]."\\,\\!".substr($bad, 1), $latex);
}

// Force math mode
// Dollar sign preceeded by an even number of slashes
$ends_inline_math_mode =
	'/' .
	'(?<!\\\\)' .			// Not preceded by a single slash
	'(?:\\\\\\\\)*' .	// Even number of slashes
	'(?:\$|\\\\\\))' . // Dollar sign "$" or slash-close-paren "\)"
	'/';
if (preg_match($ends_inline_math_mode, $latex)) {
	// DEBUG: doesn't allow \hbox{$X$}
	// exit('You must stay in inline math mode');
}

if (strlen($latex)>2000) {
	exit('The formula is too long');
}

// Force math mode and add a newline before the latex so that any indentations are all even
$do_not_touch = array('\LaTeX', '\TeX', '\AmS', '\AmS-\TeX', '\AmS-\LaTeX');
if (!in_array($latex, $do_not_touch)) {
	$latex = "\$\\\\[0pt]\n$latex\n\$";
}

if ($size!==false) {
	$latex = "\\begin{{$size}}\n$latex\n\\end{{$size}}";
}

$tex = "$preamble\n\\begin{document}\n$latex\n\\end{document}\n";

/* end wp-latex code segments */

if ($debug) {
	?><form action="." method="get" accept-charset="utf-8">
		<input type="hidden" name="debug" value="true">
		<textarea name="latex" rows="8" cols="160"><?php echo htmlentities($_GET['latex'], ENT_QUOTES, "UTF-8"); ?></textarea>
		<p><input type="submit" value="Change"></p>
	</form><?php
	echo "<pre>\n";
	echo htmlspecialchars($tex);
	// exit;
}

// generate svg

$name = basename(tempnam($tmpdir, 'formula'));
file_put_contents("$tmpdir/$name.tex", $tex);
$out = shell_exec("cd $tmpdir && latex $name.tex && dvisvgm --exact --no-fonts $name.dvi");

if ($debug) {
	echo "<hr>\n";
	echo "<pre>\n";
	echo htmlspecialchars($out);
	shell_exec("rm $tmpdir/$name*");
	exit;
}
// 

header("Content-type: image/svg+xml");
$svg = file_get_contents("$tmpdir/$name.svg");

echo adjustViewBox($svg);

$sql = "INSERT INTO cache(latex, svg) VALUES(:latex, :svg);";
$stmt = $db->prepare($sql);
$result = $stmt->execute(array(":latex" => $latex_key, ":svg" => $svg));

shell_exec("rm $tmpdir/$name*");

// viewbox of \fbox{a} is "38.8543 71.6646 12.6276 11.922"
// make sure this is contained vertically for
// baseline alignment of simple characters
// $ascender = 71.6646;
// $descender = 71.6646 + 11.922; // = 83.5866
function adjustViewBox($svg, $ascender = 69, $descender = 85.45) {
	$doc = new DOMDocument();
	$doc->loadXML($svg);
	foreach ($doc->getElementsByTagName('svg') as $item) {
		$vb = explode(' ', $item->getAttribute('viewBox'));
		$y1 = $vb[1];
		$y2 = $vb[1] + $vb[3];
		if ($y1 > $ascender) $y1 = $ascender;
		if ($y2 < $descender) $y2 = $descender;
		$vb[1] = $y1;
		$vb[3] = $y2 - $y1;
		$item->setAttribute('viewBox', implode(' ', $vb));
		$item->setAttribute('height', "$vb[3]pt");
		// add color
		if (isset($_GET['fg'])) {
			// TODO: requires to add '#' to keep compatibility
			$item->setAttribute('fill', $_GET['fg']);
		}
		if (isset($_GET['color'])) {
			$item->setAttribute('fill', $_GET['color']);
		}
	}
	return $doc->saveXML();
}

