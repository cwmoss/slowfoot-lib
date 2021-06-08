<?php

namespace slowfoot;

class store {
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
    }

    public function data() {
        return $this->data;
    }

    public function get($id) {
        if (is_array($id)) {
            $id = $id['_id'];
        }
        return $this->data[$id];
    }

    public function ref($id) {
        if (is_array($id)) {
            $id = $id['_ref'];
        }
        return $this->data[$id];
    }

    public function add($id, $row) {
        if (isset($this->data[$id])) {
            return false;
        }
        $row['_id'] = $id;
        $this->data[$id] = $row;
        $this->info['loaded'][$row['_type']]++;
        $this->add_path($row);
        return true;
    }

    public function add_row($row) {
        return $this->add($row['_id'], $row);
    }

    public function update($id, $row) {
        if (!isset($this->data[$id])) {
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
