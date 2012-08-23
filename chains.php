<?php
define('EMPTY_CHAR', chr(0));

function emptyfill($str, $len = 32) {
    ///// echo "<pre>";
    //debug_print_backtrace();
    ///// echo "</pre>";
	return str_repeat(EMPTY_CHAR, $len - strlen($str)) . $str;
}

function numberfill($str, $len = 32) {
    // /// echo "<pre>";
    // debug_print_backtrace();
    // /// echo "</pre>";
    return str_repeat('0', $len - strlen($str)) . $str;
}

function guagua($a) {
    echo "<pre><strong>{$a}</strong><br />";
    debug_print_backtrace();
    echo "</pre>";
}

class VirtualChain {
    private $dest = '';
    private $realpath = '';

    public function __construct($dest, $realpath = '') {
        $this->dest = $dest;
        $this->realpath = $realpath;
    }

    public function data() {
        return $this->dest;
    }

    public function realpath() {
        return $this->realpath;
    }

}

class chain {

    private $path;
    private static $inodeKeys = [];
    private static $inodeValues = [];

    private static $data = [];
    private static $boxes = [];


    private static $lastInode = [];
    private static $dataOffset = [];

    private static $registered = false;
    private static $inode_max = 1024;

    private $box = '';
    private $path_hash = '';

    public function __construct( $path ) {
    
    	if(!self::$registered) {
    		register_shutdown_function('chain::saveall');
            self::$registered = true;
    	}

        /* simple overload ... */

        if (is_array($path)) {
        	$this->path = $path;
        	$this->box = $path[0];
        } elseif (is_string($path)) {
        	$this->path = [$path];
        	$this->box = $path;
            self::$data[$this->box] = [];
        	self::open($path);
        } else {
        	throw new Exception('406', 100);
        }

        $this->path_hash = MD5(implode('/', $this->path));

        if(!isset(self::$data[$this->path_hash])) {
            self::seek($this->box, $this->path_hash);
        }
    
        /// echo 'this is ' . implode( '/', $this->path ) . ' ' . MD5(implode('/', $this->path)) .'<br />';
        
        return;
    
    }
    
    public function __get($key) {
        if(isset(self::$data[$this->box][$this->path_hash][$key])) {
            if(self::$data[$this->box][$this->path_hash][$key] instanceof VirtualChain) {
                return new chain(self::$data[$this->box][$this->path_hash][$key]->realpath());
            } else {
                return self::$data[$this->box][$this->path_hash][$key];
            }
        } else {
            return new chain(array_merge($this->path, [$key]));
        }
    
    }

    public function __set($key, $value) {
    	//self::set();
    	if($value instanceof chain) {
    		echo "Chain link created <br />";
            if($value->box() != $this->box || $value->path_hash() == $this->path_hash) {
                throw new Exception('406', 100);
            } else {
                self::$data[$this->box][$this->path_hash][$key] = new VirtualChain($value->path_hash(), $value->path());
            }
    	} else {
            self::$data[$this->box][$this->path_hash][$key] = $value;

    	}
    	return ;
    }

    public function __unset($key) {
        if(isset(self::$data[$this->box][$this->path_hash][$key])) {
            /// echo "不要删除我嘛...主人...<br />";
        } elseif(isset(self::$data[$this->box][MD5(implode('/',$this->path) . '/' . $key)])) {
            self::remove($this->box, MD5(implode('/',$this->path) . '/' . $key));

            /// echo "你居然敢删除我！不想活了！<br />";
        } else {
            /// echo "嘛都没找到还删...二逼啊<br />";
        }
    }

    public function box() {
        return $this->box;
    }

    public function path_hash() {
        return $this->path_hash;
    }

    public function path() {
        return $this->path;
    }

    private static function file($box) {
    	return '/webroot/files/' . ($box) .'.box';
    }

    private static function open($box) {
    	/// echo "open box {$box}<br />";

    	self::init($box);

    }

    private static function init($box) {
    	/// echo "init {$box} <br />";

    	$file = self::file($box);

    	if(!file_exists($file)) {
    		/// echo "creating box {$box} on disk<br />";
	    	self::$boxes[$box] = fopen(self::file($box), 'wrx+b');
            flock(self::$boxes[$box], LOCK_EX);

	    	$lastInode = 0;
	    	$Inodes = self::$inode_max;


	    	// write last inode
            //duck::guagua(numberfill($lastInode));
            self::$dataOffset[$box] = 32 * (6 + $Inodes);


	    	fwrite(self::$boxes[$box], str_pad($lastInode, 32, 0, STR_PAD_LEFT) .
                                       str_pad($Inodes, 32, 0, STR_PAD_LEFT) .
                                       str_pad(self::$dataOffset[$box], 32, 0, STR_PAD_LEFT) .
                                       str_pad(0, 32, 0, STR_PAD_LEFT) .
                                       str_pad(0, 32, 0, STR_PAD_LEFT) .
                                       str_pad(0, 32, 0, STR_PAD_LEFT));

            fflush(self::$boxes[$box]);

	    	// write inodes
			fwrite(self::$boxes[$box], str_repeat(0, 32 * $Inodes));
			

            self::$lastInode[$box] = $lastInode;

        } else {
	    	self::$boxes[$box] = fopen(self::file($box), 'rw+b');

            flock(self::$boxes[$box], LOCK_EX);

	    	/// echo "fetching {$box} on disk<br />";

    		fseek(self::$boxes[$box],0);

	    	self::$lastInode[$box] = fread(self::$boxes[$box], 32) + 0;

            // duck::guagua(self::$lastInode[$box]);

    		echo "last available inode " . self::$lastInode[$box] . "<br />";

            if(self::$lastInode[$box] == 0) {
                self::$inodeKeys[$box] = self::$inodeValues[$box] = [];
            } else {
                // Get Inode - Part 1: Path Table

    			fseek(self::$boxes[$box], 6 * 32);

                // duck::guagua(self::$lastInode[$box] * 16);

    			self::$inodeKeys[$box] = array_flip(str_split(fread(self::$boxes[$box], self::$lastInode[$box] * 16), 16));
                /// duck::guagua(self::$inodeKeys[$box]);

                // Get Inode - Part 2: Offset&&Length Table

                fseek(self::$boxes[$box], 6 * 32 + self::$inode_max * 16);

                self::$inodeValues[$box] = (str_split(fread(self::$boxes[$box], self::$lastInode[$box] * 16),16));
                /// duck::guagua(self::$inodeValues[$box]);

            }

            // Get Data Offset

            fseek(self::$boxes[$box], 64);

            self::$dataOffset[$box] = fread(self::$boxes[$box], 32) + 0;

            /// echo "data offset " . self::$dataOffset[$box] . "<br />";
		}
    }

    private static function inode($box, $pathhash, $inodeOffset, $offset, $length) {

        
    	/// echo "last available inode " . self::$lastInode[$box] . "<br />";


    	if(self::$lastInode[$box]>self::$inode_max) {
    		throw new Exception('406',100);
    	}


    	$lastInodeKeyOffset = (6 * 32) + ($inodeOffset * 16);
        $lastInodeValueOffset = (6 * 32) + (($inodeOffset + self::$inode_max) * 16);

        /// echo "lastInodeValueOffset {$lastInodeValueOffset} <br />";

        // 32 length

    	$dataKey = hex2bin($pathhash);

        if($offset == 'link') {
            //var_dump($length);
            $dataValue = hex2bin($length);
        } else {
            $dataValue = numberfill(intval($offset), 8) . numberfill(intval($length), 8);
        }

        /// echo "Written iNode {$inodeOffset} {$dataValue} <br />";
        /*
		fseek(self::$boxes[$this->box],0);
		//// fwrite(self::$boxes[$this->box], numberfill($lastAvailableInode), 32);

        fseek(self::$boxes[$this->box],0);
        */

		// jump to inode table

		fseek(self::$boxes[$box], $lastInodeKeyOffset);
		fwrite(self::$boxes[$box], $dataKey, 16);

        fseek(self::$boxes[$box], $lastInodeValueOffset);
        fwrite(self::$boxes[$box], $dataValue, 16);

		fflush(self::$boxes[$box]);

        if($inodeOffset>=self::$lastInode[$box]) {
            self::$lastInode[$box]++;
        }

		unset($data, $lastAvailableInode, $lastInodeOffset);

    }

    private static function seek($box, $path_hash, $realpath_hash = null) {

        if($realpath_hash === null) {
            $realpath_hash = $path_hash;
        } else {
            echo "SEEKING" ;
        }

        if(isset(self::$inodeKeys[$box][hex2bin($realpath_hash)])) {
            echo "<b style='color:green'> found {$realpath_hash}</b> <br />";
            guagua(self::$inodeValues[$box][self::$inodeKeys[$box][hex2bin($realpath_hash)]]);

            if(is_numeric(self::$inodeValues[$box][self::$inodeKeys[$box][hex2bin($realpath_hash)]])) {
                $data = str_split(self::$inodeValues[$box][self::$inodeKeys[$box][hex2bin($realpath_hash)]], 8);

                echo "{$data[0]} {$data[1]}<br/>";
                // add link path
                fseek(self::$boxes[$box], $data[0] - $data[1]);


                $result = fread(self::$boxes[$box], $data[1] + 0);
                // var_dump($result);
                self::$data[$box][$path_hash] = unserialize($result);

                /// duck::guagua(self::$data[$box][$path_hash]);
            } else {
                
                self::seek($box, $path_hash, bin2hex(self::$inodeValues[$box][self::$inodeKeys[$box][hex2bin($realpath_hash)]]));

            }
        } else {
            echo "<b style='color:red'> not found {$realpath_hash}</b> <br />";
        }
    }

    private static function set($box, $path_hash, $path) {

        /// echo "CALLED: data offset" . self::$dataOffset[$box] . "<br />";
        if(!isset(self::$data[$box][$path_hash])) {
            return ;
        }

        foreach (self::$data[$box][$path_hash] as $key => $value) {
            if($value instanceof VirtualChain) {
                echo "Added Chain  <br />";
                self::inode($box, MD5(implode('/', $path) . '/' . $key), self::$lastInode[$box], 'link', $value->data());
                unset(self::$data[$box][$path_hash][$key]);
            }
        }


        $data = serialize(self::$data[$box][$path_hash]);

        $length = strlen($data);

        ///if(isset(self::$inodeKeys[$box][hex2bin($path_hash)])) {
        ///    $old_data = str_split(self::$inodeValues[$box][self::$inodeKeys[$box][hex2bin($path_hash)]], 8);
        ///    $old_offset = $old_data[0] + 0;
        ///    $old_length = $old_data[1] + 0;

        ///    if($old_length>=$length) {
        ///        // if old_length>length then overwrite this block
        ///        fseek(self::$boxes[$box], $old_offset);
        ///         guagua("Used offset {$old_offset}");
        ///    } else {
                fseek(self::$boxes[$box], self::$dataOffset[$box]);
                /// guagua("Used offset " . self::$dataOffset[$box]);
                self::$dataOffset[$box] += $length;
        ///    }
        ///} else {
        ///    fseek(self::$boxes[$box], self::$dataOffset[$box]);
        ///    guagua("Used offset " . self::$dataOffset[$box]);
        ///    self::$dataOffset[$box] += $length;
        ///}

        /// echo "吱一声... ".ftell(self::$boxes[$box])."<br />";
        fwrite(self::$boxes[$box], $data);

        if(isset(self::$inodeKeys[$box][hex2bin($path_hash)])) {
            $inodeOffset = self::$inodeKeys[$box][hex2bin($path_hash)];
        } else {
            $inodeOffset = self::$lastInode[$box];
        }

        /// echo "CALLED: inode offset {$inodeOffset},".self::$dataOffset[$box]."<br />";

        self::inode($box, $path_hash, $inodeOffset, self::$dataOffset[$box], $length);

        

        unset($data, $length);
    }

    private static function remove($box, $hash) {
        /// guagua("Guagua..");
        /// duck::guagua(self::$data[$box]);
        if(isset(self::$data[$box][$hash]) && isset(self::$inodeKeys[$box][hex2bin($hash)])) {
            unset(self::$data[$box][$hash]);
            $inodeOffset = self::$inodeKeys[$box][hex2bin($hash)];


            fseek(self::$boxes[$box], (6 * 32) + ($inodeOffset * 16));
            fwrite(self::$boxes[$box], str_repeat(0, 16), 16);

            fseek(self::$boxes[$box], (6 * 32) + (($inodeOffset + self::$inode_max) * 16));
            fwrite(self::$boxes[$box], str_repeat(0, 16), 16);

            fflush(self::$boxes[$box]);
            // No Auto Reallocate 

            /// echo "。。。。 <br />";
        }
    }

    private static function commit($box = null) {

    }

    private static function gc($box, $handle) {
        if(count(self::$inodeValues[$box]) == 0) {
            return ;
        }

        $usingBlocks = array();
        $usingBlockRelationships = array();
        $usingBlockData = '';

        // get inode blocks
        $usingBlocks[] = [0, 32 * (self::$inode_max + 6)];
        $usingBlockRelationships[] = 'INODE';

        // get all using blocks
        foreach (self::$inodeValues[$box] as $i=>$ol) {
            if(is_numeric($ol)) {
                $ol = str_split($ol, 8);
                $usingBlocks[] = [(Int) $ol[0], (Int) $ol[1]];
                $usingBlockRelationships[] = $i;

                fseek($handle, (Int) $ol[0]);
                echo (Int) $ol[1];

                $usingBlockData .= fread($handle, (Int) $ol[1]);
                $usingBlockR
            } else {

            }
        }

        // then rewrite data



        fseek($handle, $usingBlocks[0][1]);
        fwrite($handle, $usingBlockData);

        $newlen = $usingBlocks[0][1] + strlen($usingBlockData);

        ftruncate($handle, $newlen);

    }
    
    public static function close() {
    	foreach(self::$boxes as $box=>$handle) {
            fseek($handle, 0);
            fwrite($handle, str_pad(self::$lastInode[$box], 32, 0, STR_PAD_LEFT));


            fseek($handle, 64);

            /// echo "FFF:" . self::$dataOffset[$box]  ."<br />";
            fwrite($handle, str_pad(self::$dataOffset[$box], 32, 0, STR_PAD_LEFT));

            // 扔色子玩儿...
            $p = intval(ini_get('sinkhole.gc.magicnumber')) || 0;
            $p1  = intval(ini_get('sinkhole.gc.maxnumber')) || 0;
            if(mt_rand(0, $p1) == $p) {
                self::gc($box, $handle);
            }

            flock($handle, LOCK_UN);
    		fclose($handle);

            /// echo "Closed box {$box} <br />";

            unset(self::$boxes[$box]);
    	}
    }

    public static function saveall() {
        return self::close();
    }

    public function __destruct() {
        self::set($this->box, $this->path_hash, $this->path);
        //self::close();
        // /// echo "Pass count {$} <br />";
    }
}


$chain = new chain( 'box17' );

//$chain->aa->bb->cc = 'a';
for($v = 0; $v<1; $v++) {
    $j = array_rand(array_flip(array('a','b','c','d','e','f','g'))) . time();
    //var_dump($chain->$j);
    //$chain->$j->$j = 'v';
}

$chain->google->it = 'baiduqusi';
//$chain->baidu->google = $chain->google;


///// echo "Chain->Test = {$chain->Test} <br />";


//duck::guagua($chain);

//chain::close();

?>
<script>
	setInterval(function() {
		//window.location.reload();
	},100);
</script>