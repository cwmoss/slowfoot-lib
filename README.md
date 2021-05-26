# slowfoot lib

## config

### sources

content sources have a unique name, a source type and opts

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

