<script>
import { onMount } from 'svelte';

export let json
let domnode
let collapse_states = {}

// params = params || {};
let params = {}
let defaults = {
    element: 'body',
    debug: false,
    collapsed: true,
    url: '' // window.location.pathname
};

params = Object.assign( {}, defaults, params );

function toggle(e){
    console.log("+++ toggle", e)
}
function toggle_array(e){
    console.log("+++ togglearray", e)
}

var toString = Object.prototype.toString;

function HTMLEscape( string ){
	var div = document.createElement('div');
	div.appendChild( document.createTextNode( string ) );
	return div.innerHTML;
};

var typeOf = function(object) {
  var type = typeof object;

  if (type === 'undefined') {
    return 'undefined';
  }
  
  if (object) {
    type = object.constructor.name; 
  } else if (type === 'object') {
    type = toString.call(object).slice(8, -1);
  }

  return type.toLowerCase();
}

/*
https://stackoverflow.com/questions/56389375/accessing-generated-custom-element-in-svelte-3
https://stackoverflow.com/questions/67272460/sveltecomponent-with-dom-elements
*/

function processItem( item, parent, key, path ){
			
    var type = typeOf( item );
    var parent_type = typeOf( parent );
    var markup = '';
    
    // Create a string representation of the JSON path to this value
    if( parent_type === 'array' ){
        path += '['+ key +']';
    }
    else if( parent_type === 'object' ){
        path += '.'+ key;
    }
    else {
        path = key || 'this';
    }
    
    // Start the <li>
    if( parent ){
        markup += ( collapse_states[path] || !params.collapsed || ( type !== 'object' && type !== 'array' ) ) ?
            '<li class="'+ type +'" data-path="'+ path +'">' :
            '<li class="'+ type +' collapsed" data-path="'+ path +'">';
    }
    
    // Generate markup by value type. Recursion for arrays and objects.
    if( type === 'object' ){
        if( key ){
            markup += '<a href="#toggle"><strong>'+ key + '</strong></a>';
        }
        markup += '<ul>';
        for( key in item ){
            markup += processItem( item[key], item, key, path );
        }
        markup += '</ul>';
    }
    else if( type === 'array' ){
        if( key ){
            markup += '<a href="javascript:;" on:click={toggle_array}><strong>'+ key +'</strong></a>Array('+ item.length +')';
        }
        markup += '<ol>';
        for( var i in item ){
            markup += processItem( item[i], item, i, path );
        }
        markup += '</ol>';
    }
    else if( type === 'string' ){
        markup += '<strong>'+ key + '</strong><span>"'+ HTMLEscape( item ) +'"</span>';
    }
    else if( type === 'number' ){
        markup += '<strong>'+ key + '</strong><var>'+ item.toString() +'</var>';
    }
    else if( type === 'boolean' ){
        markup += '<strong>'+ key + '</strong><em>'+ item.toString() + '</em>';
    }
    else if( type === 'null' ){
        markup += '<strong>'+ key + '</strong><i>null</i>';
    }
    
    // End the </li>
    if( parent ){
        markup += '</li>';
    }
    
    return markup;

};

function do_inspect(){
    let data = JSON.parse(json)
    let markup = processItem(data)
    domnode.innerHTML = markup;
}

onMount(()=>{
    console.log("mounted inspector")
    do_inspect()
    //console.log("== env, kalender2 == meta.env, browser, prerendering ", import.meta.env, browser, prerendering)
    //if(!prerendering) img_prefix = ""
})

</script>



<div class="inspector-json viewer" bind:this={domnode} on:click="{toggle}"></div>
