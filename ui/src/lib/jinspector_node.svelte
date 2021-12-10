<script>
import { onMount } from 'svelte';

export let data
export let parent
export let key
export let path

let type
let parent_type
let collapsed

$: init(data)

function init(data){
    type = typeOf(data)
    if(parent) parent_type=typeOf(parent)
}

console.log("++ data ", data, typeOf(data))
//let toString = Object.prototype.toString;

function HTMLEscape( string ){
	var div = document.createElement('div');
	div.appendChild( document.createTextNode( string ) );
	return div.innerHTML;
};

function typeOf(object) {
  var type = typeof object;

  if (type === 'undefined') {
    return 'undefined';
  }
  
  if (object) {
    type = object.constructor.name; 
  } else if (type === 'object') {
    type = Object.prototype.toString(object).slice(8, -1);
  }

  return type.toLowerCase();
}

onMount(()=>{
  //  console.log("mounted node", data)
  //  type = typeOf(data)
    //do_inspect()
    //console.log("== env, kalender2 == meta.env, browser, prerendering ", import.meta.env, browser, prerendering)
    //if(!prerendering) img_prefix = ""
})
</script>
{#if type && data}
    {#if type=='object'}
        <li class="object" class:collapsed>
            <a on:click="{()=>{collapsed=!collapsed}}" href="javascript:;"><strong>{key}</strong></a>
            <ul>{#each Object.entries(data) as [k, value]}<svelte:self data={value} parent={data} key={k}></svelte:self>{/each}</ul>
        </li>
    {:else if type=='array'}
        <li class="array" class:collapsed>
            <a on:click="{()=>{collapsed=!collapsed}}" href="javascript:;"><strong>{key}</strong></a>
            Array({data.length})
        <ol>{#each data as value, index}
            <svelte:self data={value} parent={data} key={index}></svelte:self>
            {/each}
        </ol>
        </li>
    {:else if type=='number'}
        <li class={type}><strong>{key}</strong><var>{data}</var></li>
    {:else if type=='boolean'}
        <li class={type}><strong>{key}</strong><em>{data}</em></li>
    {:else if type=='null'}
        <li class={type}><strong>{key}</strong><i>null</i></li>
    {:else if type=='string'}<li class={type}><strong>{key}</strong><span>{data}</span></li>{/if}
{/if}