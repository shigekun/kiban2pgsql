<?php
/**
 * edit_gazetteer.php
 * 地名辞典登録・編集処理
 *
 * @package 	webmap 道路基盤Webマッピングシステム
 * @version 	0.5
 * @copyright 	Copyright 2013 chodai.co.,ltd.
 * @author		**@Chuo Geomatics
 * @license		GPL
 *
 *
 * @see
 * @todo
 */
	// 共通ファイルのインクルード
	$pwd = getcwd();
	chdir("..");
	include "common/common.inc";
	chdir($pwd);

	$pref_name_code = array(
		"北海道" => "01","青森県" => "02","岩手県" => "03","宮城県" => "04","秋田県" => "05",
		"山形県" => "06","福島県" => "07","茨城県" => "08","栃木県" => "09","群馬県" => "10",
		"埼玉県" => "11","千葉県" => "12","東京都" => "13","神奈川県" => "14","新潟県" => "15",
		"富山県" => "16","石川県" => "17","福井県" => "18","山梨県" => "19","長野県" => "20",
		"岐阜県" => "21","静岡県" => "22","愛知県" => "23","三重県" => "24","滋賀県" => "25",
		"京都府" => "26","大阪府" => "27","兵庫県" => "28","奈良県" => "29","和歌山県" => "30",
		"鳥取県" => "31","島根県" => "32","岡山県" => "33","広島県" => "34","山口県" => "35",
		"徳島県" => "36","香川県" => "37","愛媛県" => "38","高知県" => "39","福岡県" => "40",
		"佐賀県" => "41","長崎県" => "42","熊本県" => "43","大分県" => "44","宮崎県" => "45",
		"鹿児島県" => "46","沖縄県" => "47"
	);

	// セッションをクローズして排他ロックを解除($_SESSIONへの書き込みをする場合は注意)
	session_write_close();

	set_time_limit(1*60*60);	// 3600s

	$user_no = $_SESSION["USER_NO"];
	if ( empty($user_no) ) {
		$ar_res = array(
				'stat'		=>	-999,			//ユーザーエラー
				'info'		=>	"wrong user",
		) ;
		echo  json_encode($ar_res) ;
		return;
	}
	$regist_timestamp = "'".date("Y/m/d H:i:s")."'";

	//DBIOクラス
	include_once("dbio.php");
	//テーブル構造定義クラス
	include_once("db_table.php");
	//DBIO 接続
	$cDBIO = new cls_DBIO();
	if ($cDBIO->connect(DATASOURCE,"","")== false){
		$ar_res = array(
				'stat'		=>	-1,			//接続エラー
				'info'		=>	$cDBIO->error_msg,
		) ;
		echo  json_encode($ar_res) ;

		return;
	}

	//パラメータの取り込み
	$json_string = file_get_contents('php://input');
	Log_debug('edit_gazetteer', $json_string);

	$Req = json_decode($json_string);
	$CMD = $Req->CMD ; 		//	"CMD" 	: "SELECT","ADD","REMOVE"

	//検索
	if ( $CMD == "SELECT"){
		$sWHERE = $Req->WHERE ;	// 条件
		$iORDER = $Req->ORDER ;	// ソート（1:昇順 -1:降順）

		Log_debug('edit_gazetteer:SELECT:sWHERE=', $sWHERE);

		//地名辞典の検索
		$ct_dictionarys = $cDBIO->select_t_dictionary($sWHERE , $iORDER );
		//対象がない場合
		if ( $ct_dictionarys == null ){
			$ar_res = array(
					'stat'		=>	0,
					'info'		=>	$cDBIO->error_msg,
					't_dictionarys' => (array)$ct_dictionarys
			) ;
			echo  json_encode($ar_res) ;
			return;
		}


		//返却リストにつめる
		$ar_res['t_dictionarys'] = (array)$ct_dictionarys;
		$ar_res['stat'] = 1  ;
		$ar_res['info'] = ''  ;

		echo  json_encode($ar_res) ;
		return ;
	}
	//削除
	else if ( $CMD == "REMOVE"){
		$sDICTIONARY_ID = $Req->DICTIONARY_ID ;	// 条件

		Log_debug('edit_symbol:REMOVE:sDICTIONARY_ID=', $sDICTIONARY_ID);

		//削除
		if( ! $cDBIO->delete_t_dictionary($sDICTIONARY_ID) ){
			$ar_res = array(
					'stat'		=>	-2,
					'info'		=>	$cDBIO->error_msg,
			) ;
			echo  json_encode($ar_res) ;
			return;
		}
		// これ以降の削除でエラーが起きるとゴミデータが残る
		// t_dictionarydataから削除
		if( $cDBIO->delete_t_dictionarydata($sDICTIONARY_ID) ){
			$cDBIO->execQuery("vacuum analyze t_dictionarydata;");
		}
		// t_addressから削除
		if( $cDBIO->delete_t_address($sDICTIONARY_ID) ){
			$res = rebuild_city_list($cDBIO);
			if ( $res ){
				// 成功
				$ret = true;
				$cDBIO->execQuery("vacuum analyze t_address;");
			} else {
				// 失敗
				Log_err('rebuild_city_list',"Error".$cDBIO->error_msg);
			}
		}
		// t_kpから削除
		if( $cDBIO->delete_t_kp($sDICTIONARY_ID) ){
			$cDBIO->execQuery("vacuum analyze t_kp;");
		}
		// t_postalcodeから削除
		if( $cDBIO->delete_t_postalcode_by_dictionary($sDICTIONARY_ID) ){
			$cDBIO->execQuery("vacuum analyze t_kp;");
		}

// delete from t_postalcode where dictionary_id = -9999
// delete from t_kp where dictionary_id = -9999
// delete from t_address where dictionary_id = -9999
// delete from t_dictionary where dictionary_id = -9999
// delete from t_dictionarydata where dictionary_id = -9999


		//返却リストにつめる
		$ar_res['stat'] = 0  ;
		$ar_res['info'] = ''  ;

		echo  json_encode($ar_res) ;
		return ;
	}
	//追加
	else if ( $CMD == "ADD"){
		$sFILENAME		= $Req->FILENAME ;	//サーバ上のファイルパス。（基準パスからの相対)
		$sKIND			= $Req->KIND ;		//GAZETTEER/ADDRESS/ZIPCODE/KMPOST

		Log_debug('edit_gazetteer:ADD:sFILENAME=', $sFILENAME);
		Log_debug('edit_gazetteer:ADD:sKIND=', $sKIND);

		$sFILENAME = mb_convert_encoding($sFILENAME,"sjis-win","UTF-8");

		//格納先
		$uploaddir = "";
		if ( $sKIND == "GAZETTEER" || $sKIND == "GAZETTEER_CSV"){
			$uploaddir = GAZETTEER_UPLOAD_FOLDER ;
		}
		else if ( $sKIND == "ADDRESS"){
			$uploaddir = ADDRESS_UPLOAD_FOLDER ;
		}
		else if ( $sKIND == "ZIPCODE"){
			$uploaddir = ZIPCODE_UPLOAD_FOLDER ;
		}
		else if ( $sKIND == "KMPOST"){
			$uploaddir = KMPOST_UPLOAD_FOLDER ;
		}

		$metadata = array("KIND" => $sKIND);

		$sql4copy = "";

		// 地名辞典
		if ( $sKIND == "GAZETTEER"){

			////////////////////////////////
			// dictionary_idの発行(共通化)
			$dictionary_id = nextDictionaryId($cDBIO);
			if ( $dictionary_id < 0 ) {
				$ar_res = array(
						'stat'		=>	-3,
						'info'		=>	"dictionary_id is not found",
				) ;
				echo  json_encode($ar_res) ;
				return;
			}

			$metadata["dictionary_id"] = $dictionary_id;
			$metadata["regist_timestamp"] = $regist_timestamp;
			$metadata["user_no"] = $user_no;

			////////////////////////////////
			// KMLをCSVへ加工(共通化出来ない)
			$uploadedfile = $uploaddir . "/" . $sFILENAME;
			$sql4copy = $uploaddir . "/" . $sFILENAME . ".sql";

			$KMLtoCSV = gazetteer_kml2csv($metadata,$uploadedfile,$sql4copy);


			$metadata = array_merge( $metadata,$KMLtoCSV);


			////////////////////////////////
			// COPYでDBへ登録(共通化)
			$copyedcount = processCOPY($sql4copy,$dictionary_id,$cDBIO,"t_dictionarydata");
			if ( $copyedcount < 0 ) {
				$ar_res = array(
						'stat'		=>	$copyedcount,
						'info'		=>	"COPY failture",
				) ;
				echo  json_encode($ar_res) ;
				return;
			}

		}
		// 住所
		else if ( $sKIND == "ADDRESS"){
			$sNAME = $Req->NAME;
			$sYOMI = $Req->YOMI;
			$sCOMMENT = $Req->COMMENT;
			$sPROVIDER = $Req->PROVIDER;
			$sDATE = $Req->DATE;
			$sLICENSE = $Req->LICENSE;

			$metadata = array_merge( $metadata, array(
				 "NAME" => $sNAME
				,"YOMI" => $sYOMI
				,"COMMENT" => $sCOMMENT
				,"PROVIDER" => $sPROVIDER
				,"DATE" => $sDATE
				,"LICENSE" => $sLICENSE
			) );


			Log_debug('edit_gazetteer:ADD:sNAME=', $sNAME);
			Log_debug('edit_gazetteer:ADD:sYOMI=', $sYOMI);
			Log_debug('edit_gazetteer:ADD:sCOMMENT=', $sCOMMENT);
			Log_debug('edit_gazetteer:ADD:sPROVIDER=', $sPROVIDER);
			Log_debug('edit_gazetteer:ADD:sDATE=', $sDATE);
			Log_debug('edit_gazetteer:ADD:sLICENSE=', $sLICENSE);

			////////////////////////////////
			// dictionary_idの発行(共通化)
			$dictionary_id = nextDictionaryId($cDBIO);
			if ( $dictionary_id < 0 ) {
				$ar_res = array(
						'stat'		=>	-3,
						'info'		=>	"dictionary_id is not found",
				) ;
				echo  json_encode($ar_res) ;
				return;
			}

			////////////////////////////////
			// CSVの加工(共通化出来ない)
			$uploadedfile = $uploaddir . "/" . $sFILENAME;

			// ファイルを開く(モード[r]の読み込み専用)
			if (! ($fp = fopen ( $uploadedfile, "r" ))) {
				//echo "ファイルが開けません。";
			} else {
				$sql4copy = $uploaddir . "/" . $sFILENAME . ".sql";
				// COPY文
				textout($sql4copy,"COPY t_address (pref,city,district,districtno,latitude,longitude,dictionary_id,dictionarydata_id,signup_user_no,signup_timestamp,cd_pref,renban,name) FROM stdin DELIMITER '\t';\n",true);
				// ファイルの読み込み(１行ずつファイルを読み込む)
				$linecount = 0;
				while (! feof ($fp)) {
					$line = fgets ($fp);
					// UTF8に変換
					$line = mb_convert_encoding($line,"UTF-8","sjis-win");
					// 行末の改行を削除
					$line = str_replace("\r","",str_replace("\n","",$line));
					// カンマ区切りを配列にする
					$ar_line = explode(",",$line);
					// ここでカラム数のチェック
					if ( $linecount == 0 ) {	// 1行目はヘッダー
						// 都道府県名,市区町村名,大字・町丁目名,街区符号・地番,座標系番号,Ｘ座標,Ｙ座標,緯度,経度,住居表示フラグ,代表フラグ,更新前履歴フラグ,更新後履歴フラグ
					} else {
						if ( count($ar_line) != 13 ) {	// フォーマットに合わせること
							Log_debug('edit_gazetteer:count($ar_line)=', count($ar_line));
							break;
						}
/*
 0 都道府県名
 1 市区町村名
 2 大字・町丁目名
 3 街区符号・地番
 4 座標系番号
 5 Ｘ座標
 6 Ｙ座標
 7 緯度
 8 経度
 9 住居表示フラグ
10 代表フラグ
11 更新前履歴フラグ
12 更新後履歴フラグ

t_address
(
$ar_line[0]  pref text, -- 都道府県名
$ar_line[1]  city text, -- 市町村名
$ar_line[2]  district text, -- 大字・町丁目名
$ar_line[3]  districtno text, -- 街区符号・番地
$ar_line[7]  latitude numeric(11,8) NOT NULL, -- 表示位置（緯度）　999.99999999
$ar_line[8]  longitude numeric(11,8) NOT NULL, -- 表示位置（経度）　999.99999999
$dictionary_id  dictionary_id integer NOT NULL, -- 地名辞典のID
$linecount  dictionarydata_id integer NOT NULL, -- 地理識別子のID
$user_no  signup_user_no integer, -- 登録・更新したユーザNo
$regist_timestamp  signup_timestamp timestamp with time zone, -- 登録・更新日
'00'  cd_pref character(2) NOT NULL, -- 都道府県コード（2ケタ固定）...
1  renban integer NOT NULL, -- 連番
$ar_line[0].$ar_line[1].$ar_line[2].$ar_line[3]  name text, -- 地名識別子の名称
)
*/
/*
update t_dictionarydata set name_2 =  translate(name,
'－０１２３４５６７８９ＡＢＣＤＥＦＧＨＩＪＫＬＭＮＯＰＱＲＳＴＵＶＷＸＹＺａｂｃｄｅｆｇｈｉｊｋｌｍｎｏｐｑｒｓｔｕｖｗｘｙｚ','-0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz')
*/
						// COPY用レコード
						$insert_line = "" . $ar_line[0]
							."\t". $ar_line[1]
							."\t". $ar_line[2]
							."\t". $ar_line[3]
							."\t". $ar_line[7]
							."\t". $ar_line[8]
							."\t". $dictionary_id
							."\t". $linecount
							."\t". $user_no
							."\t". $regist_timestamp
							."\t". $pref_name_code[$ar_line[0]]
							."\t". 1
							."\t". $ar_line[0].$ar_line[1].$ar_line[2].$ar_line[3]
						;
						// １レコード分の書き出し
						textout("{$sql4copy}","{$insert_line}\n",false);
					}
					$linecount++;

					// テスト用に中断
					//if ( $linecount > 10 ) break;
				}
				// 終了マーク
				textout("{$sql4copy}","\\.\n",false);
				// vacuum
				textout("{$sql4copy}","vacuum analyze t_address;\n",false);
				// ファイルを閉じる
				fclose ($fp);
			}

			////////////////////////////////
			// COPYでDBへ登録(共通化)
			$copyedcount = processCOPY($sql4copy,$dictionary_id,$cDBIO,"t_address");
			if ( $copyedcount < 0 ) {
				$ar_res = array(
						'stat'		=>	$copyedcount,
						'info'		=>	"COPY failture",
				) ;
				echo  json_encode($ar_res) ;
				return;
			}


		}
		// 距離標
		else if ( $sKIND == "KMPOST"){
			$sNAME = $Req->NAME;
			$sYOMI = $Req->YOMI;
			$sCOMMENT = $Req->COMMENT;
			$sPROVIDER = $Req->PROVIDER;
			$sDATE = $Req->DATE;
			$sLICENSE = $Req->LICENSE;

			$metadata = array_merge( $metadata, array(
				 "NAME" => $sNAME
				,"YOMI" => $sYOMI
				,"COMMENT" => $sCOMMENT
				,"PROVIDER" => $sPROVIDER
				,"DATE" => $sDATE
				,"LICENSE" => $sLICENSE
			) );

			Log_debug('edit_gazetteer:ADD:sNAME=', $sNAME);
			Log_debug('edit_gazetteer:ADD:sYOMI=', $sYOMI);
			Log_debug('edit_gazetteer:ADD:sCOMMENT=', $sCOMMENT);
			Log_debug('edit_gazetteer:ADD:sPROVIDER=', $sPROVIDER);
			Log_debug('edit_gazetteer:ADD:sDATE=', $sDATE);
			Log_debug('edit_gazetteer:ADD:sLICENSE=', $sLICENSE);

			////////////////////////////////
			// dictionary_idの発行(共通化)
			$dictionary_id = nextDictionaryId($cDBIO);
			if ( $dictionary_id < 0 ) {
				$ar_res = array(
						'stat'		=>	-3,
						'info'		=>	"dictionary_id is not found",
				) ;
				echo  json_encode($ar_res) ;
				return;
			}

			////////////////////////////////
			// CSVの加工(共通化出来ない)
			$uploadedfile = $uploaddir . "/" . $sFILENAME;

			// ファイルを開く(モード[r]の読み込み専用)
			if (! ($fp = fopen ( $uploadedfile, "r" ))) {
				//echo "ファイルが開けません。";
			} else {
				$sql4copy = $uploaddir . "/" . $sFILENAME . ".sql";
/*
CREATE TABLE t_kp
(
  chisei text, -- 地方整備局名
  jimusyo text, -- 事務所名
  cd_chisei character(2), -- 地整コード
  cd_jimusyo character(4), -- 事務所コード（地整コードを含む）
  cd_roadtype character(1), -- 道路種別
  roadno integer, -- 路線番号
  cd_currentoldnew character(1), -- 現旧新区分
  cd_updown character(1), -- 上下線コード
  kp numeric(4,1), -- 距離標
  latitude numeric(11,8) NOT NULL, -- 表示位置（緯度）　999.99999999
  longitude numeric(11,8) NOT NULL, -- 表示位置（経度）　999.99999999
  dictionary_id integer NOT NULL, -- 地名辞典のID
  dictionarydata_id integer NOT NULL, -- 地理識別子のID
  renban smallint NOT NULL, -- 連番
  name text, -- 地名識別子の名称
  signup_user_no integer, -- 登録・更新したユーザNo
  signup_timestamp timestamp with time zone, -- 登録・更新日
)
*/
/*
 0 地方整備局               81
 1 事務所                   8121
 2 道路種別                 1
 3 路線                     5
 4 現旧新区分               1
 5 上下区分                 3
 6 補助番号                 0
 7 地点標名称               280
 8 緯度（度）               43.08137431
 9 経度（度）               141.351018
 0 標高                     12.068
11 業務完了日               2008/1/21
12 データ登録日時           2008/3/5 13:28
*/
				// COPY文
				textout($sql4copy,"COPY t_kp (chisei, jimusyo, cd_chisei, cd_jimusyo, cd_roadtype, roadno, cd_currentoldnew, cd_updown, kp, latitude, longitude, dictionary_id, dictionarydata_id, renban, name, signup_user_no, signup_timestamp) FROM stdin DELIMITER '\t';\n",true);
				// ファイルの読み込み(１行ずつファイルを読み込む)
				$linecount = 0;
				while (! feof ($fp)) {
					$line = fgets ($fp);
					// UTF8に変換
					$line = mb_convert_encoding($line,"UTF-8","sjis-win");
					// 行末の改行を削除
					$line = str_replace("\r","",str_replace("\n","",$line));
					// カンマ区切りを配列にする
					$ar_line = explode(",",$line);
					// ここでカラム数のチェック
					if ( $linecount == 0 ) {	// 1行目はヘッダー
					} else {
						if ( count($ar_line) != 13 ) {	// フォーマットに合わせること
							Log_debug('edit_gazetteer:count($ar_line)=', count($ar_line));
							break;
						}

						// COPY用レコード
						$insert_line =
							"".    $ar_line[0]	// chisei text, -- 地方整備局名
							."\t". $ar_line[1]	// jimusyo text, -- 事務所名
							."\t". $ar_line[0]	// 地方整備局    cd_chisei character(2), -- 地整コード
							."\t". $ar_line[1]	// 事務所        cd_jimusyo character(4), -- 事務所コード（地整コードを含む）
							."\t". $ar_line[2]	// 道路種別      cd_roadtype character(1), -- 道路種別
							."\t". $ar_line[3]	// 路線          roadno integer, -- 路線番号
							."\t". $ar_line[4]	// 現旧新区分    cd_currentoldnew character(1), -- 現旧新区分
							."\t". $ar_line[5]	// 上下区分      cd_updown character(1), -- 上下線コード
							."\t". $ar_line[7]	// kp numeric(4,1), -- 距離標
							."\t". $ar_line[8]	// latitude numeric(11,8) NOT NULL, -- 表示位置（緯度）　999.99999999
							."\t". $ar_line[9]	// longitude numeric(11,8) NOT NULL, -- 表示位置（経度）　999.99999999
							."\t". $dictionary_id	// dictionary_id integer NOT NULL, -- 地名辞典のID
							."\t". $linecount	// dictionarydata_id integer NOT NULL, -- 地理識別子のID
							."\t". 1	// renban smallint NOT NULL, -- 連番
							."\t". "{$ar_line[0]},{$ar_line[1]},{$ar_line[2]},{$ar_line[3]},{$ar_line[4]},{$ar_line[5]},{$ar_line[7]}"	// name text, -- 地名識別子の名称
							."\t". $user_no	// signup_user_no integer, -- 登録・更新したユーザNo
							."\t". $regist_timestamp	// signup_timestamp timestamp with time zone, -- 登録・更新日
						;
						// １レコード分の書き出し
						textout("{$sql4copy}","{$insert_line}\n",false);
					}

					$linecount++;

					// テスト用に中断
					//if ( $linecount > 10 ) break;
				}
				// 終了マーク
				textout("{$sql4copy}","\\.\n",false);
				// vacuum
				textout("{$sql4copy}","vacuum analyze t_kp;\n",false);
				// ファイルを閉じる
				fclose ($fp);
			}

			////////////////////////////////
			// COPYでDBへ登録(共通化)
			$copyedcount = processCOPY($sql4copy,$dictionary_id,$cDBIO,"t_kp");
			if ( $copyedcount < 0 ) {
				$ar_res = array(
						'stat'		=>	$copyedcount,
						'info'		=>	"COPY failture",
				) ;
				echo  json_encode($ar_res) ;
				return;
			}
			////////////////////////////////
			// t_kpを更新
			if ( false === update_t_kp($cDBIO,$dictionary_id) ) {
				$ar_res = array(
						'stat'		=>	-4,
						'info'		=>	"update_t_kp failture",
				) ;
				echo  json_encode($ar_res) ;
				return;
			}
		}
		// 郵便番号
		else if ( $sKIND == "ZIPCODE"){
			$sNAME = $Req->NAME;
			$sYOMI = $Req->YOMI;
			$sCOMMENT = $Req->COMMENT;
			$sPROVIDER = $Req->PROVIDER;
			$sDATE = $Req->DATE;
			$sLICENSE = $Req->LICENSE;

			$metadata = array_merge( $metadata, array(
				 "NAME" => $sNAME
				,"YOMI" => $sYOMI
				,"COMMENT" => $sCOMMENT
				,"PROVIDER" => $sPROVIDER
				,"DATE" => $sDATE
				,"LICENSE" => $sLICENSE
			) );

			Log_debug('edit_gazetteer:ADD:sNAME=', $sNAME);
			Log_debug('edit_gazetteer:ADD:sYOMI=', $sYOMI);
			Log_debug('edit_gazetteer:ADD:sCOMMENT=', $sCOMMENT);
			Log_debug('edit_gazetteer:ADD:sPROVIDER=', $sPROVIDER);
			Log_debug('edit_gazetteer:ADD:sDATE=', $sDATE);
			Log_debug('edit_gazetteer:ADD:sLICENSE=', $sLICENSE);

			////////////////////////////////
			// dictionary_idの発行(共通化)
			$dictionary_id = nextDictionaryId($cDBIO);
			if ( $dictionary_id < 0 ) {
				$ar_res = array(
						'stat'		=>	-3,
						'info'		=>	"dictionary_id is not found",
				) ;
				echo  json_encode($ar_res) ;
				return;
			}

			////////////////////////////////
			// CSVの加工(共通化出来ない)
			$uploadedfile = $uploaddir . "/" . $sFILENAME;

			// 既存データ削除用の都道府県コード
			//$ar_kencode = array();	// キーに都道府県コードを2桁文字列で追加

			// ファイルを開く(モード[r]の読み込み専用)
			if (! ($fp = fopen ( $uploadedfile, "r" ))) {
				//echo "ファイルが開けません。";
			} else {
				$sql4copy = $uploaddir . "/" . $sFILENAME . ".sql";
				// COPY文
				textout($sql4copy,"COPY t_postalcode (cd_pref,city_code,postalcode,old_postalcode,pref_kana,city_kana,district_kana,pref,city,district,signup_user_no,signup_timestamp,dictionary_id,dictionarydata_id,renban,name) FROM stdin DELIMITER '\t';\n",true);
				// ファイルの読み込み(１行ずつファイルを読み込む)
				$linecount = 0;
				while (! feof ($fp)) {
					$line = fgets ($fp);
					// UTF8に変換
					$line = mb_convert_encoding($line,"UTF-8","sjis-win");
					// 行末の改行を削除
					$line = str_replace("\r","",str_replace("\n","",$line));
					// カンマ区切りを配列にする
					$ar_line = csv2array($line);
					// ここでカラム数のチェック
					if ( count($ar_line) != 15 ) {	// フォーマットに合わせること
						Log_debug('edit_gazetteer:count($ar_line)=', count($ar_line));
						break;
					}
/*

この郵便番号データファイルでは、以下の順に配列しています。
 0 全国地方公共団体コード(JIS X0401、X0402)………　半角数字
 1 (旧)郵便番号(5桁)………………………………………　半角数字
 2 郵便番号(7桁)………………………………………　半角数字
 3 都道府県名　…………　半角カタカナ(コード順に掲載)　(注1)
 4 市区町村名　…………　半角カタカナ(コード順に掲載)　(注1)
 5 町域名　………………　半角カタカナ(五十音順に掲載)　(注1)
 6 都道府県名　…………　漢字(コード順に掲載)　(注1,2)
 7 市区町村名　…………　漢字(コード順に掲載)　(注1,2)
 8 町域名　………………　漢字(五十音順に掲載)　(注1,2)
 9 一町域が二以上の郵便番号で表される場合の表示　(注3)　(「1」は該当、「0」は該当せず)
10 小字毎に番地が起番されている町域の表示　(注4)　(「1」は該当、「0」は該当せず)
11 丁目を有する町域の場合の表示　(「1」は該当、「0」は該当せず)
12 一つの郵便番号で二以上の町域を表す場合の表示　(注5)　(「1」は該当、「0」は該当せず)
13 更新の表示（注6）（「0」は変更なし、「1」は変更あり、「2」廃止（廃止データのみ使用））
14 変更理由　(「0」は変更なし、「1」市政・区政・町政・分区・政令指定都市施行、「2」住居表示の実施、「3」区画整理、「4」郵便区調整等、「5」訂正、「6」廃止(廃止データのみ使用))

class cls_t_postalcode {
	public $cd_pref			= "";	//"都道府県コード（2ケタ固定）※地方公共団体コードの上位2ケタを格納"
	public $city_code		= "";	//地方公共団体コード（5ケタ固定）
	public $postalcode		= "";	//郵便番号（7ケタ可変）
	public $old_postalcode	= "";	//旧郵便番号（7ケタ可変）
	public $pref_kana		= "";	//都道府県名（カナ）
	public $city_kana		= "";	//市町村名（カナ）
	public $district_kana	= "";	//町域名（カナ）
	public $pref			= "" ;	//都道府県名
	public $city			= "";	//市町村名
	public $district		= "";	//町域名
	public $signup_user_no	= 0;	//登録・更新したユーザNo
	public $signup_timestamp	= NULL;	//登録・更新日
	public $dictionary_id		= 0 ;	//地名辞典のID
	public $dictionarydata_id	= 0 ;	//地理識別子のID
	public $renban				= 0;	//連番
	public $name				= "";	//地名識別子の名称
}
*/
					// 既存データ削除用の都道府県コードチェック
					$kencode = substr($ar_line[0],0,2);
					//$ar_kencode[$kencode] = $ar_line[6];
					// COPY用レコード
					$insert_line = "" . substr($ar_line[0],0,2)	//"都道府県コード（2ケタ固定）※地方公共団体コードの上位2ケタを格納"
						."\t". $ar_line[0]	//地方公共団体コード（5ケタ
						."\t". $ar_line[2]	//郵便番号（7ケタ可変）
						."\t". $ar_line[1]	//旧郵便番号（7ケタ可変）
						."\t". $ar_line[3]	//都道府県名（カナ）
						."\t". $ar_line[4]	//市町村名（カナ）
						."\t". $ar_line[5]	//町域名（カナ）
						."\t". $ar_line[6]	//都道府県名
						."\t". $ar_line[7]	//市町村名
						."\t". $ar_line[8]	//町域名
						."\t". $user_no
						."\t". $regist_timestamp
						."\t". $dictionary_id
						."\t". $linecount
						."\t". 1
						."\t". $ar_line[6].$ar_line[7].$ar_line[8]
					;
					// １レコード分の書き出し
					textout("{$sql4copy}","{$insert_line}\n",false);

					$linecount++;

					// テスト用に中断
					//if ( $linecount > 10 ) break;
				}
				// 終了マーク
				textout("{$sql4copy}","\\.\n",false);
				// vacuum
				textout("{$sql4copy}","vacuum analyze t_postalcode;\n",false);

				// ファイルを閉じる
				fclose ($fp);
			}

			////////////////////////////////
			// COPYでDBへ登録(共通化)
			$copyedcount = processCOPY($sql4copy,$dictionary_id,$cDBIO,"t_postalcode");
			if ( $copyedcount < 0 ) {
				$ar_res = array(
						'stat'		=>	$copyedcount,
						'info'		=>	"COPY failture",
				) ;
				echo  json_encode($ar_res) ;
				return;
			}
			//$metadata["remove_kencode"] = $ar_kencode;

		}
		// 地名辞典(CSV)
		else if ( $sKIND == "GAZETTEER_CSV"){

			////////////////////////////////
			// dictionary_idの発行(共通化)
			$dictionary_id = nextDictionaryId($cDBIO);
			if ( $dictionary_id < 0 ) {
				$ar_res = array(
						'stat'		=>	-3,
						'info'		=>	"dictionary_id is not found",
				) ;
				echo  json_encode($ar_res) ;
				return;
			}

			$metadata["dictionary_id"] = $dictionary_id;
			$metadata["regist_timestamp"] = $regist_timestamp;
			$metadata["user_no"] = $user_no;

			////////////////////////////////
			// KMLをCSVへ加工(共通化出来ない)
			$uploadedfile = $uploaddir . "/" . $sFILENAME;
			$sql4copy = $uploaddir . "/" . $sFILENAME . ".sql";

			$CSV = gazetteer_makeCsv($metadata,$uploadedfile,$sql4copy, $Req->TIRI, $Req->IDO, $Req->KEIDO);

			$metadata["NAME"] = trim($Req->NAME);
			$metadata["YOMI"] = trim($Req->YOMI);
			$metadata["COMMENT"] = trim($Req->COMMENT);
			$metadata["PROVIDER"] = trim($Req->PROVIDER);
			$metadata["DATE"] = trim($Req->DATE);
			$metadata["LICENSE"] = trim($Req->LICENSE);
			$metadata = array_merge($metadata,$CSV);

			////////////////////////////////
			// COPYでDBへ登録(共通化)
			$copyedcount = processCOPY($sql4copy,$dictionary_id,$cDBIO,"t_dictionarydata");
			if ( $copyedcount < 0 ) {
				$ar_res = array(
						'stat'		=>	$copyedcount,
						'info'		=>	"COPY failture",
				) ;
				echo  json_encode($ar_res) ;
				return;
			}

		}

		// t_dictionarydataへコピー
		$ret = copy2dictionarydata($sKIND,$dictionary_id,$cDBIO);
		if ( $ret == false ) {
			$ar_res = array(
					'stat'		=>	-5,
					'info'		=>	"failture : copy to t_dictionarydata. KIND={$sKIND},dictionary_id={$dictionary_id}"
			) ;
			echo  json_encode($ar_res) ;
			return;
		}

		$metadata["dictionary_id"] = $dictionary_id;
		$metadata["count"] = $copyedcount;
		$metadata["regist_timestamp"] = $regist_timestamp;
		$metadata["user_no"] = $user_no;

		////////////////////////////////
		// クライアントへレスポンス
		Log_debug('edit_gazetteer', "dictionary_id={$dictionary_id}, count={$copyedcount}");
		$ar_res = array(
				'stat'		=>	$copyedcount,
				'info'		=>	"dictionary_id={$dictionary_id}, count={$copyedcount}",
				'metadata'	=> $metadata
		) ;
		echo  json_encode($ar_res) ;
		return;


	}
	else if ( $CMD == "ADD_COMMIT" ) {
		$metadata = $Req->metadata;
		// t_dictionaryへ追加
		$ret = commit_dictionary($metadata,$cDBIO);
		if ( $ret == false ) {
			$ar_res = array(
					'stat'		=>	-6,
					'info'		=>	"failture : commit_dictionary. " . var_export($metadata,true)
			) ;
			echo  json_encode($ar_res) ;
			return;
		}
		////////////////////////////////
		// クライアントへレスポンス
		Log_debug('edit_gazetteer', "commit_dictionary. ". var_export($metadata,true));
		$ar_res = array(
				'stat'		=>	0,
				'info'		=>	"commit_dictionary. ". var_export($metadata,true),
				'metadata'	=> $metadata
		) ;
		echo  json_encode($ar_res) ;
		return;

	}
	else{
		$ar_res = array(
				'stat'		=>	-99,			//コマンドエラー
				'info'		=>	"コマンドエラー",
		) ;
		echo  json_encode($ar_res) ;
	}
return;
// ここで本体は終了

// dictionary_idの発行
function nextDictionaryId($cDBIO) {
	Log_debug('edit_gazetteer', "dictionary_idの発行");
	$sql = "select nextval('t_dictionary_dictionary_id_seq') as nextval;";
	$res = $cDBIO->execQuery($sql);
	$ar_result = array();
	if ( $res ){
		while($result = $res->fetch(PDO::FETCH_ASSOC)){
			$ar_result[] = $result ;
		}
	} else {
		return -3;
	}
	//Log_debug('edit_gazetteer:dictionary_idの発行', var_export($ar_result,true));
	if ( isset($ar_result[0]) && isset($ar_result[0]["nextval"]) ) {
		$dictionary_id = $ar_result[0]["nextval"];
	} else {
		return -3;
	}
	Log_debug('edit_gazetteer:dictionary_idの発行', $dictionary_id);
	return $dictionary_id;
}

// COPYでDBへ登録(共通化)
function processCOPY($sql4copy,$dictionary_id,$cDBIO,$table) {
	$DATABASE_HOST = DATABASE_HOST;
	$DATABASE_PORT = DATABASE_PORT;
	$DATABASE_NAME = DATABASE_NAME;
	$DATABASE_USER = DATABASE_USER;
	$DATABASE_PASSWORD = DATABASE_PASSWORD;
	$PSQLEXE = PSQLEXE;

	$count = -4;

	if ( !empty($sql4copy)) {
		$sql4copy_ = str_replace("/","\\",$sql4copy);

		$bat_contents = <<<EOT
SET PGCLIENTENCODING=UTF8
set PGPASSWORD={$DATABASE_PASSWORD}
"{$PSQLEXE}" --set=PGCLIENTENCODING=UTF8 -h {$DATABASE_HOST} -p {$DATABASE_PORT} -U {$DATABASE_USER} -d {$DATABASE_NAME} -f "{$sql4copy_}"
exit
EOT;

		textout("{$sql4copy}.bat",$bat_contents,true);
/* TODO:必要かどうかを確認。多分不要
(1)コントロールパネル＞管理ツール＞サービスで、Apacheのサービスを右クリックし、プロパティを表示。
(2)「ログオン」タブで、ローカルシステムアカウントの「デスクトップとの対話をサービスに許可」にチェック。
(3)Apacheの再起動。
*/
		$ret = exec("cmd.exe /c \"{$sql4copy}.bat\"",$output);
		Log_debug('edit_gazetteer:COPY実行', $ret);
		//Log_debug('edit_gazetteer:COPY実行', var_export($output,true));

		// 追加した件数を取得
		$sql = "select count(*) as count from {$table} where dictionary_id={$dictionary_id};";
		$res = $cDBIO->execQuery($sql);
		$count = 0;
		if ( $res ){
			while($result = $res->fetch(PDO::FETCH_ASSOC)){
				$count = $result["count"] ;
			}
		} else {
		}

	} else {
	}
	return $count;
}

/*
update t_dictionarydata set name_2 =  translate(name,
'－０１２３４５６７８９ＡＢＣＤＥＦＧＨＩＪＫＬＭＮＯＰＱＲＳＴＵＶＷＸＹＺａｂｃｄｅｆｇｈｉｊｋｌｍｎｏｐｑｒｓｔｕｖｗｘｙｚ','-0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz'
)
*/
//
function copy2dictionarydata($type,$dictionary_id,$cDBIO) {

	$ret = false;
	$sql = "";
	switch ( $type ) {
	case "GAZETTEER" :
	case "GAZETTEER_CSV" :
		// ここでは何もしない
		$ret = true;
		break;
	case "ADDRESS" :
		$sql = "insert into t_dictionarydata (dictionary_id, dictionarydata_id, renban, "
			."name, "
			."geographic_extent, temporal_extent_s, temporal_extent_e, administrator,"
			."latitude, longitude,"
			."location_type, signup_user_no, signup_timestamp, the_geom) ";
		$sql .= " select "
			."dictionary_id,dictionarydata_id, renban, "
			."translate(name,'ー－―‐０１２３４５６７８９ＡＢＣＤＥＦＧＨＩＪＫＬＭＮＯＰＱＲＳＴＵＶＷＸＹＺａｂｃｄｅｆｇｈｉｊｋｌｍｎｏｐｑｒｓｔｕｖｗｘｙｚ','----0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz'), "
			."NULL,NULL,NULL,NULL, latitude, longitude, "
			."NULL, signup_user_no, signup_timestamp, "
			."ST_SetSRID(ST_MakePoint(longitude, latitude),4612) "
			." from t_address where dictionary_id = " . $dictionary_id;
		break;
	case "ZIPCODE" :
		$ret = true;
/*		検索対象外のため、登録不要
		$sql = "insert into t_dictionarydata (dictionary_id, dictionarydata_id, renban, name, geographic_extent, "
			."temporal_extent_s, temporal_extent_e, administrator,"
			."latitude, longitude,"
			."location_type, signup_user_no, signup_timestamp) ";
		$sql .= " select "
			."dictionary_id,dictionarydata_id, renban, name, NULL, "
			."NULL,NULL,NULL, NULL, NULL, "
			."NULL, signup_user_no, signup_timestamp"
			." from t_postalcode where dictionary_id = " . $dictionary_id;
*/
		break;
	case "KMPOST" :
		$sql = "insert into t_dictionarydata (dictionary_id, dictionarydata_id, renban, "
			."name, "
			."geographic_extent, temporal_extent_s, temporal_extent_e, administrator,"
			."latitude, longitude,"
			."location_type, signup_user_no, signup_timestamp, the_geom) ";
		$sql .= " select "
			."dictionary_id,dictionarydata_id, renban, "
			."name, "
			."NULL,NULL,NULL,NULL, latitude, longitude, "
			."NULL, signup_user_no, signup_timestamp, "
			."ST_SetSRID(ST_MakePoint(longitude, latitude),4612) "
			." from t_kp where dictionary_id = " . $dictionary_id;
		break;
	}
	if ( !empty($sql) ) {
		$res = $cDBIO->execQuery($sql);
		if ( $res ){
			// 成功
			$ret = true;
			// vacuum
			$cDBIO->execQuery("vacuum analyze t_dictionarydata;");
		} else {
			// 失敗
			Log_err('copy2dictionarydata',"Error({$type}:)".$cDBIO->error_msg);
		}
	}
	return $ret;
}

// t_kp(距離標)のいくつかの項目を更新する
// コードから文字列への変換
function update_t_kp($cDBIO,$dictionary_id) {
	$ret = false;
/*
update t_kp
set chisei = COALESCE((select trim(chisei_name) as name from cd_chisei where cd_chisei = t_kp.cd_chisei),'')
,jimusyo = COALESCE((select trim(jimusyo_name) as name from cd_jimusyo where cd_jimusyo = t_kp.cd_jimusyo),'')
,name = COALESCE((select trim(roadtype_name) as name from cd_roadtype where cd_roadtype = t_kp.cd_roadtype),'')
 || roadno || '号線'
 || ' ' || COALESCE((select trim(currentoldnew_name) as name from cd_currentoldnew where cd_currentoldnew = t_kp.cd_currentoldnew),'')
 || ' ' || COALESCE((select trim(updown_name) as name from cd_updown where cd_updown = t_kp.cd_updown),'')
 || ' ' || kp || 'KP'
where dictionary_id = 280
;
*/
	$sql = "";
	$sql .= "update t_kp";
	$sql .= " set chisei = COALESCE((select trim(chisei_name) as name from cd_chisei where cd_chisei = t_kp.cd_chisei),'')";
	$sql .= " ,jimusyo = COALESCE((select trim(jimusyo_name) as name from cd_jimusyo where cd_jimusyo = t_kp.cd_jimusyo),'')";
	$sql .= " ,name = COALESCE((select trim(roadtype_name) as name from cd_roadtype where cd_roadtype = t_kp.cd_roadtype),'')";
	$sql .= "  || roadno || '号'";
	$sql .= "  || ' ' || COALESCE((select trim(currentoldnew_name) as name from cd_currentoldnew where cd_currentoldnew = t_kp.cd_currentoldnew),'')";
	$sql .= "  || ' ' || COALESCE((select trim(updown_name) as name from cd_updown where cd_updown = t_kp.cd_updown),'')";
	$sql .= "  || ' ' || kp || ''";
	$sql .= " where dictionary_id = {$dictionary_id}";
	$sql .= " ;";

	if ( !empty($sql) ) {
		$res = $cDBIO->execQuery($sql);
		if ( $res ){
			// 成功
			$ret = true;
		} else {
			// 失敗
			Log_err('update_t_kp',"Error({$type}:)".$cDBIO->error_msg);
		}
	}
	return $ret;

}

// t_dictionaryへ
/*
stat:10
info:dictionary_id=140, count=10
metadata[KIND]:ADDRESS
metadata[NAME]:AAA
metadata[YOMI]:aaa
metadata[COMMENT]:aaa
metadata[PROVIDER]:aaa
metadata[DATE]://
metadata[LICENSE]:
metadata[dictionary_id]:140
metadata[count]:10
metadata[regist_timestamp]:'2014/02/06 21:11:31'
CMD:ADD_COMMIT*/
function commit_dictionary($metadata,$cDBIO) {

	$dictionary_type = "";
	switch ( $metadata->KIND ) {
	case "GAZETTEER" :
	case "GAZETTEER_CSV" :
		$dictionary_type = "地名辞典";
		break;
	case "ADDRESS" :
		$dictionary_type = "住所";
		break;
	case "ZIPCODE" :
		$dictionary_type = "郵便番号";
		break;
	case "KMPOST" :
		$dictionary_type = "距離標";
		break;
	}

	$ct_dictionary = new cls_t_dictionary() ;

	$ct_dictionary->dictionary_id = $metadata->dictionary_id ;			//地名辞典のID
	$ct_dictionary->name = $metadata->NAME;					//地名辞典の名称
	$ct_dictionary->scope = "" ;				//適用範囲
	$ct_dictionary->territory_of_use = "" ;		//使用領域
	$ct_dictionary->custodian = "" ;			//責任者
	$ct_dictionary->coodinate_system = 0 ;		//座標参照系 ※EPSGコードに準拠
	$ct_dictionary->date = !empty($metadata->updated_at) ? $metadata->updated_at : ($metadata->DATE == "//" ? null : $metadata->DATE);	//作成または更新した日付
	$ct_dictionary->lrs = 0 ;					//参照する空間参照系
	$ct_dictionary->alias = "" ;				//
	$ct_dictionary->signup_user_no = $metadata->user_no ;		//登録・更新したユーザNo
	//$ct_dictionary->signup_timestamp = !empty($metadata->updated_at) ? $metadata->updated_at : $metadata->regist_timestamp;	//登録・更新日
	$ct_dictionary->signup_timestamp = $metadata->regist_timestamp;	//登録・更新日
	$ct_dictionary->dictionary_type = $dictionary_type ;		//データ種別カラム（地名辞典、住所、郵便番号、距離標
	$ct_dictionary->record_count = $metadata->count ;  		//レコード数
	$ct_dictionary->description = $metadata->COMMENT ;			//データの説明
	$ct_dictionary->data_creator = $metadata->PROVIDER ;			//提供元
	$ct_dictionary->data_date = ($metadata->DATE == "//" ? null : $metadata->DATE) ;			//データ作成日(年月の場合もある)
	$ct_dictionary->license = $metadata->LICENSE ; 				//ライセンス
	$ct_dictionary->created_at = !empty($metadata->created_at) ? $metadata->created_at : $metadata->regist_timestamp ;			//登録日時(レコード登録日時)
	$ct_dictionary->kana = !empty($metadata->kana) ? $metadata->kana : "" ;			// kmlのkana
	$ct_dictionary->original_id = !empty($metadata->original_id) ? $metadata->original_id : "" ;			// kmlのoriginal_id
	$ct_dictionary->doc_name = !empty($metadata->doc_name) ? $metadata->doc_name : "" ;			// kmlのdoc_name

	$ret = false;
	$res = $cDBIO->insert_t_dictionary( array( $ct_dictionary ) );
	if ( $res ){
		// 成功
		$ret = true;
		// vacuum
		$cDBIO->execQuery("vacuum analyze t_dictionary;");

	} else {
		// 失敗
		Log_err('commit_dictionary','Error:'.$cDBIO->error_msg);
		return $ret;
	}

	switch ( $metadata->KIND ) {
	case "ADDRESS" :
		$res = rebuild_city_list($cDBIO);
		if ( $res ){
			// 成功
			$ret = true;
		} else {
			// 失敗
			Log_err('update t_city_list',"Error({$metadata->KIND}:)".$cDBIO->error_msg);
		}
		break;
	default :
		break;
	}

	return $ret;
}

function rebuild_city_list($cDBIO) {
	$ret = false;

	$sql1 = "truncate table t_city_list;";
	$sql2 = "insert into t_city_list (cd_pref,pref_name,city)";
	$sql2 .= "select distinct";
	$sql2 .= " cd_pref,pref,city";
	$sql2 .= " from t_address";
	$sql2 .= " where dictionary_id in (select distinct dictionary_id from t_dictionary )";
	$sql2 .= " order by pref,city";
	$sql2 .= ";";
	$sql3 = "vacuum analyze t_city_list;";
	if ( !empty($sql1) && !empty($sql2) && !empty($sql3) ) {
		$res = $cDBIO->begin();
		$res = $cDBIO->execQuery($sql1);
		$res = $cDBIO->execQuery($sql2);
		$res = $cDBIO->commit();
		if ( $res ){
			// 成功
			$cDBIO->execQuery($sql3);
			$ret = true;
		} else {
			// 失敗
			Log_err('update t_city_list',"Error({$metadata->KIND}:)".$cDBIO->error_msg);
		}
	}
	return $ret;
}


function gazetteer_kml2csv($metadata,$from_kml,$to_sql4copy) {

	$dst_metadata = $metadata;
	if (isset($php_errormsg)) unset($php_errormsg);

	if ( file_exists($from_kml) ) {
		$reader = new XMLReader();
		$reader->open( $from_kml );

		$ar_result = array();
		$ar_result["Placemark"] = array();
		$is_in_placemark = false;
		$is_in_extended_data = false;
		$ar_extended_data = array();
		$ar_placemark = array();
		$ar_coordinates = array();
		$document_name = "";
		$tmp_name = "";
		$tmp_data_name = "";
		$tmp_data_value = "";
		while (@$reader->read()) {
			//if ( $reader->nodeType != 14 ) error_log( " " . $reader->nodeType ."-". $reader->localName );

			switch ($reader->nodeType) {
			case (XMLREADER::ELEMENT):	// 開始タグ
				switch ( strtolower($reader->localName) ) {
				case "kml" :
					break;
				case "document" :
					break;
				case "extendeddata" :
					$is_in_extended_data = true;
					$ar_extended_data = array();
					break;
				case "placemark" :
					$is_in_placemark = true;
					$ar_placemark = array();
					$tmp_id = $reader->getAttribute("id");
					$ar_placemark["id"] = $tmp_id;
					break;

				case "name" :
					//error_log($reader->localName . var_export($reader->readString(),true));
					if ( $is_in_placemark ) {
						$tmp_name = $reader->readString();
						$ar_placemark["name"] = $tmp_name;
					} else {
						$document_name = $reader->readString();
						$ar_result["name"] = $document_name;
					}
					break;

				case "data" :
					//error_log($reader->localName . var_export($reader->getAttribute("name"),true));
					$tmp_data_name = $reader->getAttribute("name");
					break;
				case "value" :
					//error_log($reader->localName . var_export($reader->readString(),true));
					$tmp_data_value = $reader->readString();
					$ar_extended_data[$tmp_data_name] = $tmp_data_value;
					break;

				case "point" :
					break;
				case "coordinates" :
					$tmp = $reader->readString();
					$ar_coordinates = explode(",",$tmp);
					break;
				default :
					if ( $reader->nodeType != 14 ) error_log( " " . $reader->nodeType ."-". $reader->localName );
					break;
				}
				break;

			case (XMLREADER::END_ELEMENT):	// 終了タグ
				switch ( strtolower($reader->localName) ) {
				case "kml" :
					break;
				case "document" :
					break;
				case "extendeddata" :
					if ( $is_in_placemark ) {
						$ar_placemark["ExtendedData"] = $ar_extended_data;
					} else {
						$ar_result["ExtendedData"] = $ar_extended_data;
					}
					$is_in_extended_data = false;
					break;
				case "placemark" :
					$ar_result["Placemark"][] = $ar_placemark;
					$is_in_placemark = false;
					break;
				case "name" :
					break;
				case "data" :
					break;
				case "value" :
					break;
				case "point" :
					$ar_placemark["Point"] = $ar_coordinates;
					break;
				case "coordinates" :
					break;
				default :
					if ( $reader->nodeType != 14 ) error_log( " " . $reader->nodeType ."-". $reader->localName );
					break;
				}
				break;
			}

		}



		if (isset($php_errormsg) ) {
			//echo header("HTTP/1.1 503 Service Unavailable");
			$ar_res = array(
				'stat'		=>	-45,
				'info'		=>	"$php_errormsg",
			) ;
			echo  json_encode($ar_res) ;
			exit();
		}

		//error_log(var_export($ar_result,true));
		mb_regex_encoding('utf-8');
		$conv_ptn = "[ー－―‐]"; //全角でハイフン、ダッシュ、マイナス
		$conv_rep = "-"; //半角ハイフン

		// 配列からCOPY用SQLへ
		if ( !empty($ar_result) ) {
			// metadata(t_dictionary)
			//($ar_result["name"]);
			//($ar_result["ExtendedData"]);

			$dst_metadata = array_merge( $metadata, array(
				 "doc_name" => $ar_result["name"]
				,"original_id" => $ar_result["ExtendedData"]["id"]
				,"NAME" => $ar_result["ExtendedData"]["name"]
				,"YOMI" => $ar_result["ExtendedData"]["kana"]
				,"COMMENT" => $ar_result["ExtendedData"]["description"]
				,"PROVIDER" => $ar_result["ExtendedData"]["data_creator"]
				,"DATE" => $ar_result["ExtendedData"]["data_date"]
				,"LICENSE" => $ar_result["ExtendedData"]["license"]
				,'created_at' => $ar_result["ExtendedData"]["created_at"]
				,'updated_at' => $ar_result["ExtendedData"]["updated_at"]
				,'kana' => empty($ar_result["ExtendedData"]["kana"]) ? "" : $ar_result["ExtendedData"]["kana"]
			) );

			// ファイルの読み込み(１行ずつファイルを読み込む)
			$linecount = 0;

			// COPY文
			textout($to_sql4copy,"COPY t_dictionarydata (dictionary_id,dictionarydata_id,renban,name,geographic_extent,temporal_extent_s,temporal_extent_e,administrator,latitude,longitude,location_type,signup_user_no,signup_timestamp,the_geom,original_id,user_id,data_creator,data_date,license) FROM stdin DELIMITER '\t';\n",true);
			// data(t_dictionarydata)
			foreach ( $ar_result["Placemark"] as $Placemark ) {
				//($Placemark["name"]);
				//($Placemark["ExtendedData"]);
				//($Placemark["Point"]);


				// 半角英数に変換
				$Placemark["name"] = mb_convert_kana($Placemark["name"], "as", "UTF-8");
				$Placemark["name"] = mb_ereg_replace($conv_ptn,$conv_rep,$Placemark["name"]);
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
				textout("{$to_sql4copy}","{$insert_line}\n",false);
				$linecount++;

				// kanaがあった場合は、renban=2として追加
				if ( !empty($Placemark["ExtendedData"]["kana"]) ) {
					$Placemark["ExtendedData"]["kana"] = mb_convert_kana($Placemark["ExtendedData"]["kana"], "as", "UTF-8");
					$Placemark["ExtendedData"]["kana"] = mb_ereg_replace($conv_ptn,$conv_rep,$Placemark["ExtendedData"]["kana"]);
					$insert_line = "" . $metadata["dictionary_id"]	// dictionary_id
						."\t". $linecount	// dictionarydata_id
						."\t". 2	// renban
						."\t". $Placemark["ExtendedData"]["kana"]	// name
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
					textout("{$to_sql4copy}","{$insert_line}\n",false);
					$linecount++;
				}
			}
			// 終了マーク
			textout("{$to_sql4copy}","\\.\n",false);
			// vacuum
			textout("{$to_sql4copy}","vacuum analyze t_dictionarydata;\n",false);
		}
	}

	return $dst_metadata;
}

function gazetteer_makeCsv($metadata,$from_csv,$to_sql4copy, $tiri, $ido, $keido) {

	$dst_metadata = $metadata;
	if (isset($php_errormsg)) unset($php_errormsg);

	if ( file_exists($from_csv) ) {

		//error_log(var_export($ar_result,true));
		mb_regex_encoding('utf-8');
		$conv_ptn = "[ー－―‐]"; //全角でハイフン、ダッシュ、マイナス
		$conv_rep = "-"; //半角ハイフン

		// metadata(t_dictionary)
		//($ar_result["name"]);
		//($ar_result["ExtendedData"]);

		/*
		$dst_metadata = array_merge( $metadata, array(
			 "doc_name" => $ar_result["name"]
			,"original_id" => $ar_result["ExtendedData"]["id"]
			,"NAME" => $ar_result["ExtendedData"]["name"]
			,"YOMI" => $ar_result["ExtendedData"]["kana"]
			,"COMMENT" => $ar_result["ExtendedData"]["description"]
			,"PROVIDER" => $ar_result["ExtendedData"]["data_creator"]
			,"DATE" => $ar_result["ExtendedData"]["data_date"]
			,"LICENSE" => $ar_result["ExtendedData"]["license"]
			,'created_at' => $ar_result["ExtendedData"]["created_at"]
			,'updated_at' => $ar_result["ExtendedData"]["updated_at"]
			,'kana' => empty($ar_result["ExtendedData"]["kana"]) ? "" : $ar_result["ExtendedData"]["kana"]
		) );
		*/
		$dst_metadata = array_merge( $metadata, array(
			 "doc_name" => ""// ？
			,"original_id" => ""// ？
			,'created_at' => ""// ？
			,'updated_at' => "'".date("Y/m/d")."'"
			,'kana' => ""// ？
		) );
		// COPY文
		textout($to_sql4copy,"COPY t_dictionarydata (dictionary_id,dictionarydata_id,renban,name,geographic_extent,temporal_extent_s,temporal_extent_e,administrator,latitude,longitude,location_type,signup_user_no,signup_timestamp,the_geom,original_id,user_id,data_creator,data_date,license) FROM stdin DELIMITER '\t';\n",true);
		// data(t_dictionarydata)

		$fp = fopen($from_csv,"r");
		// 先頭カラムを読み込んでおく
		$data = fgetcsv($fp);
		mb_language("Japanese");
		$linecount = 1;
		while($data = fgetcsv($fp)) {
			$name = $data[$tiri];
			$name = mb_convert_encoding($name, "UTF-8", "auto");
			$name = mb_convert_kana($name, "as", "UTF-8");
			$name = mb_ereg_replace($conv_ptn,$conv_rep,$name);

			// ファイルの読み込み(１行ずつファイルを読み込む)

			// COPY用レコード
			$insert_line = "" . $metadata["dictionary_id"]	// dictionary_id
				."\t". $linecount	// dictionarydata_id
				."\t". 1	// renban
				."\t". $name// 地理識別子？ $Placemark["name"]	// name
				."\t". "\\N"	// geographic_extent
				."\t". "\\N"	// temporal_extent_s
				."\t". "\\N"	// temporal_extent_e
				."\t". "\\N"	// administrator
				."\t". $data[$ido]	// latitude
				."\t". $data[$keido]	// longitude
				."\t". "\\N"	// location_type
				."\t". $metadata["user_no"]	// signup_user_no
				."\t". $metadata["regist_timestamp"]	// signup_timestamp
				."\t". "SRID=4612;POINT(".$data[$keido]." ".$data[$ido].")"	// the_geom
				."\t". ""// ？ $Placemark["id"]	// original_id
				."\t". "\\N"// ？ (($Placemark["ExtendedData"]["user_id"] === "") ? "\\N" : $Placemark["ExtendedData"]["user_id"])	// user_id
				."\t". ""// ？ $Placemark["ExtendedData"]["data_creator"]	// data_creator
				."\t". ""// ？ $Placemark["ExtendedData"]["data_date"]	// data_date
				."\t". ""// ？ $Placemark["ExtendedData"]["license"]	// license
			;
			// １レコード分の書き出し
			textout("{$to_sql4copy}","{$insert_line}\n",false);

			$linecount++;
			// カナはない？

		}
		// 終了マーク
		textout("{$to_sql4copy}","\\.\n",false);
		// vacuum
		textout("{$to_sql4copy}","vacuum analyze t_dictionarydata;\n",false);
	}
	return $dst_metadata;
}

