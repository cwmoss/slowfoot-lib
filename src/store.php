<?php

namespace slowfoot;

class store {
   
    // key: _id, value: [path_name => path]
    public $paths = [];
    // key: path, value: [_id, path_name]
    public $paths_rev = [];

    public $info = ['loaded' => [], 'rejected' => [], 'conflicts' => 0];
    public $config = [];
    public $conflicts = [];

    public $db;

    public function __construct($db, $config) {
        $this->db = $db;
        $this->config = $config;
    }

    public function has_data_on_create(){
        return $this->db->has_data_on_create();
    }

    public function query_sql($q, $params){
        return $this->db->query_sql($q, $params);
    }

    public function query($q, $order="", $limit=20){
        return $this->db->query($q, $order, $limit);
    }

    public function query_type($type){
        return $this->db->query_type($type);
    }

    public function data() {
        return $this->db->data();
    }

    public function get($id) {
        if (is_array($id)) {
            $id = $id['_id'];
        }
        return $this->db->get('docs', $id);
    }

    public function ref($id) {
        if (is_array($id)) {
            $id = $id['_ref'];
        }
        return $this->db->get('docs', $id);
    }

    public function add($id, $row) {
        if($this->db->exists("docs", $id)){
            return false;
        }
        $row['_id'] = $id;
        $this->db->add("docs", $id, $row);
        $this->info['loaded'][$row['_type']]++;
        $this->add_path($row);
        return true;
    }

    public function add_row($row) {
        return $this->add($row['_id'], $row);
    }

    public function update($id, $row) {
        if (!$this->db->exists("docs", $id)) {
            return false;
        }
        $row['_id'] = $id;
        return $this->db->update("docs", $id, $row);
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
        $this->db->add_ref($src_id, $src_prop, $dest);
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
            if($this->db->path_exists($path)){
                $this->conflict($path, $name, $row);
            } else {
                $this->db->path_add($path, $row['_id'], $name);
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
        return $this->db->path_get($id, $name);
    }

    public function get_by_path($path) {
        return $this->db->path_get_props($path);
    }

    public function rejected($type) {
        $this->info['rejected'][$type]++;
    }

    public function info(){
        return $this->db->info();
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
