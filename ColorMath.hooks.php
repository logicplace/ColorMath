<?php

class ColorMathHooks {

	/**
	 * @param $parser Parser
	 * @return bool
	 */
	public static function onParserFirstCallInit( $parser ) {
		// These functions accept DOM-style arguments
		$parser->setFunctionHook( 'color', 'ColorMath::color', Parser::SFH_OBJECT_ARGS );

		return true;
	}
}
