<?php
function procAdmBdry($from_xml, $to_file, $tablename, $meshcode, $feature_type, $data_date) {

	$reccount = 0;

	if ( file_exists($from_xml) ) {

		// COPY文
		textout($to_file,"COPY {$tablename} (fid,feature_type,geom,attributes,data_date,meshcode) FROM stdin DELIMITER '\t';\n");

		$reader = new XMLReader();
		$reader->open( $from_xml );

		$is_in_AdmBdry = false;
		$is_in_loc = false;
		$is_in_exterior = false;
		$is_in_interior = false;
		$is_in_lfSpanFr = false;
		$is_in_devDate = false;

		$fid = "";
		$attributes = "";
		$geom = "";

		$AdmBdry_id = "";
		$AdmBdry_lfSpanFr = "";
		$AdmBdry_devDate = "";
		$AdmBdry_orgGILvl = "";
		$AdmBdry_vis = "";
		$AdmBdry_Curve_id = "";
		$AdmBdry_Curve_srsName = "";
		$AdmBdry_type = "";

		$is_debug_break = false;
		while (@$reader->read()) {
			//if ( $reader->nodeType != 14 ) errorout( " " . $reader->nodeType ."-". $reader->localName, INFO );

			switch ($reader->nodeType) {
			case (XMLREADER::ELEMENT):	// 開始タグ
				switch ( ($reader->localName) ) {
				case "Dataset" :
					break;
				case "description" :
					$tmp_data = $reader->readString();
					errorout( " description " . $tmp_data, INFO );
					break;

				case "AdmBdry" :
					errorout("-- " . $reader->localName . " -------------------------", INFO);
					$is_in_AdmBdry = true;
					$AdmBdry_id = $reader->getAttribute("gml:id");
					errorout( " id " . $AdmBdry_id, INFO );
					break;

				case "fid" :
					$fid = $reader->readString();
					errorout( " fid " . $fid, INFO );
					$attributes .= "<fid>". trim($reader->readString()) ."</fid>";
					break;
				case "lfSpanFr" :
					$is_in_lfSpanFr = true;
					$AdmBdry_lfSpanFr = "lfSpanFr";
					errorout( " lfSpanFr ", INFO );
					$attributes .= "<lfSpanFr>". trim($reader->readString()) ."</lfSpanFr>";
					break;
				case "devDate" :
					$is_in_devDate = true;
					$AdmBdry_devDate = "devDate";
					errorout( " devDate ", INFO );
					$attributes .= "<devDate>". trim($reader->readString()) ."</devDate>";
					break;
				case "timePosition" :
					$tmp_data = $reader->readString();
					errorout( "  timePosition " . $tmp_data, INFO );
					if ( $is_in_lfSpanFr ) $AdmBdry_lfSpanFr = $tmp_data;
					if ( $is_in_devDate ) $AdmBdry_devDate = $tmp_data;
					break;

				case "orgGILvl" :
					$AdmBdry_orgGILvl = $reader->readString();
					errorout( " orgGILvl " . $AdmBdry_orgGILvl, INFO );
					$attributes .= "<orgGILvl>". trim($reader->readString()) ."</orgGILvl>";
					break;
				case "vis" :
					$AdmBdry_vis = $reader->readString();
					errorout( " vis " . $AdmBdry_vis, INFO );
					$attributes .= "<vis>". trim($reader->readString()) ."</vis>";
					break;

				case "type" :
					//errorout($reader->localName . var_export($reader->readString(),true));
					if ( $is_in_AdmBdry ) {
						$AdmBdry_type = $reader->readString();
						errorout( " Adm type " . $AdmBdry_type, INFO );
						$attributes .= "<type>". trim($reader->readString()) ."</type>";
					} else {
						$tmp_data = $reader->readString();
						errorout( " type " . $tmp_data, WARN );
					}
					break;

				case "loc" :
					$is_in_loc = true;
					break;
				case "Curve" :
					//errorout($reader->localName . var_export($reader->getAttribute("name"),true), INFO);
					$AdmBdry_Curve_id = $reader->getAttribute("gml:id");
					errorout( "  loc Curve id " . $AdmBdry_Curve_id, INFO );
					$AdmBdry_Curve_srsName = $reader->getAttribute("srsName");
					errorout( " loc Curve srsName " . $AdmBdry_Curve_srsName, INFO );
					break;

				case "posList" :
					//error_log($reader->localName . var_export($reader->readString(),true), INFO);
					$tmp_posList = $reader->readString();
					//errorout( "   Adm posList " . $tmp_posList, INFO );
					$tmp_posList = trim($tmp_posList);
					$ar_coordinates = explode("\n",$tmp_posList);
					$ar_coordinates = array_map(function($v){ $v2 = explode(" ",trim($v)); return ($v2[1]." ".$v2[0]); },$ar_coordinates);
					//errorout( "   Adm posList " . var_export($ar_coordinates,true), ERROR );
					$geom = "SRID=4612;" . "LINESTRING(" . implode(",",$ar_coordinates) . ")";
					break;

				default :
					if ( $is_in_AdmBdry ) {
						if ( !$is_in_loc && $reader->nodeType != 14 ) errorout( " Adm " . $reader->nodeType ."-". $reader->localName, INFO );
					} else {
						if ( $reader->nodeType != 14 ) errorout( " " . $reader->nodeType ."-". $reader->localName, INFO );
					}
					break;
				}
				break;

			case (XMLREADER::END_ELEMENT):	// 終了タグ
				switch ( ($reader->localName) ) {
				case "Dataset" :
					break;
				case "description" :
					break;

				case "AdmBdry" :
					//$is_debug_break = true;

					$attributes = str_replace("\n", " ", $attributes);
					// COPY用レコード
					$insert_line =
						""   . $fid	// fid
						."\t". $feature_type	// feature_type
						."\t". $geom	// geom
						."\t". "<attributes>".$attributes."</attributes>"	// attributes
						."\t". $data_date	// data_date
						."\t". $meshcode	// meshcode
					;

					// １レコード分の書き出し
					textout("{$to_file}","{$insert_line}\n");
					$reccount++;

					$is_in_AdmBdry = false;
					$is_in_loc = false;
					$is_in_lfSpanFr = false;
					$is_in_devDate = false;
					$fid = "";
					$attributes = "";
					$geom = "";
					$AdmBdry_id = "";
					$AdmBdry_lfSpanFr = "";
					$AdmBdry_devDate = "";
					$AdmBdry_orgGILvl = "";
					$AdmBdry_vis = "";
					$AdmBdry_Curve_id = "";
					$AdmBdry_Curve_srsName = "";
					$AdmBdry_type = "";
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

				case "loc" :
					$is_in_loc = false;
					break;

				case "type" :
					break;

				default :
					if ( $is_in_AdmBdry ) {
						if ( !$is_in_loc && $reader->nodeType != 14 ) errorout( " Adm " . $reader->nodeType ."-". $reader->localName, INFO );
					} else {
						if ( $reader->nodeType != 14 ) errorout( " " . $reader->nodeType ."-". $reader->localName, INFO );
					}
					break;
				}
				break;
			
			case (XMLREADER::TEXT):	// TEXT
				break;
			case (XMLREADER::SIGNIFICANT_WHITESPACE):	// SIGNIFICANT_WHITESPACE
				break;
			default :
				errorout("none support nodeType : " . $reader->nodeType, ERROR);
				break;
			}

			if ( $is_debug_break ) break;
		}

		// 終了マーク
		textout("{$to_file}","\\.\n");
	}

	return $reccount;
}
