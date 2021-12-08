<script>

import Search from "$lib/search.svelte";

console.log("layout script")

</script>

<nav>
    <a href="/" class="logo"><strong>slowfoot explorer</strong></a>

	<a href="/about">About</a>
	
    <Search></Search>
</nav>

<main>
<slot></slot>
</main>