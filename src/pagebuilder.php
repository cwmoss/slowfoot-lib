<?php

namespace slowfoot;

class pagebuilder {

    public template $engine;

    public function __construct(
        public configuration $config,
        public store $ds,
        public array $helper
    ) {
        $this->engine = $config->get_template_engine();
    }

    public function make_template(
        string $obj_id,
        string $name,
        array $context
    ): string {
        $obj = $this->ds->get($obj_id);

        // $template = $templates[$obj['_type']][$name]['template'];
        $template = $this->template_name($this->config->templates, $obj['_type'], $name);
        #dbg('template', $template, $obj);
        $content = $this->engine->run(
            $template,
            [
                'page' => $obj,
                'path' => $this->ds->get_path($obj_id, $name),
                'template_config' => $this->config->templates[$obj['_type']][$name], //TODO
                'path_name' => $name
            ],
            $this->helper,
            $this->template_context('template', $context, $obj, $this->ds, $this->config)
        );
        debug_js("page", $obj);
        debug_js("meta", \collect_data('meta', true));
        return $content;
    }

    public function make_page(
        string $pagename,
        string $pagenr,
        string $requestpath,
        array $context
    ): string {
        #$pagination_query = check_pagination($pagename, $src);
        $pp = $this->engine->preprocess($pagename, $context['src']);
        #dbg('page query', $pp);
        if ($page_query = ($pp['page-query'] ?? null)) {
            //var_dump($paginate);
            dbg('[page] query', $page_query);
            if ($page_query['paginate']) {
                [$info, $pagequery] = $this->ds->query_paginated(
                    $page_query['__content'],
                    $page_query['paginate'],
                    []
                );

                //foreach (range(1, $pages) as $page) {
                $qres = $pagequery($pagenr);
                $pagination = self::pagination($info, $pagenr ?: 1);
                //}
            } else {
                $pagination = [];
                $qres = $this->ds->query($page_query['__content']);  // query_page($ds, $pagination_query, $pagenr);
            }

            #var_dump($qres);
            //print_r($coll);
            $content = $this->engine->run_page(
                $pagename,
                ['page' => $qres, 'pagination' => $pagination],
                $this->helper,
                $this->template_context('page', $context, $qres, $ds, $config)
            );
            $content = $this->engine->remove_tags($content, ['page-query']);

            debug_js("page", $qres);
            debug_js("meta", \collect_data('meta', true));
        } else {
            $content = $this->engine->run_page(
                $requestpath,
                [],
                $this->helper,
                $this->template_context('page', $context, [], $ds, $config)
            );

            debug_js("page", []);
            debug_js("meta", \collect_data('meta', true));
        }
        return $content;
    }


    public function template_name($tconfig, $type, $name) {
        return $tconfig[$type][$name]['template'];
    }

    public function template_context($type, $context, $data, $ds, $config): array {
        $context['template_type'] = $type;
        return hook::invoke_filter('modify_template_context', $context, $data, $ds, $config);
    }

    static public function pagination($info, $page) {
        return array_merge($info, [
            'page' => $page,
            'prev' => ($page - 1) ?: null,
            'next' => (($page + 1) <= $info['totalpages']) ? ($page + 1) : null
        ]);
    }
}
