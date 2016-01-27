#!/usr/local/bin/php -q

<?
/*  AKAI S2000/S3000/S900 Disk Image Editor v1.1.2
 *  Copyright (C) 2004 Michael Kane <mick@checkoutwax.com>
 *
 *  This program is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program; if not, write to the Free Software
 *  Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA 02111, USA.
 *
 */

$tmpfile =		"/tmp/akaitmp";
$floppy = 		"/dev/fd0";
$pathtosetfdprm =	"/usr/bin/"; /* include trailing slash */

$chars = array ("0","1","2","3","4","5","6","7","8","9",
		" ","A","B","C","D","E","F","G","H","I",
		"J","K","L","M","N","O","P","Q","R","S",
		"T","U","V","W","X","Y","Z","#","+","-",".");


$types = array (chr(240) => "PROGRAM",
		chr(243) => "SAMPLE",
		chr(120) => "EFFECTS",
		chr(237) => "MULTI",
		chr(99)  => "OS",
		chr(0)   => "DELETED");

$dp2 = str_repeat("\x00",3166); 

$dp3 = "\x18\x19\x1E\x0A\x18\x0B\x17\x0F\x0E\x0A\x0A\x0A";  /* Volume name in AKAI format. (offset 4736) */

$dp4 = "\x00\x00\x00\x11\x00\x01\x01\x23\x00\x00\x32\x09\x0C\xFF" . str_repeat("\x00",358); 
$ddp4 = "\x00\x00\x32\x10\x00\x01\x01\x00\x00\x00\x32\x09\x0C\xFF" . str_repeat("\x00",358);

$dp5 = str_repeat("\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00",512);
/* 512 possible directory entries starting at offset 5120. */

$dp6 = str_repeat(str_repeat("\x00",1024),1583);
/* 1583 * 1024 byte sectors for data. */

set_time_limit(0);

function read() { 
	$fp=fopen("/dev/stdin","r");
	$input = fgets($fp,255);
	fclose($fp);
	return str_replace("\n", "", $input);
} 

function as2ak($var) {  /* convert normal ASCII to AKAI format. */
	global $chars;
	if (strlen($var) > 12) { $var = substr($var,0,12); }
	$return = '';
	for ($i = 0; $i <= strlen($var)-1; $i++) {
		$char = strtoupper($var[$i]);
		$key = array_search($char,$chars);
		$return = $return . chr($key);
	}
	return str_pad($return,12,chr(10));
}

function tonote($note) {
	$notes = array ( "A","A#","B", "C","C#","D","D#","E","F","F#","G","G#" );
	$key = -1;
	$il = 0;
	for ($i = 0; $i <= ord($note) - 21; $i++) {
		if ($il == 12) { $il = 0; }
		$nr = $notes[$il];
		if ($notes[$il] == "C") { $key++; }
		$il++;
	}
	return str_pad($nr,2,"_") . $key;
}

if (!function_exists('str_split')) {
	function str_split($string, $chunksize=1) {
		preg_match_all('/('.str_repeat('.', $chunksize).')/Uims', $string, $matches);
		   return $matches[1];
	}
} 

function ak2as($var) { /* convert AKAI format text to ASCII */
	global $chars;
	if (strlen($var) > 12) { $var = substr($var,0,12); }
	$return = '';
	for ($i = 0; $i <= strlen($var)-1; $i++) {
		$key = $chars[ord($var[$i])];
		$return = $return . $key;
	}
	return str_pad($return,12," ");
}

function dp1() {
	global $type,$dp1;
	if ($type == "S2000") { $id = chr(17); }
	if ($type == "S3000") { $id = chr(16); }
	if ($type == "S900") { $id = chr(0); }
	$dp1 =            "\x0A\x0A\x0A\x0A\x0A\x0A\x0A\x0A\x0A\x0A\x0A\x0A\x00\x00\x06\x0A\xFF\x00\x00\x04\x00\x00\x00" . $id .
	       str_repeat("\x0A\x0A\x0A\x0A\x0A\x0A\x0A\x0A\x0A\x0A\x0A\x0A\x00\x00\x06\x0A\x00\x00\x00\x04\x00\x00\x00"  . $id,63) . 
	       str_repeat("\x00\x40",17);
}

function image() {
	global $dp1,$dp2,$dp3,$dp4,$ddp4,$dp5,$dp6,$fat,$file, $volname, $fsize, $newd, $type;
	dp1();
	if ($fat != '') { $ifat = implode("",$fat); } else { $ifat = ''; }
	if ($type == "S900") { 
		$contents = str_pad($ifat,1536,chr(0)) . str_repeat("\x00",8) . str_pad($newd,2552,chr(0)); 
	} else {
		$contents = $dp1 . str_pad($newd,3166,chr(0)) . $volname . str_repeat("\x00",358) . str_pad($ifat,12288,chr(0));
	}
	$cl = 0;
	$dp7 = '';
	if ($fat != '') {
		foreach($fat as $fe) {
			$dp7 = $dp7 . str_pad($file[$cl],ceil($fsize[$cl] / 1024)*1024,chr(0));
			$cl++;
		}
	} else { $dp7 = ''; }
	$pl = 1620992;
	if ($type == "S900") { $pl = 815104; }
	$contents = $contents . str_pad($dp7,$pl,chr(0));
	return $contents;
}

function importimage() {
	global $volname, $contents, $fat, $file, $freeblocks, $fsize,$type,$fs;
	$volname = substr($contents,4736,26);
	if ($contents[23] == chr(17)) { $type = "S2000"; }
	if ($contents[23] == chr(16)) { $type = "S3000"; }
	if ($contents[23] == chr(0)) { $type = "S900"; }
	$cl = 0;
	$block = 0;
	$startb = 1570;
	$endb = 3166;
	$offs = 5120;
	$te = 512;
	$fb = 1583;
	if ($type == "S900") {
		$start = 1536;
		$endb = 3136;
		$offs = 0;
		$te = 64;
		$fb = 796;
	}
	if ($contents[$offs] != chr(0)) {
		$tmap = substr($contents,$startb,$endb);
		$blocks = str_split($contents,1024);
		$bc = 0;
		for ($i = 0; $i < $te; $i++) {
			if ($contents[$offs+($i*24)] != chr(0)) {
				$fileentry = substr($contents,$offs + ($i * 24),24);
				$fat[$cl] = $fileentry;
				$sb = hexdec(str_pad(dechex(ord($fileentry[21])),2,"0",STR_PAD_LEFT) . str_pad(dechex(ord($fileentry[20])),2,"0",STR_PAD_LEFT));
 				$fsize[$cl] = hexdec(str_pad(dechex(ord($fileentry[19])),2,"0",STR_PAD_LEFT) . 
					       str_pad(dechex(ord($fileentry[18])),2,"0",STR_PAD_LEFT) . 
					       str_pad(dechex(ord($fileentry[17])),2,"0",STR_PAD_LEFT));
				$file[$cl] = $blocks[$sb];
				$block = $block + ceil($fsize[$cl] / 1024);
				$cl++;
			} else { break; }
		}
		for ($i = 0; $i <= strlen($tmap); $i = $i + 2) {
			if ($bc >= sizeof($fat)) { continue; }
			if (($tmap[$i] == chr(0)) and ($tmap[$i+1] == chr(0))) {
				continue;
			}
			if ((($tmap[$i] == chr(0)) and ($tmap[$i+1] == chr(128))) or (($tmap[$i] == chr(0)) and ($tmap[$i+1] == chr(192)))) {
				$bc++;
			} else {
				$file[$bc] = $file[$bc] . $blocks[hexdec(str_pad(dechex(ord($tmap[$i+1])),2,"0",STR_PAD_LEFT) . str_pad(dechex(ord($tmap[$i])),2,"0",STR_PAD_LEFT))];
			}
		}
		$freeblocks = $fb - $block;
	}
}

if (!function_exists('str_split')) {
        Function str_split($string, $chunksize=1) {
                preg_match_all('/('.str_repeat('.', $chunksize).')/Uims', $string, $matches);
                return $matches[1];
        }
}

function numtohexs($var,$co) {
	$var2 = str_pad(dechex($var),$co*2,"0",STR_PAD_LEFT);
	$var = str_split($var2 , 2);
	krsort($var);
	$cl = '';
	foreach($var as $hi) {
		$cl = $cl . chr(hexdec($hi));
	}
	return $cl;
}

function defrag() {
	global $fat, $file, $fsize, $newd, $type;
	if ($fat != '') {
		$cl = 0;
		$nextb = ' ';
		$b2 = "\x11";
		if ($type == "S900") { $b2 = "\x04"; }
		$b1 = "\x00";
		$newd = '';
		foreach($fat as $fe) {
			$fat[$cl][20] = $b2; $fat[$cl][21] = $b1;
			$sb = hexdec(str_pad(dechex(ord($fat[$cl][21])),2,"0",STR_PAD_LEFT) . str_pad(dechex(ord($fat[$cl][20])),2,"0",STR_PAD_LEFT));
			$size = $fsize[$cl];
			$sizeb = ceil(($size/ 1024));
			$nextb = ($sb + $sizeb); 
			$nextb = str_pad(dechex($nextb),4,"0",STR_PAD_LEFT);
			$b1 = chr(hexdec($nextb[0] . $nextb[1]));
			$b2 = chr(hexdec($nextb[2] . $nextb[3]));
			$cl++;
		}
		$cl = 0;
		foreach($fat as $fe) {
			$sb = hexdec(str_pad(dechex(ord($fe[21])),2,"0",STR_PAD_LEFT) . str_pad(dechex(ord($fe[20])),2,"0",STR_PAD_LEFT));
			$size = $fsize[$cl];
			$blocks = ceil($size / 1024);
			if ($blocks > 1) {
				for ($i = ($sb+1); $i <= ($sb+$blocks-1); $i++) {
					$newd = $newd . numtohexs($i,2);
				}
			}
			$cl++;
			if ($type == "S900") { $newd = $newd . "\x00\x80"; } else { $newd = $newd . "\x00\xC0"; }
		}
	}
}

$type = "S2000";
dp1();
$contents = $dp1 . $dp2 . $dp3 . $dp4 . $dp5 . $dp6;
$fat = '';
$fsize = '';
$file = '';
$freeblocks = 1583;
$fs = 1638400;
$rl = '';

importimage();


print "AKAI S2000/S3000/S900 Disk Image Editor v1.1.2\n(? for help.)\n\n";

$floppyt = 1;
if (!file_exists($pathtosetfdprm . "setfdprm")) {
	print "Floppy read/writes disabled, setfdprm not found.\n\n";
	$floppyt = 0;
}

while (($rl != "QUIT") and ($rl != "EXIT") and ($rl != "Q")) {
	print "Command: ";
	$rl = read();
	$rl2 = $rl;
	$rl = strtoupper($rl);

    if ((substr($rl, 0, 4) == "HELP") or ($rl == "?")) {
		print 	"\nSWITCH <sourceid> <destid>  Swap file order.\n".
            "DELAY  <bpm>                Calculate milliseconds for echo/reverb delay.\n".
            "FSAVE  <id>                 Save a file using id number from DIR command.\n".
			"FLOAD  <filename>           Load a file into the image (defaults to sample, use ATTR to change).\n".
			"WLOAD  <filename>           Load a 16bit 22kHz/44kHz WAV file as an akai s2/3k sample file.\n".
			"   <id>                 Save an akai sample as a WAV file.\n".
			"SAVE   <filename>           Save current image to file (leave blank to write to floppy).\n".
            "LOAD   <filename>           Load image from image file (leave blank to load from floppy).\n".
            "                            If loading an S900 image from a floppy disk use LOAD -S900\n".
			"VOL    <name>               Display or change current volume name.\n".
			"REN    <id> <name>          Rename file with id to specified name.\n".
			"COPY   <id> <name>          Copy file with id to specified name.\n".
			"DEL    <id>                 Delete file.\n".
			"ATTR   <id> <type>          Change file type (".implode(",",$types).").\n".
			"PD     <id>                 Program file header dump.\n".
			"SD     <id>                 Sample file header dump.\n".
			"PC     <id> <num> <chan>    Change the program number and midi channel of a program file.\n".
			"BLANK  <type>               Format the current image in memory.  (S900,S2000,S3000)\n".
			"MAP                         Display the disk block map.\n".
			"DIR                         Display the contents of the current image.\n\n";
	}

    if (substr($rl, 0, 4) == "SAVE") {
		$args = explode(" ", $rl2);
		if ($args[1] == '') { $imagefile = $tmpfile; } else { $imagefile = $args[1]; }
		if (is_dir($imagefile)) {
			print "Specified filename is a directory.\n";
			continue;
		}
		$contents = image();
		$fd = fopen ("$imagefile", "w");
	        fwrite ($fd, $contents, $fs);
	        fclose ($fd);
		if ($args[1] == '') {
			if ($floppyt == 0) { print "Install fdutils and/or change the \$setfdprmset variable in s2kdie.php\n"; continue; }
			if ($fs == 819200) {
				exec($pathtosetfdprm . "setfdprm $floppy sect=5 dd ssize=1024 cyl=80");
			} else {
	                        exec($pathtosetfdprm . "setfdprm $floppy sect=10 hd ssize=1024 cyl=80");
			}
			exec("cat $imagefile > $floppy 2> /dev/null", $ra, $rv);
	                if ($rv) { print "Please insert an AKAI formatted floppy disk.\n"; }
			unlink("$imagefile");
		}
		if (!$rv) { print "Image saved.\n"; }
	}

    if (substr($rl, 0, 5) == "FLOAD") {
		$args = explode(" ", $rl2);
		if (file_exists($args[1])) {
			if ($fat != '') {
				foreach($fat as $fe) {
					if ((trim(ak2as($fe)) == strtoupper(trim($args[1]))) or (substr($types[chr(ord($fat[$args[1]][16] - 160))],0,1) == strtoupper(trim($args[1])))) {
						print "File with that name already exists.\n";
						continue(2);
					}
				}
				$cap = sizeof($fat);
			}
			if ($fat == '') { $cap = 0; }
			$box = ceil(filesize($args[1]) / 1024);
			if ($freeblocks >= $box) {
				$fsize[$cap] = filesize($args[1]);
				$tip = "\xF3";
				$fat[$cap] = as2ak($args[1]) . "\x00\x00\x06\x0A" . $tip . "\xf0\x01\x00\x11\x00\x00\x11";
				if ($type == "S900") { 
					$fat[$cap] = strtoupper(str_pad(substr($args[1],0,10),10," ")) . "\x00\x00\x00\x00\x00\x00" . "S" . "\x00\x00\x00\x00\x00\x00\x00";
				}
				if ($type == "S3000") {
					$fat[$cap] = as2ak($args[1]) . "\x00\x00\x00\x02" . $tip . "\xf0\x00\x00\x00\x00\x00\x0C";
				}
				$freeblocks = $freeblocks - ceil($fsize[$cap] / 1024);
	                        $fd = fopen ($args[1], "r");
	                        $file[$cap] = str_pad(fread ($fd, $fsize[$cap]),ceil($fsize[$cap] / 1024) * 1024, chr(0));
	                        fclose ($fd);
				$nextb = numtohexs($fsize[$cap],3);
				$fat[$cap][19] = $nextb[2];
				$fat[$cap][18] = $nextb[1];
				$fat[$cap][17] = $nextb[0];
				defrag();
				print "File loaded onto disk image.\n";
			} else { print "Insufficient space.  $freeblocks blocks free.  $box required.\n"; }
		} else { print "File not found\n"; }
	}

    if (substr($rl, 0, 6) == "SWITCH") {
		if ($fat == '') {
			print "No files to switch!\n";
			continue;
		}
		$args = explode(" ", $rl);
		if (intval($args[1]) > sizeof($fat)) {
			print "Invalid source file ID.\n";
			continue;
		}
		if (intval($args[2]) > sizeof($fat)) {
			print "Invalid destination file ID.\n";
			continue;
		}
		$fstmp = $fsize[$args[1]];
		$fatmp = $fat[$args[1]];
		$fitmp = $file[$args[1]];
		$fsize[$args[1]] = $fsize[$args[2]];
		$file[$args[1]] = $file[$args[2]];
		$fat[$args[1]] = $fat[$args[2]];
		$fat[$args[2]] = $fatmp;
		$fsize[$args[2]] = $fstmp;
		$file[$args[2]] = $fitmp;
		$fstmp = ''; $fatmp = ''; $fitmp = '';
		defrag();
		print "Files switched.\n";
	}

    if (substr($rl, 0, 2) == "SD") {
		$args = explode(" ", $rl);
		if ($file[$args[1]][0] == chr(3)) {
			print "\nSHNAME: " . ak2as(substr($file[$args[1]],3,12)) . "\n";
			print "SBANDW: ";
			if ($file[$args[1]][1] == chr(0)) {
				print "22050Hz";
			}
			if ($file[$args[1]][1] == chr(1)) {
				print "44100Hz";
			}
			print "\n";
			print "SPITCH: " . tonote($file[$args[1]][2]) . "\n";
			print "SPTYPE: ";
			if  ($file[$args[1]][19] == chr(0)) {
				print "normal looping";
			}
			if  ($file[$args[1]][19] == chr(1)) {
				print "loop until release";
			}
			if  ($file[$args[1]][19] == chr(2)) {
				print "no looping";
			}
			if  ($file[$args[1]][19] == chr(3)) {
				print "play to sample end";
			}
			print "\n STUNO: ";
			$pm = "+";
			$semi = ord($file[$args[1]][21]);
			$cent = ord($file[$args[1]][20]);
			if ($semi > 99) {
				$semi = 256 - $semi;
				$pm = "-";
			}
			if ($cent > 99) {
				$cent = 256 - $cent;
				$pm = "-";
			}
			print "Semi.Cent " . $pm . str_pad($semi,2,"0",STR_PAD_LEFT) . "." . str_pad($cent,2,"0",STR_PAD_LEFT) . "\n";
			$b4 = str_pad(dechex(ord($file[$args[1]][33])),2,"0",STR_PAD_LEFT);
			$b3 = str_pad(dechex(ord($file[$args[1]][32])),2,"0",STR_PAD_LEFT);
			$b2 = str_pad(dechex(ord($file[$args[1]][31])),2,"0",STR_PAD_LEFT);
			$b1 = str_pad(dechex(ord($file[$args[1]][30])),2,"0",STR_PAD_LEFT);
			print "SLNGTH: " . hexdec($b4 . $b3 . $b2 . $b1) . "\n";
			print "\n";
		} else {
			if ($args[1] == '') { print "Usage: sd <id>\n"; } else {
				print "Not a valid S2000/S3000 sample file.\n";
			}
		}
	}

    if (substr($rl, 0, 2) == "PC") {
		$args = explode(" ", $rl);
		if (($args[1] >= sizeof($fat)) or ($fat == '')) { print "File not found.\n"; continue; }
		if ($fat[$args[1]][16] == chr(240)) {
			$file[$args[1]][15] = chr($args[2] - 1);
			$file[$args[1]][16] = chr($args[3] - 1);
			print ak2as(substr($file[$args[1]],3,12)) . "\nProgram:  " . $args[2] . "\nMidi channel " . $args[3] . "\n";
		} else {
			if ($args[1] == '') { print "Usage: pc <id> <program> <channel>\n"; } else {
				print "File not a program. (check file type)\n";
			}
		}
	}

    if (substr($rl, 0, 2) == "PD") {
		$args = explode(" ", $rl);
		if ($fat[$args[1]][16] == chr(240)) {
			print "\nPRNAME: " . ak2as(substr($file[$args[1]],3,12)) . "\n";
			print "PRGNUM: " . (ord($file[$args[1]][15]) + 1) . "\n";
			print "PMCHAN: " . (ord($file[$args[1]][16]) + 1) . "\n";
			print "POLYPH: " . (ord($file[$args[1]][17]) + 1) . "\n";
			print "PRIORT: ";
			if ($file[$args[1]][18] == chr(0)) { print "low"; }
			if ($file[$args[1]][18] == chr(1)) { print "norm"; }
			if ($file[$args[1]][18] == chr(2)) { print "high"; }
			if ($file[$args[1]][18] == chr(3)) { print "hold"; }
			print "\n";
			print "PLAYLO: " . tonote($file[$args[1]][19]) . "\n"; /* 21-127 = A-1 to G-8 */
			print "PLAYHI: " . tonote($file[$args[1]][20]) . "\n";
			print "OSHIFT: " . (ord($file[$args[1]][21])) . "\n"; /* not used */
			print "OUTPUT: ";
			if ($file[$args[1]][22] == chr(255)) { print "off"; } else { print ord($file[$args[1]][22])+1; }
			print "\n";
			print "STEREO: " . (ord($file[$args[1]][23])) . "\n";
			print "PANPOS: " . (ord($file[$args[1]][24])) . "\n";
			print "PRLOUD: " . (ord($file[$args[1]][25])) . "\n";
			print "V_LOUD: " . (ord($file[$args[1]][26])) . "\n";
			print "K_LOUD: " . (ord($file[$args[1]][27])) . "\n"; /* not used */
			print "P_LOUD: " . (ord($file[$args[1]][28])) . "\n"; /* not used */
			print "PANRAT: " . (ord($file[$args[1]][29])) . " (LFO speed)\n";
			print "PANDEP: " . (ord($file[$args[1]][30])) . "\n";
			print "PANDEL: " . (ord($file[$args[1]][31])) . "\n";
			print "K_PANP: " . (ord($file[$args[1]][32])) . "\n";
			print "LFORAT: " . (ord($file[$args[1]][33])) . "\n";
			print "LFODEP: " . (ord($file[$args[1]][34])) . "\n";
			print "LFODEL: " . (ord($file[$args[1]][35])) . "\n";
			print "MWLDEP: " . (ord($file[$args[1]][36])) . "\n";
			print "PRSDEP: " . (ord($file[$args[1]][37])) . "\n";
			print "VELDEP: " . (ord($file[$args[1]][38])) . "\n";
			print "B_PTCH: " . (ord($file[$args[1]][39])) . "\n";
			print "P_PTCH: " . (ord($file[$args[1]][40])) . "\n";
			print "KXFADE: ";
			if ((ord($file[$args[1]][41])) == 0) { print "off"; } else { print "on"; }
			print "\n";
			print "GROUPS: " . (ord($file[$args[1]][42])) . "\n";
			print " TPNUM: " . (ord($file[$args[1]][43])) . "\n";
			print "\n";
		} else {
			if ($args[1] == '') { print "Usage: pd <id>\n"; } else {
				print "File not a valid S2000/S3000 program.\n";
			}
		}
	}

    if (substr($rl, 0, 4) == "ATTR") {
		$args = explode(" ", $rl);
		foreach ($types as $key => $value) {
			if ($args[2] == $value) {
				if ($type == "S900") {
					$fat[$args[1]][16] = chr(ord($key) - 160);
				} else { $fat[$args[1]][16] = $key; }
				print "Attribute changed.\n";
			}
		}
	}

    if (substr($rl, 0, 4) == "COPY") {
		$args = explode(" ", $rl);
		if ($args[2] == '') { print "Please specify a filename for the new file.\n"; continue; }
		if (($args[1] >= sizeof($fat)) or ($fat == '')) { print "File not found.\n"; continue; }
		$cap = sizeof($fat);
		$box = ceil($fsize[$args[1]] / 1024);
		if ($freeblocks >= $box) {
			$fat[$cap] = $fat[$args[1]];
			$fsize[$cap] = $fsize[$args[1]];
	                $args[0] = '';
			$args[1] = '';
	                $args = trim(implode(" ", $args));
			if ($args != '') { 
				if ($type == "S900") { $fat[$cap] = strtoupper(substr(str_pad($args,10," "),0,10)) . substr($fat[$cap],-14,14); } else {
					$fat[$cap] = as2ak("$args") . substr($fat[$cap],-12,12);
				}
			}
			$freeblocks = $freeblocks - ceil($fsize[$cap] / 1024);
			defrag();
			print "File copied.\n";
		} else { print "Insufficient space.\n"; }
	}

        if (substr($rl, 0, 4) == "LOAD") {
		$args = explode(" ", $rl2);
		$fs = 1638400;
		if (strtoupper($args[1]) == '-S900') {
			$fs = 819200;
			$args[1] = '';
		}
		if ($args[1] == '') {
			if ($floppyt == 0) { print "Install fdutils and/or change the $$setfdprmset variable in s2kdie.php\n"; continue; }
			if ($fs == 819200) {
				exec($pathtosetfdprm . "setfdprm $floppy sect=5 dd ssize=1024 cyl=80");
			} else {
	                        exec($pathtosetfdprm . "setfdprm $floppy sect=10 hd ssize=1024 cyl=80");
			}
                        exec("cat $floppy > $tmpfile 2> /dev/null", $ra, $rv);
                        if ($rv) { print "Please insert an AKAI floppy disk.\n"; continue; }
			$imagefile = $tmpfile;
		} else { $imagefile = $args[1]; }
                if (file_exists("$imagefile")) {
			if (filesize($imagefile) == 819200) {
				$fs = 819200;
			}
			$contents = '';
                        $fd = fopen ("$imagefile", "r");
                        $contents = fread ($fd, $fs);
                        fclose ($fd);
		} else { print "There was an error loading the disk.\n"; continue; }
		if ($args[1] == '') { unlink("$tmpfile"); }
		if ($contents[1569] == chr(64)) {
			$fat = '';
			$freeblocks = 1583;
			$fsize = '';
			$file = '';
			importimage();
			defrag();
		} else {
			$fat = '';
			$freeblocks = 796;
			$fsize = '';
			importimage();
			defrag();
		}
		print "$type image loaded.\n";
	}

    if (substr($rl, 0, 5) == "WLOAD") {
		$args = explode(" ", $rl2);
                if (file_exists($args[1])) {
			$fsize[$cap] = filesize($args[1]);
                        $fd = fopen ("$args[1]", "r");
                        $wav = fread ($fd, filesize($args[1]));
                        fclose ($fd);
			if (substr($wav,8,4) == "WAVE") {
				$channels = ord($wav[22]);
				$comp = ord($wav[20]);
				if ($comp != 1) { print "WAV file not uncompressed PCM.\n"; continue; }
				$bits = ord($wav[34]);
				if ($bits != 16) { print "WAV file isn't 16bit.\n"; continue; }
				$data = substr($wav,44,(filesize($args[1]) - 44));
				$box = ceil(strlen($data) / 1024);
				if ($box > $freeblocks) { print "Insufficient space.\n"; continue; }
				if ($channels > 2) {
					print "More than 2 channels?\n"; continue;
				}
				if ($channels == 2) {
					$c1 = '';
					$c2 = '';
					$dat = strlen($data);
					preg_match_all('/(..)../s', $data, $matches);
					$c1 = implode("",$matches[1]);
					preg_match_all('/..(..)/s', $data, $matches);
					$c2 = implode("",$matches[1]);
					$data = $c1;
				}
				$b4 = str_pad(dechex(ord($wav[27])),2,"0",STR_PAD_LEFT);
				$b3 = str_pad(dechex(ord($wav[26])),2,"0",STR_PAD_LEFT);
				$b2 = str_pad(dechex(ord($wav[25])),2,"0",STR_PAD_LEFT);
				$b1 = str_pad(dechex(ord($wav[24])),2,"0",STR_PAD_LEFT);
				$hz = hexdec($b4 . $b3 . $b2 . $b1);
				$nextb = numtohexs(strlen($data) / 2,4);
				if ($type != "S900") {
					if ($hz == 44100) { $hz = 1; } else if ($hz == 22050) { $hz = 0; } else { print "Sample rate not supported.\n"; continue; }
					$smphdr = "\x03" . chr($hz) . "\x3C" . as2ak($args[1]) . "\x80\x01\x00\x00\x02\x00\x00\x00\x04\x01\x00";
					$smphdr = $smphdr . $nextb . "\x00\x00\x00\x00" . $nextb . $nextb;
					$hds = 140;
				} else {
					$smphdr = str_pad(strtoupper($args[1]),10," ") . "\x00\x00\x00\x00" . $nextb . numtohexs($hz,2) . "\xC0\x03\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00" . chr(140) . chr(185) . chr(0) . chr(78) . "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00" . chr(224) . chr(43) . chr(38) . "\x00\x00\x00";
					$hds = 60;
				}
				$cap = sizeof($fat); if ($fat == '') { $cap = 0; }
				$file[$cap] = str_pad($smphdr,$hds,chr(0)) . $data;
				if ($channels == 2) { $file[$cap+1] = str_pad($smphdr,$hds,chr(0)) . $c2; }
				$tip = "\xF3";
				if ($channels == 2) {
					$dn1 = as2ak($args[1]);
					$dn1[10] = "\x27";
					$dn1[11] = "\x16";
					$fat[$cap] = $dn1 . "\x00\x00\x06\x0A" . $tip . "\xf0\x01\x00\x11\x00\x00\x11";
					if ($type == "S900") { 
						$fat[$cap] = strtoupper(str_pad(substr($args[1],0,10),10," ")) . "\x00\x00\x00\x00\x00\x00" . "S" . "\x00\x00\x00\x00\x00\x00\x00";
						$fat[$cap][8] = "-";
						$fat[$cap][9] = "L";
					}
					if ($type == "S3000") {
						$fat[$cap] = $dn1 . "\x00\x00\x00\x02" . $tip . "\xf0\x00\x00\x00\x00\x00\x0C";
					}

					if ($type == "S900") {
						$file[$cap][8] = "-";
						$file[$cap][9] = "L";
						$file[$cap+1][8] = "-";
						$file[$cap+1][9] = "R";
						$dn2 = strtoupper(str_pad(substr($args[1],0,10),10," "));
						$fat[$cap][8] = "-";
						$fat[$cap][9] = "L";
						$dn2[8] = "-";
						$dn2[9] = "R";
					} else {
						$file[$cap][13] = "\x27";
						$file[$cap][14] = "\x16";
						$file[$cap+1][13] = "\x27";
						$file[$cap+1][14] = "\x1C";
						$dn2 = as2ak($args[1]);
						$dn2[10] = "\x27";
						$dn2[11] = "\x1C";
					}


					$fat[$cap+1] = $dn2 . "\x00\x00\x06\x0A" . $tip . "\xf0\x01\x00\x11\x00\x00\x11";
					if ($type == "S900") { 
						$fat[$cap+1] = $dn2 . "\x00\x00\x00\x00\x00\x00" . "S" . "\x00\x00\x00\x00\x00\x00\x00";
					}
					if ($type == "S3000") {
						$fat[$cap+1] = $dn2 . "\x00\x00\x00\x02" . $tip . "\xf0\x00\x00\x00\x00\x00\x0C";
					}
					$fsize[$cap] = strlen($file[$cap]);
					$fsize[$cap+1] = strlen($file[$cap+1]);
					$fb = ($fsize[$cap] + $fsize[$cap+1]);
				} else {
					$fat[$cap] = as2ak($args[1]) . "\x00\x00\x06\x0A" . $tip . "\xf0\x01\x00\x11\x00\x00\x11";
					if ($type == "S900") { 
						$dn2 = strtoupper(str_pad(substr($args[1],0,10),10," "));
						$fat[$cap] = $dn2 . "\x00\x00\x00\x00\x00\x00" . "S" . "\x00\x00\x00\x00\x00\x00\x00";
					}
					if ($type == "S3000") {
						$fat[$cap] = as2ak($args[1]) . "\x00\x00\x00\x02" . $tip . "\xf0\x00\x00\x00\x00\x00\x0C";
					}
					$fsize[$cap] = strlen($file[$cap]);
					$fb = $fsize[$cap];
				}
				$freeblocks = $freeblocks - ceil($fb / 1024);
				$nextb = numtohexs($fsize[$cap],3);
				$fat[$cap][17] = $nextb[0];
				$fat[$cap][18] = $nextb[1];
				$fat[$cap][19] = $nextb[2];
				if ($channels == 2) {
					$fat[$cap+1][17] = $nextb[0];
					$fat[$cap+1][18] = $nextb[1];
					$fat[$cap+1][19] = $nextb[2];
				}
				defrag();
				if ($channels == 1) { print "WAV imported as akai sample.\n"; } else { print "Stereo WAV imported as akai samples.\n"; }
			} else { print "WAV file invalid.\n"; }
		} else { print "File not found.\n"; }
	}

    if (substr($rl, 0, 5) == "FSAVE") {
		$args = explode(" ", $rl);
		if (($args[1] >= sizeof($fat)) or ($fat == '')) { print "File not found.\n"; continue; }
		if ($type == "S900") {
			$fname = strtolower(trim(substr($fat[$args[1]],0,10)) . "." . substr($fat[$args[1]][16],0,1));
		} else {
			$fname = strtolower(trim(ak2as($fat[$args[1]])) . "." . substr($types[$fat[$args[1]][16]],0,1));
		}
                $fd = fopen ($fname , "w");
		fwrite ($fd,$file[$args[1]],$fsize[$args[1]]);
		fclose ($fd);
		if ($type != "S900") { print trim(ak2as($fat[$args[1]])); } else {
			print trim(substr($fat[$args[1]],0,10));
		}
		print " saved.\n";
	}

        if (substr($rl, 0, 5) == "WSAVE") {
		$args = explode(" ", $rl);
/*		if ($type == "S900") {
			print "Currently only samples from S2000/S3000 disk images can be saved as WAV.\n";
			continue;
		} */
		if (($args[1] >= sizeof($fat)) or ($fat == '')) { print "File not found.\n"; continue; }
		if (($fat[$args[1]][16] != chr(243)) and ($fat[$args[1]][16] != "S")) { print "Not a valid sample file.\n"; continue; }
		if ($type == "S900") {
			$fname =  strtolower(trim(substr($fat[$args[1]],0,10))) . ".wav";
		} else {
			$fname = strtolower(trim(ak2as($fat[$args[1]]))) . ".wav";
		}
		$wav = "RIFF" . numtohexs($fsize[$args[1]] - 140 + 36,4) . "WAVEfmt " . chr(16) . "\x00\x00\x00" . "\x01\x00" . chr(1) . chr(0);
		if ($file[$args[1]][1] == chr(0)) {
			$wav = $wav . chr(34) . chr(86);
			$br = 22050;
		}
		if ($file[$args[1]][1] == chr(1)) {
			$wav = $wav . chr(68) . chr(172);
			$br = 44100;
		}
		$hdrsize = 140;
		if ($type == "S900") {
			$wav = $wav . $file[$args[1]][20] . $file[$args[1]][21];
			$br = hexdec(str_pad(dechex(ord($file[$args[1]][21])),2,"0",STR_PAD_LEFT) . str_pad(dechex(ord($file[$args[1]][20])),2,"0",STR_PAD_LEFT));
			$hdrsize = 60;
		}
		$data = substr($file[$args[1]],$hdrsize,$fsize[$args[1]] - $hdrsize);
		if ($type == "S900") {
			$data2 = '';
			for ($i = 0; $i < strlen($data); $i = $i + 2) {
				$val = str_pad(dechex(ord($data[$i])),2,"0",STR_PAD_LEFT);
				$val = chr(hexdec($val[0] . "0"));
				$word = $val . $data[$i + 1];
				$data2 = $data2 . $word;
			}
			$data = $data2;
			$data2 = '';
		}
		$wav = $wav . "\x00\x00" . numtohexs($br * 1 * 16/8,4) . numtohexs(1*16/8,2) . chr(16) . "\x00data" . numtohexs(strlen($data),4) . $data;
                $fd = fopen ($fname , "w");
		fwrite ($fd,$wav);
		fclose ($fd);
		if ($type == "S900") {
			print trim(substr($fat[$args[1]],0,10));
		} else {
			print trim(ak2as($fat[$args[1]]));
		}
		print " saved as WAV file.\n";
	}

        if (substr($rl, 0, 3) == "VOL") {
		if ($type == "S900") { print "S900 disks have no volume name.\n"; continue; }
		$args = explode(" ", $rl);
                $args[0] = '';
                $args = trim(implode(" ", $args));
		if ($args != '') { $volname = as2ak("$args") . substr($volname,12,14); }
		print ak2as("$volname") . "\n";
	}

        if (substr($rl, 0, 3) == "REN") {
		$args = explode(" ", $rl);
		if ($args[1] == "") { print "You might want to specify an id to rename, and new name.\n"; continue; }
                $args[0] = '';
		$arg = $args[1];
		$args[1] = '';
		if (($arg >= sizeof($fat)) or ($fat == '')) { print "File not found.\n"; continue; }
                $args = trim(implode(" ", $args));
		if ($args != '') { 
			if ($type != "S900") { $fat[$arg] = as2ak("$args") . substr($fat[$arg],-12,12); } else {
				$fat[$arg] = strtoupper(substr(str_pad($args,10," "),0,10)) . substr($fat[$arg],-14,14);
			}
			print "File renamed.\n";
		}
	}

    if (substr($rl, 0, 5) == "BLANK") {
		$args = explode(" ", $rl);
		if (count($args) != 1) { $args[1] = "S2000"; }
		if (($args[1] == "S3000") or ($args[1] == "S2000") or ($args[1] == "S900")) {
			$type = $args[1];
		} else { print "Invalid type.  S900/S2000/S3000 are valid.\n"; continue; }
		dp1();
		$freeblocks = 1583;
		$fs = 1638400;
		if ($args[1] == "S3000") { $contents = $dp1 . $dp2 . $dp3 . $ddp4 . $dp5 . $dp6; } else if 
		   ($args[1] == "S2000") { $contents = $dp1 . $dp2 . $dp3 . $dp4 . $dp5 . $dp6; } else {
		   $contents = str_repeat("\x00",819200);
		   $freeblocks = 796;
		   $fs = 819200;
		}
		$fat = '';
		$fsize = '';
		$file = '';
		$newd = '';
		importimage();
		print "Image in memory blanked.\n";
	}

    if (substr($rl, 0, 5) == "DELAY") {
		$args = explode(" ", $rl);
		if ($args[1] == '') { print "Usage: DELAY <bpm>"; continue; }
		print "1/4: " . round(60000 / $args[1]) . "ms   1/8: " . round((60000 / $args[1]) / 2) . "ms\n";
		$rl = '';
	}


    if (substr($rl, 0, 3) == "DEL") {
		$args = explode(" ", $rl);
		if (($args[1] >= sizeof($fat)) or ($fat == '')) { print "File not found.\n"; continue; }
		if ($args[1] != '') { 
			if ($type == "S900") {
				$name = trim(substr($fat[$args[1]],0,10));
			} else { $name = ak2as($fat[$args[1]]); }
			$freeblocks = $freeblocks + ceil($fsize[$args[1]] / 1024);
			array_splice($file,$args[1],1);
			array_splice($fat,$args[1],1);
			array_splice($fsize,$args[1],1);
			print trim($name) . " removed.\n";
			if (sizeof($fat) == 0) { $fat = ''; $fsize = ''; $file = ''; } else { defrag(); }
		} else { print "Please specify a file number.\n"; }
	}

    if (substr($rl, 0, 3) == "MAP") {
		if ($fat != '') {
			for ($i = 0; $i < strlen($newd); $i++) {
				print strtoupper(str_pad(dechex(ord($newd[$i])),2,"0",STR_PAD_LEFT)) . " ";
			}
		} else { print "No files to map!"; }
		print "\n";
	}			

        if (substr($rl, 0, 3) == "DIR") {
		print "\n";
		if ($type != "S900") {
			print "      $type Volume: " . ak2as($volname) . "\n\n";
		} else { print "      $type Volume.\n\n"; }
		if ($fat != '') {
			print "      Filename       Type        Bytes\n";
			$li = 0;
			$blocks = 0;
			foreach($fat as $fe) {
				print str_pad("[".$li."]",5," ",STR_PAD_LEFT);
				print " ";
				if ($type == "S900") {
					print str_pad(substr($fe,0,10),12," ");
				} else { print ak2as("$fe"); }
				print "   ";
				if ($type == "S900") { $fe[16] = chr(ord($fe[16]) + 160); }
				if ($types[$fe[16]] == '') { $ftype = "UNKNOWN"; } else { $ftype = $types[$fe[16]]; }
				print str_pad("<" . $ftype . ">",12," ");
				print $fsize[$li];
				print "\n";
				$li++;
			}
			print "\n      $freeblocks unused sectors.  (" . $freeblocks * 1024 . " bytes free)\n\n";
		} else { print "      Empty disk.  (";
			 if ($type == "S900") { print "815104"; } else { print "1620992"; }
			 print " bytes free)\n\n"; 
		}
	}
}
print "\n";

?>
