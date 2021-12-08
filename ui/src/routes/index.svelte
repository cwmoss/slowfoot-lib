<script context="module">
/*
https://svelte.dev/repl/3238e5737f764431a26e243800dccc6d?version=3.16.4

RemoteTable
*/
	//import { base_url } from '$lib/stores.js';
	import {base_url, handle_resp} from '$lib/config.js'
    // var base_url = "http://localhost:3039/__api"
	
	console.log("index page module", base_url)

	export async function load({ page, fetch, session, stuff }) {
		const url = `${base_url}/index`;
		const res = await fetch(url);
        
		if (res.ok) {
            const data =  await res.json()
            handle_resp(data)
			return {
				props: {
					stats: data['res']
				}
			};
		}

		return {
			status: res.status,
			error: new Error(`Could not load ${url}`)
		};
	}    
</script>
<script>
export let stats
</script>


<ul>
    {#each stats as type}
    <li><a href="{type._type}">{type._type} ({type.total})</a></li>
    {/each}
</ul>
