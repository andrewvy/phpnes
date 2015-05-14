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

	public function updateControlReg1($val) {
	}

	public function updateControlReg2($val) {
	}

	public function setMirroring($type) {
	}
}
