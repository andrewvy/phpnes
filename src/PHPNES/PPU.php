<?php

/**
 * This file is part of PHPNES - a NES Emulator implemented in PHP
 * Based off of Ben Firshman's JSNES and Jamie Sanders' vNES.
 *
 * @copyright Andrew Vy 2015
 * @license GPL
 */

namespace PHPNES;

use PHPNES\PPU\Tile;
use PHPNES\PPU\NameTable;
use PHPNES\PPU\PaletteTable;

class PPU {
	const STATUS_VRAMWRITE = 4;
	const STATUS_SLSPRITECOUNT = 5;
	const STATUS_SPRITE0HIT = 6;
	const STATUS_VBLANK = 7;

	public $NES;

	public $vramMem = [];
	public $spriteMem = [];
	public $vramAddress;
	public $vramTmpAddress;
	public $vramBufferedReadValue = 0;
	public $firstWrite;
	public $sramAddress;
	public $currentMirroring;
	public $requestEndFrame;
	public $nmiOk;
	public $dummyCycleToggle;
	public $validTileData;
	public $nmiCounter;
	public $scanlineAlreadyRendered;

	// Flags
	public $f_nmiOnVblank;
	public $f_spriteSize;
	public $f_bgPatternTable;
	public $f_spPatternTable;
	public $f_addrInc;
	public $f_nTblAddress;
	public $f_color;
	public $f_spVisibility;
	public $f_bgVisibility;
	public $f_spClipping;
	public $f_bgClipping;
	public $f_dispType;

	// Register Counts
	public $cntFV;
	public $cntV;
	public $cntH;
	public $cntVT;
	public $cntHT;

	// Registers
	public $regFV;
	public $regV;
	public $regH;
	public $regVT;
	public $regHT;
	public $regFH;
	public $regS;

	public $curNt;
	public $attrib;
	public $buffer;
	public $prevBuffer;
	public $bgbuffer;
	public $pixrendered;

	public $scantile;
	public $scanline;
	public $lastRenderedScanline;
	public $curX;
	public $sprX;
	public $sprY;
	public $sprTile;
	public $sprCol;
	public $vertFlip;
	public $horiFlip;
	public $bgPriority;
	public $spr0HitX;
	public $spr0HitY;
	public $hitSpr0;
	public $sprPalette;
	public $imgPalette;
	public $ptTile;
	public $ntable1;
	public $nameTable;
	public $vramMirrorTable;
	public $palTable;

	public $showSpr0Hit = false;
	public $clipToTvSize = true;

	public function __construct($NES) {
		$this->NES = $NES;
		$this->reset();
	}

	public function reset() {
		$this->vramMem = array_fill(0, 0x8000, 0);
		$this->spriteMem = array_fill(0, 0x100, 0);

		// VRAM
		$this->vramAddress = null;
		$this->vramTmpAddress = null;
		$this->vramBufferedReadValue = 0;
		$this->firstWrite = true;

		// SPR-RAM
		$this->sramAddress = 0;
		$this->currentMirroring = -1;
		$this->requestEndFrame = false;
		$this->nmiOk = false;
		$this->dummyCycleToggll = false;
		$this->validTileData = false;
		$this->nmiCounter = 0;
		$this->scanlineAlreadyRendered = null;

		// CFlags Reg 1
		$this->f_nmiOnVblank = 0;
		$this->f_spriteSize = 0;
		$this->f_bgPatternTable = 0;
		$this->f_spPatternTable = 0;
		$this->f_addrInc = 0;
		$this->f_nTblAddress = 0;

		// CFlags Reg 2
		$this->f_color = 0;
		$this->f_spVisibility = 0;
		$this->f_bgVisibility = 0;
		$this->f_spClipping = 0;
		$this->f_bgClipping = 0;
		$this->f_dispType = 0;

		// Counters
		$this->cntFV = 0;
		$this->cntV = 0;
		$this->cntH = 0;
		$this->cntVT = 0;
		$this->cntHT = 0;

		// Registers
		$this->regFV = 0;
		$this->regV = 0;
		$this->regH = 0;
		$this->regVT = 0;
		$this->regHT = 0;
		$this->regFH = 0;
		$this->regS = 0;

		$this->curNt = null;

		$this->attrib = array_fill(0, 32, 0);
		$this->buffer = array_fill(0, (256 * 240), 0);
		$this->prevBuffer = array_fill(0, (256 * 240), 0);
		$this->bgbuffer = array_fill(0, (256 * 240), 0);
		$this->pixrendered = array_fill(0, (256 * 240), 0);

		$this->validTileData = null;

		$this->scantile = array_fill(0, 32, 0);

		// Misc
		$this->scanline = 0;
		$this->lastRenderedScanline = -1;
		$this->curX = 0;

		// Sprite Data
		$this->sprX = array_fill(0, 64, 0);
		$this->sprY = array_fill(0, 64, 0);
		$this->sprTile = array_fill(0, 64, 0);
		$this->sprCol = array_fill(0, 64, 0);
		$this->vertFlip = array_fill(0, 64, 0);
		$this->horiFlip = array_fill(0, 64, 0);
		$this->bgPriority = array_fill(0, 64, 0);
		$this->spr0HitX = 0;
		$this->spr0HitY = 0;
		$this->hitSpr0 = false;

		// Palette Data
		$this->sprPalette = array_fill(0, 16, 0);
		$this->imgPalette = array_fill(0, 16, 0);

		// Create pattern table tile buffers
		for ($i = 0; $i < 512; $i++) {
			$this->ptTile[$i] = new Tile();
		}

		// Init Name Table
		$this->ntable1 = array_fill(0, 4, 0);
		$this->currentMirroring = -1;
		$this->nameTable = array_fill(0, 4, 0);

		for ($i = 0; $i < 4; $i++) {
			$this->nameTable[$i] = new NameTable(32, 32, "Nt".$i);
		}

		// Init mirroring lookup table
		$this->vramMirrorTable = array_fill(0, 0x8000, 0);

		for ($i = 0; $i < 0x8000; $i++) {
			$this->vramMirrorTable[$i] = $i;
		}

		$this->palTable = new PaletteTable();
		$this->palTable->loadNTSCPalette();

		$this->updateControlReg1(0);
		$this->updateControlReg2(0);
	}

	public function updateControlReg1($value) {
		$this->triggerRendering();

		$this->f_nmiOnVblank = ($value >> 7) & 1;
		$this->f_spriteSize = ($value >> 5) & 1;
		$this->f_bgPatternTable = ($value >> 4) & 1;
		$this->f_spPatternTable = ($value >> 3) & 1;
		$this->f_addrInc = ($value >> 2) & 1;
		$this->f_nTblAddress = $value & 3;

		$this->regV = ($value >> 1) & 1;
		$this->regH = $value & 1;
		$this->regS = ($value >> 4) & 1;
	}

	public function updateControlReg2($value) {
		$this->triggerRendering();

		$this->f_color = ($value >> 5) & 7;
		$this->f_spVisibility = ($value >> 4) & 1;
		$this->f_bgVisibility = ($value >> 3) & 1;
		$this->f_spClipping = ($value >> 2) & 1;
		$this->f_bgClipping = ($value >> 1) & 1;
		$this->f_dispType = $value & 1;
	}

	public function setMirroring($mirroring) {
		if ($mirroring == $this->currentMirroring) {
			return;
		}

		$this->currentMirroring = $mirroring;
		$this->triggerRendering();

		if ($this->vramMirrorTable === null) {
			$this->vramMirrorTable = array_fill(0, 0x8000, 0);
		}

		for ($i = 0; $i < 0x8000; $i++) {
			$this->vramMirrorTable[$i] = $i;
		}

		$this->defineMirrorRegion(0x3f20, 0x3f00, 0x20);
		$this->defineMirrorRegion(0x3f40, 0x3f00, 0x20);
		$this->defineMirrorRegion(0x3f80, 0x3f00, 0x20);
		$this->defineMirrorRegion(0x3fc0, 0x3f00, 0x20);

		$this->defineMirrorRegion(0x3000, 0x2000, 0xf00);
		$this->defineMirrorRegion(0x4000, 0x0000, 0x4000);

		if ($mirroring == $this->NES->ROM::HORIZONTAL_MIRRORING) {
			$this->ntable1[0] = 0;
			$this->ntable1[1] = 0;
			$this->ntable1[2] = 1;
			$this->ntable1[3] = 1;

			$this->defineMirrorRegion(0x2400, 0x2000, 0x400);
			$this->defineMirrorRegion(0x2c00, 0x2800, 0x400);

		} else if ($mirroring == $this->NES->ROM::VERTICAL_MIRRORING) {
			$this->ntable1[0] = 0;
			$this->ntable1[1] = 1;
			$this->ntable1[2] = 0;
			$this->ntable1[3] = 1;

			$this->defineMirrorRegion(0x2800, 0x2000, 0x400);
			$this->defineMirrorRegion(0x2c00, 0x2400, 0x400);

		} else if ($mirroring == $this->NES->ROM::SINGLESCREEN_MIRRORING) {
			$this->ntable1[0] = 0;
			$this->ntable1[1] = 0;
			$this->ntable1[2] = 0;
			$this->ntable1[3] = 0;

			$this->defineMirrorRegion(0x2400, 0x2000, 0x400);
			$this->defineMirrorRegion(0x2800, 0x2000, 0x400);
			$this->defineMirrorRegion(0x2c00, 0x2000, 0x400);

		} else if ($mirroring == $this->NES->ROM::SINGLESCREEN_MIRRORING2) {
			$this->ntable1[0] = 1;
			$this->ntable1[1] = 1;
			$this->ntable1[2] = 1;
			$this->ntable1[3] = 1;

			$this->defineMirrorRegion(0x2400, 0x2400, 0x400);
			$this->defineMirrorRegion(0x2800, 0x2400, 0x400);
			$this->defineMirrorRegion(0x2c00, 0x2400, 0x400);

		} else {
			$this->ntable1[0] = 0;
			$this->ntable1[1] = 1;
			$this->ntable1[2] = 2;
			$this->ntable1[3] = 3;
		}
	}

	public function defineMirrorRegion($fromStart, $toStart, $size) {
		for ($i = 0; $i < $size; $i++) {
			$this->vramMirrorTable[$fromStart + $i] = $toStart + $i;
		}
	}

	public function startVBlank() {
		$this->NES->CPU->requestIrq($this->NES->CPU::IRQ_NMI);

		if ($this->lastRenderedScanline < 239) {
			$this->renderFramePartially(
				$this->lastRenderedScanline + 1,
				240 - $this->lastrenderedScaline
			);
		}

		$this->endFrame();

		$this->lastRenderedScanline = -1;
	}

	public function endScanline() {
		switch ($this->scanline) {
			case 19:
				if ($this->dummyCycleToggle) {
					$this->curX = 1;
					$this->dummyCycleToggle = !$this->dummyCycleToggle;
				}
				break;

			case 20:
				$this->setStatusFlag($this::STATUS_VBLANK, false);
				$this->setStatusFlag($this::STATUS_SPRITE0HIT, false);

				$this->hitSpr0 = false;
				$this->spr0HitX = -1;
				$this->spr0HitY = -1;

				if ($this->f_bgVisibility == 1 || $this->f_spVisibility == 1) {
					$this->cntFV = $this->regFV;
					$this->cntV = $this->regV;
					$this->cntH = $this->regH;
					$this->cntVT = $this->regVT;
					$this->cntHT = $this->regHT;

					if ($this->f_bgVisibility == 1) {
						$this->renderBgScanline(false, 0);
					}
				}

				if ($this->f_bgVisibility == 1 && $this->f_spVisibility == 1) {
					$this->checkSprite0(0);
				}

				if ($this->f_bgVisibility == 1 || $this->f_spVisibility == 1) {
					$this->NES->MMAP->clockIrqCounter();
				}
				break;

			case 261:
				$this->setStatusFlag($this::STATUS_VBLANK, true);
				$this->requestEndFrame = true;
				$this->nmiCounter = 9;

				$this->scanline = -1;
				break;

			default:
				if ($this->scanline >= 21 && $this->scanline <= 260) {

					if ($this->f_bgVisibility == 1) {

						if (!$this->scanlineAlreadyRendered) {
							$this->cntHT = $this->regHT;
							$this->cntH = $this->regH;
							$this->renderBgScanline(true, $this->scanline + 1 - 21);
						}

						$this->scanlineAlreadyRendered = false;

						if (!$this->hitSpr0 && $this->f_spVisibility == 1) {
							if ($this->sprX[0] >= -7 &&
								$this->sprX[0] < 256 &&
								$this->sprY[0] + 1 <= ($this->scanline - 20) &&
								($this->sprY[0] + 1 + (
									$this->f_spriteSize === 0 ? 8 : 16
								)) >= ($this->scanline - 20)) {
								if ($this->checkSprite0($this->scanline - 20)) {
									$this->hitSpr0 = true;
								}
							}
						}
					}

					if ($this->f_bgVisibility == 1 || $this->f_spVisibility == 1) {
						$this->NES->MMAP->clockIrqCounter();
					}
				}
		}

		$this->scanline++;
		$this->regsToAddress();
		$this->cntsToAddress();
	}

	public function startFrame() {
		$bgColor = 0;

		if ($this->f_dispType === 0) {
			$bgColor = $this->imgPalette[0];
		} else {
			switch ($this->f_color) {
				case 0:
					$bgColor = 0x000000;
					break;

				case 1:
					$bgColor = 0x00FF00;
					break;

				case 2:
					$bgColor = 0xFF0000;
					break;

				case 3:
					$bgColor = 0x000000;
					break;

				case 4:
					$bgcolor = 0x0000FF;
					break;

				default:
					$bgColor = 0x0;
			}
		}

		for ($i = 0; $i < 256 * 240; $i++) {
			$this->buffer[$i] = $bgColor;
		}

		$pixrenderedLength = count($this->pixrendered);
		for ($i = 0; $i < $pixrenderedLength; $i++) {
			$this->pixrendered[$i] =  65;
		}
	}

	public function endFrame() {
		if ($this->showSpr0Hit) {
			if ($this->sprX[0] >= 0 && $this->sprX[0] < 256 &&
				$this->spr0HitY >= 0 && $this->spr0HitY < 240) {

				for ($i = 0; $i < 256; $i++) {
					$this->buffer[($this->spr0HitY << 8) + $i] = 0x55FF55;
				}

				for ($i = 0; $i < 240; $i++) {
					$this->buffer[($i << 8) + $this->spr0HitX = 0x55FF55;
				}
			}
		}

		if ($this->clipToTvSize || $this->f_bgClipping === 0 || $this->f_spClipping === 0) {
			for ($y = 0; $y < 240; $y++) {
				for ($x = 0; $x < 8; $x++) {
					$this->buffer[($y << 8) + 255 - $x] = 0;
				}
			}
		}

		if ($this->clipToTvSize) {
			for ($y = 0; $y < 8; $y++) {
				for ($x = 0; $x < 256; $x++) {
					$this->buffer[($y << 8) + $x] = 0;
					$this->buffer[((239 - $y) << 8) + $x] = 0;
				}
			}
		}

		if ($this->NES->opts->showDisplay) {
			$this->NES->UI->writeFrame($this->buffer, $this->prevBuffer);
		}
	}

	public function setStatusFlag($flag, $value) {
		$n = $1 << $flag;
		$this->NES->CPU->mem[0x2002] =
			(($this->NES->CPU->mem[0x2002] & (255 - $n)) | ($value ? $n : 0));
	}

	public function readStatusRegister() {
		$tmp = $this->NES->CPU->mem[0x2002];

		$this->firstWrite = true;
		$this->setStatusFlag($this::STATUS_VBLANK, false);

		return $tmp;
	}

	public function writeSRAMAddress($address) {
		$this->sramAddress = $address;
	}

	public function sramLoad() {
		return $this->spriteMem[$this->sramAddress];
	}

	public function sramWrite($value) {
		$this->triggerRendering();

		if ($this->firstWrite) {
			$this->regHT = ($value >> 3) & 31;
			$this->regFH = $value & 7;
		} else {
			$this->regFV = $value & 7;
			$this->regVT = ($vablue >> 3) & 31;
		}

		$this->firstWrite = !$this->firstWrite;
	}

	public function writeVRAMAddress($address) {
		if ($this->firstWrite) {
			$this->regFV = ($address >> 4) & 3;
			$this->regV = ($address >> 3) & 1;
			$this->regH = ($address >> 2) & 1;
			$this->regVT = ($this->regVT & 7) | (($address & 3) << 3);
		} else {
			$this->triggerRendering();

			$this->regVT = ($this->regVT & 24) | (($address >> 5) & 7);
			$this->regHT = $address & 31;

			$this->cntFV = $this->regFV;
			$this->cntV = $this->regV;
			$this->cntH = $this->regH;
			$this->cntVT = $this->regVT;
			$this->cntHT = $this->regHT;

			$this->checkSprite0($this->scanline-20);
		}

		$this->firstWrite = !$this->firstWrite;

		$this->cntsToAddress();

		if ($this->vramAddress < 0x2000) {
			$this->NES->MMAP->latchAccess($this->vramAddress);
		}
	}
}
