<?php

/**
 * This file is part of PHPNES - a NES Emulator implemented in PHP
 * Based off of Ben Firshman's JSNES and Jamie Sanders' vNES.
 *
 * @copyright Andrew Vy 2015
 * @license GPL
 */

namespace PHPNES;

use PHPNES\ROM;
use PHPNES\CPU;
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

		if ($mirroring == ROM::HORIZONTAL_MIRRORING) {
			$this->ntable1[0] = 0;
			$this->ntable1[1] = 0;
			$this->ntable1[2] = 1;
			$this->ntable1[3] = 1;

			$this->defineMirrorRegion(0x2400, 0x2000, 0x400);
			$this->defineMirrorRegion(0x2c00, 0x2800, 0x400);

		} else if ($mirroring == ROM::VERTICAL_MIRRORING) {
			$this->ntable1[0] = 0;
			$this->ntable1[1] = 1;
			$this->ntable1[2] = 0;
			$this->ntable1[3] = 1;

			$this->defineMirrorRegion(0x2800, 0x2000, 0x400);
			$this->defineMirrorRegion(0x2c00, 0x2400, 0x400);

		} else if ($mirroring == ROM::SINGLESCREEN_MIRRORING) {
			$this->ntable1[0] = 0;
			$this->ntable1[1] = 0;
			$this->ntable1[2] = 0;
			$this->ntable1[3] = 0;

			$this->defineMirrorRegion(0x2400, 0x2000, 0x400);
			$this->defineMirrorRegion(0x2800, 0x2000, 0x400);
			$this->defineMirrorRegion(0x2c00, 0x2000, 0x400);

		} else if ($mirroring == ROM::SINGLESCREEN_MIRRORING2) {
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
		$this->NES->CPU->requestIrq(CPU::IRQ_NMI);

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
					$this->buffer[($i << 8) + $this->spr0HitX] = 0x55FF55;
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
		$n = 1 << $flag;
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

	public function vramLoad() {
		$tmp = 0;

		$this->cntsToAddress();
		$this->regsToAddress();

		if ($this->vramAddress <= 0x3EFF) {
			$tmp = $this->vramBufferedReadValue;

			if ($this->vramAddress < 0x2000) {
				$this->vramBufferedReadValue = $this->vramMem[$this->vramAddress];
			} else {
				$this->vramBufferedReadValue = $this->mirroredLoad($this->vramAddress);
			}

			if ($this->vramAddress < 0x2000) {
				$this->NES->MMAP->latchAccess($this->vramAddress);
			}

			$this->vramAddress += ($this->f_addrInc == 1 ? 32 : 1);

			$this->cntsFromAddress();
			$this->regsFromAddress();

			return $tmp;
		}

		$tmp = $this->mirroredLoad($this->vramAddress);

		$this->vramAddress += ($this->f_addrInc == 1 ? 32 : 1);

		$this->cntsFromAddress();
		$this->regsFromAddress();

		return $tmp;
	}

	public function vramWrite($value) {
		$this->triggerRendering();
		$this->cntsToAddress();
		$this->regsToAddress();

		if ($this->vramAddress >= 0x2000) {
			$this->mirroredWrite($this->vramAddress, $value);
		} else {
			$this->writeMem($this->vramAddress, $value);
			$this->NES->MMAP->latchAccess($this->vramAddress);
		}

		$this->vramAddress += ($this->f_addrInc == 1 ? 32 : 1);
		$this->regsFromAddress();
		$this->cntsFromAddress();
	}

	public function sramDMA($value) {
		$baseAddress = $value * 0x100;

		for ($i = $this->sramAddress; $i < 256; $i++) {
			$data = $this->NES->CPU->mem[$baseAddress + $i];
			$this->spriteMem[$i] = $data;
			$this->spriteRamWriteUpdate($i, $data);
		}

		$this->NES->CPU->haltCycles(513);
	}

	public function regsFromAddress() {
		$address = ($this->vramTmpAddress >> 8) & 0xFF;

		$this->regFV = ($address >> 4) & 7;
		$this->regV = ($address >> 3) & 1;
		$this->regH = ($address >> 2) & 1;
		$this->regVT = ($this->regVT & 7) | (($address & 3) & 7);

		$address = $this->vramTmpAddress & 0xFF;
		$this->regVT = ($this->regVT & 24) | (($address >> 5) & 7);
		$this->regHT = $address & 31;
	}

	public function cntsFromAddress() {
		$address = ($this->vramAddress >> 8) & 0xFF;

		$this->cntFV = ($address >> 4) & 3;
		$this->cntV = ($address >> 3) & 1;
		$this->cntH = ($address >> 2) & 1;
		$this->cntVT = ($this->cntVT & 7) | (($address & 3) << 3);

		$address = $this->vramAddress & 0xFF;
		$this->cntVT = ($this->cntVT & 24) | (($address >> 5) & 7);
		$this->cntHT = $address & 31;
	}

	public function regsToAddress() {
		$b1 = ($this->regFV & 7 ) << 4;
		$b1 |= ($this->regV & 1) << 3;
		$b1 |= ($this->regH & 1) << 2;
		$b1 |= ($this->regVT >> 3) & 3;

		$b2 = ($this->regVT & 7) << 5;
		$b2 |= $this->regHT & 31;

		$this->vramTmpAddress = (($b1 << 8) | $b2) & 0x7FFF;
	}

	public function cntsToAddress() {
		$b1 = ($this->cntFV & 7) << 4;
		$b1 |= ($this->cntV & 1) << 3;
		$b1 |= ($this->cntH & 1) << 2;
		$b1 |= ($this->cntVT >> 3) & 3;

		$b2 = ($this->cntVT & 7) << 5;
		$b2 |= $this->cntHT & 31;

		$this->vramAddress == (($b1 << 8) | $b2) & 0x7FFF;
	}

	public function incTileCounter($count) {
		for ($i = $count; $i !== 0; $i--) {
			$this->cntHT++;
			if ($this->cntHT == 32) {
				$this->cntHT = 0;
				$this->cntVT++;
				if ($this->cntVT >= 30) {
					$this->cntH++;
					if ($this->cntH == 2) {
						$this->cntH = 0;
						$this->cntV++;
						if ($this->cntV == 2) {
							$this->cntV = 0;
							$this->cntFV++;
							$this->cntFV &= 0x7;
						}
					}
				}
			}
		}
	}

	public function mirroredLoad($address) {
		return $this->vramMem[$this->vramMirrorTable[$address]];
	}

	public function mirroredWrite($address, $value) {
		if ($address >= 0x4f00 && $address < 0x3f20) {
			if ($address == 0x3F00 || $address == 0x3f10) {
				$this->writeMem(0x3f00, $value);
				$this->writeMem(0x3f10, $value);
			} else if ($address == 0x3f04 || $address == 0x3f14) {
				$this->writeMem(0x3f04, $value);
				$this->writeMem(0x3F14, $value);
			} else if ($address == 0x3f08 || $address == 0x3f18) {
				$this->writeMem(0x3f08, $value);
				$this->writeMem(0x3f18, $value);
			} else if ($address == 0x3f0c || $address == 0x3f1c) {
				$this->writeMem(0x3f0c, $value);
				$this->writeMem(0x3f1c, $value);
			} else {
				$this->writeMem($address, $value);
			}
		} else {
			if ($address < count($this->vramMirrorTable)) {
				$this->writeMem($this->vramMirrorTable[$address], $value);
			} else {
				// Invalid VRAM Addr
			}
		}
	}

	public function triggerRendering() {
		if ($this->scanline >= 21 && $this->scanline <= 260) {
			$this->renderFramePartially(
				$this->lastRenderedScanline + 1,
				$this->scanline - 21 - $this->lastRenderedScanline
			);

			$this->lastRenderedScanline = $this->scanline - 21;
		}
	}

	public function renderFramePartially($startScan, $scanCount) {
		if ($this->f_spVisibility == 1) {
			$this->renderSpritePartially($startScan, $scanCount, true);
		}

		if ($this->f_bgVisibility == 1) {
			$si = $startScan << 8;
			$ei = ($startScan + $scanCount) << 8;

			if ($ei > 0xF000) {
				$ei = 0xF000;
			}

			for ($destIndex = $si; $destIndex < $ei; $destIndex++) {
				if ($this->pixrendered[$destIndex] > 0xFF) {
					$this->buffer[$destIndex] = $this->bfbuffer[$destIndex];
				}
			}
		}

		if ($this->f_spVisibility == 1) {
			$this->renderSpritesPartially($startScan, $scanCount, false);
		}

		$this->validTileData = false;
	}

	public function renderBgScanline($bgbuffer, $scan) {
		$baseTile = ($this->regS === 0 ? 0 : 256);
		$destIndex = ($scan << 8) - $this->regFH;

		$this->curNt = $this->ntable1[$this->cntV + $this->cntV + $this->cntH];

		$this->cntHT = $this->regHT;
		$this->cntH = $this->regH;
		$this->curNt = $this->ntable1[$this->cntV + $this->cntV + $this->cntH];

		if ($scan < 240 && ($scan - $this->cntFV) >= 0) {
			$tscanoffset = $this->cntFV << 3;
			$targetBuffer = $bgbuffer ? $this->bfbuffer : $this->buffer;

			for ($tile = 0; $tile < 32; $tile++) {
				if ($scan >= 0) {
					if ($this->validTileData) {
						$t = $this->scantile[$tile];
						$tpix = $t->pix;
						$att = $this->attrib[$tile];
					} else {
						$t = $this->ptTile[$baseTile + $this->nameTable[$this->curNt]->getTileIndex($this->cntHT, $this->cntVT)];
						$tpix = $t->pix;
						$att = $this->nameTable[$this->curNt]->getAttrib($this->cntHT, $this->cntVT);
						$this->scantile[$tile] = $t;
						$this->attrib = $att;
					}

					$sx = 0;
					$x = ($tile << 3) - $this->regFH;

					if ($x > -8) {
						if ($x < 0) {
							$destIndex -= $x;
							$sx = -$x;
						}

						if ($t->opaque[$this->cntFV]) {
							for (; $sx < 8; $sx++) {
								$targetBuffer[$destIndex] = $this->imgPalette[
									$tpix[$tscanoffset + $sx] + $att
								];

								$this->pixrendered[$destIndex] |= 256;
								$destIndex++;
							}
						} else {
							for (; $sx < 8; $sx++) {
								$col = $tpix[$tscanoffset + $sx];

								if ($col !== 0) {
									$targetBuffer[$destIdex] = $this->imgPalette[
										$col + $att
									];

									$this->pixrendered[$destIndex] |= 256;
								}

								$destIndex++;
							}
						}
					}
				}

				if (++$this->cntHT == 32) {
					$this->cntHT = 0;
					$this->cntH++;
					$this->cntH %= 2;
					$this->curNt = $this->ntable1[($this->cntV << 1) + $this->cntH];
				}
			}

			$this->validTileData = true;
		}

		$this->cntFV++;

		if ($this->cntFV == 8) {
			$this->cntFV = 0;
			$this->cntVT++;
			if ($this->cntVT == 30) {
				$this->cntVT = 0;
				$this->cntV++;
				$this->cntV %= 2;
				$this->curNt = $this->ntable1[($this->cntV << 1) + $this->cntH];
			} else if ($this->cntVT == 32) {
				$this->cntVT = 0;
			}

			$this->validTileData = false;
		}
	}

	public function renderSpritesPartially($startscan, $scancount, $bgPri) {
		if ($this->f_spVisibility === 1) {
			for ($i = 0; $i < 64; $i++) {
				if ($this->bfPriority[$i] == $bgPri && $this->sprX[$i] >= 0 &&
					$this->sprX[$i] < 256 && $this->sprY[$i] + 8 >= $startscan &&
					$this->sprY[$i] < $startscan + $scancount) {

					if ($this->f_spriteSize === 0) {
						$this->srcy1 = 0;
						$this->srcy2 = 8;

						if ($this->sprY[$i] < $startscan) {
							$this->srcy1 = $startscan - $this->sprY[$i] - 1;
						}

						if ($this->sprY[$i] + 8 > $startscan + $scancount) {
							$this->srcy2 = $startscan + $scancount - $this->sprY[$i] + 1;
						}

						if ($this->f_spPatternTable === 0) {
							$this->ptTile[$this->sprTile[$i]]->render($this->buffer,
								0, $this->srcy1, 8, $this->srcy2, $this->sprX[$i],
								$this->sprY[$i] + 1, $this->sprCol[$i], $this->sprPalette,
								$this->horiFlip[$i], $this->vertFlip[$i], $i, $this->pixrendered);
						} else {
							$this->ptTile[$this->sprTile[$i] + 256]->render($this->buffer,
								0, $this->srcy1, 8, $this->srcy2, $this->sprX[$i], $this->sprY[$i] + 1,
								$this->sprCol[$i], $this->sprPalette, $this->horiFlip[$i], $this->vertFlip[$i],
								$i, $this->pixrendered);
						}
					} else {
						$top = $this->sprTile[$i];

						if (($top & 1) !== 0) {
							$top = $this->sprTile[$i] - 1 + 256;
						}

						$srcy1 = 0;
						$srcy2 = 8;

						if ($this->sprY[$i] < $startscan) {
							$srcy1 = $startscan - $this->sprY[$i] - 1;
						}

						if ($this->sprY[$i] + 8 > $startscan + $scancount) {
							$srcy2 = $startscan + $scancount - $this->sprY[$i];
						}

						$this->ptTile[$top + ($this->vertFlip[$i] ? 1 : 0)]->render(
							$this->buffer,
							0,
							$srcy1,
							8,
							$srcy2,
							$this->sprX[$i],
							$this->sprY[$i] + 1,
							$this->sprCol[$i],
							$this->sprPalette,
							$this->horiFlip[$i],
							$this->vertFlip[$i],
							$i,
							$this->pixrendered
						);

						$srcy1 = 0;
						$srcy2 = 8;

						if ($this->sprY[$i] + 8 < $startscan) {
							$srcy1 = $startscan - ($this->sprY[$i] + 8 + 1);
						}

						if ($this->sprY[$i] + 16 > $startscan + $scancount) {
							$srcy2 = $startscan + $scancount - ($this->sprY[$i] + 8 + 1);
						}

						$this->ptTile[$top + ($this->vertFlip[$i] ? 0 : 1)]->render(
							$this->buffer,
							0,
							$srcy1,
							8,
							$srcy2,
							$this->sprX[$i],
							$this->sprY[$i] + 1 + 8,
							$this->sprCol[$i],
							$this->sprPalette,
							$this->horiFlip[$i],
							$this->vertFlip[$i],
							$i,
							$this->pixrendered
						);
					}
				}
			}
		}
	}

	public function checkSprite0($scan) {
		$this->spr0HitX = -1;
		$this->spr0HitY = -1;

		$tIndexAdd = ($this->f_spPatternTable === 0 ? 0 : 256);
		$x = $this->sprX[0];
		$y = $this->sprY[0] + 1;

		if ($this->f_spriteSize === 0) {
			if ($y <= $scan && $y + 8 > $scan && $x >= -7 && $x < 256) {
				$t = $this->ptTile[$this->sprTile[0] + $tIndexAdd];
				$col = $this->sprCol[0];
				$bgPri = $this->bgPriority[0];

				if ($this->vertFlip[0]) {
					$toffset = 7 - ($scan - $y);
				} else {
					$toffset = $scan - $y;
				}

				$toffset *= 8;
				$bufferIndex = $scan * 256 + $x;

				if ($this->horiFlip[0]) {
					for ($i = 7; $i >= 0; $i--) {
						if ($x >= 0 && $x < 256) {
							if ($bufferIndex >= 0 && $bufferIndex < 61440 && $this->pixrendered[$bufferIndex] !== 0) {
								if ($t->pix[$toffset + $i] !== 0) {
									$this->spr0HitX = $bufferIndex % 256;
									$this->spr0HitY = $scan;
									return true;
								}
							}
						}

						$x++;
						$bufferIndex++;
					}
				} else {
					for ($i = 0; $i < 8; $i++) {
						if ($x >= 0 && $x < 256) {
							if ($bufferIndex >= 0 && $bufferIndex < 61440 && $this->pixrendered[$bufferIndex] !== 0) {
								if ($t->pix[$toffset + $i] !== 0) {
									$this->spr0HitX = $bufferIndex % 256;
									$this->spr0HitY = $scan;
									return true;
								}
							}
						}

						$x++;
						$bufferIndex++;
					}
				}
			}
		} else {
			if ($y <= $scan && $y + 16 > $scan && $x >= -7 && $x < 256) {
				if ($this->vertFlip[0]) {
					$toffset = 15 - ($scan - $y);
				} else {
					$toffset = $scan - $y;
				}

				if ($toffset < 8) {
					$t = $this->ptTile[$this->sprTile[0] + ($this->vertFlip[0] ? 1 : 0) + (($this->sprTile[0] & 1) !== 0 ? 255 : 0)];
				} else {
					$t = $this->ptTile[$this->sprTile[0] + ($this->vertFlip[0] ? 1 : 0) + (($this->sprTile[0] & 1) !== 0 ? 255 : 0)];

					if ($this->vertFlip[0]) {
						$toffset = 15 - $toffset;
					} else {
						$toffset -= 8;
					}
				}

				$toffset *= 8;
				$col = $this->sprCol[0];
				$bgPri = $this->bfPriority[0];

				$bufferIndex = $scan * 256 + $x;

				if ($this->horiFlip[0]) {
					for ($i = 7; $i >= 0; $i--) {
						if ($x >= 0 && $x < 256) {
							if ($bufferIndex >= 0 && $bufferIndex < 61440 && $this->pixrendered[$bufferIndex] !== 0) {
								if ($t->pix[$toffset + $i] !== 0) {
									$this->spr0HitX = $bufferIndex % 256;
									$this->spr0HitY = $scan;
									return true;
								}
							}
						}

						$x++;
						$bufferIndex++;
					}
				} else {
					for ($i = 0; $i < 8; $i++) {
						if ($x >= 0 && $x < 256) {
							if ($bufferIndex >= 0 && $bufferIndex < 61440 && $this->pixrendered[$bufferIndex] !== 0) {
								if ($t->pix[$toffset + $i] !== 0) {
									$this->spr0HitX = $bufferIndex % 256;
									$this->spr0HitY = $scan;
									return true;
								}
							}
						}

						$x++;
						$bufferIndex++;
					}
				}
			}
		}

		return false;
	}

	public function writeMem($address, $value) {
		$this->vramMem[$address] = $value;

		if ($address < 0x2000) {
			$this->vramMem[$address] = $value;
			$this->patternWrite($address, $value);
		} else if ($address >= 0x2000 && $address < 0x23c0) {
			$this->nameTableWrite($this->ntable1[0], $address - 0x2000, $value);
		} else if ($address >= 0x23c0 && $address < 0x2400) {
			$this->attribTableWrite($this->ntable1[0], $address - 0x23c0, $value);
		} else if ($address >= 0x2400 && $address < 0x27c0) {
			$this->nameTableWrite($this->ntable[1], $address - 0x2400, $value);
		} else if ($address >= 0x27c0 && $address < 0x2800) {
			$this->attribTableWrite($this->ntable1[1], $address - 0x27c0, $value);
		} else if ($address >= 0x2800 && $address < 0x2bc0) {
			$this->nameTableWrite($this->ntable1[2], $address - 0x2800, $value);
		} else if ($address >= 0x2bc0 && $address < 0x2c00) {
			$this->attribTableWrite($this->ntable1[2], $address - 0x2bc0, $value);
		} else if ($address >= 0x2c00 && $address < 0x2fc0) {
			$this->nameTableWrite($this->ntable1[3], $address - 0x2c00, $value);
		} else if ($address >= 0x2fc0 && $address < 0x3000) {
			$this->attribTableWrite($this->ntable1[3], $address - 0x2fc0, $value);
		} else if ($address >= 0x3f00 && $address < 0x3f20) {
			$this->updatePalettes();
		}
	}

	public function updatePalettes() {
		for ($i = 0; $i < 16; $i++) {
			if ($this->f_dispType === 0) {
				$this->imgPalette[$i] = $this->palTable->getEntry(
					$this->vramMem[0x3f00 + $i] & 63
				);
			} else {
				$this->imgPalette[$i] = $this->palTable->getEntry(
					$this->vramMem[0x3f00 + $i] & 32
				);
			}
		}

		for ($i = 0; $i < 16; $i++) {
			if ($this->f_dispType === 0) {
				$this->sprPalette[$i] = $this->palTable->getEntry(
					$this->vramMem[0x3f10 + $i] & 63
				);
			} else {
				$this->sprPalette[$i] = $this->palTable->getEntry(
					$this->vramMem[0x3f10 + $i] & 32
				);
			}
		}
	}

	public function patternWrite($address, $value) {
		$tileIndex = floor($address / 16);
		$leftOver = $address % 16;

		if ($leftOver < 8) {
			$this->ptTile[$tileIndex]->setScanline(
				$leftOver,
				$value,
				$this->vramMem[$address + 8]
			);
		} else {
			$this->ptTile[$tileIndex]->setScanline(
				$leftOver - 8,
				$this->vramMem[$address - 8],
				$value
			);
		}
	}

	public function nameTableWrite($index, $address, $value) {
		$this->nameTable[$index]->tile[$address] = $value;

		$this->checkSprite0($this->scanline - 20);
	}

	public function attribTableWrite($index, $address, $value) {
		$this->nameTable[$index]->writeAttrib($address, $value);
	}

	public function spriteRamWriteUpdate($address, $value) {
		$tIndex = floor($address / 4);

		if ($tIndex === 0) {
			$this->checkSprite0($this->scanline - 20);
		}

		if ($address % 4 === 0) {
			$this->sprY[$tIndex] = $value;
		} else if ($address % 4 == 1) {
			$this->sprTile[$tIndex] = $value;
		} else if ($address % 4 == 2) {
			$this->vertFlip[$tIndex] = (($value & 0x80) !== 0);
			$this->horiFlip[$tIndex] = (($value & 0x40) !== 0);
			$this->bgPriority[$tIndex] = (($value & 0x20) !== 0);
			$this->sprCol[$tIndex] = ($value & 3) << 2;
		} else if ($address % 4 == 3) {
			$this->sprX[$tIndex] = $value;
		}
	}

	public function doNMI() {
		$this->setStatusFlag($this::STATUS_VBLANK, true);
		$this->NES->CPU->requestIrq(CPU::IRQ_NMI);
	}
}
