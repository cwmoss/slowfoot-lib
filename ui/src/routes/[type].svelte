<script context="module">
    var base_url = "http://localhost:3039/__api"

	export async function load({ page, fetch, session, stuff }) {
		const url = `${base_url}/type/${page.params.type}`;
		const res = await fetch(url);
        
		if (res.ok) {
            const data =  await res.json()
            
			return {
				props: {
					rows: data.rows
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
export let rows
</script>
<h1>slowfoot explorer</h1>

<ol>
    {#each rows as row}
    <li><a href="id/{row._id}">{row._id}</a></li>
    {/each}
</ol>