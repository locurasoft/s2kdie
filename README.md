S2000/S3000/S900 Disk Image Editor version 1.1.2
Copyright (C) 2004 Michael Kane <mick@checkoutwax.com>
   
This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License as 
published by the Free Software Foundation; either version 2 of
the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of 
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
General Public License for more details.

You should have received a copy of the GNU General Public
License along with this program; if not, write to the Free 
Software Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139,
USA.


-Things you will need.

Command line PHP 4.0+

-Optional items.

fdutils 5.0+  : if you want to read/write images to floppy you'll need this installed.
              : http://fdutils.linux.lu
wteledsk      : to convert .td0 floppy images into those readable by this program.
              : http://www.fpns.net/willy/wteledsk.htm

-Configure the editor.

Ensure the path to command line php is correct in s2kdie.php
Change $pathtosetfdprm to the path to setfdprm (include trailing forward slash)
Change the floppy device variable if required

-Getting there.

To Akai format a floppy disk (high density) with fdutils.
superformat /dev/fd0 sect=10 hd ssize=1024 cyl=80 
You will need to format a disk before saving an image to it, unless the disk has 
already been used with a sampler.

The editor should work with the S3200 and S2800.  I'm basing this on the fact that
they run the same opering system as the S3000.  I'd appreciate a floppy image from
either sampler.

The S950 will load S900 double density disks and this is currently the only way to
get samples onto them.  I'll include support for the high density S9XX disk format
when I get an image to check the ID bytes.

Disks are defragmented upon loading.  Currently only S900 double density floppies
are supported.  S2000 and S3000 support is with high density images only.

S3000 support is not tested.  I'm sure it can read S2000 floppies so it might be
safer to save your images in that format.

I own an S2000 and an S900.  I'm looking for a detailed S900 sysex list and an image
of the S900 operating system v4.0.

-Places of interest.

Akai disk formats. (Shout outs to Paul Kellett)
 http://mda.smartelectronix.com/akai/akaiinfo.htm
WAVE PCM soundfile format.
 http://ccrma.stanford.edu/courses/422/projects/WaveFormat/
Some S2000 disk images (in teledisk format)
 http://www.geocities.com/SunsetStrip/Studio/7012/

-Things to do.

In-depth program/sample editing.
Saving -L and -R samples into stereo WAV.
A total rewrite. (in C perhaps)

-Example

AKAI S2000/S3000/S900 Disk Image Editor v1.1.2
(? for help.)

Command: wload g2.wav
Stereo WAV imported as akai samples.
Command: vol G2
G2          
Command: dir

      S2000 Volume: G2          

      Filename       Type        Bytes
  [0] G2.WAV    -L   <SAMPLE>    84108
  [1] G2.WAV    -R   <SAMPLE>    84108

      1418 unused sectors.  (1452032 bytes free)

Command: sd 0

SHNAME: G2.WAV    -L
SBANDW: 44100Hz
SPITCH: C_3
SPTYPE: no looping
 STUNO: Semi.Cent +00.00
SLNGTH: 0

Command: save
Image saved.
Command: q
