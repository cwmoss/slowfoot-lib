<script context="module">
    import {base_url} from '$lib/config.js'

	export async function load({ page, fetch, session, stuff }) {
		const url = `${base_url}/type/${page.params.type}`;
		const res = await fetch(url);
        
		if (res.ok) {
            const data =  await res.json()
            
			return {
				props: {
					rows: data.res.rows
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
import {page} from '$app/stores'
export let rows
</script>

<h1>_type: {$page.params.type}</h1>
<ol>
    {#each rows as row}
    <li><a href="id/{row._id}">{row._id}</a></li>
    {/each}
</ol>