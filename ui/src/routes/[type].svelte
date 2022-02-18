<script context="module">
    import {base_url, handle_resp} from '$lib/config.js'

	export async function load({ params, fetch, session, stuff }) {
		const url = `${base_url}/type/${params.type}`;
		handle_resp(true)
		const res = await fetch(url);
        
		if (res.ok) {
            const data =  await res.json()
            handle_resp(data)
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


{#if $page.params.type == '__paths'}
<h1>paths</h1>
<ol>
    {#each rows as row}
    <li><a href="id/{encodeURIComponent(row.id)}">{row.path} {#if row.name!='_'}({row.name}){/if}</a></li>
    {/each}
</ol>
{:else}

<h1>_type: {$page.params.type}</h1>
<ol>
    {#each rows as row}
    <li><a href="id/{encodeURIComponent(row._id)}">{row._id}</a></li>
    {/each}
</ol>
{/if}