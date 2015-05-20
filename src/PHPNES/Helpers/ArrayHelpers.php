<?php

/**
 * This file is part of PHPNES - a NES Emulator implemented in PHP
 * Based off of Ben Firshman's JSNES and Jamie Sanders' vNES.
 *
 * @copyright Andrew Vy 2015
 * @license GPL
 */

namespace PHPNES\Helpers;

class ArrayHelpers {
	public static function copyArrayElements(&$src, $srcPos, &$dest, $destPos, $length, $debug=false) {
		for ($i = 0; $i < $length; $i++) {
			if ($debug) {
				print $src[$srcPos + $i].PHP_EOL;
			}

			$dest[$destPos + $i] = $src[$srcPos + $i];
		}
	}
}
