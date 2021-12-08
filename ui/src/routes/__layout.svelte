<script>

import Search from "$lib/search.svelte";
import { req_meta } from "$lib/stores.js";

console.log("layout script", $req_meta)

</script>

<nav>
    <a href="/" class="logo"><strong>slowfoot explorer</strong></a>

	<a href="/about">About</a>
	
    <Search></Search>
    {#if $req_meta}<span>{$req_meta.time_print || ''}</span>{/if}

</nav>

<main>
<slot></slot>
</main>