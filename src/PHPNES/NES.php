<?php

/**
 * This file is part of PHPNES - a NES Emulator implemented in PHP
 * Based off of Ben Firshman's JSNES and Jamie Sanders' vNES.
 *
 * @copyright Andrew Vy 2015
 * @license GPL
 */

namespace PHPNES;

use PHPNES\CPU;
use PHPNES\PPU;
use PHPNES\PAPU;
use PHPNES\MMAP;
use PHPNES\ROM;
use PHPNES\MMAP\MapperProvider;

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
	public $MapperProvider;

	public $isRunning = false;
	public $rom;
	public $romData;
	public $debugMode = false;

	public function __construct() {
		// We definitely need more than the default memory limit.

		ini_set('memory_limit', '1G');

		$this->CPU = new CPU($this);
		$this->PPU = new PPU($this);
		$this->PAPU = new PAPU($this);

		$this->MapperProvider = new MapperProvider($this);
	}

	public function reset() {
		if ($this->MMAP) {
			$this->MMAP->reset();
		}

		$this->CPU->reset();
		$this->PPU->reset();
		$this->PAPU->reset();
	}

	public function start() {
		if ($this->rom != null && $this->rom->isValid) {
			// Start running rom!

			$this->isRunning = true;

		} else {
			// Alert that the rom is either not loaded or is invalid.

		}
	}

	public function stop() {
		$this->isRunning = false;
	}

	public function loadRom($data) {
		if ($this->isRunning) {
			$this->stop();
		}

		// Load rom!
		$this->rom = new ROM($this);
		$this->rom->load($data);

		// Check if the rom is valid and initialize MMAP & PPU.
		if ($this->rom->isValid) {
			$this->reset();
			$this->MMAP = $this->rom->createMapper();

			if ($this->MMAP == null) {
				return;
			}

			$this->MMAP->loadROM();
			$this->PPU->setMirroring($this->rom->getMirroringType());
			$this->romData = $data;
		} else {
			// An invalid rom was loaded.
		}

		return $this->rom->isValid;
	}

	public function reloadRom() {
		if ($this->romData != null) {
			$this->loadRom($this->romData);
		}
	}

}
