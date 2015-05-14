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
}
