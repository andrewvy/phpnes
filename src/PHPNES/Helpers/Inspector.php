<?php

/**
 * This file is part of PHPNES - a NES Emulator implemented in PHP
 * Based off of Ben Firshman's JSNES and Jamie Sanders' vNES.
 *
 * @copyright Andrew Vy 2015
 * @license GPL
 */

namespace PHPNES\Helpers;

class Inspector {
	public $NES;

	public function __construct($NES) {
		$this->NES = $NES;
	}

	public function dumpRam() {
		$memCount = count($this->NES->CPU->mem);
		for ($i = 0; $i < $memCount; $i+4) {
			print $this->NES->CPU->mem[$i];
			print $this->NES->CPU->mem[$i+1];
			print $this->NES->CPU->mem[$i+2];
			print $this->NES->CPU->mem[$i+3];
			print $this->NES->CPU->mem[$i+4];
			print PHP_EOL;
		}
	}
}
