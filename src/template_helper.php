<?php

function load_template_helper($ds, $paths, $src) {
    return [
        'path' => function ($oid) use ($paths) {
            //print "-- $oid";
            return path($paths, $oid);
        },
        'get' => function ($oid) use ($ds) {
            return get($ds, $oid);
        },
        'ref' => function ($oid) use ($ds) {
            return ref($ds, $oid);
        },
        'query' => function ($q) use ($ds) {
            //print "-- $oid";
            return query($ds, $q);
        },
        'partial' => function ($template, $data) use ($src) {
            //dbg('+++ partial src', $src);
            return partial($src, $template, $data, []);
        }
    ];
}
