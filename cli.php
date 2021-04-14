<?php
include 'CSSName2Color.php';
include 'ColorMath_body.php';

if (isset($argv)) {
	$color = new Color($argv[1]);
	$outputted = false;

	for ( $i = 2; $i < count($argv); ++$i ) {
		$tmp = explode( "=", $argv[$i], 2 );
		$var = $tmp[0];
		if ( $var == '--output' || $var == '-o' ) {
			print $color->string( $argv[++$i] ) . "\n";
			$outputted = true;
		} elseif ( $var == '--format' || $var == '-f' ) {
			print $color->customString( $argv[++$i] ) . "\n";
			$outputted = true;
		} elseif ( count( $tmp ) == 2 ) {
			$op = trim( $tmp[1], "\"'" );
			$color->apply( $var, $op );
		} else {
			$color->transform( $var );
		}
	}

	if ( ! $outputted ) { 
		$rgb = $color->rgbString();
		$hsl = $color->hslString();
		$css = $color->cssString();
		print "$rgb\n$hsl\n$css\n";
	}
}
