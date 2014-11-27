kiban2pgsql
================
基盤地図情報(GML)をPostGIS用のロード文に変換するもの。
一つのxmlファイルを読み込み、標準出力する。

必要な項目から徐々に実装していくつもり。

# Usage
    php kiban2pgsql.php [<options>] <xmlfile> [<schema>.]<table>

# Options

    -s [<from>:]<srid> Set the SRID field. Defaults to 0.
      Optionally reprojects from given SRID (cannot be used with -D).

    (-d|a|c|p) These are mutually exclusive options:
     -d  Drops the table, then recreates it and populates
         it with current shape file data.
     -a  Appends shape file into current table, must be
         exactly the same table schema.
     -c  Creates a new table and populates it, this is the
         default if you do not specify any options.
     -p  Prepare mode, only creates the table.
    
    -g <geocolumn> Specify the name of the geometry/geography column
      (mostly useful in append mode).


# 基盤地図情報
## ダウンロードサイト
http://www.gsi.go.jp/kiban/

# その他

# 履歴
2014-11-27 開始
　[基盤地図対応GDAL/OGR](http://www.osgeo.jp/foss4g-mext/)がGMLに対応していないので作り始めた

