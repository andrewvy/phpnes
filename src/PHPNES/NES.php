<?php

/**
 * This file is part of PHPNES - a NES Emulator implemented in PHP
 * Based off of Ben Firshman's JSNES and Jamie Sanders' vNES.
 *
 * @copyright Andrew Vy 2015
 * @license MIT
 */

namespace PHPNES;

use PHPNES\CPU;
use PHPNES\PPU;
use PHPNES\PAPU;
use PHPNES\MMAP;

class NES {

	// Options

	public $preferredFrameRate = 60;
	public $fpsInterval = 500;
	public $emulateSound = false;
	public $sampleRate = 44100;

	const CPU_FREQ_NTSC = 1789772.5;
	const CPU_FREQ_PAL = 1773447.4;

	// Internal

	public $CPU;
	public $PPU;
	public $PAPU;
	public $MMAP;

	public $isRunning

	public function __construct() {
		$this->CPU = new CPU($this);
		$this->PPU = new PPU($this);
		$this->PAPU = new PAPU($this);
	}

	public function reset() {
		if ($this->MMAP) {
			$this->MMAP->reset();
		}

		$this->CPU->reset();
		$this->PPU->reset();
		$this->PAPU->reset();
	}

	public function loadRom($dir)
}
