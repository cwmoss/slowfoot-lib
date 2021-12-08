<script>

import Search from "$lib/search.svelte";


</script>

<nav>
    <a href="/" class="logo"><strong>slowfoot explorer</strong></a>

	<a href="/about">About</a>
	
    <Search></Search>
</nav>

<main>
<slot></slot>
</main>