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

class DirectAccess extends Mapper {
	public $joy1StrobeState = 0;
	public $joy2StrobeState = 0;
	public $joypadLastWrite = 0;

	public $mousePressed = false;
	public $mouseX = null;
	public $mouseY = null;

	public function __construct($NES) {
		parent::__construct($NES);
	}

	public function write($address, $value) {
		if ($address < 0x2000) {
			$this->NES->CPU->mem[$address & 0x7FF] = $value;
		} else if ($address > 0x4017) {
			$this->NES->CPU->mem[$address] = $value;
			if ($address >= 0x6000 && $address < 0x8000) {
				// Write to battery ram
			}
		} else if ($address > 0x2007 && $address < 0x4000) {
			$this->regWrite(0x2000 + ($address & 0x7), $value);
		} else {
			$this->regWrite($address, $value);
		}
	}

	public function writelow($address, $value) {
		if ($address < 0x2000) {
			$this->NES->CPU->mem[$address & 0x7FF] = $value;
		} else if ($address > 0x4017) {
			$this->NES->CPU->mem[$address] = $value;
		} else if ($address > 0x2007 && $address < 0x4000) {
			$this->regWrite(0x2000 + ($address & 0x7), $value);
		} else {
			$this->regWrite($address, $value);
		}
	}

	public function load($address) {
		$address &= 0xFFFF;

		if ($address > 0x4017) {
			return $this->NES->CPU->mem[$address];
		} else if ($address >= 0x2000) {
			return $this->regLoad($address);
		} else {
			return $this->NES->CPU->mem[$address & 0x7FF];
		}
	}

	public function regLoad($address) {

	}

	public function loadRom() {
	}
}
