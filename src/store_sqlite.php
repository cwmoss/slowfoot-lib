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
    public $info = ['loaded' => [], 'rejected' => [], 'conflicts' => 0];
    public $config = [];
    public $conflicts = [];

    public function __construct($config) {
        $this->config = $config;
        $db = $config['base'].'/slowfoot.db';
        $this->db = \ParagonIE\EasyDB\Factory::fromArray([
            "sqlite:$db"
        ]);
        $this->create_schema();
    }

    public function create_schema(){
        $ddl = "
CREATE TABLE IF NOT EXISTS docs (
    body TEXT,
    _id TEXT GENERATED ALWAYS AS (json_extract(body, '\$._id')) VIRTUAL NOT NULL,
    _type TEXT GENERATED ALWAYS AS (json_extract(body, '\$._type')) VIRTUAL NOT NULL,
    );
CREATE INDEX IF NOT EXISTS docs_id on docs(_id);
CREATE INDEX IF NOT EXISTS docs_type on docs(_type);
        ";
        return $this->db->run($ddl);
    }

    public function data() {
        return [];
    }

    public function get($id) {
        if (is_array($id)) {
            $id = $id['_id'];
        }
        return $this->_select_one($id);
    }

    public function ref($id) {
        if (is_array($id)) {
            $id = $id['_ref'];
        }
        return $this->_select_one($id);
    }

    function _select_one($id){
        return $this->db->cell('SELECT body from docs WHERE _id=?', $id);
    }

    function _exists($id){
        return $this->db->cell('SELECT count(_id) from docs WHERE _id=?', $id)?true:false;
    }

    function _update($id, $row){

    }
    
    public function add($id, $row) {
        if ($this->_exists($id)) {
            return false;
        }
        $row['_id'] = $id;
        $this->db->insert('docs', [
            'body' => \json_encode($row),
        ]);
        
        $this->add_path($row);
        return true;
    }

    public function add_row($row) {
        return $this->add($row['_id'], $row);
    }

    public function update($id, $row) {
        if (!$this->_exists($id)) {
            return false;
        }
        $row['_id'] = $id;
        $this->data[$id] = $row;
    }

    public function update_row($row) {
        return $this->update($row['_id'], $row);
    }

    public function add_ref($src_id, $src_prop, $dest) {
        if (is_array($src_id)) {
            $src_id = $src_id['_id'];
        }
        if (is_array($dest)) {
            $dest = $dest['_id'];
        }
        $this->data[$src_id][$src_prop][] = ['_ref' => $dest];
    }

    public function add_path($row) {
        //print ' type: ' . $row['_type'];
        // only, if we have a template for the type
        if (!$this->config[$row['_type']]) {
            return;
        }
        foreach ($this->config[$row['_type']] as $name => $conf) {
            //print_r($conf);
            $path = $conf['path']($row);
            if (isset($this->paths_rev[$path])) {
                $this->conflict($path, $name, $row);
            } else {
                $this->paths[$row['_id']][$name] = $path;
                $this->paths_rev[$path] = [$row['_id'], $name];
            }
        }
    }

    public function get_path($id, $name = null) {
        return PATH_PREFIX . $this->get_fpath($id, $name);
    }

    public function get_fpath($id, $name = null) {
        if (is_array($id)) {
            $id = $id['_id'];
        }
        if (!$name) {
            $name = '_';
        }
        return $this->paths[$id][$name];
    }

    public function get_by_path($path) {
        return $this->paths_rev[$path];
    }

    public function rejected($type) {
        $this->info['rejected'][$type]++;
    }

    private function conflict($path, $name, $row) {
        [$firstid, $firstname] = $this->get_by_path($path);
        $first = $this->get($firstid);

        $this->conflicts[] = [
            'path' => $path,
            'rev' => [$row['_id'], $name],
            'first' => [
                '_id' => $firstid,
                '_type' => $first['_type'],
                'name' => $firstname,
                'row' => $first
            ],
            'second' => [
                '_id' => $row['_id'],
                '_type' => $row['_type'],
                'name' => $name,
                'row' => $row
            ]
        ];
        $this->info['conflicts']++;
    }
}
