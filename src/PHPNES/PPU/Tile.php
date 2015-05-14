<?php

/**
 * This file is part of PHPNES - a NES Emulator implemented in PHP
 * Based off of Ben Firshman's JSNES and Jamie Sanders' vNES.
 *
 * @copyright Andrew Vy 2015
 * @license GPL
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

	public function render($buffer, $srcx1, $srcx2, $srcy2, $dx, $dy, $palAdd, $palette, $flipHorizontal, $flipVertical, $pri, $priTable) {
		if ($dx < (-7) || $dx >= 256 || $dy < (-7) || $dy >= 240) {
			return;
		}

		$this->w = $srcx2 - $srcx1;
		$this->h = $srcy2 - $srcy1;

		if ($dx < 0) {
			$srcx1 -= $dx;
		}

		if ($dx + $srcx2 >= 256) {
			$srcx2 = 256 - $dx;
		}

		if ($dy < 0) {
			$srcy1 -= $dy;
		}

		if ($dy + $srcy2 >= 240) {
			$srcy2 = 240 - $dy;
		}

		if (!$flipHorizontal && !$flipVertical) {
			$this->fbIndex = ($dy << 8) + $dx;
			$this->tIndex = 0;

			for ($this->y = 0; $this->y < 8; $this->y++) {
				for ($this->x = 0; $this->x < 8; $this->x++) {
					if ($this->x >= $srcx1 && $this->x < $srcx2 && $this->y >= $srcy1 && $this->y < $srcy2) {
						$this->padIndex = $this->pix[$this->tIndex];
						$this->tpri = $priTable[$this->fbIndex];

						if ($this->palIndex !== 0 && $pri <= ($this->tpri & 0xFF)) {
							$buffer[$this->fbIndex] = $palette[$this->palIndex + $palAdd];
							$this->tpri = ($this->tpri & 0xF00) | pri;

							$priTable[$this->fbIndex] = $this->tpri;
						}
					}

					$this->fbIndex++;
					$this->tIndex++;
				}

				$this->fbIndex -= 8;
				$this->fbIndex += 256;
			}
		} else if ($flipHorizontal && !$flipVertical) {
			$this->fbIndex = ($dy << 8) + $dx;
			$this->tIndex = 7;

			for ($this->y = 0; $this->y < 8; $this->y++) {
				for ($this->x = 0; $this->x < 8; $this->x++) {
					if ($this->x >= $srcx1 && $this->x < $srcx2 && $this->y >= $srcy1 && $this->y < $srcy2) {
						$this->palIndex = $this->pix[$this->tIndex];
						$this->tpri = $priTable[$this->fbIndex];

						if ($this->palIndex !== 0 && $pri <= ($this->tpri & 0xFF)) {
							$buffer[$this->fbIndex] = $palette[$this->palIndex + $palAdd];
							$this->tpri = ($this->tpri & 0xF00) | $pri;

							$priTable[$this->fbIndex] = $this->tpri;
						}
					}

					$this->fbIndex++;
					$this->tIndex--;
				}

				$this->fbIndex -= 8;
				$this->fbIndex += 256;
				$this->tIndex += 16;
			}
		} else if ($flipVertical && !$flipHorizontal) {
			$this->fbIndex = ($dy << 8) + $dx;
			$this->tIndex = 56;

			for ($this->y = 0; $this->y < 8; $this->y++) {
				for ($this->x = 0; $this->x < 8; $this->x++) {
					if ($this->x >= $srcx1 && $this->x < $srcx2 && $this->y >= $srcy1 && $this->y < $srcy2) {
						$this->palIndex = $this->pix[$this->tIndex];
						$this->tpri = $priTable[$this->fbIndex];

						if ($this->palIndex !== 0 && $pri <= ($this->tpri & 0xFF)) {
							$buffer[$this->fbIndex] = $palette[$this->palIndex + $palAdd];
							$this->tpri = ($this->tpri & 0xF00) | $pri;

							$priTable[$this->fbIndex] = $this->tpri;
						}
					}

					$this->fbIndex++;
					$this->tIndex++;
				}

				$this->fbIndex -= 8;
				$this->fbIndex += 256;
				$this->tIndex -= 16;
			}
		} else {
			$this->fbIndex = ($dy << 8) + $dx;
			$this->tIndex = 63;

			for ($this->y = 0; $this->y < 8; $this->y++) {
				for ($this->x = 0; $this->x < 8; $this->x++) {
					if ($this->x >= $srcx1 && $this->x < $srcx2 && $this->y >= $srcy1 && $this->y < $srcy2) {
						$this->palIndex = $this->pix[$this->tIndex];
						$this->tpri = $priTable[$this->fbIndex];

						if ($this->palIndex !== 0 && $pri <= ($this->tpri & 0xFF)) {
							$buffer[$this->fbIndex] = $palette[$this->palIndex + $palAdd];
							$this->tpri = ($this->tpri & 0xF00) | $pri;

							$priTable[$this->fbIndex] = $this->tpri;
						}
					}

					$this->fbIndex++;
					$this->tIndex--;
				}

				$this->fbIndex -= 8;
				$this->fbIndex += 256;
			}
		}

	}

	public function isTransparent($x, $y) {
		return ($this->pix[($y <<3) + $x] === 0);
	}
}
