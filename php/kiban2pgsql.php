<?php
/**
 * kiban2pgsql.php
 * 基盤地図情報(GML)をPostGIS用のロード文に変換するもの。
 *
 * Usage : php kiban2pgsql.php [<options>] <xmlfile> [<schema>.]<table> > test.log 2>/dev/null 
 * @see
 * @todo
 */

include "kiban_admarea.php";
include "kiban_admbdry.php";

define( "INFO", 1 );
define( "WARN", 3 );
define( "ERROR", 5 );
define( "SILENT", 999 );

	// 出力先（標準出力）
	$to_file = 'php://stdout';
	$tablename = "";	// kiban_data?

	$log_mode = ERROR;

	$is_createtable = true;
	$is_droptable = false;
	$is_prepare = false;
	$is_force = false;

	//パラメータの取り込み
	foreach ( $argv as $i => $arg ) {
		//textout( "php://stderr", "{$i} => " . $arg . "\n" );
		if ( $i == 0 ) continue;
		$arg1 = substr($arg,0,1);
		$arg2 = "";
		if ( $arg1 === "-" ) {
			$arg2 = substr($arg,1);
			//textout( "php://stderr", " option " . $arg2 . "\n" );
		}
		switch ( $arg2 ) {
		case "silent" :
			$log_mode = SILENT;
			break;
		case "info" :
			$log_mode = INFO;
			break;
		case "warn" :
			$log_mode = WARN;
			break;
		case "err" :
		case "error" :
			$log_mode = ERROR;
			break;
		case "p" :	// Prepare mode, only creates the table.
			//textout( "php://stderr", "  prepare\n" );
			$is_prepare = true;
			$is_createtable = true;
			break;
		case "a" :	// Appends into current table, must be exactly the same table schema.
			//textout( "php://stderr", "  append\n" );
			$is_prepare = false;
			$is_createtable = false;
			break;
		case "c" :	// Creates a new table and populates it, this is the default if you do not specify any options.
			//textout( "php://stderr", "  create\n" );
			$is_createtable = true;
			break;
		case "d" :	// Drops the table, then recreates it.
			//textout( "php://stderr", "  drop\n" );
			$is_droptable = true;
			$is_createtable = true;
			break;
		case "f" :	// Force Drop the table.
			//textout( "php://stderr", "  force\n" );
			$is_force = true;
			break;
		default :
			errorout( "  inputfile {$arg}\n", INFO );
			if ( empty($from_xml) ) $from_xml = $arg;
			else if ( empty($tablename) ) $tablename = $arg;
			break;
		}
	}

	if ( empty($tablename) ) {
		// テーブル名エラー
		errorout( "ERROR: no tablename ", ERROR );
	} else {
		if ( $is_droptable ) {
			if ( $is_force ) {
				textout($to_file,"DROP TABLE IF EXISTS {$tablename} CASCADE;\n");
			} else {
				textout($to_file,"DROP TABLE IF EXISTS {$tablename};\n");
			}
		}
		if ( $is_createtable ) {
			textout($to_file,"CREATE TABLE {$tablename} (\n");
			textout($to_file,"  gid bigserial,\n");
			textout($to_file,"  fid text NOT NULL,\n");
			textout($to_file,"  feature_type text NOT NULL,\n");
			textout($to_file,"  geom geometry(Geometry,4612) NOT NULL,\n");
			textout($to_file,"  attributes text,\n");
			textout($to_file,"  data_date date NOT NULL,\n");
			textout($to_file,"  meshcode text NOT NULL,\n");
			textout($to_file,"  CONSTRAINT {$tablename}_pkey PRIMARY KEY ( gid )\n");
			textout($to_file,");\n");
			textout($to_file,"CREATE INDEX {$tablename}_gidx ON {$tablename} USING GIST ( geom );\n");
			textout($to_file,"CREATE INDEX {$tablename}_idx1 ON {$tablename} ( fid );\n");
			textout($to_file,"CREATE INDEX {$tablename}_idx2 ON {$tablename} ( feature_type );\n");
			textout($to_file,"CREATE INDEX {$tablename}_idx3 ON {$tablename} ( meshcode );\n");
		}
		if ( ! $is_prepare ) {
			$count = xml2pgsql($from_xml,$to_file,$tablename);
			if ( $count > 0 ) {
				errorout( " SUCCESS: " . $count, INFO );
			} else {
				errorout( "ERROR: ". $count, ERROR );
			}
		}
	}


return;
// ここで本体は終了

// 標準エラー出力
function errorout($somecontent="",$mode=ERROR) {
global $log_mode;
	if ( $log_mode <= $mode  ) {
		textout("php://stderr",$somecontent ."\n");
	}
}

// 文字列を書き出す
function textout($filename,$somecontent) {
	if (empty($filename)) { return false; }
	if (empty($somecontent)) { return false; }
	try {
		if (!$handle = fopen($filename, 'a')) {
			echo "Cannot open file ($filename)";
			return false;
		}
		// オープンしたファイルに$somecontentを書き込みます
		if (fwrite($handle, $somecontent) === FALSE) {
			echo "Cannot write to file ($filename)";
			return false;
		}
		//echo "Success, wrote ($somecontent) to file ($filename)";
		fclose($handle);
	} catch (Exception $err) {echo($err."\n");}
	return true;
}
/////////////////////////////////////////////////////////////////////////////////////

/////////////////////////////////////////////////////////////////////////////////////
function xml2pgsql($from_xml,$to_file,$tablename) {

	errorout("- xml2pgsql ---------------------------------------", INFO);

//FG-GML-ppppqq-ss- YYYYMMDD.zip

	$meshcode = false;
	$feature_type = false;
	$data_date = false;

	$filename = basename($from_xml);
	if (preg_match('/^FG-GML-([0-9]{6})-([A-Za-z]+)-([0-9]{8})(.*)$/', $filename,$matches)) {
		$meshcode = $matches[1];
		$feature_type = $matches[2];
		$data_date = $matches[3];
	}
	
	if ( empty($tablename) ) {
		// テーブル名エラー
		errorout( "ERROR: no tablename ", ERROR );
		return -1;
	}

	if ( !$meshcode || !$feature_type || !$data_date ) {
		// ファイル名エラー
		errorout( "ERROR: bad filename ", ERROR );
		return -2;
	}
	errorout( " Read ... " . $meshcode . " " . $feature_type . " " . $data_date, INFO );

	errorout("----------------------------------------", INFO);

	$reccount = 0;

	if ( file_exists($from_xml) ) {

		switch ( $feature_type ) {
		case "AdmArea" :
			$reccount = procAdmArea($from_xml, $to_file, $tablename, $meshcode, $feature_type, $data_date);
			break;
		case "AdmBdry" :
			$reccount = procAdmBdry($from_xml, $to_file, $tablename, $meshcode, $feature_type, $data_date);
			break;
		}

	}

	return $reccount;
}

