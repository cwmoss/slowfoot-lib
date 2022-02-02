<script context="module">
    import {base_url, handle_resp} from '$lib/config.js'
    import Jinspector from '$lib/jinspector.svelte'

	export async function load({ params, fetch, session, stuff }) {
		const url = `${base_url}/id/?id=${params.id}`;
		handle_resp(true)
		const res = await fetch(url);
        
		if (res.ok) {
            const data =  await res.json()
            handle_resp(data)
			return {
				props: {
                    id: data.res._id,
					body: data.res
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
// import {InspectorJSON} from '$lib/inspector_json/inspector_json.js'


// https://github.com/sparkartgroup/Inspector-JSON
import { onMount } from 'svelte';
export let body
export let id
let inspector_el
let inspector

// <div id="jsoni" class="code" bind:this={inspector_el}>{body}</div>

onMount(() => {
		console.log('the ID page component has mounted', inspector_el);
       /* inspector = new InspectorJSON({
            element: 'jsoni',
            collapsed: false
        });
		*/
	});
</script>
<h1>{id}</h1>


{#if body}
<Jinspector json={body}></Jinspector>
{/if}
