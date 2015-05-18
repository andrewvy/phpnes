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

// Use all mappers

use PHPNES\MMAP\Mappers\DirectAccess;
use PHPNES\MMAP\Mappers\NintendoMMC1;
use PHPNES\MMAP\Mappers\NintendoMMC3;
use PHPNES\MMAP\Mappers\Unrom;

class ROM {
	const VERTICAL_MIRRORING = 0;
	const HORIZONTAL_MIRRORING = 1;
	const FOURSCREEN_MIRRORING = 2;
	const SINGLESCREEN_MIRRORING = 3;
	const SINGLESCREEN_MIRRORING2 = 4;
	const SINGLESCREEN_MIRRORING3 = 5;
	const SINGLESCREEN_MIRRORING4 = 6;
	const CHRROM_MIRRORING = 7;

	public $NES;

	public $header;
	public $rom;
	public $vrom;
	public $vromTile;

	public $romCount;
	public $vromCount;
	public $mirroring;
	public $batteryRam;
	public $trainer;
	public $fourScreen;
	public $mapperType;
	public $isValid = false;
	public $mapperName = [];

	public function __construct($NES) {
		$this->NES = $NES;
		$this->mapperName = array_fill(0, 92, "Unknown Mapper");

		$this->prefillMapperName();
	}

	public function prefillMapperName() {
		$this->mapperName[0] = "Direct Access";
		$this->mapperName[1] = "Nintendo MMC1";
		$this->mapperName[2] = "UNROM";
		$this->mapperName[3] = "CNROM";
		$this->mapperName[4] = "Nintendo MMC3";
		$this->mapperName[5] = "Nintendo MMC5";
		$this->mapperName[6] = "FFE F4xxx";
		$this->mapperName[7] = "AOROM";
		$this->mapperName[8] = "FFE F3xxx";
		$this->mapperName[9] = "Nintendo MMC2";
		$this->mapperName[10] = "Nintendo MMC4";
		$this->mapperName[11] = "Color Dreams Chip";
		$this->mapperName[12] = "FFE F6xxx";
		$this->mapperName[15] = "100-in-1 switch";
		$this->mapperName[16] = "Bandai chip";
		$this->mapperName[17] = "FFE F8xxx";
		$this->mapperName[18] = "Jaleco SS8806 chip";
		$this->mapperName[19] = "Namcot 106 chip";
		$this->mapperName[20] = "Famicon Disk System";
		$this->mapperName[21] = "Konami VRC4a";
		$this->mapperName[22] = "Konami VRC2a";
		$this->mapperName[23] = "Konami VRC2a";
		$this->mapperName[24] = "Konami VRC6";
		$this->mapperName[25] = "Konami VRC4b";
		$this->mapperName[32] = "Irem G-101 chip";
		$this->mapperName[33] = "Taito TC0190/TC0350";
		$this->mapperName[34] = "32kb ROM switch";

		$this->mapperName[64] = "Tengen RAMBO-1 chip";
		$this->mapperName[65] = "Irem H-3001 chip";
		$this->mapperName[66] = "GNROM switch";
		$this->mapperName[67] = "SunSoft3 chip";
		$this->mapperName[68] = "SunSoft4 chip";
		$this->mapperName[69] = "SunSoft5 FME-7 chip";
		$this->mapperName[71] = "Camerica chip";
		$this->mapperName[78] = "Irem 74HC161/32-based";
		$this->mapperName[91] = "Pirate HK-SF3 chip";

	}

	public function load($data) {
		$dataLength = strlen($data);

		if (strrpos($data, "NES\x1a") === false) {
			// This is an invalid ROM.
			return false;
		}

		$this->header = array_fill(0, 6, 0x00);

		for ($i = 0; $i < 16; $i++) {
			$this->header[$i] = ord($data[$i]) & 0xFF;
		}

		$this->romCount = $this->header[4];
		$this->vromCount = $this->header[5] * 2;
		$this->mirroring = (($this->header[6] & 1) !== 0 ? 1 : 0);
		$this->batteryRam = ($this->header[6] & 2) !== 0;
		$this->trainer = ($this->header[6] & 4) !== 0;
		$this->fourScreen = ($this->header[6] & 8) !== 0;
		$this->mapperType = ($this->header[6] >> 4) | ($this->header[7] & 0xF0);

		$foundError = false;

		for ($i = 8; $i < 16; $i++) {
			if ($this->header[$i] !== 0) {
				$foundError = true;
				break;
			}
		}

		if ($foundError) {
			$this->mapperType &= 0xF;
		}

		// Load PRG-ROM Banks

		$this->rom = array_fill(0, $this->romCount, 0);

		$offset = 16;

		for ($i = 0; $i < $this->romCount; $i++) {
			$this->rom[$i] = array_fill(0, 16384, 0x00);
			for ($j = 0; $j < 16384; $j++) {
				// if offset + j >= data length
				if ($offset + $j >= $dataLength) {
					break;
				}

				$this->rom[$i][$j] = ord($data[$offset+$j]) & 0xFF;
			}

			$offset += 16384;
		}

		// Load CHR-ROM Banks

		$this->vrom = array_fill(0, $this->vromCount, 0);

		for ($i = 0; $i < $this->vromCount; $i ++) {
			$this->vrom[$i] = array_fill(0, 4096, 0x00);
			for ($j = 0; $j < 4096; $j++) {
				if ($offset + $j >= $dataLength) {
					break;
				}

				$this->vrom[$i][$j] = ord($data[$offset+$j]) & 0xFF;
			}

			$offset += 4096;
		}

		// Create VROM Tiles

		$this->vromTile = array_fill(0, $this->vromCount, 0);

		for ($i = 0; $i < $this->vromCount; $i++) {
			$this->vromTile[$i] = array_fill(0, 256, 0x00);
			for ($j = 0; $j < 256; $j++) {
				$this->vromTile[$i][$j] = new Tile();
			}
		}

		// Convert CHR-ROM Banks to Tiles

		$tileIndex = 0;
		$leftOver = 0;

		for ($v = 0; $v < $this->vromCount; $v++) {
			for ($i = 0; $i < 4096; $i++) {
				$tileIndex = $i >> 4;
				$leftOver = $i % 16;

				if ($leftOver < 8) {
					$this->vromTile[$v][$tileIndex]->setScanline(
						$leftOver,
						$this->vrom[$v][$i],
						$this->vrom[$v][$i+8]
					);
				} else {
					$this->vromTile[$v][$tileIndex]->setScanline(
						$leftOver-8,
						$this->vrom[$v][$i-8],
						$this->vrom[$v][$i]
					);
				}
			}
		}

		$this->isValid = true;
	}

	public function createMapper() {
		$mapper = $this->NES->MapperProvider->getMapperByType($this->mapperType);

		if ($mapper) {
			return new $mapper($this->NES);
		} else {
			// Mapper doesn't exist or isn't supported
		}
	}

	public function getMirroringType() {
	}
}
