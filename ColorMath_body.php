<?php
/**
 * Search string in reverse for first matching character and return that character.
 * Return false if nothing found.
 * @param $haystack String to search
 * @param $characters This parameter is case sensitive.
 * @return string|null
 */
function strrhas( $haystack, $characters ) {
	$chars = array();
	for ( $i = strlen( $characters ) - 1; $i >= 0; --$i ) {
		$chars[$characters[$i]] = 1;
	}

	for ( $i = strlen( $haystack ) - 1; $i >= 0; --$i ) {
		if ( isset( $chars[$haystack[$i]] ) ) {
			return $haystack[$i];
		}
	}
	return null;
}

class ColorMath {
	/**
	 * @param $parser Parser
	 * @param $frame PPFrame
	 * @param $args array
	 * @return string
	 */
	public static function color( $parser, $frame, $args ) {
		$color = isset( $args[0] ) ? trim( $frame->expand( array_shift( $args ) ) ) : '';
		if ( $color === '' ) {
			return 'transparent';
		}

		// Parse color to something usable
		$color = new Color( $color );
		$output = 'css';

		// Apply modifications
		foreach( $args as $arg ) {
			$xpl = explode( '=', trim( $frame->expand( $arg ) ), 2 );
			if ( count( $xpl ) == 2 ) {
				$v = strtolower( trim( $xpl[0] ) );
				if ( $v == 'output' ) $output = trim( $xpl[0] );
				else $color->apply( $v, $xpl[1] );
			} else {
				$color->transform( $xpl[0] );
			}
		}

		if ( strncmp( $output, 'format:', 7 ) ) {
			return $color->customString( substr( $output, 7 ) );
		}
		return $color->string($output);
	}
}

class Color {
	public $rawColor = false;
	public $hsl = false;
	public $rgb = false;
	public $alpha = false;

	/**
	 * @param $color Color value
	 */
	public function __construct( $color = false ) {
		if ( $color ) $this->parse( $color );
	}

	/**
	 * @param $color Color value
	 */
	public function parse( $color ) {
		$color = preg_replace( '/^ *| *;? *$/', '', $color );
		$this->rawColor = $color;

		$cv = ' *(\d+%?|0\.\d+) *';
		if ( preg_match( "/^rgba? *\\($cv,$cv,$cv(?:,$cv)?\\)\$/i", $color, $rgb_m ) ) {
			$this->rgb = array(
				Color::RGB( $rgb_m[1] ),
				Color::RGB( $rgb_m[2] ),
				Color::RGB( $rgb_m[3] )
			);

			if ( isset( $rgb_m[4] ) ) $this->alpha = Color::A( $rgb_m[4] );
		} elseif ( preg_match( "/^hsla? *\\($cv,$cv,$cv(?:,$cv)?\\)\$/i", $color, $hsl_m ) ) {
			$this->hsl = array(
				Color::H( $hsl_m[1] ),
				Color::SL( $hsl_m[2] ),
				Color::SL( $hsl_m[3] )
			);

			if ( isset( $hsl_m[4] ) ) $this->alpha = Color::A( $hsl_m[4] );
		} elseif ( preg_match( '/^#([0-9a-f]+)$/i', $color, $hash_m ) ) {
			$color = $hash_m[1];
			$len = strlen( $color );

			$r = $g = $b = '';

			if ( $len % 3 == 0 && $len <= 6 ) {
				$spacing = $len / 3;
				$r = hexdec( substr( $color, 0, $spacing ) );
				$g = hexdec( substr( $color, $spacing, $spacing ) );
				$b = hexdec( substr( $color, -$spacing ) );

				$max = ( 1 << ( $spacing * 4 ) ) - 1;

				$this->rgb = array(
					$r = $r * 255 / $max,
					$g = $g * 255 / $max,
					$b = $b * 255 / $max
				);
			} else {
				// Failure sets transparency.
				$this->alpha = 0;
			}
		} else {
			$color = strtolower( $color );
			if ( array_key_exists( $color, CSSName2Color::$CSSName2Color ) ) {
				$this->rgb = CSSName2Color::$CSSName2Color[$color];
			} else {
				$this->alpha = 0;
			}
		}
	}

	/**
	 * Note this ignores alpha!
	 * @return CSS #RRGGBB style string
	 */
	public function hexString() {
		$rgb = $this->getRGB();
		return $rgb ? vsprintf( '#%02x%02x%02x', $rgb ) : '';
	}

	/**
	 * @param $percent Return values as a percentage instead
	 * @return CSS rgb(r, g, b) or rgba style string
	 */
	public function rgbString( $percent = false ) {
		$rgb = $this->getRGB();
		if ( $rgb === false ) return '';
		list( $r, $g, $b ) = $rgb;

		if ( $percent ) {
			$r = round( $r * 100 / 255 ) . "%";
			$g = round( $g * 100 / 255 ) . "%";
			$b = round( $b * 100 / 255 ) . "%";
			$a = round( $this->getAlpha() * 100 ) . '%';
		} else {
			$a = $this->getAlpha();
		}

		if ( $this->alpha === false ) return "rgb($r, $g, $b)";
		return "rgba($r, $g, $b, $a)";
	}

	/**
	 * @param $percent Return alpha value as a percentage instead
	 * @return CSS hsl(r, g, b) or hsla style string
	 */
	public function hslString( $percent = false ) {
		$hsl = $this->getHSL();
		if ( $hsl === false ) return '';
		list( $h, $s, $l ) = $hsl;

		if ( $this->alpha === false ) return "hsl($h, $s%, $l%)";
		$a = $percent ? round( $this->alpha * 100 ) . '%' : $this->alpha;
		return "hsla($h, $s%, $l%, $a)";
	}

	/**
	 * @return Most appropriate CSS string version
	 */
	public function cssString() {
		$hasColor = $this->rgb !== false || $this->hsl !== false;
		$hasAlpha = $this->alpha !== false;

		if ( !$hasColor && !$hasAlpha ) return '';
		// There can't be any translucency without colour...
		if ( !$hasColor && $hasAlpha ) return 'transparent';
		if ( $hasColor && !$hasAlpha ) {
			$colorName = array_search( $this->getRGB(), CSSName2Color::$CSSName2Color );
			return $colorName === false ? $this->hexString() : $colorName;
		}
		return $this->rgbString();
	}

	/**
	 * @param $format Name of format to use: css, hex, rgb, rgb%, hsl, hsl%
	 * @return Some standard CSS output form
	 */
	public function string( $format ) {
		switch ( $format ) {
		case 'hex': case 'hexadecimal': return $this->hexString();
		case 'rgb': return $this->rgbString();
		case 'rgb%': return $this->rgbString(true);
		case 'hsl': return $this->hslString();
		case 'hsl%': return $this->hslString(true);
		default: return $this->cssString();
		}
	}

	/**
	 * Accepts a formatting string similar to PHP's sprintf and Python's modulo-based formatting
	 * The conversion specification is one of the following:
	 *   %[component name$][flags][width][.precision]specifier
	 *   %[(component name)][flags][width][.precision]specifier
	 *   %{[component name:][flags][width][.precision]specifier}
	 * If the component name is not specified, it's positional, assuming the input:
	 *   red, green, blue, alpha
	 * Flags can be one of the following:
	 *   - or <  Left-justify within the given field width
	 *   >       Right-justify within the given field width (default)
	 *   ^       Center within the given field width (use with < to favor the left)
	 *   =       Place padding between sign and number
	 *   +       Prepend a + sign for positive numbers
	 *   (space) Use a space as the sign for positive numbers
	 *   0       Pad with zeroes (by default, pad with space)
	 *   '(char) Pad with the given character
	 *   #       Use with o, p, or x/X to affix with 0o, %, or 0x respectively
	 * Width is an integer specifying the minimum number of characters to write out
	 * Precision is the exact number of digits to show after the decimal place
	 * Specifier may be one of the following:
	 *    %  A literal percent sign
	 *    d  Decimal value on the scale typically associated with this component
	 *       i and u are aliases of this
	 *    f  Floating point representation between 0 and 1 (default precision is 1)
	 *       F is an alias of this
	 *    o  Octal representation of the integer value
	 *    p  Percentage value
	 *    x  Hexadecimal value with lowercase letters
	 *    X  Hexdecimal value with uppercase letters
	 * @param $format Formatting string
	 * @return Custom formatted string
	 */
	public function customString( $format ) {
		$name = '[a-zA-Z]';
		$spec = '((?:\'.|\p{S}|\p{P})*)(\d+)?(?:\.(\d+))?([a-zA-Z])'; // 4 groups
		preg_match_all(
			"/([^%]+)|(%%)|%\\{($name(?=:))?:?$spec\\}|%(?:($name)\\\$|\\(($name)\\))?$spec/",
			$format, $out, PREG_SPLIT_DELIM_CAPTURE
		);

		$idx = 0;
		$vars_by_idx = array( 'r', 'g', 'b', 'a' );
		$result = '';
		foreach ( $out as $mo ) {
			if ( $mo[1] ) {
				$result .= $mo[1];
			} elseif ( $mo[2] ) {
				$result .= '%';
			} else {
				// Collect pieces and do defaulting
				$var = strtolower( $mo[3] . $mo[8] . $mo[9] );
				if ( !$var ) $var = $vars_by_idx[$idx++];
				$flags = $mo[4] . $mo[10];
				$width = +('0' . $mo[5] . $mo[11]);
				$precision = $mo[6] . $mo[12];
				if ( !$precision ) $precision = '1';
				$specifier = $mo[7] . $mo[13];

				// Parse flags
				$i = strpos( $flags, "'" );
				if ( $i !== false ) {
					$padding = $flags[$i + 1];
					$flags = substr( $flags, 0, $i ) . substr( $flags, $i + 2 );
				} else {
					$padding = strrhas( $flags, '0' ) ?? ' ';
				}

				$pos_sign = strrhas( $flags, ' +' ) ?? '';
				$justify1 = strrhas( $flags, '^=' ) ?? '';
				$justify2 = strrhas( $flags, '-<>' ) ?? '>';
				$justify = $justify1 == '^' ? $justify1 . $justify2 : ($justify1 ? $justify1 : $justify2);
				$affix = strpos( $flags, '#' ) !== false;

				// Format number
				$value = '';
				switch ( $specifier ) {
				case 'd': case 'i': case 'u': case 'o': case 'x': case 'X':
					$v = $this->getDec( $var );
					if ( $v !== false ) {
						switch ( $specifier ) {
						case 'o': $value = ($affix ? '0o' : '') . decoct( $v ); break;
						case 'x': $value = ($affix ? '0x' : '') . dechex( $v ); break;
						case 'X': $value = ($affix ? '0x' : '') . strtoupper( dechex( $v ) ); break;
						default: $value = strval( $v );
						}
					}
					break;
				case 'f': case 'F':
					$v = $this->get( $var );
					if ( $v !== false ) $value = sprintf( "%.{$precision}F", $value );
					break;
				case 'p':
					$v = $this->get( $var );
					if ( $v !== false ) $value = round( $v * 100 ) . ($affix ? '%' : '');
					break;
				}

				// Add padding
				if ( !$value ) $pos_sign = '';
				$len = ($pos_sign ? 1 : 0) + strlen($value);
				if ( $len < $width ) {
					$amount = $width - $len;
					$padding = str_repeat( $padding, $amount );
					switch ( $justify ) {
					case '-': case '<': $final = $pos_sign . $value . $padding; break;
					case '>': $final = $padding . $pos_sign . $value; break;
					case '^<':
						$x = floor( $amount / 2 );
					case '^>':
						if ( !isset( $x ) ) $x = ceil( $amount / 2 );
						$final = substr( $padding, 0, $x ) . $pos_sign . $value . substr( $padding, $x );
						break;
					case '=': $final = $pos_sign . $padding . $value; break;
					}
				} else {
					$final = $pos_sign . $value;
				}
				$result .= $final;
			}
		}
		return $result;
	}

	/**
	 * @param $x String of color value
	 * @return Numerical value bounded to 0..255
	 */
	public static function RGB( $x, $float = false ) {
		$rgb = false;
		if ( substr( $x, -1 ) == '%' ) $rgb = ( $float ? 1 : 255 ) * +substr( $x, 0, -1 ) / 100;
		elseif ( substr( $x, 0, 2 ) == '0.' || $x[0] == '.' ) $rgb = +$x * ( $float ? 1 : 255 );
		elseif ( is_numeric( $x ) ) $rgb = +$x / ( $float ? 255 : 1 );
		return $rgb === false ? false : min( $float ? 1.0 : 255, abs( $rgb ) );
	}

	/**
	 * @param $x String of color value
	 * @return Numerical value bounded to 0..360
	 */
	public static function H( $x, $float = false ) {
		if ( is_numeric( $x ) ) return min( 360, abs( +$x ) ) / ( $float ? 360 : 1 );
		return false;
	}

	/**
	 * @param $x String of color value
	 * @return Numerical value bounded to 0..100
	 */
	public static function SL( $x, $float = false ) {
		$sl = false;
		if ( substr( $x, -1 ) == '%' ) $sl = +substr( $x, 0, -1 ) / ( $float ? 100 : 1 );
		elseif ( substr( $x, 0, 2 ) == '0.' || $x[0] == '.' ) $sl = +$x * ( $float ? 1 : 100 );
		elseif ( is_numeric( $x ) ) $sl = +$x / ( $float ? 100 : 1 );
		return $sl === false ? false : min( $float ? 1.0 : 100, abs( $sl ) );
	}

	/**
	 * @param $x String of color value
	 * @return Numerical value bounded to 0..1
	 */
	public static function A( $x ) {
		$a = false;
		if ( substr( $x, -1 ) == '%' ) $a = +substr( $x, 0, -1 ) / 100;
		elseif ( substr( $x, 0, 2 ) == '0.' || $x[0] == '.' || $x == '0' || $x == '1' ) $a = +$x;
		return $a === false ? false : min( 1.0, abs( $a ) );
	}

	public function get( $var ) {
		$var = strtolower( $var );
		switch ( $var ) {
		case 'r': case 'red':   return $this->getRGB()[0] / 255;
		case 'g': case 'green': return $this->getRGB()[1] / 255;
		case 'b': case 'blue':  return $this->getRGB()[2] / 255;
		case 'h': case 'hue':                     return $this->getHSL()[0] / 360;
		case 's': case 'sat': case 'saturation':  return $this->getHSL()[1] / 100;
		case 'l': case 'light': case 'lightness': return $this->getHSL()[2] / 100;
		case 'a': case 'alpha': return $this->getAlpha();
		}

		return false;
	}

	public function getDec( $var ) {
		$var = strtolower( $var );
		switch ( $var ) {
		case 'r': case 'red':   return $this->getRGB()[0];
		case 'g': case 'green': return $this->getRGB()[1];
		case 'b': case 'blue':  return $this->getRGB()[2];
		case 'h': case 'hue':                     return $this->getHSL()[0];
		case 's': case 'sat': case 'saturation':  return $this->getHSL()[1];
		case 'l': case 'light': case 'lightness': return $this->getHSL()[2];
		}

		return false;
	}

	public function getValAs( $var, $val ) {
		$var = strtolower( $var );
		switch ( $var ) {
		case 'r': case 'red':
		case 'g': case 'green':
		case 'b': case 'blue': return Color::RGB( $val, true );
		case 'h': case 'hue':                     return Color::H( $val, true );
		case 's': case 'sat': case 'saturation':
		case 'l': case 'light': case 'lightness': return Color::SL( $val, true );
		case 'a': case 'alpha': return Color::A( $val );
		}

		return false;
	}

	public function set( $var, $val ) {
		$var = strtolower( $var );
		$set = '';
		$val = abs( +$val );

		if ( $this->rgb === false ) $this->getRGB();
		if ( $this->hsl === false ) $this->getHSL();

		switch ( $var ) {
		case 'r': case 'red':   $this->rgb[0] = min( 255, $val * 255 ); $set='rgb'; break;
		case 'g': case 'green': $this->rgb[1] = min( 255, $val * 255 ); $set='rgb'; break;
		case 'b': case 'blue':  $this->rgb[2] = min( 255, $val * 255 ); $set='rgb'; break;
		case 'h': case 'hue':                     $this->hsl[0] = min( 100, $val * 360 ); $set='hsl'; break;
		case 's': case 'sat': case 'saturation':  $this->hsl[1] = min( 100, $val * 100 ); $set='hsl'; break;
		case 'l': case 'light': case 'lightness': $this->hsl[2] = min( 100, $val * 100 ); $set='hsl'; break;
		case 'a': case 'alpha': $this->alpha = min( 1.0, $val );
		}

		if ( $set == 'rgb' ) $this->hsl = false;
		if ( $set == 'hsl' ) $this->rgb = false;
	}

	// https://gist.github.com/brandonheyer/5254516
	public function getRGB() {
		if ( $this->rgb === false && $this->hsl !== false ) {
			// Convert HSL->RGB
			list( $h, $s, $l ) = $this->hsl;

			$s /= 100; $l /= 100;

			$c = ( 1 - abs( 2 * $l - 1 ) ) * $s;
			$x = $c * ( 1 - abs( fmod( ( $h / 60 ), 2 ) - 1 ) );
			$m = $l - ( $c / 2 );

			switch ( (int)( $h / 60 ) ) {
				case 0:  $r=$c; $g=$x; $b= 0; break;
				case 1:  $r=$x; $g=$c; $b= 0; break;
				case 2:  $r= 0; $g=$c; $b=$x; break;
				case 3:  $r= 0; $g=$x; $b=$c; break;
				case 4:  $r=$x; $g= 0; $b=$c; break;
				default: $r=$c; $g= 0; $b=$x; break;
			}

			$r = floor( ( $r + $m ) * 255 );
			$g = floor( ( $g + $m ) * 255 );
			$b = floor( ( $b + $m ) * 255 );

			$this->rgb = array( $r, $g, $b );
		}
		return $this->rgb;
	}

	public function getHSL() {
		if ( $this->hsl === false && $this->rgb !== false ) {
			// Convert RGB->HSL
			$max = max( $this->rgb ) / 255;
			$min = min( $this->rgb ) / 255;

			list( $r, $g, $b ) = $this->rgb;
			$r /= 255; $g /= 255; $b /= 255;

			$h = $s = 0;
			$l = ( $max + $min ) / 2;
			$delta = $max - $min;

			if ( $delta ) {
				$s = $delta / ( 1 - abs( 2 * $l - 1 ) );
				switch ( $max ) {
					case $r:
						$h = 60 * fmod( ( ( $g - $b ) / $delta ), 6 );
						if ( $b > $g ) $h += 360;
						break;
					case $g: $h = 60 * ( ( $b - $r ) / $delta + 2 ); break;
					case $b: $h = 60 * ( ( $r - $g ) / $delta + 4 ); break;
				}
			}

			$this->hsl = array( round( $h, 2 ), round( $s * 100, 2 ), round( $l * 100, 2 ) );
		}
		return $this->hsl;
	}

	public function getAlpha() {
		if ( $this->alpha === false ) return 1;
		else return $this->alpha;
	}

	/**
	 * @param $var Color component name
	 * @param $math Math to apply. [+-] E sets relative, E sets exact. E -> E [+-] E | component | number
	 */
	public function apply( $var, $math ) {
		$tokens = preg_split( '/([+\\-])/', $math, -1, PREG_SPLIT_DELIM_CAPTURE );

		$value = $this->get( $var );
		if ( $value === false ) return;

		$op = '';
		foreach ( $tokens as $token ) {
			switch ( $token ) {
			case '+': case '-': $op = $token; break;
			case '': break;
			default:
				$mod = $this->getValAs( $var, $token );
				if ( $mod === false ) $mod = $this->get( $token );

				switch ( $op ) {
				case '+': $value += $mod; break;
				case '-': $value -= $mod; break;
				case '':  $value = $mod; break;
				}
				$op = '';
				break;
			}
		}

		$this->set( $var, $value );
	}

	public function transform( $func ) {
		switch ( strtolower( $func ) ) {
		case 'darken': case 'dark':
			if ( array_key_exists( 'dark' . $this->rawColor, CSSName2Color::$CSSName2Color ) ) {
				$this->parse( 'dark' . $this->rawColor );
			} elseif ( substr( $this->rawColor, 0, 5 ) == 'light' && array_key_exists( substr( $this->rawColor, 5 ), CSSName2Color::$CSSName2Color ) ) {
				$this->parse( substr( $this->rawColor, 5 ) );
			} else {
				$l = $this->get( 'l' );
				if ( $l <= 0.05 ) {
					$this->set( 'l', 0 );
					$this->set( 's', $this->get( 'h' ) / 2 );
				} else {
					$this->set( 'l', $l / 2 );
				}
			}
			break;
		case 'lighten': case 'light':
			if ( array_key_exists( 'light' . $this->rawColor, CSSName2Color::$CSSName2Color ) ) {
                                $this->parse( 'light' . $this->rawColor );
			} elseif ( substr( $this->rawColor, 0, 4 ) == 'dark' && array_key_exists( substr( $this->rawColor, 4 ), CSSName2Color::$CSSName2Color ) ) {
				$this->parse( substr($this->rawColor, 4) );
                        } else {
				$l = $this->get( 'l' );
				$this->set( 'l', $l * 1.5 );
			}
			break;
		//default: unknown command...
		}
	}

}

