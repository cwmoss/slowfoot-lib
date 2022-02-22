<?php
/*

CREATE VIRTUAL TABLE docs_fts USING fts5( _id, btext);

INSERT INTO docs_fts(_id, btext)
    SELECT _id, group_concat(b.key || ': ' ||  b.value, x'0a') as btext from docs, json_tree(body) b where b.atom not null group by _id;

*/
namespace slowfoot;

class store_sqlite
{
    public $data = [];

    // key: _id, value: [path_name => path]
    public $paths = [];
    // key: path, value: [_id, path_name]
    public $paths_rev = [];
    public $config = [];
    public $was_filled = false;

    public function __construct($config)
    {
        $this->config = $config;
        $adapter = explode(':', $config['adapter']);
        $name = $adapter[1]??($adapter['name'])??'slowfoot.db';
        if ($name=='memory') {
            $name = ':memory:';
        } else {
            if ($name[0]!='/') {
                $name = $config['base'].'/'.$name;
            }
            $this->was_filled = \file_exists($name);
        }
        $this->db = \ParagonIE\EasyDB\Factory::fromArray([
            "sqlite:$name"
        ]);
        $this->create_schema();
    }

    public function has_data_on_create()
    {
        return $this->was_filled;
    }

    public function create_schema()
    {
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
        foreach ($statements as $ddl_s) {
            if (trim($ddl_s)) {
                $this->db->run($ddl_s);
            }
        }
        return ;
    }

    public function query_sql($q, $params=[])
    {
        $res = $this->db->safeQuery($q, $params);
        dbg("[sqlite] query_sql", $q, $params);
        #var_dump($q);
        #var_dump($res);
        $res = array_map(function ($r) {
            return json_decode($r['body'], true);
        }, $res);
        return $res;
        return [[], 0];
        $res = lquery($this->docs, $q);
        return [$res, count($res)];
    }

    public function query_paginated($q, $limit_per_page, $params=[])
    {
        $query = \lolql\parse($q, $params);
        $fn = \lolql\eval_cond_as_sql_function($query['q']);
        $name = 'lolql_'.bin2hex(\random_bytes(8));
        $pdo = $this->db->getPdo();
        $pdo->sqliteCreateFunction($name, $fn, 1);
        $q = 'SELECT body from docs WHERE '.$name.'(body)';
        $order = $this->build_order($query['order_raw']);
        if ($order) {
            $q.=' ORDER BY '.$order;
        }
        $q_count = 'SELECT count(*) from docs WHERE '.$name.'(body)';
        $total = $this->db->cell($q_count);
        $db = $this->db;
        $page_query = function ($page) use ($db, $q, $total, $limit_per_page) {
            $off = ($page - 1) * $limit_per_page;
            $q .= " LIMIT {$limit_per_page} OFFSET $off";
            $res = $this->db->run($q);
            $res = array_map(function ($r) {
                return json_decode($r['body'], true);
            }, $res);
            return $res;
        };
        return [$total, $page_query];
    }

    public function query($q, $params)
    {
        $query = \lolql\parse($q, $params);
        $fn = \lolql\eval_cond_as_sql_function($query['q']);
        $name = 'lolql_'.bin2hex(\random_bytes(8));
        #$name = 'lolq';
        $pdo = $this->db->getPdo();
        $pdo->sqliteCreateFunction($name, $fn, 1);
        $q = 'SELECT body from docs WHERE '.$name.'(body)';
        $order = $this->build_order($query['order_raw']);
        if ($order) {
            $q.=' ORDER BY '.$order;
        }
        if ($query['limit']['limit']) {
            $q.=" LIMIT {$query['limit']['limit']}";
            if ($query['limit']['offset']) {
                $q.=" OFFSET {$query['limit']['offset']}";
            }
        }
        dbg("[store sqlite] query", $q, $query['order_raw'], $query['limit'], $query['limit_raw']);
        $res = $this->db->run($q);
        $res = array_map(function ($r) {
            return json_decode($r['body'], true);
        }, $res);
        return $res;
        return [[], 0];
        $res = lquery($this->docs, $q);
        return [$res, count($res)];
    }

    public function build_order($o=[])
    {
        if (!$o) {
            return "";
        }
        $sql = [];
        foreach ($o as $order) {
            $sql[]=$this->propname($order['k']).' '.$order['d'];
        }
        return join(", ", $sql);
    }
    public function propname($n)
    {
        $name = sprintf(
            "json_extract(body, '\$.%s')",
            $n
        );
        return $name;
    }

    public function query_type($type)
    {
        $res = $this->db->run("select body from docs WHERE _type=?", $type);
        $res = array_map(function ($r) {
            return json_decode($r['body'], true);
        }, $res);
        #var_dump($res);
        return $res;
        $filter = ['_type' => $type];
        $rs = array_filter($this->docs, function ($row) use ($filter) {
            return evaluate($filter, $row);
        });
        return $rs;
    }

    public function exists($collection, $id)
    {
        return $this->db->cell('SELECT count(_id) from docs WHERE _id=?', $id)?true:false;
    }

    public function get($collection, $id)
    {
        return $this->_select_one($id);
    }

    public function add($collection, $id, $row)
    {
        $this->db->insert('docs', [
            'body' => \json_encode($row),
        ]);
        return true;
    }

    public function update($collection, $id, $row)
    {
        $this->db->update('docs', [
            'body' => \json_encode($row),
        ], [
            '_id' => $id
        ]);
        return true;
    }

    public function add_ref($src_id, $src_prop, $dest)
    {
        //    $this->data[$src_id][$src_prop][] = ['_ref' => $dest];
        $row = $this->get('docs', $src_id);
        $row[$src_prop][] = ['_ref' => $dest];
        $this->update('docs', $row['_id'], $row);
    }

    public function path_exists($path)
    {
        return $this->db->cell('SELECT count(id) from paths WHERE path=?', $path)?true:false;
    }

    public function path_add($path, $id, $name)
    {
        $this->db->insert('paths', [
            'path' => $path,
            'id' => $id,
            'name' => $name
        ]);
        return true;
    }
    public function path_get($id, $name)
    {
        $p = $this->db->cell('SELECT path from paths WHERE id=? AND name=?', $id, $name);
        return $p;
    }

    public function path_get_props($path)
    {
        $p = $this->db->row('SELECT id,name from paths WHERE path=?', $path);
        return [$p['id'], $p['name']];
    }

    public function _select_one($id)
    {
        return json_decode($this->db->cell('SELECT body from docs WHERE _id=?', $id), true);
    }

    public function info()
    {
        $types = $this->db->run('SELECT _type, count(*) AS total FROM docs GROUP BY _type');
        $routes = $this->db->run("SELECT '__paths' as _type, count(*) AS total FROM paths");
        return array_merge($types, $routes);
    }
}
