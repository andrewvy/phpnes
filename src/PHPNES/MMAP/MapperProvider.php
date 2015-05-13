<?php

/**
 * This file is part of PHPNES - a NES Emulator implemented in PHP
 * Based off of Ben Firshman's JSNES and Jamie Sanders' vNES.
 *
 * @copyright Andrew Vy 2015
 * @license GPL
 */

namespace PHPNES\MMAP;

class MapperProvider {
	public $NES;
	public $Mappers = [];

	public function __construct($NES) {
		$this->NES = $NES;
		$this->registerMappers();
	}

	public function registerMappers() {
		$this->Mappers[0] = 'PHPNES\MMAP\Mappers\DirectAccess';
		$this->Mappers[1] = 'PHPNES\MMAP\Mappers\NintendoMMC1';
		$this->Mappers[2] = 'PHPNES\MMAP\Mappers\Unrom';
		$this->Mappers[4] = 'PHPNES\MMAP\Mappers\NintendoMMC3';
	}

	public function getMapperByType($type) {
		return $this->Mappers[$type];
	}
}
