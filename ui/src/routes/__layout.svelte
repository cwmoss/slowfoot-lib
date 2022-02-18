<script>

import Search from "$lib/search.svelte";
import { req_meta } from "$lib/stores.js";
import {stats} from '$lib/fetchstore.js'
import {path} from '$lib/config.js'

console.log("layout script", $req_meta)

</script>

<nav>
    
    <svg width="16" height="16" fill="currentColor"><g transform="translate(0 0)"><path d="M9.2,0H5.4c-0.4,0-0.8,0.3-1,0.7l-2,7C2.2,8.4,2.7,9,3.3,9H7l-1.5,7l7.3-9.4C13.3,6,12.8,5,12,5H9l1.1-3.7 C10.3,0.6,9.8,0,9.2,0z"></path></g></svg>
    <a href="{path('')}" class="logo"><strong>slowfoot navigator</strong></a>

	<a href="{path('about')}">About</a>
   
    <div class="nav-stats">{#if $req_meta}<span>{$req_meta.m.time_print || ''}</span>{/if}</div>
    <div class="spinner">
    {#if $req_meta.loading}
        <svg version="1.1" width="32" height="32" viewBox="0 0 16 16" class="octicon octicon-infinity" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" fill="currentColor"><path fill-rule="evenodd" d="M3.5 6c-1.086 0-2 .914-2 2 0 1.086.914 2 2 2 .525 0 1.122-.244 1.825-.727.51-.35 1.025-.79 1.561-1.273-.536-.483-1.052-.922-1.56-1.273C4.621 6.244 4.025 6 3.5 6zm4.5.984c-.59-.533-1.204-1.066-1.825-1.493-.797-.548-1.7-.991-2.675-.991C1.586 4.5 0 6.086 0 8s1.586 3.5 3.5 3.5c.975 0 1.878-.444 2.675-.991.621-.427 1.235-.96 1.825-1.493.59.533 1.204 1.066 1.825 1.493.797.547 1.7.991 2.675.991 1.914 0 3.5-1.586 3.5-3.5s-1.586-3.5-3.5-3.5c-.975 0-1.878.443-2.675.991-.621.427-1.235.96-1.825 1.493zM9.114 8c.536.483 1.052.922 1.56 1.273.704.483 1.3.727 1.826.727 1.086 0 2-.914 2-2 0-1.086-.914-2-2-2-.525 0-1.122.244-1.825.727-.51.35-1.025.79-1.561 1.273z"/></svg>
    {/if}
    </div>
	<div class="nav-right">
        <Search></Search>    
    </div>

 
</nav>
<nav class="subnav">
    {#if $stats.loaded}
        {#each $stats.types as type}
        <a href="{path(type._type)}">{type._type} ({type.total})</a>
        {/each}
        {/if}
    
</nav>
<main>
<slot></slot>
</main>

<style>
    .spinner{
        position: relative;
    }
    .spinner svg{
        width: 20px;
        margin-left: 8px;
        animation: 1s linear 0s infinite normal none running rotate;
        position: absolute;
        z-index: 99;
        top: -16px;
        left: 4px;
    }
    @keyframes rotate {
    0% {
      transform: rotate(0);
    }
    100% {
      transform: rotate(360deg);
    }
  }
</style>