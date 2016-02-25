<?php
class ColorMath {
	/**
	 * @param $parser Parser
	 * @param $frame PPFrame
	 * @param $args array
	 * @return string
	 */
	public static function color( $parser, $frame, $args ) {
		$color = isset( $args[0] ) ? trim( $frame->expand( $args[0] ) ) : '';
		if ( $color === '' ) {
			return "transparent";
		}

		// Parse color to something usable
		$color = new Color($color);

		// Apply modifications
		foreach($args as $idx => $arg) {
			if ( $idx == 0 ) continue;
			$xpl = explode("=", trim( $frame->expand( $arg ) ), 2);
			if ( count($xpl) == 2 ) $color->apply($xpl[0], $xpl[1]);
			else $color->transform($xpl[0]);
		}

		return $color->cssString();
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
	public function Color( $color = false ) {
		if ( $color ) $this->parse($color);
	}

	/**
	 * @param $color Color value
	 */
	public function parse( $color ) {
		$color = preg_replace("/^ *| *;? *$/", "", $color);
		$this->rawColor = $color;

		$cv = ' *(\d+%?|0.\d+) *';
		if (preg_match("/^rgba? *\\($cv,$cv,$cv(,$cv)?\\)$/i", $color, $rgb_m)) {
			$this->rgb = array(
				Color::RGB($rgb_m[1]),
				Color::RGB($rgb_m[2]),
				Color::RGB($rgb_m[3])
			);

			if ( isset($rgb_m[4]) ) $this->alpha = Color::A($rgb_m[4]);
		} else if (preg_match("/^hsla? *\\($cv,$cv,$cv(,$cv)?\\)$/i", $color, $hsl_m)) {
			$this->hsl = array(
				Color::H($hsl_m[1]),
				Color::SL($hsl_m[2]),
				Color::SL($hsl_m[3])
			);

			if ( isset($hsl_m[4]) ) $this->alpha = Color::A($hsl_m[4]);
		} else if (preg_match("/^#([0-9a-f]+)$/i", $color, $hash_m)) {
			$color = $hash_m[1];
			$len = strlen($color);

			$r = $g = $b = "";

			if ( $len % 3 == 0 && $len <= 6 ) {
				$spacing = $len / 3;
				$r = hexdec(substr($color, 0, $spacing));
				$g = hexdec(substr($color, $spacing, $spacing));
				$b = hexdec(substr($color, -$spacing));

				$max = (1 << ($spacing * 4)) - 1;

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
			$color = strtolower($color);
			if ( array_key_exists($color, CSSName2Color::$CSSName2Color) ) {
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
		return $rgb ? vsprintf("#%02x%02x%02x", $rgb) : "";
	}

	/**
	 * @return CSS rgb(r, g, b) or rgba style string
	 */
	public function rgbString($percent = false) {
		$rgb = $this->getRGB();
		if ( $rgb === false ) return "";
		list($r, $g, $b) = $rgb;

		if ( $percent ) {
			$r = "$r%";
			$g = "$g%";
			$b = "$b%";
		}

		if ( $this->alpha === false ) return "rgb($r, $g, $b)";
		return "rgba($r, $g, $b, " . $this->alpha . ")";
	}

	/**
	 * @return CSS hsl(r, g, b) or hsla style string
	 */
	public function hslString() {
		$hsl = $this->getHSL();
		if ( $hsl === false ) return "";
		list($h, $s, $l) = $hsl;

		if ( $this->alpha === false ) return "hsl($h, $s%, $l%)";
		return "hsla($h, $s%, $l%, " . $this->alpha . ")";
	}

	/**
	 * @return Most appropriate CSS string version
	 */
	public function cssString() {
		$hasColor = $this->rgb !== false || $this->hsl !== false;
		$hasAlpha = $this->alpha !== false;

		if ( !$hasColor && !$hasAlpha ) return "";
		// There can't be any translucency without colour...
		if ( !$hasColor && $hasAlpha ) return "transparent";
		if ( $hasColor && !$hasAlpha ) {
			$colorName = array_search($this->getRGB(), CSSName2Color::$CSSName2Color);
			return $colorName === false ? $this->hexString() : $colorName;
		}
		return $this->rgbString();
	}

	/**
	 * @param $x String of color value
	 * @return Numerical value bounded to 0..255
	 */
	public static function RGB( $x, $float = false ) {
		$rgb = false;
		if ( substr($x, -1) == "%" ) $rgb = ($float ? 1 : 255) * +substr($x, 0, -1) / 100;
		else if ( substr($x, 0, 2) == '0.' || $x[0] == '.' ) $rgb = +$x * ($float ? 1 : 255);
		else if ( is_numeric($x) ) $rgb = +$x / ($float ? 255 : 1);
		return $rgb === false ? false : min($float ? 1.0 : 255, abs($rgb));
	}

	/**
	 * @param $x String of color value
	 * @return Numerical value bounded to 0..360
	 */
	public static function H( $x, $float = false ) {
		if ( is_numeric($x) ) return min(360, abs(+$x)) / ($float ? 360 : 1);
		return false;
	}

	/**
	 * @param $x String of color value
	 * @return Numerical value bounded to 0..100
	 */
	public static function SL( $x, $float = false ) {
		$sl = false;
		if ( substr($x, -1) == "%" ) $sl = +substr($x, 0, -1) / ($float ? 100 : 1);
		else if ( substr($x, 0, 2) == '0.' || $x[0] == '.' ) $sl = +$x * ($float ? 1 : 100);
		else if ( is_numeric($x) ) $sl = +$x / ($float ? 100 : 1);
		return $sl === false ? false : min($float ? 1.0 : 100, abs($sl));
	}

	/**
	 * @param $x String of color value
	 * @return Numerical value bounded to 0..1
	 */
	public static function A( $x ) {
		$a = false;
		if ( substr($x, -1) == "%" ) $a = +substr($x, 0, -1) / 100;
		else if ( substr($x, 0, 2) == '0.' || $x[0] == '.' || $x == '0' || $x == '1' ) $a = +$x;
		return $a === false ? false : min(1.0, abs($a));
	}

	public function get( $var ) {
		$var = strtolower($var);
		switch ( $var ) {
		case "r": case "red":   return $this->getRGB()[0] / 255;
		case "g": case "green": return $this->getRGB()[1] / 255;
		case "b": case "blue":  return $this->getRGB()[2] / 255;
		case "h": case "hue":                     return $this->getHSL()[0] / 360;
		case "s": case "sat": case "saturation":  return $this->getHSL()[1] / 100;
		case "l": case "light": case "lightness": return $this->getHSL()[2] / 100;
		case "a": case "alpha": return $this->getAlpha();
		}

		return false;
	}

	public function getValAs( $var, $val ) {
		$var = strtolower($var);
		switch ( $var ) {
		case "r": case "red": 
		case "g": case "green":
		case "b": case "blue": return Color::RGB($val, true);
		case "h": case "hue":                     return Color::H($val, true);
		case "s": case "sat": case "saturation": 
		case "l": case "light": case "lightness": return Color::SL($val, true); 
		case "a": case "alpha": return Color::A($val);
		}

		return false;
	}

	public function set( $var, $val ) {
		$var = strtolower($var);
		$set = "";
		$val = abs(+$val);

		if ($this->rgb === false) $this->getRGB();
		if ($this->hsl === false) $this->getHSL();

		switch ( $var ) {
		case "r": case "red":   $this->rgb[0] = min(255, $val * 255); $set="rgb"; break;
		case "g": case "green": $this->rgb[1] = min(255, $val * 255); $set="rgb"; break;
		case "b": case "blue":  $this->rgb[2] = min(255, $val * 255); $set="rgb"; break;
		case "h": case "hue":                     $this->hsl[0] = min(100, $val * 360); $set="hsl"; break;
		case "s": case "sat": case "saturation":  $this->hsl[1] = min(100, $val * 100); $set="hsl"; break;
		case "l": case "light": case "lightness": $this->hsl[2] = min(100, $val * 100); $set="hsl"; break;
		case "a": case "alpha": $this->alpha = min(1.0, $val);
		}

		if ($set == "rgb") $this->hsl = false;
		if ($set == "hsl") $this->rgb = false;
	}

	// https://gist.github.com/brandonheyer/5254516
	public function getRGB() {
		if ( $this->rgb === false && $this->hsl !== false ) {
			// Convert HSL->RGB
			list($h, $s, $l) = $this->hsl;

			$s /= 100; $l /= 100;

			$c = ( 1 - abs( 2 * $l - 1 ) ) * $s;
			$x = $c * ( 1 - abs( fmod( ( $h / 60 ), 2 ) - 1 ) );
			$m = $l - ( $c / 2 );

			switch ( (int)($h / 60) ) {
				case 0:  $r=$c; $g=$x; $b= 0; break;
				case 1:  $r=$x; $g=$c; $b= 0; break;
				case 2:  $r= 0; $g=$c; $b=$x; break;
				case 3:  $r= 0; $g=$x; $b=$c; break;
				case 4:  $r=$x; $g= 0; $b=$c; break;
				default: $r=$c; $g= 0; $b=$x; break;
			}

			$r = floor(( $r + $m ) * 255);
			$g = floor(( $g + $m ) * 255);
			$b = floor(( $b + $m ) * 255);

			$this->rgb = array($r, $g, $b);
		}
		return $this->rgb;
	}

	public function getHSL() {
		if ( $this->hsl === false && $this->rgb !== false ) {
			// Convert RGB->HSL
			$max = max($this->rgb) / 255;
			$min = min($this->rgb) / 255;

			list($r, $g, $b) = $this->rgb;
			$r /= 255; $g /= 255; $b /= 255;

			$h = $s = 0;
			$l = ($max + $min) / 2;
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

			$this->hsl = array(round($h, 2), round($s * 100, 2), round($l * 100, 2));
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
		$tokens = preg_split("/([+\\-])/", $math, -1, PREG_SPLIT_DELIM_CAPTURE);

		$value = $this->get($var);
		if ( $value === false ) return;

		$op = "";
		foreach ( $tokens as $token ) {
			switch ($token) {
			case '+': case '-': $op = $token; break;
			case '': break;
			default:
				$mod = $this->getValAs($var, $token);
				if ( $mod === false ) $mod = $this->get($token);

				switch ( $op ) {
				case '+': $value += $mod; break;
				case '-': $value -= $mod; break;
				case '':  $value = $mod; break;
				}
				$op = "";
				break;
			}
		}

		$this->set($var, $value);
	}

	public function transform( $func ) {
		switch ( strtolower($func) ) {
		case 'darken': case 'dark':
			if ( array_key_exists('dark' . $this->rawColor, CSSName2Color::$CSSName2Color) ) {
				$this->parse('dark' . $this->rawColor);
			} else if ( substr($this->rawColor, 0, 5) == 'light' && array_key_exists(substr($this->rawColor, 5), CSSName2Color::$CSSName2Color) ) {
				$this->parse(substr($this->rawColor, 5));
			} else {
				$l = $this->get('l');
				if ( $l <= 0.05 ) {
					$this->set('l', 0);
					$this->set('s', $this->get('h') / 2);
				} else {
					$this->set('l', $l / 2);
				}
			}
			break;
		case 'lighten': case 'light':
			if ( array_key_exists('light' . $this->rawColor, CSSName2Color::$CSSName2Color) ) {
                                $this->parse('light' . $this->rawColor);
			} else if ( substr($this->rawColor, 0, 4) == 'dark' && array_key_exists(substr($this->rawColor, 4), CSSName2Color::$CSSName2Color) ) {
				$this->parse(substr($this->rawColor, 4));
                        } else {
				$l = $this->get('l');
				$this->set('l', $l * 1.5);
			}
			break;
		//default: unknown command...
		}
	}

}

