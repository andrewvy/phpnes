<?php

/**
 * This file is part of PHPNES - a NES Emulator implemented in PHP
 * Based off of Ben Firshman's JSNES and Jamie Sanders' vNES.
 *
 * @copyright Andrew Vy 2015
 * @license GPL
 */

namespace PHPNES;

use PHPNES\CPU\OpData;

class CPU {
	const IRQ_NORMAL = 0;
	const IRQ_NMI = 1;
	const IRQ_RESET = 2;

	public $NES;
	public $OpData;

	public $mem = [];
	public $REG_ACC;
	public $REG_X;
	public $REG_Y;

	public $SP;
	public $PC;
	public $PC_NEW;

	public $REG_STATUS;

	public $F_CARRY;
	public $F_DECIMAL;
	public $F_INTERRUPT;
	public $F_INTERRUPT_NEW;
	public $F_SIGN;
	public $F_NOTUSED;
	public $F_NOTUSED_NEW;
	public $F_BRK;
	public $F_BRK_NEW;

	public $cyclesToHalt;
	public $crash;
	public $irqRequested;
	public $irqType;

	public function __construct($NES) {
		$this->NES = $NES;
	}

	public function reset() {
		$this->mem = array_fill(0, 0x10000, 0xFF);

		for ($p = 0; $p < 4; $p++) {
			$i = $p * 0x800;

			$this->mem[$i + 0x008] = 0xF7;
			$this->mem[$i + 0x009] = 0xEF;
			$this->mem[$i + 0x00A] = 0xDF;
			$this->mem[$i + 0x00F] = 0xBF;
		}

		for ($i = 0x2001; $i < $this->mem; $i++) {
			$this->mem[$i] = 0x00;
		}

		// Reset CPU Registers
		$this->REG_ACC = 0x00;
		$this->REG_X = 0x00;
		$this->REG_Y = 0x00;

		// Reset stack pointer and program counter
		$this->SP = 0x01FF;
		$this->PC = 0x8000-1;
		$this->PC_NEW = 0x8000-1;

		// Reset status register
		$this->REG_STATUS = 0x28;

		// Set status
		$this->setStatus(0x28);

		// Set flags
		$this->F_CARRY = 0x00;
		$this->F_DECIMAL = 0x00;
		$this->F_INTERRUPT = 1;
		$this->F_INTERRUPT_NEW = 1;
		$this->F_OVERFLOW = 0x00;
		$this->F_SIGN = 0x00;
		$this->F_ZERO = 1;

		$this->F_NOTUSED = 1;
		$this->F_NOTUSED_NEW = 1;
		$this->F_BRK = 1;
		$this->F_BRK_NEW = 1;

		$this->OpData = new OpData();
		$this->cyclesToHalt = 0;

		// Reset crash bool
		$this->crash = false;

		// Interrupt notifications
		$this->irqRequested = false;
		$this->irqType = null;
	}

	public function emulate() {
	}

	public function load($addr) {
		if ($addr < 0x2000) {
			return $this->mem[$addr & 0x7FF];
		} else {
			return $this->NES->MMAP->load($addr);
		}
	}

	public function load16bit($addr) {
		if ($addr < 0x1FFF) {
			return $this->mem[$addr & 0x7FF] | ($this->mem[($addr + 1) & 0x7FF] << 8);
		} else {
			return $this->NES->MMAP->load($addr) | ($this->NES->MMAP->load($addr+1) << 8);
		}
	}

	public function write($addr, $val) {
		if ($addr < 0x2000) {
			$this->mem[$addr & 0x7FF] = $val;
		} else {
			$this->NES->MMAP->write($addr, $val);
		}
	}

	public function requestIrq($type) {
		if ($this->irqRequested === true) {
			if ($type == $this::IRQ_NORMAL) {
				return;
			}
		}

		$this->irqRequested = true;
		$this->irqType = $type;
	}

	public function push($val) {
		$this->NES->MMAP->write($this->SP, $val);
		$this->SP--;
		$this->stackWrap();
	}

	public function stackWrap() {
		$this->SP = 0x0100 | ($this->SP & 0xFF);
	}

	public function pull() {
		$this->SP++;
		$this->stackWrap();
		return $this->NES->MMAP->load($this->SP);
	}

	public function pageCrossed($addr1, $addr2) {
		return (($addr1 & 0xFF00) != ($addr2 & 0xFF00));
	}

	public function haltCycles($n) {
		$this->cyclesToHalt += $n;
	}

	public function doNonMaskableInterrupt($status) {
		// Only when vblank interrupts are enabled
		if (($this->NES->MMAP->load(0x2000) & 128) != 0) {
			$this->PC_NEW++;
			$this->push(($this->PC_NEW >> 8) && 0xFF);
			$this->push($status);

			$this->PC_NEW = $this->NES->MMAP->load(0xFFFA) | ($this->NES->MMAP->load(0xFFFB) << 8);
			$this->PC_NEW--;
		}
	}

	public function doResetInterrupt() {
		$this->PC_NEW = $this->NES->MMAP->load(0xFFFC) | ($this->NES->MMAP->load(0xFFFD) << 8);
		$this->PC_NEW--;
	}

	public function doIrq($status) {
		$this->PC_NEW++;
		$this->push(($this->PC_NEW >> 8) & 0xFF);
		$this->push($this->PC_NEW & 0xFF);
		$this->push($status);
		$this->F_INTERRUPT_NEW = 1;
		$this->F_BRK_NEW = 0;

		$this->PC_NEW = $this->NES->MMAP->load(0xFFFE) | ($this->NES->MMAP->load(0xFFFF) << 8);
		$this->PC_NEW--;
	}

	public function getStatus() {
		return ($this->F_CARRY)
				| ($this->F_ZERO << 1)
				| ($this->F_INTERRUPT << 2)
				| ($this->F_DECIMAL << 3)
				| ($this->F_BRK << 4)
				| ($this->F_NOTUSED << 5)
				| ($this->F_OVERFLOW << 6)
				| ($this->F_SIGN << 7);
	}

	public function setStatus($status) {
		$this->F_CARRY = (st) & 1;
		$this->F_ZERO = (st >> 1) & 1;
		$this->F_INTERRUPT = (st >> 2) & 1;
		$this->F_DECIMAL = (st >> 3) & 1;
		$this->F_BRK = (st >> 4) & 1;
		$this->F_NOTUSED = (st >> 5) & 1;
		$this->F_OVERFLOW = (st >> 6) & 1;
		$this->F_SIGN = (st >> 7) & 1;
	}
}
