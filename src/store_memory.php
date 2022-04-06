<?php

namespace slowfoot;

use function lolql\parse;
use function lolql\query as lquery;

class store_memory
{
    public $docs = [];

    // key: _id, value: [path_name => path]
    public $paths = [];
    // key: path, value: [_id, path_name]
    public $paths_rev = [];
    
    public function __construct()
    {
    }

    public function has_data_on_create()
    {
        return false;
    }

    public function query_sql($q, $params=[])
    {
        return [];
    }
    
    public function query_paginated($q, $limit_per_page, $params=[])
    {
        $all = lquery($this->docs, $q, $params);
        $total = count($all);
        $page_query = function ($page) use ($all, $limit_per_page) {
            $offset = ($page - 1) * $limit_per_page;
            $res = array_slice($all, $offset, $limit_per_page);
            return $res;
        };
        
        return [$total, $page_query];
    }

    public function query($q, $params=[])
    {
        $res = lquery($this->docs, $q, $params);
        return $res;
        return [$res, count($res)];
    }

    public function query_type($type)
    {
        $filter = ['_type' => $type];
        $rs = array_filter($this->docs, function ($row) use ($filter) {
            return evaluate($filter, $row);
        });
        return $rs;
    }

    public function exists($collection, $id)
    {
        return isset($this->$collection[$id]);
    }

    public function get($collection, $id)
    {
        return $this->$collection[$id]??null;
    }

    public function add($collection, $id, $row)
    {
        $this->$collection[$id] = $row;
        return true;
    }

    public function update($collection, $id, $row)
    {
        $this->$collection[$id] = $row;
        return true;
    }

    public function add_ref($src_id, $src_prop, $dest)
    {
        $this->data[$src_id][$src_prop][] = ['_ref' => $dest];
    }

    public function path_exists($path)
    {
        return isset($this->paths_rev[$path]);
    }
    public function path_add($path, $id, $name)
    {
        $this->paths[$id][$name] = $path;
        $this->paths_rev[$path] = [$id, $name];
    }
    public function path_get($id, $name)
    {
        return $this->paths[$id][$name];
    }

    public function path_get_props($path)
    {
        return $this->paths_rev[$path];
    }

    public function info()
    {
        $info = [];
        foreach($this->docs as $doc){
            if(!\key_exists($doc['_type'], $info)){
                $info[$doc['_type']] = ['_type' => $doc['_type'], 'total'=> 0];
            }
            $info[$doc['_type']]['total']++;
        }
        $paths = array_reduce($this->paths, function($res, $item){
            return $res+count($item);
        }, 0);
        $info[] = ['_type' => '__paths', 'total'=>$paths];
        return array_values($info);
    }
}
