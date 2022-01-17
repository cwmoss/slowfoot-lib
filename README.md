# slowfoot lib

## config

### sources

content sources have a unique name, a source type and opts

included source loader
* dataset, json nd, load_dataset()
* json, load_json()
* directory, markdown/ frontmatter, load_directory()

### types

types are content types with template, path pattern or path function

### hooks

available hooks
* on_load(row) => row || null

## pipeline

    include src/helper.php => SLOWFOOT_BASE (project root directory)
    read config.php => sources, types, hooks
    | load_sources
    | load template helper
    => dataset, paths
      | build pages from all types with defined templates
      | build pages from src/pages folder
      => website


/*

select _id , group_concat(b.key || ': ' ||  b.value, x'0a') as kv from docs, json_tree(body) b where b.atom not null limit 20;

select _id , group_concat(b.key || ': ' ||  b.value, x'0a') as kv from docs, json_tree(body) b where b.atom not null group by _id limit 3;


CREATE VIRTUAL TABLE docs_fts USING fts5(
    btext, 
    content='docs', 
    content_rowid='_id' 
)

CREATE VIRTUAL TABLE docs_fts USING fts5(
    _id,
    btext
)

    INSERT INTO extra_q (id, chinese, pinyin, english, [type], description, tag)
    SELECT @id, @chinese, @pinyin, @english, @type, @description, @tag
    WHERE EXISTS (SELECT 1 FROM extra WHERE id = @id)

INSERT INTO docs_fts(_id, btext) 
SELECT _id, group_concat(b.key || ': ' ||  b.value, x'0a') as btext from docs, json_tree(body) b where b.atom not null group by _id

composer // lib development

dev
COMPOSER=composer-dev.json composer install

OR 

composer install --prefer-source

prod
{
    "type": "vcs",
    "url": "https://github.com/cwmoss/slowfoot-lib"
}

dev (besser: --prefer-source)

{
    "type": "path",
    "url": "../slowfoot-lib",
    "options": {
        "symlink": true
        }
}

{"type": "vcs","url": "https://github.com/cwmoss/slowfoot"}

composer create-project -s dev --repository '{"type": "vcs","url": "https://github.com/cwmoss/slowfoot"}' cwmoss/slowfoot slowf
*/