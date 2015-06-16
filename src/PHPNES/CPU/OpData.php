<?php

/**
 * This file is part of PHPNES - a NES Emulator implemented in PHP
 * Based off of Ben Firshman's JSNES and Jamie Sanders' vNES.
 *
 * @copyright Andrew Vy 2015
 * @license GPL
 */

namespace PHPNES\CPU;

class OpData {
	public $opdata = [];
	public $cycTable = [];
	public $instname = [];
	public $addrDesc = [];
	const INS_ADC = 0;
	const INS_AND = 1;
	const INS_ASL = 2;

	const INS_BCC = 3;
	const INS_BCS = 4;
	const INS_BEQ = 5;
	const INS_BIT = 6;
	const INS_BMI = 7;
	const INS_BNE = 8;
	const INS_BPL = 9;
	const INS_BRK = 10;
	const INS_BVC = 11;
	const INS_BVS = 12;

	const INS_CLC = 13;
	const INS_CLD = 14;
	const INS_CLI = 15;
	const INS_CLV = 16;
	const INS_CMP = 17;
	const INS_CPX = 18;
	const INS_CPY = 19;

	const INS_DEC = 20;
	const INS_DEX = 21;
	const INS_DEY = 22;

	const INS_EOR = 23;

	const INS_INC = 24;
	const INS_INX = 25;
	const INS_INY = 26;

	const INS_JMP = 27;
	const INS_JSR = 28;

	const INS_LDA = 29;
	const INS_LDX = 30;
	const INS_LDY = 31;
	const INS_LSR = 32;

	const INS_NOP = 33;

	const INS_ORA = 34;

	const INS_PHA = 35;
	const INS_PHP = 36;
	const INS_PLA = 37;
	const INS_PLP = 38;

	const INS_ROL = 39;
	const INS_ROR = 40;
	const INS_RTI = 41;
	const INS_RTS = 42;

	const INS_SBC= 43;
	const INS_SEC = 44;
	const INS_SED = 45;
	const INS_SEI = 46;
	const INS_STA = 47;
	const INS_STX = 48;
	const INS_STY = 49;

	const INS_TAX= 50;
	const INS_TAY = 51;
	const INS_TSX = 52;
	const INS_TXA = 53;
	const INS_TXS = 54;
	const INS_TYA = 55;

	const INS_DUMMY = 56;

	const ADDR_ZP = 0;
	const ADDR_REL = 0;
	const ADDR_IMP = 0;
	const ADDR_ABS = 0;
	const ADDR_ACC = 0;
	const ADDR_IMM = 0;
	const ADDR_ZPX = 6;
	const ADDR_ZPY = 7;
	const ADDR_ABSX = 8;
	const ADDR_ABSY = 9;
	const ADDR_PREIDXIND = 10;
	const ADDR_POSTIDXIND = 11;
	const ADDR_INDABS = 12;

	public function __construct() {
		$this->opdata = array_fill(0, 256, 0xFF);

		$this->setOp($this::INS_ADC, 0x69, $this::ADDR_IMM, 2, 2);
		$this->setOp($this::INS_ADC, 0x65, $this::ADDR_ZP, 2, 3);
		$this->setOp($this::INS_ADC, 0x75, $this::ADDR_ZPX, 2, 4);
		$this->setOp($this::INS_ADC, 0x6D, $this::ADDR_ABS, 3, 4);
		$this->setOp($this::INS_ADC, 0x7D, $this::ADDR_ABSX, 3, 4);
		$this->setOp($this::INS_ADC, 0x79, $this::ADDR_ABSY, 3, 4);
		$this->setOp($this::INS_ADC, 0x61, $this::ADDR_PREIDXIND, 2, 6);
		$this->setOp($this::INS_ADC, 0x71, $this::ADDR_POSTIDXIND, 2, 5);

		$this->setOp($this::INS_AND, 0x29, $this::ADDR_IMM, 2, 2);
		$this->setOp($this::INS_AND, 0x25, $this::ADDR_ZP, 2, 3);
		$this->setOp($this::INS_AND, 0x35, $this::ADDR_ZPX, 2, 4);
		$this->setOp($this::INS_AND, 0x2D, $this::ADDR_ABS, 3, 4);
		$this->setOp($this::INS_AND, 0x3D, $this::ADDR_ABSX, 3, 4);
		$this->setOp($this::INS_AND, 0x39, $this::ADDR_ABSY, 3, 4);
		$this->setOp($this::INS_AND, 0x21, $this::ADDR_PREIDXIND, 2, 6);
		$this->setOp($this::INS_AND, 0x31, $this::ADDR_POSTIDXIND, 2, 5);

		$this->setOp($this::INS_ASL, 0x0A, $this::ADDR_ACC, 1, 2);
		$this->setOp($this::INS_ASL, 0x06, $this::ADDR_ACC, 2, 5);
		$this->setOp($this::INS_ASL, 0x16, $this::ADDR_ACC, 2, 6);
		$this->setOp($this::INS_ASL, 0x0E, $this::ADDR_ACC, 3, 6);
		$this->setOp($this::INS_ASL, 0x1E, $this::ADDR_ACC, 3, 7);

		$this->setOp($this::INS_BCC, 0x90, $this::ADDR_REL, 2, 2);

		$this->setOp($this::INS_BCS, 0xB0, $this::ADDR_REL, 2, 2);

		$this->setOp($this::INS_BEQ, 0xF0, $this::ADDR_REL, 2, 2);

		$this->setOp($this::INS_BIT, 0x24, $this::ADDR_ZP, 2, 3);
		$this->setOp($this::INS_BIT, 0x2C, $this::ADDR_ABS, 3, 4);

		$this->setOp($this::INS_BMI, 0x30, $this::ADDR_REL, 2, 2);

		$this->setOp($this::INS_BNE, 0xD0, $this::ADDR_REL, 2, 2);

		$this->setOp($this::INS_BPL, 0x10, $this::ADDR_REL, 2, 2);

		$this->setOp($this::INS_BRK, 0x00, $this::ADDR_IMP, 1, 7);

		$this->setOp($this::INS_BVC, 0x50, $this::ADDR_REL, 2, 2);

		$this->setOp($this::INS_CLC, 0x18, $this::ADDR_IMP, 1, 2);

		$this->setOp($this::INS_CLD, 0xD8, $this::ADDR_IMP, 1, 2);

		$this->setOp($this::INS_CLI, 0x58, $this::ADDR_IMP, 1, 2);

		$this->setOp($this::INS_CLV, 0xB8, $this::ADDR_IMP, 1, 2);

		$this->setOp($this::INS_CMP, 0xC9, $this::ADDR_IMM, 2, 2);
		$this->setOp($this::INS_CMP, 0xC5, $this::ADDR_ZP, 2, 3);
		$this->setOp($this::INS_CMP, 0xD5, $this::ADDR_ZPX, 2, 4);
		$this->setOp($this::INS_CMP, 0xCD, $this::ADDR_ABS, 3, 4);
		$this->setOp($this::INS_CMP, 0xDD, $this::ADDR_ABSX, 3, 4);
		$this->setOp($this::INS_CMP, 0xD9, $this::ADDR_ABSY, 3, 4);
		$this->setOp($this::INS_CMP, 0xC1, $this::ADDR_PREIDXIND, 2, 6);
		$this->setOp($this::INS_CMP, 0xD1, $this::ADDR_POSTIDXIND, 2, 5);

		$this->setOp($this::INS_CPX, 0xE0, $this::ADDR_IMM, 2, 2);
		$this->setOp($this::INS_CPX, 0xE4, $this::ADDR_ZP, 2, 3);
		$this->setOp($this::INS_CPX, 0xEC, $this::ADDR_ABS, 3, 4);

		$this->setOp($this::INS_CPY, 0xC0, $this::ADDR_IMM, 2, 2);
		$this->setOp($this::INS_CPY, 0xC4, $this::ADDR_ZP, 2, 3);
		$this->setOp($this::INS_CPY, 0xCC, $this::ADDR_ABS, 3, 4);

		$this->setOp($this::INS_DEC, 0xC6, $this::ADDR_ZP, 2, 5);
		$this->setOp($this::INS_DEC, 0xD6, $this::ADDR_ZPX, 2, 6);
		$this->setOp($this::INS_DEC, 0xCE, $this::ADDR_ABS, 3, 6);
		$this->setOp($this::INS_DEC, 0xDE, $this::ADDR_ABSX, 3, 7);

		$this->setOp($this::INS_DEX, 0xCA, $this::ADDR_IMP, 1, 2);

		$this->setOp($this::INS_DEY, 0x88, $this::ADDR_IMP, 1, 2);

		$this->setOp($this::INS_EOR, 0x49, $this::ADDR_IMM, 2, 2);
		$this->setOp($this::INS_EOR, 0x45, $this::ADDR_ZP, 2, 3);
		$this->setOp($this::INS_EOR, 0x55, $this::ADDR_ZPX, 2, 4);
		$this->setOp($this::INS_EOR, 0x4D, $this::ADDR_ABS, 3, 4);
		$this->setOp($this::INS_EOR, 0x5D, $this::ADDR_ABSX, 3, 4);
		$this->setOp($this::INS_EOR, 0x59, $this::ADDR_ABSY, 3, 4);
		$this->setOp($this::INS_EOR, 0x41, $this::ADDR_PREIDXIND, 2, 6);
		$this->setOp($this::INS_EOR, 0x51, $this::ADDR_POSTIDXIND, 2, 5);

		$this->setOp($this::INS_INC, 0xE6, $this::ADDR_ZP, 2, 5);
		$this->setOp($this::INS_INC, 0xF6, $this::ADDR_ZPX, 2, 6);
		$this->setOp($this::INS_INC, 0xEE, $this::ADDR_ABS, 3, 6);
		$this->setOp($this::INS_INC, 0xFE, $this::ADDR_ABSX, 3, 7);

		$this->setOp($this::INS_INX, 0xE8, $this::ADDR_IMP, 1, 2);

		$this->setOp($this::INS_INY, 0xC8, $this::ADDR_IMP, 1, 2);

		$this->setOp($this::INS_JMP, 0x4C, $this::ADDR_ABS, 3, 5);
		$this->setOp($this::INS_JMP, 0x6C, $this::ADDR_INDABS, 3, 5);

		$this->setOp($this::INS_JSR, 0x20, $this::ADDR_ABS, 3, 6);

		$this->setOp($this::INS_LDA, 0xA9, $this::ADDR_IMM, 2, 3);
		$this->setOp($this::INS_LDA, 0xA5, $this::ADDR_ZP, 2, 3);
		$this->setOp($this::INS_LDA, 0xB5, $this::ADDR_ZPX, 2, 4);
		$this->setOp($this::INS_LDA, 0xAD, $this::ADDR_ABS, 3, 4);
		$this->setOp($this::INS_LDA, 0xBD, $this::ADDR_ABSX, 3, 4);
		$this->setOp($this::INS_LDA, 0xB9, $this::ADDR_ABSY, 3, 4);
		$this->setOp($this::INS_LDA, 0xA1, $this::ADDR_PREIDXIND, 2, 6);
		$this->setOp($this::INS_LDA, 0xB1, $this::ADDR_POSTIDXIND, 2, 5);

		$this->setOp($this::INS_LDX, 0xA2, $this::ADDR_IMM, 2, 2);
		$this->setOp($this::INS_LDX, 0xA6, $this::ADDR_ZP, 2, 3);
		$this->setOp($this::INS_LDX, 0xB6, $this::ADDR_ZPY, 2, 4);
		$this->setOp($this::INS_LDX, 0xAE, $this::ADDR_ABS, 3, 4);
		$this->setOp($this::INS_LDX, 0xBE, $this::ADDR_ABSY, 3, 4);

		$this->setOp($this::INS_LDY, 0xA0, $this::ADDR_IMM, 2, 2);
		$this->setOp($this::INS_LDY, 0xA4, $this::ADDR_ZP, 2, 3);
		$this->setOp($this::INS_LDY, 0xB4, $this::ADDR_ZPX, 2, 4);
		$this->setOp($this::INS_LDY, 0xAC, $this::ADDR_ABS, 3, 4);
		$this->setOp($this::INS_LDY, 0xBC, $this::ADDR_ABSX, 3, 4);

		$this->setOp($this::INS_LSR, 0x4A, $this::ADDR_ACC, 1, 2);
		$this->setOp($this::INS_LSR, 0x46, $this::ADDR_ZP, 2, 5);
		$this->setOp($this::INS_LSR, 0x56, $this::ADDR_ZPX, 2, 6);
		$this->setOp($this::INS_LSR, 0x4E, $this::ADDR_ABS, 3, 6);
		$this->setOp($this::INS_LSR, 0x5E, $this::ADDR_ABSX, 3, 7);

		$this->setOp($this::INS_NOP, 0xEA, $this::ADDR_IMP, 1, 2);

		$this->setOp($this::INS_ORA, 0x09, $this::ADDR_IMM, 2, 2);
		$this->setOp($this::INS_ORA, 0x05, $this::ADDR_ZP, 2, 3);
		$this->setOp($this::INS_ORA, 0x15, $this::ADDR_ZPX, 2, 4);
		$this->setOp($this::INS_ORA, 0x0D, $this::ADDR_ABS, 3, 4);
		$this->setOp($this::INS_ORA, 0x1D, $this::ADDR_ABSX, 3, 4);
		$this->setOp($this::INS_ORA, 0x19, $this::ADDR_ABSY, 3, 4);
		$this->setOp($this::INS_ORA, 0x01, $this::ADDR_PREIDXIND, 2, 6);
		$this->setOp($this::INS_ORA, 0x11, $this::ADDR_POSTIDXIND, 2, 5);

		$this->setOp($this::INS_PHA, 0x48, $this::ADDR_IMP, 1, 3);

		$this->setOp($this::INS_PHP, 0x08, $this::ADDR_IMP, 1, 3);

		$this->setOp($this::INS_PLA, 0x68, $this::ADDR_IMP, 1, 4);

		$this->setOp($this::INS_PLP, 0x28, $this::ADDR_IMP, 1, 4);

		$this->setOp($this::INS_ROL, 0x2A, $this::ADDR_ACC, 1, 2);
		$this->setOp($this::INS_ROL, 0x26, $this::ADDR_ZP, 2, 5);
		$this->setOp($this::INS_ROL, 0x36, $this::ADDR_ZPX, 2, 6);
		$this->setOp($this::INS_ROL, 0x2E, $this::ADDR_ABS, 3, 6);
		$this->setOp($this::INS_ROL, 0x3E, $this::ADDR_ABSX, 3, 7);

		$this->setOp($this::INS_ROR, 0x6A, $this::ADDR_ACC, 1, 2);
		$this->setOp($this::INS_ROR, 0x66, $this::ADDR_ZP, 2, 5);
		$this->setOp($this::INS_ROR, 0x76, $this::ADDR_ZPX, 2, 6);
		$this->setOp($this::INS_ROR, 0x6E, $this::ADDR_ABS, 3, 6);
		$this->setOp($this::INS_ROR, 0x7E, $this::ADDR_ABSX, 3, 7);

		$this->setOp($this::INS_RTI, 0x40, $this::ADDR_IMP, 1, 6);

		$this->setOp($this::INS_RTS, 0x60, $this::ADDR_IMP, 1, 6);

		$this->setOp($this::INS_SBC, 0xF9, $this::ADDR_IMM, 2, 2);
		$this->setOp($this::INS_SBC, 0xE5, $this::ADDR_ZP, 2, 3);
		$this->setOp($this::INS_SBC, 0xF5, $this::ADDR_ZPX, 2, 4);
		$this->setOp($this::INS_SBC, 0xED, $this::ADDR_ABS, 3, 4);
		$this->setOp($this::INS_SBC, 0xFD, $this::ADDR_ABSX, 3, 4);
		$this->setOp($this::INS_SBC, 0xF9, $this::ADDR_ABSY, 3, 4);
		$this->setOp($this::INS_SBC, 0xE1, $this::ADDR_PREIDXIND, 2, 6);
		$this->setOp($this::INS_SBC, 0xF1, $this::ADDR_POSTIDXIND, 2, 5);

		$this->setOp($this::INS_SEC, 0x38, $this::ADDR_IMP, 1, 2);

		$this->setOp($this::INS_SED, 0xF8, $this::ADDR_IMP, 1, 2);

		$this->setOp($this::INS_SEI, 0x78, $this::ADDR_IMP, 1, 2);

		$this->setOp($this::INS_STA, 0x85, $this::ADDR_ZP, 2, 3);
		$this->setOp($this::INS_STA, 0x95, $this::ADDR_ZPX, 2, 4);
		$this->setOp($this::INS_STA, 0x8D, $this::ADDR_ABS, 3, 4);
		$this->setOp($this::INS_STA, 0x9D, $this::ADDR_ABSX, 3, 4);
		$this->setOp($this::INS_STA, 0x99, $this::ADDR_ABSY, 3, 4);
		$this->setOp($this::INS_STA, 0x81, $this::ADDR_PREIDXIND, 2, 6);
		$this->setOp($this::INS_STA, 0x91, $this::ADDR_POSTIDXIND, 2, 5);

		$this->setOp($this::INS_STX, 0x86, $this::ADDR_ZP, 2, 3);
		$this->setOp($this::INS_STX, 0x96, $this::ADDR_ZPY, 2, 4);
		$this->setOp($this::INS_STX, 0x8E, $this::ADDR_ABS, 3, 4);

		$this->setOp($this::INS_STY, 0x84, $this::ADDR_ZP, 2, 3);
		$this->setOp($this::INS_STY, 0x94, $this::ADDR_ZPX, 2, 4);
		$this->setOp($this::INS_STY, 0x8C, $this::ADDR_ABS, 3, 4);

		$this->setOp($this::INS_TAX, 0xAA, $this::ADDR_IMP, 1, 2);

		$this->setOp($this::INS_TAY, 0xA8, $this::ADDR_IMP, 1, 2);

		$this->setOp($this::INS_TSX, 0xBA, $this::ADDR_IMP, 1, 2);

		$this->setOp($this::INS_TXA, 0x8A, $this::ADDR_IMP, 1, 2);

		$this->setOp($this::INS_TXS, 0x9A, $this::ADDR_IMP, 1, 2);

		$this->setOp($this::INS_TYA, 0x98, $this::ADDR_IMP, 1, 2);

		$this->cycTable = [
			7,6,2,8,3,3,5,5,3,2,2,2,4,4,6,6,
			2,5,2,8,4,4,6,6,2,4,2,7,4,4,7,7,
			6,6,2,8,3,3,5,5,4,2,2,2,4,4,6,6,
			2,5,2,8,4,4,6,6,2,4,2,7,4,4,7,7,
			6,6,2,8,3,3,5,5,3,2,2,2,3,4,6,6,
			2,5,2,8,4,4,6,6,2,4,2,7,4,4,7,7,
			6,6,2,8,3,3,5,5,4,2,2,2,5,4,6,6,
			2,5,2,8,4,4,6,6,2,4,2,7,4,4,7,7,
			2,6,2,6,3,3,3,3,2,2,2,2,4,4,4,4,
			2,6,2,6,4,4,4,4,2,5,2,5,5,5,5,5,
			2,6,2,6,3,3,3,3,2,2,2,2,4,4,4,4,
			2,5,2,5,4,4,4,4,2,4,2,4,4,4,4,4,
			2,6,2,8,3,3,5,5,2,2,2,2,4,4,6,6,
			2,5,2,8,4,4,6,6,2,4,2,7,4,4,7,7,
			2,6,3,8,3,3,5,5,2,2,2,2,4,4,6,6,
			2,5,2,8,4,4,6,6,2,4,2,7,4,4,7,7
		];

		$this->instname[0] = "ADC";
		$this->instname[1] = "AND";
		$this->instname[2] = "ASL";
		$this->instname[3] = "BCC";
		$this->instname[4] = "BCS";
		$this->instname[5] = "BEQ";
		$this->instname[6] = "BIT";
		$this->instname[7] = "BMI";
		$this->instname[8] = "BNE";
		$this->instname[9] = "BPL";
		$this->instname[10] = "BRK";
		$this->instname[11] = "BVC";
		$this->instname[12] = "BVS";
		$this->instname[13] = "CLC";
		$this->instname[14] = "CLD";
		$this->instname[15] = "CLI";
		$this->instname[16] = "CLV";
		$this->instname[17] = "CMP";
		$this->instname[18] = "CPX";
		$this->instname[19] = "CPY";
		$this->instname[20] = "DEC";
		$this->instname[21] = "DEX";
		$this->instname[22] = "DEY";
		$this->instname[23] = "EOR";
		$this->instname[24] = "INC";
		$this->instname[25] = "INX";
		$this->instname[26] = "INY";
		$this->instname[27] = "JMP";
		$this->instname[28] = "JSR";
		$this->instname[29] = "LDA";
		$this->instname[30] = "LDX";
		$this->instname[31] = "LDY";
		$this->instname[32] = "LSR";
		$this->instname[33] = "NOP";
		$this->instname[34] = "ORA";
		$this->instname[35] = "PHA";
		$this->instname[36] = "PHP";
		$this->instname[37] = "PLA";
		$this->instname[38] = "PLP";
		$this->instname[39] = "ROL";
		$this->instname[40] = "ROR";
		$this->instname[41] = "RTI";
		$this->instname[42] = "RTS";
		$this->instname[43] = "SBC";
		$this->instname[44] = "SEC";
		$this->instname[45] = "SED";
		$this->instname[46] = "SEI";
		$this->instname[47] = "STA";
		$this->instname[48] = "STX";
		$this->instname[49] = "STY";
		$this->instname[50] = "TAX";
		$this->instname[51] = "TAY";
		$this->instname[52] = "TSX";
		$this->instname[53] = "TXA";
		$this->instname[54] = "TXS";
		$this->instname[55] = "TYA";

		$this->addrDesc = [
			"Zero Page",
			"Relative",
			"Implied",
			"Absolute",
			"Accumulator",
			"Immediate",
			"Zero Page, X",
			"Zero Page, Y",
			"Absolute, X",
			"Absolute, Y",
			"Preindexed Indirect",
			"Postindexed Indirect",
			"Indirect Absolute"
		];

	}

	public function setOp($inst, $op, $addr, $size, $cycles) {
		$this->opdata[$op] =
			(($inst & 0xFF)) |
			(($addr & 0xFF) << 8) |
			(($size & 0xFF) << 16) |
			(($cycles & 0xFF) << 24);
	}
}
