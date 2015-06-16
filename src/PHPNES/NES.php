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
use React\EventLoop\Factory;

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
	public $SDLWrapper;

	public $isRunning = false;
	public $rom;
	public $romData;
	public $debugMode = false;

	public function __construct($sdlEnabled=false) {
		// We definitely need more than the default memory limit.

		ini_set('memory_limit', '512M');

		$this->CPU = new CPU($this);
		$this->PPU = new PPU($this);
		$this->PAPU = new PAPU($this);

		$this->loop = Factory::create();

		$this->MapperProvider = new MapperProvider($this);

		if ($sdlEnabled) {
			$this->SDLWrapper = new SDLWrapper($this);
		}
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

			$this->loop->addPeriodicTimer(1 / 60, function() {
				$this->frame();
			});

			$this->loop->run();
		} else {
			// Alert that the rom is either not loaded or is invalid.

		}
	}

	public function stop($hasCrashed=false) {
		$this->isRunning = false;

		if ($this->debugMode && $hasCrashed) {
			(Int) $memAddr = readline("Inspect Memory At: ");
			print "Value: ".$this->CPU->mem[$memAddr].PHP_EOL;
			print "Opcode: ".($this->CPU->opdata[$this->CPU->mem[$memAddr]] & 0xFF).PHP_EOL;
		} else if ($hasCrashed) {
			throw new \Exception("Unhandled close, NES shutting down!");
		}
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

	public function frame() {
		$this->PPU->startFrame();

		$cycles = 0;
		$emulateSound = $this->emulateSound;
		for (;;) {

			if ($this->CPU->cyclesToHalt === 0) {
				$cycles = $this->CPU->emulate();

				if ($emulateSound) {
					$this->PAPU->clockFrameCounter($cycles);
				}

				$cycles *= 3;
			} else {
				if ($this->CPU->cyclesToHalt > 8) {
					$cycles = 24;

					if ($emulateSound) {
						$this->PAPU->clockFrameCounter(8);
					}

					$this->CPU->cyclesToHalt -= 8;
				} else {
					$cycles = $this->CPU->cyclesToHalt * 3;

					if ($emulateSound) {
						$this->PAPU->clockFrameCounter($this->CPU->cyclesToHalt);
					}

					$this->CPU->cyclesToHalt = 0;
				}
			}

			for (; $cycles > 0; $cycles--) {
				if ($this->PPU->curX === $this->PPU->spr0HitX &&
					$this->PPU->f_spVisibility === 1 &&
					$this->PPU->scanline - 21 === $this->PPU->spr0HitY) {
					$this->PPU->setStatusFlag(PPU::STATUS_SPRITE0HIT, true);
				}

				if ($this->PPU->requestEndFrame) {
					$this->PPU->nmiCounter--;

					if ($this->PPU->nmiCounter === 0) {
						$this->PPU->requestEndFrame = false;
						$this->PPU->startVBlank();
						break;
					}
				}

				$this->PPU->curX++;

				if ($this->PPU->curX === 341) {
					$this->PPU->curX = 0;
					$this->PPU->endScanline();
				}
			}
		}
	}
}
