<?php

namespace slowfoot;

class store_memory {
    public $docs = [];

    // key: _id, value: [path_name => path]
    public $paths = [];
    // key: path, value: [_id, path_name]
    public $paths_rev = [];
    
    public function __construct() {

    }

    public function exists($collection, $id) {
        return isset($this->$collection[$id]);
    }

    public function get($collection, $id) {
        return $this->$collection[$id]??null;
    }

    public function add($collection, $id, $row) {
        $this->$collection[$id] = $row;
        return true;
    }

    public function update($collection, $id, $row) {
        $this->$collection[$id] = $row;
        return true;
    }

    public function add_ref($src_id, $src_prop, $dest) {
        $this->data[$src_id][$src_prop][] = ['_ref' => $dest];
    }

    public function path_exists($path){
        return isset($this->paths_rev[$path]);
    }
    public function path_add($path, $id, $name){
        $this->paths[$id][$name] = $path;
        $this->paths_rev[$path] = [$id, $name];
    }
    public function path_get($id, $name){
        return $this->paths[$id][$name];
    }

    public function path_get_props($path) {
        return $this->paths_rev[$path];
    }

}
