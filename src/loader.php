<?php

namespace slowfoot;

class loader {

    public function __construct(public configuration $config) {
    }

    public function load() {
        $db = $this->config->get_store();
        $db_store = get_class($db->db);
        $onload = $this->config->hooks['on_load'] ?? null;
        # TODO fetch or not
        if ($db->has_data_on_create()) {
            shell_info("store {$db_store} using old data", true);
            return $db;
        }

        shell_info("fetching data {$db_store}", true);

        foreach ($this->config->sources as $name => $loader) {
            $opts = ['loader' => $name, 'type' => $name, 'name' => $name];

            $fun = $loader(...);

            shell_info("fetching $name");

            foreach ($fun($this->config) as $row) {
                if (!isset($row['_type']) || !$row['_type']) {
                    #print_r($row);
                    $row['_type'] = $opts['type'];
                }
                $otype = $row['_type'];
                $row['_src'] = $name;
                if ($onload) {
                    $row = $onload($row, $db);
                }
                if (!$row) {
                    $db->rejected($otype);
                } else {
                    if (!$row['_id']) {
                        $row['_id'] = $row['id'];
                    }
                    // $row['_id'] = str_replace('/', '-', $row['_id']);
                    $db->add($row['_id'], $row);
                }
            }
            shell_info();
        }
        return $db;
    }
}
