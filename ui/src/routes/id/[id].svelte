<script context="module">
    import {base_url} from '$lib/config.js'

	export async function load({ page, fetch, session, stuff }) {
		const url = `${base_url}/id/${page.params.id}`;
		const res = await fetch(url);
        
		if (res.ok) {
            const data =  await res.json()
            
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

export let body
export let id

</script>
<h1>{id}</h1>

<div class="code">{body}</div>
