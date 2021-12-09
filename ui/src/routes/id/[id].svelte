<script context="module">
    import {base_url, handle_resp} from '$lib/config.js'
    import {InspectorJSON} from '$lib/inspector_json/inspector_json.js'

	export async function load({ page, fetch, session, stuff }) {
		const url = `${base_url}/id/${page.params.id}`;
		const res = await fetch(url);
        
		if (res.ok) {
            const data =  await res.json()
            handle_resp(data)
			return {
				props: {
                    id: data.res._id,
					body: data.res.body
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
// https://github.com/sparkartgroup/Inspector-JSON
import { onMount } from 'svelte';
export let body
export let id
let inspector_el
let inspector

onMount(() => {
		console.log('the component has mounted', inspector_el);
        inspector = new InspectorJSON({
            element: 'jsoni',
            collapsed: false
        });
	});
</script>
<h1>{id}</h1>

<div id="jsoni" class="code" bind:this={inspector_el}>{body}</div>
