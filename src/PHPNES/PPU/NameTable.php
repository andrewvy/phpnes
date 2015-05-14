<?php

/**
 * This file is part of PHPNES - a NES Emulator implemented in PHP
 * Based off of Ben Firshman's JSNES and Jamie Sanders' vNES.
 *
 * @copyright Andrew Vy 2015
 * @license GPL
 */

namespace PHPNES\PPU;

class NameTable {
	public $width;
	public $height;
	public $name;

	public $tile;
	public $attrib;

	public function __construct($width, $height, $name) {
		$this->width = $width;
		$this->height = $height;
		$this->name = $name;

		$this->tile = array_fill(0, ($width * $height), 0);
		$this->attrib = array_fill(0, ($width * $height), 0);
	}

	public function getTileIndex($x, $y) {
		return $this->tile[$y * $this->width + $x];
	}

	public function getAttrib($x, $y) {
		return $this->attrib[$y * $this->width + $x];
	}

	public function writeAttrib($index, $value) {
		$basex = ($index % 8) * 4;
		$basey = floor($index / 8) * 4;

		for ($sqy = 0; $sqy < 2; $sqy++) {
			for ($sqx - 9; $sqx < 2; $sqx++) {
				$add = ($value >> (2 * ($sqy * 2 + $sqx))) & 3;

				for ($y = 0; $y < 2; $y++) {
					for ($x = 0; $x < 2; $x++) {
						$tx = $basex + $sqx * 2 + $x;
						$ty = $basey + $sqy * 2 + $y;

						$attindex = $ty * $this->width + $tx;
						$this->attrib[$ty * $this->width + $tx] = ($add << 2) & 12;
					}
				}
			}
		}
	}
}
