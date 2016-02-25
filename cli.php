<?php
include 'CSSName2Color.php';
include 'ColorMath_body.php';

if (isset($argv)) {
	$color = new Color($argv[1]);

	for($i = 2; $i < count($argv); ++$i) {
		$tmp = explode("=", $argv[$i], 2);
		$var = $tmp[0];
		if ( count($tmp) == 2 ) {
			$op = trim($tmp[1], "\"'");
			$color->apply($var, $op);
		} else {
			$color->transform($var);
		}
	}

	//print_r($color->getRGB());
	//print "\n";
	//print_r($color->getHSL());
	print $color->rgbString() . "\n" . $color->hslString() . "\n" . $color->cssString() . "\n";
}
