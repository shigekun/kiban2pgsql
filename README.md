kiban2pgsql
- - -
================
基盤地図情報(GML)をPostGIS用のロード文に変換するもの。

一つのxmlファイルを読み込み、標準出力する。

必要な項目から徐々に実装していくつもり。

# ソース

https://github.com/shigekun/kiban2pgsql

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
| meshcode      | text                    | NOT NULL |          | 2次メッシュコード |


# 地物の種類
* 済
 - AdminArea
 - AdminBdry
* 未
 - AdminPt


# Usage
    php kiban2pgsql.php [<options>] <xmlfile> [<schema>.]<table>

# Options

    (-d|a|c|p) These are mutually exclusive options:
     -d  Drops the table, then recreates it.
     -a  Appends into current table, must be exactly the same table schema.
     -c  Creates a new table and populates it, this is the default if you do not specify any options.
     -p  Prepare mode, only creates the table.
    
     -f  Force Drop the table.



# 基盤地図情報
## ダウンロードサイト
http://www.gsi.go.jp/kiban/

## 問題点
### AdmArea(市町村)ポリゴンがない
654240
### AdmAreaポリゴンが交差
492937
523241
553812
604172

# その他

MEMO
createdb -p 5433 -O postgres -T template0 -E UTF8 kibanmapdb
psql -p 5433 -d kibanmapdb -c "create extension postgis"
psql -p 5433 -d kibanmapdb -c "create extension postgis_topology"
psql -p 5433 -d kibanmapdb -f /usr/local/pgsql-9.2.2/share/contrib/postgis-2.0/legacy.sql
psql -p 5433 -f /usr/local/src/postgresql-9.2.2/contrib/postgis-2.0.2/doc/postgis_comments.sql kibanmapdb
psql -p 5433 -d kibanmapdb -c "update spatial_ref_sys set proj4text=replace(proj4text,'-148,507,685,0,0,0,0','-146.414,507.337,680.507,0,0,0,0') where srtext like '%-148,507%';"
psql -p 5433 -d kibanmapdb -c "update spatial_ref_sys set srtext=replace(srtext,'-148,507,685,0,0,0,0','-146.414,507.337,680.507,0,0,0,0') where srtext like '%-148,507%';"

psql -h spatialsv02 -p 5433 -d kibanmapdb -f sql/createtable.sql

php php/kiban2pgsql.php sample/FG-GML-362442-AdmArea-20141001-0001.xml -a > test.log
psql -h spatialsv02 -p 5433 -d kibanmapdb -f test.log
psql -h spatialsv02 -p 5433 -d kibanmapdb -c "select * from kiban_data order by gid desc limit 1"

for i in xml/*AdmArea*.xml ;do php kiban2pgsql.php $i -a > test.log; done
for i in `find -L . -name "*AdmArea*"` ; do php kiban2pgsql.php $i -a >> test.log ; done
psql -h spatialsv02 -p 5433 -d kibanmapdb -f test.log
psql -h spatialsv02 -p 5433 -d kibanmapdb -c "vacuum full analyze;"


# 履歴
2014-11-28 AdmBdry
2014-11-28 AdmArea

2014-11-27 開始
　[基盤地図対応GDAL/OGR](http://www.osgeo.jp/foss4g-mext/)がGMLに対応していないので作り始めた

