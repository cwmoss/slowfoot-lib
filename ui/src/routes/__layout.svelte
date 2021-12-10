<script>

import Search from "$lib/search.svelte";
import { req_meta } from "$lib/stores.js";

console.log("layout script", $req_meta)

</script>

<nav>
    
    <svg width="16" height="16" fill="currentColor"><g transform="translate(0 0)"><path d="M9.2,0H5.4c-0.4,0-0.8,0.3-1,0.7l-2,7C2.2,8.4,2.7,9,3.3,9H7l-1.5,7l7.3-9.4C13.3,6,12.8,5,12,5H9l1.1-3.7 C10.3,0.6,9.8,0,9.2,0z"></path></g></svg>
    <a href="/" class="logo"><strong>slowfoot explorer</strong></a>

	<a href="/about">About</a>
	
    <Search></Search>
    {#if $req_meta}<span>{$req_meta.time_print || ''}</span>{/if}

</nav>

<main>
<slot></slot>
</main>