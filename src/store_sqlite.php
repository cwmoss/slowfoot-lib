<?php
/*

CREATE VIRTUAL TABLE docs_fts USING fts5( _id, btext);

INSERT INTO docs_fts(_id, btext)
    SELECT _id, group_concat(b.key || ': ' ||  b.value, x'0a') as btext from docs, json_tree(body) b where b.atom not null group by _id;

*/
namespace slowfoot;

class store_sqlite {
    public $data = [];

    // key: _id, value: [path_name => path]
    public $paths = [];
    // key: path, value: [_id, path_name]
    public $paths_rev = [];
    public $config = [];
    public $was_filled = false;

    public function __construct($config) {
        $this->config = $config;
        $adapter = explode(':', $config['adapter']);
        $name = $adapter[1]??($adapter['name'])??'slowfoot.db';
        if($name=='memory'){
            $name = ':memory:';
        }else{
            if($name[0]!='/'){
                $name = $config['base'].'/'.$name;
            }
            $this->was_filled = \file_exists($name);
        }
        $this->db = \ParagonIE\EasyDB\Factory::fromArray([
            "sqlite:$name"
        ]);
        $this->create_schema();
    }

    public function has_data_on_create(){
        return $this->was_filled;
    }

    public function create_schema(){
        $ddl = "
CREATE TABLE IF NOT EXISTS docs (
    body JSON,
    _id TEXT GENERATED ALWAYS AS (json_extract(body, '$._id'))
        VIRTUAL
        NOT NULL
        UNIQUE ON CONFLICT REPLACE,
    _type TEXT GENERATED ALWAYS AS (json_extract(body, '$._type'))
        VIRTUAL
        NOT NULL
    );
CREATE INDEX IF NOT EXISTS docs_id on docs(_id);
CREATE INDEX IF NOT EXISTS docs_type on docs(_type);
CREATE TABLE IF NOT EXISTS paths (
    path TEXT NOT NULL,
    id TEXT NOT NULL,
    name TEXT DEFAULT '_' NOT NULL
    );
CREATE INDEX IF NOT EXISTS paths_path on paths(path);
CREATE INDEX IF NOT EXISTS paths_id on paths(id);
        ";
        $statements = explode(';', $ddl);
        #print $ddl;
        foreach($statements as $ddl_s){
            if(trim($ddl_s))            $this->db->run($ddl_s);
        }
        return ;
    }

    public function query_sql($q, $params=[]){
        $res = $this->db->safeQuery($q, $params);
        #var_dump($q);
        #var_dump($res);
        $res = array_map(function($r){return json_decode($r['body'], true);}, $res);
        return $res;
        return [[], 0];
        $res = lquery($this->docs, $q);
        return [$res, count($res)];
    }

    public function query($q, $limit){
        return [[], 0];
        $res = lquery($this->docs, $q);
        return [$res, count($res)];
    }

    public function query_type($type){
        $res = $this->db->run("select body from docs WHERE _type=?", $type);
        $res = array_map(function($r){return json_decode($r['body'], true);}, $res);
        #var_dump($res);
        return $res;
        $filter = ['_type' => $type];
        $rs = array_filter($this->docs, function ($row) use ($filter) {
            return evaluate($filter, $row);
        });
        return $rs;
    }

    function exists($collection, $id){
        return $this->db->cell('SELECT count(_id) from docs WHERE _id=?', $id)?true:false;
    }

    public function get($collection, $id) {
        return $this->_select_one($id);
    }

    public function add($collection, $id, $row) {
        $this->db->insert('docs', [
            'body' => \json_encode($row),
        ]);
        return true;
    }

    public function update($collection, $id, $row) {
        $this->db->update('docs', [
            'body' => \json_encode($row),
        ], [
            '_id' => $id
        ]);
        return true;
    }

    public function add_ref($src_id, $src_prop, $dest) {
    //    $this->data[$src_id][$src_prop][] = ['_ref' => $dest];
    }

    public function path_exists($path){
        return $this->db->cell('SELECT count(id) from paths WHERE path=?', $path)?true:false;
    }

    public function path_add($path, $id, $name){
        $this->db->insert('paths', [
            'path' => $path,
            'id' => $id,
            'name' => $name
        ]);
        return true;
    }
    public function path_get($id, $name){
        $p = $this->db->cell('SELECT path from paths WHERE id=? AND name=?', $id, $name);
        return $p;
    }

    public function path_get_props($path) {
        $p = $this->db->cell('SELECT id,name from paths WHERE path=?', $path);
        return [$p['id'], $p['name']];
    }

    function _select_one($id){
        return json_decode($this->db->cell('SELECT body from docs WHERE _id=?', $id), true);
    }


}
