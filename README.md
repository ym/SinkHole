# SinkHole #

SinkHole is a light-weight File-Based Chain Key-Value-Like Database.

:-) At first, I thought it a simple task, gradually I found I'm absolutely wrong.

So I named it SinkHole.


## Structure ##
	    	
 ```
 ===========================================================
 Last Available Inode ID						   (24 bytes)
 Inode Count									   (24 bytes)
 Last Writable Offset							   (24 bytes)
 Reserved										   (24 bytes)
 Reserved										   (24 bytes)
 Reserved								  		   (24 bytes)
 ============================================================
 Inode Path Table                        (16 bytes per inode)
 Inode: Path(Hashed,16 bytes)                      (16 bytes)
 ============================================================
 Inode Offset&&Length Table              (16 bytes per inode)
 Inode: Offset(8 bytes), Length(8 Bytes)           (16 bytes)
 ============================================================
 Data Table
 ```
 
By default, every databse file has 1024 available inodes.

At present, SinkHole doesn't support resize database after being created.

## Usage ##

SinkHole only supports *PHP* now.

```
<?php
	// Load SinkHole Library
	require_once 'sinkhole.php';
	// Create a SinkHole Database (Please don't define file path here!!!)
	$hole = new SinkHole('example.hole');
	// Or Create a SinkHole Database with User-Defined Path and Inode Count
	$hole = new SinkHole('example.hole', [
		'path'		=> '/dev/60059/webroot/files/example.hole',
		'inodes'	=> 4096
	]);
	// Set a value
	$hole->rock->you = 'rock';
	// Create a link to $hole->rock
	$hole->suck = $hole->rock;
	// Flush database (By default, SinkHole will flush database before your PHP closing)
	$hole->flush();
	// Reallocate a SinkHole Database (Delete all useless data in order to reduce size of database file)
	$hole->reallocate();
```

## Advantages & Disadvantages ##


