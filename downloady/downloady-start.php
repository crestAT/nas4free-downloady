<?php
/*
    downloady-start.php 

    Copyright (c) 2015 - 2016 Andreas Schmidhuber
    All rights reserved.

    Redistribution and use in source and binary forms, with or without
    modification, are permitted provided that the following conditions are met:

    1. Redistributions of source code must retain the above copyright notice, this
       list of conditions and the following disclaimer.
    2. Redistributions in binary form must reproduce the above copyright notice,
       this list of conditions and the following disclaimer in the documentation
       and/or other materials provided with the distribution.

    THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
    ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
    WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
    DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR
    ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
    (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
    LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
    ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
    (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
    SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.

    The views and conclusions contained in the software and documentation are those
    of the authors and should not be interpreted as representing official policies,
    either expressed or implied, of the FreeBSD Project.
*/
$extension_dir = "/usr/local/www"; 
if (!is_dir($extension_dir)) { mwexec("mkdir -p {$extension_dir}", true); }

$arch = $g['arch'];
if ($arch == "i386") $arch = "x86";
elseif ($arch == "amd64") $arch = "x64";  

mwexec("cp -R {$config['downloady']['rootfolder']}ext/* {$extension_dir}/", true);          // copy extension
mwexec("cp -R {$config['downloady']['rootfolder']}{$arch}/local/* /usr/local/", true);      // copy wget binaries      
if ( !is_link ( "/usr/local/share/locale-dly")) { mwexec("ln -s {$config['downloady']['rootfolder']}locale-dly /usr/local/share/", true); }     // create link to languages

if (isset($config['downloady']['enable'])) {
	if (isset($config['downloady']['resume']) || isset($config['downloady']['enable_schedule'])) { 
        require_once("{$extension_dir}/downloady.php");
        if (isset($config['downloady']['full_bandwidth'])) $ratelimit = 0;
        $d = new downloady($dest, $ratelimit);
        $d->StartAll();
    }
}
?>
