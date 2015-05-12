<?php

/**
 * This file is part of PHPNES - a NES Emulator implemented in PHP
 * Based off of Ben Firshman's JSNES and Jamie Sanders' vNES.
 *
 * @copyright Andrew Vy 2015
 * @license MIT
 */

namespace PHPNES\PPU;

class Tile {
	public $pix = [];

	public $fbIndex;
	public $tIndex;
	public $x;
	public $y;
	public $w;
	public $h;
	public $incX;
	public $incY;
	public $palIndex;
	public $tpri;
	public $c;
	public $initialized = false;
	public $opaque = [];


	public function __construct() {
		$this->pix = array_fill(0, 64, 0);
		$this->opaque = array_fill(0, 8, 0);
	}

	public function setBuffer($scanline) {
		for ($this->y = 0; $this->y < 8; $this->y++) {
			$this->setScanline($this->y, $scanline[$this->y], $scanline[$this->y+8]);
		}
	}

	public function setScanline($sline, $b1, $b2) {
		$this->initialized = true;
		$this->tIndex = $sline << 3;

		for ($this->x = 0; $this->x < 8; $this->x++) {
			$this->pix[$this->tIndex + $this->x] = (($b1 >> (7 - $this->x)) & 1) +
				((($b2 >> (7 - $this->x)) & 1) << 1);

			if ($this->pix[$this->tIndex + $this->x] === 0) {
				$this->opaque[$sline] = false;
			}
		}
	}

	public function render() {
	}

	public function isTransparent($x, $y) {
		return ($this->pix[($y <<3) + $x] === 0);
	}
}
