DROP TABLE IF EXISTS kiban_data;
CREATE TABLE kiban_data (
  gid bigserial,
  fid text NOT NULL,
  feature_type text NOT NULL,
  geom geometry(Geometry,4612) NOT NULL,
  attributes text,
  data_date date NOT NULL,
  meshcode text NOT NULL,
  CONSTRAINT kiban_data_pkey PRIMARY KEY ( gid )
);
CREATE INDEX kiban_data_gidx ON kiban_data USING GIST ( geom );
CREATE INDEX kiban_data_idx1 ON kiban_data ( fid );
CREATE INDEX kiban_data_idx2 ON kiban_data ( feature_type );
CREATE INDEX kiban_data_idx3 ON kiban_data ( meshcode );
