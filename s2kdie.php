#!/usr/local/bin/php -q

<?
/*
 * AKAI S2000/S3000/S900 Disk Image Editor v1.1.2
 * Copyright (C) 2004 Michael Kane <mick@checkoutwax.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA 02111, USA.
 *
 */
$tmpfile = "/tmp/akaitmp";
$floppy = "/dev/fd0";
$pathtosetfdprm = "/usr/bin/"; /* include trailing slash */

$chars = array (
		"0",
		"1",
		"2",
		"3",
		"4",
		"5",
		"6",
		"7",
		"8",
		"9",
		" ",
		"A",
		"B",
		"C",
		"D",
		"E",
		"F",
		"G",
		"H",
		"I",
		"J",
		"K",
		"L",
		"M",
		"N",
		"O",
		"P",
		"Q",
		"R",
		"S",
		"T",
		"U",
		"V",
		"W",
		"X",
		"Y",
		"Z",
		"#",
		"+",
		"-",
		"." 
);

$types = array (
		chr ( 240 ) => "PROGRAM",
		chr ( 243 ) => "SAMPLE",
		chr ( 120 ) => "EFFECTS",
		chr ( 237 ) => "MULTI",
		chr ( 99 ) => "OS",
		chr ( 0 ) => "DELETED" 
);

$dp2 = str_repeat ( "\x00", 3166 );

$dp3 = "\x18\x19\x1E\x0A\x18\x0B\x17\x0F\x0E\x0A\x0A\x0A"; /* Volume name in AKAI format. (offset 4736) */

$dp4 = "\x00\x00\x00\x11\x00\x01\x01\x23\x00\x00\x32\x09\x0C\xFF" . str_repeat ( "\x00", 358 );
$ddp4 = "\x00\x00\x32\x10\x00\x01\x01\x00\x00\x00\x32\x09\x0C\xFF" . str_repeat ( "\x00", 358 );

$dp5 = str_repeat ( "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00", 512 );
/* 512 possible directory entries starting at offset 5120. */

$dp6 = str_repeat ( str_repeat ( "\x00", 1024 ), 1583 );
/* 1583 * 1024 byte sectors for data. */

set_time_limit ( 0 );
function read() {
	$fp = fopen ( "/dev/stdin", "r" );
	$input = fgets ( $fp, 255 );
	fclose ( $fp );
	return str_replace ( "\n", "", $input );
}
function as2ak($var) { /* convert normal ASCII to AKAI format. */
	global $chars;
	if (strlen ( $var ) > 12) {
		$var = substr ( $var, 0, 12 );
	}
	$return = '';
	for($i = 0; $i <= strlen ( $var ) - 1; $i ++) {
		$char = strtoupper ( $var [$i] );
		$key = array_search ( $char, $chars );
		$return = $return . chr ( $key );
	}
	return str_pad ( $return, 12, chr ( 10 ) );
}
function tonote($note) {
	$notes = array (
			"A",
			"A#",
			"B",
			"C",
			"C#",
			"D",
			"D#",
			"E",
			"F",
			"F#",
			"G",
			"G#" 
	);
	$key = - 1;
	$il = 0;
	for($i = 0; $i <= ord ( $note ) - 21; $i ++) {
		if ($il == 12) {
			$il = 0;
		}
		$nr = $notes [$il];
		if ($notes [$il] == "C") {
			$key ++;
		}
		$il ++;
	}
	return str_pad ( $nr, 2, "_" ) . $key;
}

if (! function_exists ( 'str_split' )) {
	function str_split($string, $chunksize = 1) {
		preg_match_all ( '/(' . str_repeat ( '.', $chunksize ) . ')/Uims', $string, $matches );
		return $matches [1];
	}
}
function ak2as($var) { /* convert AKAI format text to ASCII */
	global $chars;
	if (strlen ( $var ) > 12) {
		$var = substr ( $var, 0, 12 );
	}
	$return = '';
	for($i = 0; $i <= strlen ( $var ) - 1; $i ++) {
		$key = $chars [ord ( $var [$i] )];
		$return = $return . $key;
	}
	return str_pad ( $return, 12, " " );
}
function dp1() {
	global $type, $dp1;
	if ($type == "S2000") {
		$id = chr ( 17 );
	}
	if ($type == "S3000") {
		$id = chr ( 16 );
	}
	if ($type == "S900") {
		$id = chr ( 0 );
	}
	$dp1 = "\x0A\x0A\x0A\x0A\x0A\x0A\x0A\x0A\x0A\x0A\x0A\x0A\x00\x00\x06\x0A\xFF\x00\x00\x04\x00\x00\x00" . $id . str_repeat ( "\x0A\x0A\x0A\x0A\x0A\x0A\x0A\x0A\x0A\x0A\x0A\x0A\x00\x00\x06\x0A\x00\x00\x00\x04\x00\x00\x00" . $id, 63 ) . str_repeat ( "\x00\x40", 17 );
}
function image() {
	global $dp1, $dp2, $dp3, $dp4, $ddp4, $dp5, $dp6, $file_array, $file, $volname, $file_size, $newd, $type;
	dp1 ();
	if ($file_array != '') {
		$ifat = implode ( "", $file_array );
	} else {
		$ifat = '';
	}
	if ($type == "S900") {
		$contents = str_pad ( $ifat, 1536, chr ( 0 ) ) . str_repeat ( "\x00", 8 ) . str_pad ( $newd, 2552, chr ( 0 ) );
	} else {
		$contents = $dp1 . str_pad ( $newd, 3166, chr ( 0 ) ) . $volname . str_repeat ( "\x00", 358 ) . str_pad ( $ifat, 12288, chr ( 0 ) );
	}
	$cl = 0;
	$dp7 = '';
	if ($file_array != '') {
		foreach ( $file_array as $fe ) {
			$dp7 = $dp7 . str_pad ( $file [$cl], ceil ( $file_size [$cl] / 1024 ) * 1024, chr ( 0 ) );
			$cl ++;
		}
	} else {
		$dp7 = '';
	}
	$pl = 1620992;
	if ($type == "S900") {
		$pl = 815104;
	}
	$contents = $contents . str_pad ( $dp7, $pl, chr ( 0 ) );
	return $contents;
}
function importimage() {
	global $volname, $contents, $file_array, $file, $freeblocks, $file_size, $type, $floppy_size;
	$volname = substr ( $contents, 4736, 26 );
	if ($contents [23] == chr ( 17 )) {
		$type = "S2000";
	}
	if ($contents [23] == chr ( 16 )) {
		$type = "S3000";
	}
	if ($contents [23] == chr ( 0 )) {
		$type = "S900";
	}
	$cl = 0;
	$block = 0;
	$block_map_start = 1570;
	$endb = 3166;
	$offs = 5120;
	$te = 512;
	$fb = 1583;
	if ($type == "S900") {
		$block_map_start = 1536;
		$endb = 3136;
		$offs = 0;
		$te = 64;
		$fb = 796;
	}
	if ($contents [$offs] != chr ( 0 )) {
		$block_map = substr ( $contents, $block_map_start, $endb );
		$blocks = str_split ( $contents, 1024 );
		$bc = 0;
		for($i = 0; $i < $te; $i ++) {
			if ($contents [$offs + ($i * 24)] != chr ( 0 )) {
				$file_entry = substr ( $contents, $offs + ($i * 24), 24 );
				$file_array [$cl] = $file_entry;
				$sb = hexdec ( str_pad ( dechex ( ord ( $file_entry [21] ) ), 2, "0", STR_PAD_LEFT ) . str_pad ( dechex ( ord ( $file_entry [20] ) ), 2, "0", STR_PAD_LEFT ) );
				$file_size [$cl] = hexdec ( str_pad ( dechex ( ord ( $file_entry [19] ) ), 2, "0", STR_PAD_LEFT ) . str_pad ( dechex ( ord ( $file_entry [18] ) ), 2, "0", STR_PAD_LEFT ) . str_pad ( dechex ( ord ( $file_entry [17] ) ), 2, "0", STR_PAD_LEFT ) );
				$file [$cl] = $blocks [$sb];
				$block = $block + ceil ( $file_size [$cl] / 1024 );
				$cl ++;
			} else {
				break;
			}
		}
		for($i = 0; $i <= strlen ( $block_map ); $i = $i + 2) {
			if ($bc >= sizeof ( $file_array )) {
				continue;
			}
			if (($block_map [$i] == chr ( 0 )) and ($block_map [$i + 1] == chr ( 0 ))) {
				continue;
			}
			if ((($block_map [$i] == chr ( 0 )) and ($block_map [$i + 1] == chr ( 128 ))) or (($block_map [$i] == chr ( 0 )) and ($block_map [$i + 1] == chr ( 192 )))) {
				$bc ++;
			} else {
				$file [$bc] = $file [$bc] . $blocks [hexdec ( str_pad ( dechex ( ord ( $block_map [$i + 1] ) ), 2, "0", STR_PAD_LEFT ) . str_pad ( dechex ( ord ( $block_map [$i] ) ), 2, "0", STR_PAD_LEFT ) )];
			}
		}
		$freeblocks = $fb - $block;
	}
}

if (! function_exists ( 'str_split' )) {
	Function str_split($string, $chunksize = 1) {
		preg_match_all ( '/(' . str_repeat ( '.', $chunksize ) . ')/Uims', $string, $matches );
		return $matches [1];
	}
}
function numtohexs($var, $co) {
	$var2 = str_pad ( dechex ( $var ), $co * 2, "0", STR_PAD_LEFT );
	$var = str_split ( $var2, 2 );
	krsort ( $var );
	$cl = '';
	foreach ( $var as $hi ) {
		$cl = $cl . chr ( hexdec ( $hi ) );
	}
	return $cl;
}
function defrag() {
	global $file_array, $file, $file_size, $newd, $type;
	if ($file_array != '') {
		$cl = 0;
		$nextb = ' ';
		$b2 = "\x11";
		if ($type == "S900") {
			$b2 = "\x04";
		}
		$b1 = "\x00";
		$newd = '';
		foreach ( $file_array as $fe ) {
			$file_array [$cl] [20] = $b2;
			$file_array [$cl] [21] = $b1;
			$sb = hexdec ( str_pad ( dechex ( ord ( $file_array [$cl] [21] ) ), 2, "0", STR_PAD_LEFT ) . str_pad ( dechex ( ord ( $file_array [$cl] [20] ) ), 2, "0", STR_PAD_LEFT ) );
			$size = $file_size [$cl];
			$sizeb = ceil ( ($size / 1024) );
			$nextb = ($sb + $sizeb);
			$nextb = str_pad ( dechex ( $nextb ), 4, "0", STR_PAD_LEFT );
			$b1 = chr ( hexdec ( $nextb [0] . $nextb [1] ) );
			$b2 = chr ( hexdec ( $nextb [2] . $nextb [3] ) );
			$cl ++;
		}
		$cl = 0;
		foreach ( $file_array as $fe ) {
			$sb = hexdec ( str_pad ( dechex ( ord ( $fe [21] ) ), 2, "0", STR_PAD_LEFT ) . str_pad ( dechex ( ord ( $fe [20] ) ), 2, "0", STR_PAD_LEFT ) );
			$size = $file_size [$cl];
			$blocks = ceil ( $size / 1024 );
			if ($blocks > 1) {
				for($i = ($sb + 1); $i <= ($sb + $blocks - 1); $i ++) {
					$newd = $newd . numtohexs ( $i, 2 );
				}
			}
			$cl ++;
			if ($type == "S900") {
				$newd = $newd . "\x00\x80";
			} else {
				$newd = $newd . "\x00\xC0";
			}
		}
	}
}

$type = "S2000";
dp1 ();
$contents = $dp1 . $dp2 . $dp3 . $dp4 . $dp5 . $dp6;
$file_array = '';
$file_size = '';
$file = '';
$freeblocks = 1583;
$floppy_size = 1638400;
$read_line_upper = '';

importimage ();

print "AKAI S2000/S3000/S900 Disk Image Editor v1.1.2\n(? for help.)\n\n";

$floppyt = 1;
if (! file_exists ( $pathtosetfdprm . "setfdprm" )) {
	print "Floppy read/writes disabled, setfdprm not found.\n\n";
	$floppyt = 0;
}

while ( ($read_line_upper != "QUIT") and ($read_line_upper != "EXIT") and ($read_line_upper != "Q") ) {
	print "Command: ";
	$read_line = trim ( fgets ( STDIN ) );
	$read_line_upper = strtoupper ( $read_line );
	
	if ((substr ( $read_line_upper, 0, 4 ) == "HELP") or ($read_line_upper == "?")) {
		print "\nSWITCH <sourceid> <destid>  Swap file order.";
		print "DELAY  <bpm>                Calculate milliseconds for echo/reverb delay.";
		print "FSAVE  <id>                 Save a file using id number from DIR command.";
		print "FLOAD  <filename>           Load a file into the image (defaults to sample, use ATTR to change).";
		print "WLOAD  <filename>           Load a 16bit 22kHz/44kHz WAV file as an akai s2/3k sample file.";
		print "       <id>                 Save an akai sample as a WAV file.";
		print "SAVE   <filename>           Save current image to file (leave blank to write to floppy).";
		print "LOAD   <filename>           Load image from image file (leave blank to load from floppy).";
		print "                            If loading an S900 image from a floppy disk use LOAD -S900";
		print "VOL    <name>               Display or change current volume name.";
		print "REN    <id> <name>          Rename file with id to specified name.";
		print "COPY   <id> <name>          Copy file with id to specified name.";
		print "DEL    <id>                 Delete file.";
		print "ATTR   <id> <type>          Change file type (" . implode ( ",", $types ) . ").";
		print "PD     <id>                 Program file header dump.";
		print "SD     <id>                 Sample file header dump.";
		print "PC     <id> <num> <chan>    Change the program number and midi channel of a program file.";
		print "BLANK  <type>               Format the current image in memory.  (S900,S2000,S3000)";
		print "MAP                         Display the disk block map.";
		print "DIR                         Display the contents of the current image.";
		print "";
	}
	
	if (substr ( $read_line_upper, 0, 4 ) == "SAVE") {
		$args = explode ( " ", $read_line );
		if ($args [1] == '') {
			$imagefile = $tmpfile;
		} else {
			$imagefile = $args [1];
		}
		if (is_dir ( $imagefile )) {
			print "Specified filename is a directory.\n";
			continue;
		}
		$contents = image ();
		$file_pointer = fopen ( "$imagefile", "w" );
		fwrite ( $file_pointer, $contents, $floppy_size );
		fclose ( $file_pointer );
		if ($args [1] == '') {
			if ($floppyt == 0) {
				print "Install fdutils and/or change the \$setfdprmset variable in s2kdie.php\n";
				continue;
			}
			if ($floppy_size == 819200) {
				exec ( $pathtosetfdprm . "setfdprm $floppy sect=5 dd ssize=1024 cyl=80" );
			} else {
				exec ( $pathtosetfdprm . "setfdprm $floppy sect=10 hd ssize=1024 cyl=80" );
			}
			exec ( "cat $imagefile > $floppy 2> /dev/null", $ra, $rv );
			if ($rv) {
				print "Please insert an AKAI formatted floppy disk.\n";
			}
			unlink ( "$imagefile" );
		}
		if (! $rv) {
			print "Image saved.\n";
		}
	}
	
	if (substr ( $read_line_upper, 0, 5 ) == "FLOAD") {
		$args = explode ( " ", $read_line );
		if (file_exists ( $args [1] )) {
			if ($file_array != '') {
				foreach ( $file_array as $file_entry ) {
					if ((trim ( ak2as ( $file_entry ) ) == strtoupper ( trim ( $args [1] ) )) or (substr ( $types [chr ( ord ( $file_array [$args [1]] [16] - 160 ) )], 0, 1 ) == strtoupper ( trim ( $args [1] ) ))) {
						print "File with that name already exists.\n";
						continue (2);
					}
				}
				$file_index = sizeof ( $file_array );
			}
			if ($file_array == '') {
				$file_index = 0;
			}
			$box = ceil ( filesize ( $args [1] ) / 1024 );
			if ($freeblocks >= $box) {
				$file_size [$file_index] = filesize ( $args [1] );
				$tip = "\xF3";
				$file_array [$file_index] = as2ak ( $args [1] ) . "\x00\x00\x06\x0A" . $tip . "\xf0\x01\x00\x11\x00\x00\x11";
				if ($type == "S900") {
					$file_array [$file_index] = strtoupper ( str_pad ( substr ( $args [1], 0, 10 ), 10, " " ) ) . "\x00\x00\x00\x00\x00\x00" . "S" . "\x00\x00\x00\x00\x00\x00\x00";
				}
				if ($type == "S3000") {
					$file_array [$file_index] = as2ak ( $args [1] ) . "\x00\x00\x00\x02" . $tip . "\xf0\x00\x00\x00\x00\x00\x0C";
				}
				$freeblocks = $freeblocks - ceil ( $file_size [$file_index] / 1024 );
				$file_pointer = fopen ( $args [1], "r" );
				$file [$file_index] = str_pad ( fread ( $file_pointer, $file_size [$file_index] ), ceil ( $file_size [$file_index] / 1024 ) * 1024, chr ( 0 ) );
				fclose ( $file_pointer );
				$nextb = numtohexs ( $file_size [$file_index], 3 );
				$file_array [$file_index] [19] = $nextb [2];
				$file_array [$file_index] [18] = $nextb [1];
				$file_array [$file_index] [17] = $nextb [0];
				defrag ();
				print "File loaded onto disk image.\n";
			} else {
				print "Insufficient space.  $freeblocks blocks free.  $box required.\n";
			}
		} else {
			print "File not found\n";
		}
	}
	
	if (substr ( $read_line_upper, 0, 6 ) == "SWITCH") {
		if ($file_array == '') {
			print "No files to switch!\n";
			continue;
		}
		$args = explode ( " ", $read_line_upper );
		if (intval ( $args [1] ) > sizeof ( $file_array )) {
			print "Invalid source file ID.\n";
			continue;
		}
		if (intval ( $args [2] ) > sizeof ( $file_array )) {
			print "Invalid destination file ID.\n";
			continue;
		}
		$floppy_sizetmp = $file_size [$args [1]];
		$fatmp = $file_array [$args [1]];
		$fitmp = $file [$args [1]];
		$file_size [$args [1]] = $file_size [$args [2]];
		$file [$args [1]] = $file [$args [2]];
		$file_array [$args [1]] = $file_array [$args [2]];
		$file_array [$args [2]] = $fatmp;
		$file_size [$args [2]] = $floppy_sizetmp;
		$file [$args [2]] = $fitmp;
		$floppy_sizetmp = '';
		$fatmp = '';
		$fitmp = '';
		defrag ();
		print "Files switched.\n";
	}
	
	if (substr ( $read_line_upper, 0, 2 ) == "SD") {
		$args = explode ( " ", $read_line_upper );
		if ($file [$args [1]] [0] == chr ( 3 )) {
			print "\nSHNAME: " . ak2as ( substr ( $file [$args [1]], 3, 12 ) ) . "\n";
			print "SBANDW: ";
			if ($file [$args [1]] [1] == chr ( 0 )) {
				print "22050Hz";
			}
			if ($file [$args [1]] [1] == chr ( 1 )) {
				print "44100Hz";
			}
			print "\n";
			print "SPITCH: " . tonote ( $file [$args [1]] [2] ) . "\n";
			print "SPTYPE: ";
			if ($file [$args [1]] [19] == chr ( 0 )) {
				print "normal looping";
			}
			if ($file [$args [1]] [19] == chr ( 1 )) {
				print "loop until release";
			}
			if ($file [$args [1]] [19] == chr ( 2 )) {
				print "no looping";
			}
			if ($file [$args [1]] [19] == chr ( 3 )) {
				print "play to sample end";
			}
			print "\n STUNO: ";
			$pm = "+";
			$semi = ord ( $file [$args [1]] [21] );
			$cent = ord ( $file [$args [1]] [20] );
			if ($semi > 99) {
				$semi = 256 - $semi;
				$pm = "-";
			}
			if ($cent > 99) {
				$cent = 256 - $cent;
				$pm = "-";
			}
			print "Semi.Cent " . $pm . str_pad ( $semi, 2, "0", STR_PAD_LEFT ) . "." . str_pad ( $cent, 2, "0", STR_PAD_LEFT ) . "\n";
			$b4 = str_pad ( dechex ( ord ( $file [$args [1]] [33] ) ), 2, "0", STR_PAD_LEFT );
			$b3 = str_pad ( dechex ( ord ( $file [$args [1]] [32] ) ), 2, "0", STR_PAD_LEFT );
			$b2 = str_pad ( dechex ( ord ( $file [$args [1]] [31] ) ), 2, "0", STR_PAD_LEFT );
			$b1 = str_pad ( dechex ( ord ( $file [$args [1]] [30] ) ), 2, "0", STR_PAD_LEFT );
			print "SLNGTH: " . hexdec ( $b4 . $b3 . $b2 . $b1 ) . "\n";
			print "\n";
		} else {
			if ($args [1] == '') {
				print "Usage: sd <id>\n";
			} else {
				print "Not a valid S2000/S3000 sample file.\n";
			}
		}
	}
	
	if (substr ( $read_line_upper, 0, 2 ) == "PC") {
		$args = explode ( " ", $read_line_upper );
		if (($args [1] >= sizeof ( $file_array )) or ($file_array == '')) {
			print "File not found.\n";
			continue;
		}
		if ($file_array [$args [1]] [16] == chr ( 240 )) {
			$file [$args [1]] [15] = chr ( $args [2] - 1 );
			$file [$args [1]] [16] = chr ( $args [3] - 1 );
			print ak2as ( substr ( $file [$args [1]], 3, 12 ) ) . "\nProgram:  " . $args [2] . "\nMidi channel " . $args [3] . "\n";
		} else {
			if ($args [1] == '') {
				print "Usage: pc <id> <program> <channel>\n";
			} else {
				print "File not a program. (check file type)\n";
			}
		}
	}
	
	if (substr ( $read_line_upper, 0, 2 ) == "PD") {
		$args = explode ( " ", $read_line_upper );
		if ($file_array [$args [1]] [16] == chr ( 240 )) {
			print "\nPRNAME: " . ak2as ( substr ( $file [$args [1]], 3, 12 ) ) . "\n";
			print "PRGNUM: " . (ord ( $file [$args [1]] [15] ) + 1) . "\n";
			print "PMCHAN: " . (ord ( $file [$args [1]] [16] ) + 1) . "\n";
			print "POLYPH: " . (ord ( $file [$args [1]] [17] ) + 1) . "\n";
			print "PRIORT: ";
			if ($file [$args [1]] [18] == chr ( 0 )) {
				print "low";
			}
			if ($file [$args [1]] [18] == chr ( 1 )) {
				print "norm";
			}
			if ($file [$args [1]] [18] == chr ( 2 )) {
				print "high";
			}
			if ($file [$args [1]] [18] == chr ( 3 )) {
				print "hold";
			}
			print "\n";
			print "PLAYLO: " . tonote ( $file [$args [1]] [19] ) . "\n"; /* 21-127 = A-1 to G-8 */
			print "PLAYHI: " . tonote ( $file [$args [1]] [20] ) . "\n";
			print "OSHIFT: " . (ord ( $file [$args [1]] [21] )) . "\n"; /* not used */
			print "OUTPUT: ";
			if ($file [$args [1]] [22] == chr ( 255 )) {
				print "off";
			} else {
				print ord ( $file [$args [1]] [22] ) + 1;
			}
			print "\n";
			print "STEREO: " . (ord ( $file [$args [1]] [23] )) . "\n";
			print "PANPOS: " . (ord ( $file [$args [1]] [24] )) . "\n";
			print "PRLOUD: " . (ord ( $file [$args [1]] [25] )) . "\n";
			print "V_LOUD: " . (ord ( $file [$args [1]] [26] )) . "\n";
			print "K_LOUD: " . (ord ( $file [$args [1]] [27] )) . "\n"; /* not used */
			print "P_LOUD: " . (ord ( $file [$args [1]] [28] )) . "\n"; /* not used */
			print "PANRAT: " . (ord ( $file [$args [1]] [29] )) . " (LFO speed)\n";
			print "PANDEP: " . (ord ( $file [$args [1]] [30] )) . "\n";
			print "PANDEL: " . (ord ( $file [$args [1]] [31] )) . "\n";
			print "K_PANP: " . (ord ( $file [$args [1]] [32] )) . "\n";
			print "LFORAT: " . (ord ( $file [$args [1]] [33] )) . "\n";
			print "LFODEP: " . (ord ( $file [$args [1]] [34] )) . "\n";
			print "LFODEL: " . (ord ( $file [$args [1]] [35] )) . "\n";
			print "MWLDEP: " . (ord ( $file [$args [1]] [36] )) . "\n";
			print "PRSDEP: " . (ord ( $file [$args [1]] [37] )) . "\n";
			print "VELDEP: " . (ord ( $file [$args [1]] [38] )) . "\n";
			print "B_PTCH: " . (ord ( $file [$args [1]] [39] )) . "\n";
			print "P_PTCH: " . (ord ( $file [$args [1]] [40] )) . "\n";
			print "KXFADE: ";
			if ((ord ( $file [$args [1]] [41] )) == 0) {
				print "off";
			} else {
				print "on";
			}
			print "\n";
			print "GROUPS: " . (ord ( $file [$args [1]] [42] )) . "\n";
			print " TPNUM: " . (ord ( $file [$args [1]] [43] )) . "\n";
			print "\n";
		} else {
			if ($args [1] == '') {
				print "Usage: pd <id>\n";
			} else {
				print "File not a valid S2000/S3000 program.\n";
			}
		}
	}
	
	if (substr ( $read_line_upper, 0, 4 ) == "ATTR") {
		$args = explode ( " ", $read_line_upper );
		foreach ( $types as $key => $value ) {
			if ($args [2] == $value) {
				if ($type == "S900") {
					$file_array [$args [1]] [16] = chr ( ord ( $key ) - 160 );
				} else {
					$file_array [$args [1]] [16] = $key;
				}
				print "Attribute changed.\n";
			}
		}
	}
	
	if (substr ( $read_line_upper, 0, 4 ) == "COPY") {
		$args = explode ( " ", $read_line_upper );
		if ($args [2] == '') {
			print "Please specify a filename for the new file.\n";
			continue;
		}
		if (($args [1] >= sizeof ( $file_array )) or ($file_array == '')) {
			print "File not found.\n";
			continue;
		}
		$file_index = sizeof ( $file_array );
		$box = ceil ( $file_size [$args [1]] / 1024 );
		if ($freeblocks >= $box) {
			$file_array [$file_index] = $file_array [$args [1]];
			$file_size [$file_index] = $file_size [$args [1]];
			$args [0] = '';
			$args [1] = '';
			$args = trim ( implode ( " ", $args ) );
			if ($args != '') {
				if ($type == "S900") {
					$file_array [$file_index] = strtoupper ( substr ( str_pad ( $args, 10, " " ), 0, 10 ) ) . substr ( $file_array [$file_index], - 14, 14 );
				} else {
					$file_array [$file_index] = as2ak ( "$args" ) . substr ( $file_array [$file_index], - 12, 12 );
				}
			}
			$freeblocks = $freeblocks - ceil ( $file_size [$file_index] / 1024 );
			defrag ();
			print "File copied.\n";
		} else {
			print "Insufficient space.\n";
		}
	}
	
	if (substr ( $read_line_upper, 0, 4 ) == "LOAD") {
		$args = explode ( " ", $read_line );
		$floppy_size = 1638400;
		if (strtoupper ( $args [1] ) == '-S900') {
			$floppy_size = 819200;
			$args [1] = '';
		}
		if ($args [1] == '') {
			if ($floppyt == 0) {
				print "Install fdutils and/or change the $$setfdprmset variable in s2kdie.php\n";
				continue;
			}
			if ($floppy_size == 819200) {
				exec ( $pathtosetfdprm . "setfdprm $floppy sect=5 dd ssize=1024 cyl=80" );
			} else {
				exec ( $pathtosetfdprm . "setfdprm $floppy sect=10 hd ssize=1024 cyl=80" );
			}
			exec ( "cat $floppy > $tmpfile 2> /dev/null", $ra, $rv );
			if ($rv) {
				print "Please insert an AKAI floppy disk.\n";
				continue;
			}
			$imagefile = $tmpfile;
		} else {
			$imagefile = $args [1];
		}
		if (file_exists ( "$imagefile" )) {
			if (filesize ( $imagefile ) == 819200) {
				$floppy_size = 819200;
			}
			$contents = '';
			$file_pointer = fopen ( "$imagefile", "r" );
			$contents = fread ( $file_pointer, $floppy_size );
			fclose ( $file_pointer );
		} else {
			print "There was an error loading the disk.\n";
			continue;
		}
		if ($args [1] == '') {
			unlink ( "$tmpfile" );
		}
		if ($contents [1569] == chr ( 64 )) {
			$file_array = '';
			$freeblocks = 1583;
			$file_size = '';
			$file = '';
			importimage ();
			defrag ();
		} else {
			$file_array = '';
			$freeblocks = 796;
			$file_size = '';
			importimage ();
			defrag ();
		}
		print "$type image loaded.\n";
	}
	
	if (substr ( $read_line_upper, 0, 5 ) == "WLOAD") {
		$args = explode ( " ", $read_line );
		if (file_exists ( $args [1] )) {
			$file_size [$file_index] = filesize ( $args [1] );
			$file_pointer = fopen ( "$args[1]", "r" );
			$wav = fread ( $file_pointer, filesize ( $args [1] ) );
			fclose ( $file_pointer );
			if (substr ( $wav, 8, 4 ) == "WAVE") {
				$channels = ord ( $wav [22] );
				$comp = ord ( $wav [20] );
				if ($comp != 1) {
					print "WAV file not uncompressed PCM.\n";
					continue;
				}
				$bits = ord ( $wav [34] );
				if ($bits != 16) {
					print "WAV file isn't 16bit.\n";
					continue;
				}
				$data = substr ( $wav, 44, (filesize ( $args [1] ) - 44) );
				$box = ceil ( strlen ( $data ) / 1024 );
				if ($box > $freeblocks) {
					print "Insufficient space.\n";
					continue;
				}
				if ($channels > 2) {
					print "More than 2 channels?\n";
					continue;
				}
				if ($channels == 2) {
					$c1 = '';
					$c2 = '';
					$dat = strlen ( $data );
					preg_match_all ( '/(..)../s', $data, $matches );
					$c1 = implode ( "", $matches [1] );
					preg_match_all ( '/..(..)/s', $data, $matches );
					$c2 = implode ( "", $matches [1] );
					$data = $c1;
				}
				$b4 = str_pad ( dechex ( ord ( $wav [27] ) ), 2, "0", STR_PAD_LEFT );
				$b3 = str_pad ( dechex ( ord ( $wav [26] ) ), 2, "0", STR_PAD_LEFT );
				$b2 = str_pad ( dechex ( ord ( $wav [25] ) ), 2, "0", STR_PAD_LEFT );
				$b1 = str_pad ( dechex ( ord ( $wav [24] ) ), 2, "0", STR_PAD_LEFT );
				$hz = hexdec ( $b4 . $b3 . $b2 . $b1 );
				$nextb = numtohexs ( strlen ( $data ) / 2, 4 );
				if ($type != "S900") {
					if ($hz == 44100) {
						$hz = 1;
					} else if ($hz == 22050) {
						$hz = 0;
					} else {
						print "Sample rate not supported.\n";
						continue;
					}
					$smphdr = "\x03" . chr ( $hz ) . "\x3C" . as2ak ( $args [1] ) . "\x80\x01\x00\x00\x02\x00\x00\x00\x04\x01\x00";
					$smphdr = $smphdr . $nextb . "\x00\x00\x00\x00" . $nextb . $nextb;
					$hds = 140;
				} else {
					$smphdr = str_pad ( strtoupper ( $args [1] ), 10, " " ) . "\x00\x00\x00\x00" . $nextb . numtohexs ( $hz, 2 ) . "\xC0\x03\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00" . chr ( 140 ) . chr ( 185 ) . chr ( 0 ) . chr ( 78 ) . "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00" . chr ( 224 ) . chr ( 43 ) . chr ( 38 ) . "\x00\x00\x00";
					$hds = 60;
				}
				$file_index = sizeof ( $file_array );
				if ($file_array == '') {
					$file_index = 0;
				}
				$file [$file_index] = str_pad ( $smphdr, $hds, chr ( 0 ) ) . $data;
				if ($channels == 2) {
					$file [$file_index + 1] = str_pad ( $smphdr, $hds, chr ( 0 ) ) . $c2;
				}
				$tip = "\xF3";
				if ($channels == 2) {
					$dn1 = as2ak ( $args [1] );
					$dn1 [10] = "\x27";
					$dn1 [11] = "\x16";
					$file_array [$file_index] = $dn1 . "\x00\x00\x06\x0A" . $tip . "\xf0\x01\x00\x11\x00\x00\x11";
					if ($type == "S900") {
						$file_array [$file_index] = strtoupper ( str_pad ( substr ( $args [1], 0, 10 ), 10, " " ) ) . "\x00\x00\x00\x00\x00\x00" . "S" . "\x00\x00\x00\x00\x00\x00\x00";
						$file_array [$file_index] [8] = "-";
						$file_array [$file_index] [9] = "L";
					}
					if ($type == "S3000") {
						$file_array [$file_index] = $dn1 . "\x00\x00\x00\x02" . $tip . "\xf0\x00\x00\x00\x00\x00\x0C";
					}
					
					if ($type == "S900") {
						$file [$file_index] [8] = "-";
						$file [$file_index] [9] = "L";
						$file [$file_index + 1] [8] = "-";
						$file [$file_index + 1] [9] = "R";
						$dn2 = strtoupper ( str_pad ( substr ( $args [1], 0, 10 ), 10, " " ) );
						$file_array [$file_index] [8] = "-";
						$file_array [$file_index] [9] = "L";
						$dn2 [8] = "-";
						$dn2 [9] = "R";
					} else {
						$file [$file_index] [13] = "\x27";
						$file [$file_index] [14] = "\x16";
						$file [$file_index + 1] [13] = "\x27";
						$file [$file_index + 1] [14] = "\x1C";
						$dn2 = as2ak ( $args [1] );
						$dn2 [10] = "\x27";
						$dn2 [11] = "\x1C";
					}
					
					$file_array [$file_index + 1] = $dn2 . "\x00\x00\x06\x0A" . $tip . "\xf0\x01\x00\x11\x00\x00\x11";
					if ($type == "S900") {
						$file_array [$file_index + 1] = $dn2 . "\x00\x00\x00\x00\x00\x00" . "S" . "\x00\x00\x00\x00\x00\x00\x00";
					}
					if ($type == "S3000") {
						$file_array [$file_index + 1] = $dn2 . "\x00\x00\x00\x02" . $tip . "\xf0\x00\x00\x00\x00\x00\x0C";
					}
					$file_size [$file_index] = strlen ( $file [$file_index] );
					$file_size [$file_index + 1] = strlen ( $file [$file_index + 1] );
					$fb = ($file_size [$file_index] + $file_size [$file_index + 1]);
				} else {
					$file_array [$file_index] = as2ak ( $args [1] ) . "\x00\x00\x06\x0A" . $tip . "\xf0\x01\x00\x11\x00\x00\x11";
					if ($type == "S900") {
						$dn2 = strtoupper ( str_pad ( substr ( $args [1], 0, 10 ), 10, " " ) );
						$file_array [$file_index] = $dn2 . "\x00\x00\x00\x00\x00\x00" . "S" . "\x00\x00\x00\x00\x00\x00\x00";
					}
					if ($type == "S3000") {
						$file_array [$file_index] = as2ak ( $args [1] ) . "\x00\x00\x00\x02" . $tip . "\xf0\x00\x00\x00\x00\x00\x0C";
					}
					$file_size [$file_index] = strlen ( $file [$file_index] );
					$fb = $file_size [$file_index];
				}
				$freeblocks = $freeblocks - ceil ( $fb / 1024 );
				$nextb = numtohexs ( $file_size [$file_index], 3 );
				$file_array [$file_index] [17] = $nextb [0];
				$file_array [$file_index] [18] = $nextb [1];
				$file_array [$file_index] [19] = $nextb [2];
				if ($channels == 2) {
					$file_array [$file_index + 1] [17] = $nextb [0];
					$file_array [$file_index + 1] [18] = $nextb [1];
					$file_array [$file_index + 1] [19] = $nextb [2];
				}
				defrag ();
				if ($channels == 1) {
					print "WAV imported as akai sample.\n";
				} else {
					print "Stereo WAV imported as akai samples.\n";
				}
			} else {
				print "WAV file invalid.\n";
			}
		} else {
			print "File not found.\n";
		}
	}
	
	if (substr ( $read_line_upper, 0, 5 ) == "FSAVE") {
		$args = explode ( " ", $read_line_upper );
		if (($args [1] >= sizeof ( $file_array )) or ($file_array == '')) {
			print "File not found.\n";
			continue;
		}
		if ($type == "S900") {
			$fname = strtolower ( trim ( substr ( $file_array [$args [1]], 0, 10 ) ) . "." . substr ( $file_array [$args [1]] [16], 0, 1 ) );
		} else {
			$fname = strtolower ( trim ( ak2as ( $file_array [$args [1]] ) ) . "." . substr ( $types [$file_array [$args [1]] [16]], 0, 1 ) );
		}
		$file_pointer = fopen ( $fname, "w" );
		fwrite ( $file_pointer, $file [$args [1]], $file_size [$args [1]] );
		fclose ( $file_pointer );
		if ($type != "S900") {
			print trim ( ak2as ( $file_array [$args [1]] ) );
		} else {
			print trim ( substr ( $file_array [$args [1]], 0, 10 ) );
		}
		print " saved.\n";
	}
	
	if (substr ( $read_line_upper, 0, 5 ) == "WSAVE") {
		$args = explode ( " ", $read_line_upper );
		/*
		 * if ($type == "S900") {
		 * print "Currently only samples from S2000/S3000 disk images can be saved as WAV.\n";
		 * continue;
		 * }
		 */
		if (($args [1] >= sizeof ( $file_array )) or ($file_array == '')) {
			print "File not found.\n";
			continue;
		}
		if (($file_array [$args [1]] [16] != chr ( 243 )) and ($file_array [$args [1]] [16] != "S")) {
			print "Not a valid sample file.\n";
			continue;
		}
		if ($type == "S900") {
			$fname = strtolower ( trim ( substr ( $file_array [$args [1]], 0, 10 ) ) ) . ".wav";
		} else {
			$fname = strtolower ( trim ( ak2as ( $file_array [$args [1]] ) ) ) . ".wav";
		}
		$wav = "RIFF" . numtohexs ( $file_size [$args [1]] - 140 + 36, 4 ) . "WAVEfmt " . chr ( 16 ) . "\x00\x00\x00" . "\x01\x00" . chr ( 1 ) . chr ( 0 );
		if ($file [$args [1]] [1] == chr ( 0 )) {
			$wav = $wav . chr ( 34 ) . chr ( 86 );
			$br = 22050;
		}
		if ($file [$args [1]] [1] == chr ( 1 )) {
			$wav = $wav . chr ( 68 ) . chr ( 172 );
			$br = 44100;
		}
		$hdrsize = 140;
		if ($type == "S900") {
			$wav = $wav . $file [$args [1]] [20] . $file [$args [1]] [21];
			$br = hexdec ( str_pad ( dechex ( ord ( $file [$args [1]] [21] ) ), 2, "0", STR_PAD_LEFT ) . str_pad ( dechex ( ord ( $file [$args [1]] [20] ) ), 2, "0", STR_PAD_LEFT ) );
			$hdrsize = 60;
		}
		$data = substr ( $file [$args [1]], $hdrsize, $file_size [$args [1]] - $hdrsize );
		if ($type == "S900") {
			$data2 = '';
			for($i = 0; $i < strlen ( $data ); $i = $i + 2) {
				$val = str_pad ( dechex ( ord ( $data [$i] ) ), 2, "0", STR_PAD_LEFT );
				$val = chr ( hexdec ( $val [0] . "0" ) );
				$word = $val . $data [$i + 1];
				$data2 = $data2 . $word;
			}
			$data = $data2;
			$data2 = '';
		}
		$wav = $wav . "\x00\x00" . numtohexs ( $br * 1 * 16 / 8, 4 ) . numtohexs ( 1 * 16 / 8, 2 ) . chr ( 16 ) . "\x00data" . numtohexs ( strlen ( $data ), 4 ) . $data;
		$file_pointer = fopen ( $fname, "w" );
		fwrite ( $file_pointer, $wav );
		fclose ( $file_pointer );
		if ($type == "S900") {
			print trim ( substr ( $file_array [$args [1]], 0, 10 ) );
		} else {
			print trim ( ak2as ( $file_array [$args [1]] ) );
		}
		print " saved as WAV file.\n";
	}
	
	if (substr ( $read_line_upper, 0, 3 ) == "VOL") {
		if ($type == "S900") {
			print "S900 disks have no volume name.\n";
			continue;
		}
		$args = explode ( " ", $read_line_upper );
		$args [0] = '';
		$args = trim ( implode ( " ", $args ) );
		if ($args != '') {
			$volname = as2ak ( "$args" ) . substr ( $volname, 12, 14 );
		}
		print ak2as ( "$volname" ) . "\n";
	}
	
	if (substr ( $read_line_upper, 0, 3 ) == "REN") {
		$args = explode ( " ", $read_line_upper );
		if ($args [1] == "") {
			print "You might want to specify an id to rename, and new name.\n";
			continue;
		}
		$args [0] = '';
		$arg = $args [1];
		$args [1] = '';
		if (($arg >= sizeof ( $file_array )) or ($file_array == '')) {
			print "File not found.\n";
			continue;
		}
		$args = trim ( implode ( " ", $args ) );
		if ($args != '') {
			if ($type != "S900") {
				$file_array [$arg] = as2ak ( "$args" ) . substr ( $file_array [$arg], - 12, 12 );
			} else {
				$file_array [$arg] = strtoupper ( substr ( str_pad ( $args, 10, " " ), 0, 10 ) ) . substr ( $file_array [$arg], - 14, 14 );
			}
			print "File renamed.\n";
		}
	}
	
	if (substr ( $read_line_upper, 0, 5 ) == "BLANK") {
		$args = explode ( " ", $read_line_upper );
		if (count ( $args ) != 1) {
			$args [1] = "S2000";
		}
		if (($args [1] == "S3000") or ($args [1] == "S2000") or ($args [1] == "S900")) {
			$type = $args [1];
		} else {
			print "Invalid type.  S900/S2000/S3000 are valid.\n";
			continue;
		}
		dp1 ();
		$freeblocks = 1583;
		$floppy_size = 1638400;
		if ($args [1] == "S3000") {
			$contents = $dp1 . $dp2 . $dp3 . $ddp4 . $dp5 . $dp6;
		} else if ($args [1] == "S2000") {
			$contents = $dp1 . $dp2 . $dp3 . $dp4 . $dp5 . $dp6;
		} else {
			$contents = str_repeat ( "\x00", 819200 );
			$freeblocks = 796;
			$floppy_size = 819200;
		}
		$file_array = '';
		$file_size = '';
		$file = '';
		$newd = '';
		importimage ();
		print "Image in memory blanked.\n";
	}
	
	if (substr ( $read_line_upper, 0, 5 ) == "DELAY") {
		$args = explode ( " ", $read_line_upper );
		if ($args [1] == '') {
			print "Usage: DELAY <bpm>";
			continue;
		}
		print "1/4: " . round ( 60000 / $args [1] ) . "ms   1/8: " . round ( (60000 / $args [1]) / 2 ) . "ms\n";
		$read_line_upper = '';
	}
	
	if (substr ( $read_line_upper, 0, 3 ) == "DEL") {
		$args = explode ( " ", $read_line_upper );
		if (($args [1] >= sizeof ( $file_array )) or ($file_array == '')) {
			print "File not found.\n";
			continue;
		}
		if ($args [1] != '') {
			if ($type == "S900") {
				$name = trim ( substr ( $file_array [$args [1]], 0, 10 ) );
			} else {
				$name = ak2as ( $file_array [$args [1]] );
			}
			$freeblocks = $freeblocks + ceil ( $file_size [$args [1]] / 1024 );
			array_splice ( $file, $args [1], 1 );
			array_splice ( $file_array, $args [1], 1 );
			array_splice ( $file_size, $args [1], 1 );
			print trim ( $name ) . " removed.\n";
			if (sizeof ( $file_array ) == 0) {
				$file_array = '';
				$file_size = '';
				$file = '';
			} else {
				defrag ();
			}
		} else {
			print "Please specify a file number.\n";
		}
	}
	
	if (substr ( $read_line_upper, 0, 3 ) == "MAP") {
		if ($file_array != '') {
			for($i = 0; $i < strlen ( $newd ); $i ++) {
				print strtoupper ( str_pad ( dechex ( ord ( $newd [$i] ) ), 2, "0", STR_PAD_LEFT ) ) . " ";
			}
		} else {
			print "No files to map!";
		}
		print "\n";
	}
	
	if (substr ( $read_line_upper, 0, 3 ) == "DIR") {
		print "\n";
		if ($type != "S900") {
			print "      $type Volume: " . ak2as ( $volname ) . "\n\n";
		} else {
			print "      $type Volume.\n\n";
		}
		if ($file_array != '') {
			print "      Filename       Type        Bytes\n";
			$li = 0;
			$blocks = 0;
			foreach ( $file_array as $file_entry ) {
				print str_pad ( "[" . $li . "]", 5, " ", STR_PAD_LEFT );
				print " ";
				if ($type == "S900") {
					print str_pad ( substr ( $file_entry, 0, 10 ), 12, " " );
				} else {
					print ak2as ( "$file_entry" );
				}
				print "   ";
				if ($type == "S900") {
					$file_entry [16] = chr ( ord ( $file_entry [16] ) + 160 );
				}
				if ($types [$file_entry [16]] == '') {
					$ftype = "UNKNOWN";
				} else {
					$ftype = $types [$file_entry [16]];
				}
				print str_pad ( "<" . $ftype . ">", 12, " " );
				print $file_size [$li];
				print "\n";
				$li ++;
			}
			print "\n      $freeblocks unused sectors.  (" . $freeblocks * 1024 . " bytes free)\n\n";
		} else {
			print "      Empty disk.  (";
			if ($type == "S900") {
				print "815104";
			} else {
				print "1620992";
			}
			print " bytes free)\n\n";
		}
	}
}
print "\n";

?>
