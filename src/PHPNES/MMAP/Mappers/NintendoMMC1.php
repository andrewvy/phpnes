<?php

/**
 * This file is part of PHPNES - a NES Emulator implemented in PHP
 * Based off of Ben Firshman's JSNES and Jamie Sanders' vNES.
 *
 * @copyright Andrew Vy 2015
 * @license GPL
 */

namespace PHPNES\MMAP\Mappers;

use PHPNES\MMAP\Mapper;

class NintendoMMC1 extends Mapper {

	public function __construct($NES) {
		parent::__construct($NES);
	}

	public function loadRom() {
	}
}
