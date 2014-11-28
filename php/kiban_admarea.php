<?php
function procAdmArea($from_xml, $to_file, $tablename, $meshcode, $feature_type, $data_date) {

	$reccount = 0;

	if ( file_exists($from_xml) ) {

		// COPY文
		textout($to_file,"COPY {$tablename} (fid,feature_type,geom,attributes,data_date,meshcode) FROM stdin DELIMITER '\t';\n");

		$reader = new XMLReader();
		$reader->open( $from_xml );

		$is_in_AdmArea = false;
		$is_in_area = false;
		$is_in_exterior = false;
		$is_in_interior = false;
		$is_in_lfSpanFr = false;
		$is_in_devDate = false;

		$fid = "";
		$attributes = "";
		$geom = "";
		$exterior = "";
		$interior = "";

		$AdmArea_id = "";
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

				case "AdmArea" :
					errorout("-- " . $reader->localName . " -------------------------", INFO);
					$is_in_AdmArea = true;
					$AdmArea_id = $reader->getAttribute("gml:id");
					errorout( " id " . $AdmArea_id, INFO );
					break;

				case "fid" :
					$fid = $reader->readString();
					errorout( " fid " . $fid, INFO );
					$attributes .= "<fid>". trim($reader->readString()) ."</fid>";
					break;
				case "lfSpanFr" :
					$is_in_lfSpanFr = true;
					$AdmArea_lfSpanFr = "lfSpanFr";
					errorout( " lfSpanFr ", INFO );
					$attributes .= "<lfSpanFr>". trim($reader->readString()) ."</lfSpanFr>";
					break;
				case "devDate" :
					$is_in_devDate = true;
					$AdmArea_devDate = "devDate";
					errorout( " devDate ", INFO );
					$attributes .= "<devDate>". trim($reader->readString()) ."</devDate>";
					break;
				case "timePosition" :
					$tmp_data = $reader->readString();
					errorout( "  timePosition " . $tmp_data, INFO );
					if ( $is_in_lfSpanFr ) $AdmArea_lfSpanFr = $tmp_data;
					if ( $is_in_devDate ) $AdmArea_devDate = $tmp_data;
					break;

				case "orgGILvl" :
					$AdmArea_orgGILvl = $reader->readString();
					errorout( " orgGILvl " . $AdmArea_orgGILvl, INFO );
					$attributes .= "<orgGILvl>". trim($reader->readString()) ."</orgGILvl>";
					break;
				case "vis" :
					$AdmArea_vis = $reader->readString();
					errorout( " vis " . $AdmArea_vis, INFO );
					$attributes .= "<vis>". trim($reader->readString()) ."</vis>";
					break;

				case "name" :
					//errorout($reader->localName . var_export($reader->readString(),true), INFO);
					if ( $is_in_AdmArea ) {
						$AdmArea_name = $reader->readString();
						errorout( " Adm name " . $AdmArea_name, INFO );
						$attributes .= "<name>". trim($reader->readString()) ."</name>";
					} else {
						$tmp_data = $reader->readString();
						errorout( " name " . $tmp_data, INFO );
					}
					break;

				case "type" :
					//errorout($reader->localName . var_export($reader->readString(),true));
					if ( $is_in_AdmArea ) {
						$AdmArea_type = $reader->readString();
						errorout( " Adm type " . $AdmArea_type, INFO );
						$attributes .= "<type>". trim($reader->readString()) ."</type>";
					} else {
						$tmp_data = $reader->readString();
						errorout( " type " . $tmp_data, WARN );
					}
					break;
				case "admCode" :
					if ( $is_in_AdmArea ) {
						$AdmArea_admCode = $reader->readString();
						errorout( " Adm admCode " . $AdmArea_admCode, INFO );
						$attributes .= "<admCode>". trim($reader->readString()) ."</admCode>";
					} else {
						$tmp_data = $reader->readString();
						errorout( " admCode " . $tmp_data, WARN );
					}
					break;

				case "area" :
					$is_in_area = true;
					break;
				case "exterior" :
					$is_in_exterior = true;
					break;
				case "interior" :
					$is_in_interior = true;
					errorout( "   has interior " . $fid, WARN );
					break;
				case "Surface" :
					//errorout($reader->localName . var_export($reader->getAttribute("name"),true), INFO);
					$AdmArea_Surface_id = $reader->getAttribute("gml:id");
					errorout( " area Surface id " . $AdmArea_Surface_id, INFO );
					$AdmArea_Surface_srsName = $reader->getAttribute("srsName");
					errorout( " area Surface srsName " . $AdmArea_Surface_srsName, INFO );
					break;
				case "Curve" :
					//errorout($reader->localName . var_export($reader->getAttribute("name"),true), INFO);
					$AdmArea_Curve_id = $reader->getAttribute("gml:id");
					errorout( "  area Curve id " . $AdmArea_Curve_id, INFO );
					break;

				case "posList" :
					//error_log($reader->localName . var_export($reader->readString(),true), INFO);
					$tmp_posList = $reader->readString();
					//errorout( "   Adm posList " . $tmp_posList, INFO );
					$tmp_posList = trim($tmp_posList);
					$ar_coordinates = explode("\n",$tmp_posList);
					$ar_coordinates = array_map(function($v){ $v2 = explode(" ",trim($v)); return ($v2[1]." ".$v2[0]); },$ar_coordinates);
					//errorout( "   Adm posList " . var_export($ar_coordinates,true), ERROR );
					if ( $is_in_exterior ) {
						$exterior = "(" . implode(",",$ar_coordinates) . ")";
					} else if ( $is_in_interior ) {
						if ( empty($interior) ) {
							$interior = "(" . implode(",",$ar_coordinates) . ")";
						} else {
							$interior .= ",(" . implode(",",$ar_coordinates) . ")";
						}
					}
					break;

				default :
					if ( $is_in_AdmArea ) {
						if ( !$is_in_area && $reader->nodeType != 14 ) errorout( " Adm " . $reader->nodeType ."-". $reader->localName, INFO );
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
				case "name" :
					break;
				case "description" :
					break;

				case "AdmArea" :
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

					$is_in_AdmArea = false;
					$is_in_area = false;
					$is_in_exterior = false;
					$is_in_interior = false;
					$is_in_lfSpanFr = false;
					$is_in_devDate = false;
					$fid = "";
					$attributes = "";
					$geom = "";
					$exterior = "";
					$interior = "";
					$AdmArea_id = "";
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
				case "exterior" :
					$is_in_exterior = false;
					break;
				case "interior" :
					$is_in_interior = false;
					break;
				case "PolygonPatch" :
					$tmp = "";
					if ( !empty($exterior) ) {
						$tmp = $exterior;
					}
					if ( !empty($interior) ) {
						$tmp .= "," . $interior;
					}
					$geom = "SRID=4612;" . "POLYGON(" . $tmp . ")";
					$exterior = "";
					$interior = "";
					break;

				case "type" :
					break;
				case "admCode" :
					break;

				default :
					if ( $is_in_AdmArea ) {
						if ( !$is_in_area && $reader->nodeType != 14 ) errorout( " Adm " . $reader->nodeType ."-". $reader->localName, INFO );
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
