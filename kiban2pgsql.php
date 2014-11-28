<?php
/**
 * kiban2pgsql.php
 * 基盤地図情報(GML)をPostGIS用のロード文に変換するもの。
 *
 * Usage : php kiban2pgsql.php [<options>] <xmlfile> [<schema>.]<table> > test.log 2>/dev/null 
 * @see
 * @todo
 */

	// 出力先（標準出力）
	$to_file = 'php://stdout';

	//パラメータの取り込み
	foreach ( $argv as $i => $arg ) {
		textout( "php://stderr", "{$i} => " . $arg . "\n" );
		if ( $i == 0 ) continue;
		$arg1 = substr($arg,0,1);
		$arg2 = "";
		if ( $arg1 === "-" ) {
			$arg2 = substr($arg,1,1);
			textout( "php://stderr", " option " . $arg2 . "\n" );
		}
		switch ( $arg2 ) {
		case "p" :
			textout( "php://stderr", "  prepare\n" );
			break;
		case "a" :
			textout( "php://stderr", "  append\n" );
			break;
		case "c" :
			textout( "php://stderr", "  create\n" );
			break;
		case "d" :
			textout( "php://stderr", "  drop\n" );
			break;
		default :
			textout( "php://stderr", "  input file\n" );
			textout( "php://stderr", "   {$arg}\n" );
			$from_xml = $arg;
			break;
		}
	}

	xml2pgsql($from_xml,$to_file);


return;
// ここで本体は終了


function errorout($somecontent="") {
	textout("php://stderr",$somecontent ."\n");
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
function xml2pgsql($from_xml,$to_file) {

	errorout("----------------------------------------");

	$reccount = 0;

	if ( file_exists($from_xml) ) {

		// COPY文
		textout($to_file,"COPY AdmArea (AdmArea_id,AdmArea_fid,AdmArea_lfSpanFr,AdmArea_devDate,AdmArea_orgGILvl,AdmArea_vis,AdmArea_Surface_id,AdmArea_Surface_srsName,AdmArea_Curve_id,AdmArea_name,AdmArea_type,AdmArea_admCode,geom) FROM stdin DELIMITER '\t';\n");

		$reader = new XMLReader();
		$reader->open( $from_xml );

		$is_in_AdmArea = false;
		$is_in_area = false;
		$is_in_lfSpanFr = false;
		$is_in_devDate = false;

		$AdmArea_id = "";
		$AdmArea_fid = "";
		$AdmArea_lfSpanFr = "";
		$AdmArea_devDate = "";
		$AdmArea_orgGILvl = "";
		$AdmArea_vis = "";
		$AdmArea_Surface_id = "";
		$AdmArea_Surface_srsName = "";
		$AdmArea_Curve_id = "";
		$AdmArea_name = "";
		$AdmArea_type = "";
		$AdmArea_admCode = "";

		$is_debug_break = false;
		while (@$reader->read()) {
			//if ( $reader->nodeType != 14 ) errorout( " " . $reader->nodeType ."-". $reader->localName );

			switch ($reader->nodeType) {
			case (XMLREADER::ELEMENT):	// 開始タグ
				switch ( ($reader->localName) ) {
				case "Dataset" :
					break;
				case "description" :
					$tmp_data = $reader->readString();
					errorout( " description " . $tmp_data );
					break;

				case "AdmArea" :
					errorout("-- " . $reader->localName . " -------------------------");
					$is_in_AdmArea = true;
					$AdmArea_id = $reader->getAttribute("gml:id");
					errorout( " id " . $AdmArea_id );
					break;

				case "fid" :
					$AdmArea_fid = $reader->readString();
					errorout( " fid " . $AdmArea_fid );
					break;
				case "lfSpanFr" :
					$is_in_lfSpanFr = true;
					$AdmArea_lfSpanFr = "lfSpanFr";
					errorout( " lfSpanFr " );
					break;
				case "devDate" :
					$is_in_devDate = true;
					$AdmArea_devDate = "devDate";
					errorout( " devDate "  );
					break;
				case "timePosition" :
					$tmp_data = $reader->readString();
					errorout( "  timePosition " . $tmp_data );
					if ( $is_in_lfSpanFr ) $AdmArea_lfSpanFr = $tmp_data;
					if ( $is_in_devDate ) $AdmArea_devDate = $tmp_data;
					break;

				case "orgGILvl" :
					$AdmArea_orgGILvl = $reader->readString();
					errorout( " orgGILvl " . $AdmArea_orgGILvl );
					break;
				case "vis" :
					$AdmArea_vis = $reader->readString();
					errorout( " vis " . $AdmArea_vis );
					break;
				case "area" :
					$is_in_area = true;
					break;

				case "name" :
					//errorout($reader->localName . var_export($reader->readString(),true));
					if ( $is_in_AdmArea ) {
						$AdmArea_name = $reader->readString();
						errorout( " Adm name " . $AdmArea_name );
					} else {
						$tmp_data = $reader->readString();
						errorout( " name " . $tmp_data );
					}
					break;

				case "type" :
					//errorout($reader->localName . var_export($reader->readString(),true));
					if ( $is_in_AdmArea ) {
						$AdmArea_type = $reader->readString();
						errorout( " Adm type " . $AdmArea_type );
					} else {
						$tmp_data = $reader->readString();
						errorout( " type " . $tmp_data );
					}
					break;
				case "admCode" :
					if ( $is_in_AdmArea ) {
						$AdmArea_admCode = $reader->readString();
						errorout( " Adm admCode " . $AdmArea_admCode );
					} else {
						$tmp_data = $reader->readString();
						errorout( " admCode " . $tmp_data );
					}
					break;

				case "Surface" :
					//errorout($reader->localName . var_export($reader->getAttribute("name"),true));
					$AdmArea_Surface_id = $reader->getAttribute("gml:id");
					errorout( " area Surface id " . $AdmArea_Surface_id );
					$AdmArea_Surface_srsName = $reader->getAttribute("srsName");
					errorout( " area Surface srsName " . $AdmArea_Surface_srsName );
					break;
				case "Curve" :
					//errorout($reader->localName . var_export($reader->getAttribute("name"),true));
					$AdmArea_Curve_id = $reader->getAttribute("gml:id");
					errorout( "  area Curve id " . $AdmArea_Curve_id );
					break;

				case "posList" :
					//error_log($reader->localName . var_export($reader->readString(),true));
					$tmp_posList = $reader->readString();
					//errorout( "   Adm posList " . $tmp_posList );
					$ar_coordinates = explode("\n",$tmp_posList);
					//errorout( "   Adm posList " . var_export($ar_coordinates,true) );
					break;

				case "point" :
					break;
				case "coordinates" :
					$tmp = $reader->readString();
					$ar_coordinates = explode(",",$tmp);
					break;
				default :
					if ( $is_in_AdmArea ) {
						if ( !$is_in_area && $reader->nodeType != 14 ) errorout( " Adm " . $reader->nodeType ."-". $reader->localName );
					} else {
						if ( $reader->nodeType != 14 ) errorout( " " . $reader->nodeType ."-". $reader->localName );
					}
					break;
				}
				break;

			case (XMLREADER::END_ELEMENT):	// 終了タグ
				switch ( ($reader->localName) ) {
				case "Dataset" :
					break;
				case "name" :
					break;
				case "description" :
					break;

				case "AdmArea" :
					$is_debug_break = true;

					// COPY用レコード
					$insert_line =
						""   . $AdmArea_id	// AdmArea_id
						."\t". $AdmArea_fid	// AdmArea_fid
						."\t". $AdmArea_lfSpanFr	// AdmArea_lfSpanFr
						."\t". $AdmArea_devDate	// AdmArea_devDate
						."\t". $AdmArea_orgGILvl	// AdmArea_orgGILvl
						."\t". $AdmArea_vis	// AdmArea_vis
						."\t". $AdmArea_Surface_id	// AdmArea_Surface_id
						."\t". $AdmArea_Surface_srsName	// AdmArea_Surface_srsName
						."\t". $AdmArea_Curve_id	// AdmArea_Curve_id
						."\t". $AdmArea_name	// AdmArea_name
						."\t". $AdmArea_type	// AdmArea_type
						."\t". $AdmArea_admCode	// AdmArea_admCode
						."\t". "\\N"	// 
					;

					// １レコード分の書き出し
					textout("{$to_file}","{$insert_line}\n");
					$reccount++;

					$is_in_AdmArea = false;
					$AdmArea_id = "";
					$AdmArea_fid = "";
					$AdmArea_lfSpanFr = "";
					$AdmArea_devDate = "";
					$AdmArea_orgGILvl = "";
					$AdmArea_vis = "";
					$AdmArea_Surface_id = "";
					$AdmArea_Surface_srsName = "";
					$AdmArea_Curve_id = "";
					$AdmArea_name = "";
					$AdmArea_type = "";
					$AdmArea_admCode = "";
					break;

				case "fid" :
					break;
				case "lfSpanFr" :
					$is_in_lfSpanFr = false;
					break;
				case "devDate" :
					$is_in_devDate = false;
					break;
				case "timePosition" :
					break;

				case "orgGILvl" :
					break;
				case "vis" :
					break;
				case "area" :
					$is_in_area = false;
					break;

				case "type" :
					break;
				case "admCode" :
					break;

				default :
					if ( $is_in_AdmArea ) {
						if ( !$is_in_area && $reader->nodeType != 14 ) errorout( " Adm " . $reader->nodeType ."-". $reader->localName );
					} else {
						if ( $reader->nodeType != 14 ) errorout( " " . $reader->nodeType ."-". $reader->localName );
					}
					break;
				}
				break;
			
			case (XMLREADER::TEXT):	// TEXT
				break;
			case (XMLREADER::SIGNIFICANT_WHITESPACE):	// SIGNIFICANT_WHITESPACE
				break;
			default :
				errorout("none support nodeType : " . $reader->nodeType);
				break;
			}

			if ( $is_debug_break ) break;
		}

		// 終了マーク
		textout("{$to_file}","\\.\n");

	}

/*
				// COPY用レコード
				$insert_line = "" . $metadata["dictionary_id"]	// dictionary_id
					."\t". $linecount	// dictionarydata_id
					."\t". 1	// renban
					."\t". $Placemark["name"]	// name
					."\t". "\\N"	// geographic_extent
					."\t". "\\N"	// temporal_extent_s
					."\t". "\\N"	// temporal_extent_e
					."\t". "\\N"	// administrator
					."\t". $Placemark["Point"][1]	// latitude
					."\t". $Placemark["Point"][0]	// longitude
					."\t". "\\N"	// location_type
					."\t". $metadata["user_no"]	// signup_user_no
					."\t". $metadata["regist_timestamp"]	// signup_timestamp
					."\t". "SRID=4612;POINT(".$Placemark["Point"][0]." ".$Placemark["Point"][1].")"	// the_geom
					."\t". $Placemark["id"]	// original_id
					."\t". (($Placemark["ExtendedData"]["user_id"] === "") ? "\\N" : $Placemark["ExtendedData"]["user_id"])	// user_id
					."\t". $Placemark["ExtendedData"]["data_creator"]	// data_creator
					."\t". $Placemark["ExtendedData"]["data_date"]	// data_date
					."\t". $Placemark["ExtendedData"]["license"]	// license
				;
				// １レコード分の書き出し
				textout("{$to_file}","{$insert_line}\n");
				$reccount++;

*/


	return $reccount;
}

