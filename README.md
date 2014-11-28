kiban2pgsql
================
基盤地図情報(GML)をPostGIS用のロード文に変換するもの。

一つのxmlファイルを読み込み、標準出力する。

必要な項目から徐々に実装していくつもり。

# 仕様

テーブル

| 名前           | データ型                 | NULL ?   | default  | 説明            |
|:--------------|:-----------------------:|:--------:|:--------:|:--------------:|
| gid           | bigserial               | NOT NULL | シーケンス |                |
| fid           | text                    | NOT NULL |          | fid            |
| feature_type  | text                    | NOT NULL |          | 地物の種類       |
| geom          | Geometry(GEOMETRY,4612) | NOT NULL |          | 図形            |
| attributes    | text                    | NULL     | <attributes></attributes> | 属性(XML文字列)  <attributes>...</attributes> |
| data_date     | date                    | NOT NULL |          | データ整備の年月日 |
| mesh          | text                    | NOT NULL |          | 2次メッシュコード |


# 地物の種類
* 済
 - 
* 未
 - AdminArea
 - AdminPt
 - AdminBdry


# Usage
    php kiban2pgsql.php [<options>] <xmlfile> [<schema>.]<table>

# Options

    (-d|a|c|p) These are mutually exclusive options:
     -d  Drops the table, then recreates it and populates it with current shape file data.
     -a  Appends shape file into current table, must be exactly the same table schema.
     -c  Creates a new table and populates it, this is the default if you do not specify any options.
     -p  Prepare mode, only creates the table.
    



# 基盤地図情報
## ダウンロードサイト
http://www.gsi.go.jp/kiban/

# その他

# 履歴
2014-11-27 開始
　[基盤地図対応GDAL/OGR](http://www.osgeo.jp/foss4g-mext/)がGMLに対応していないので作り始めた

