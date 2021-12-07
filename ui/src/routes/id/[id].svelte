<script context="module">
    var base_url = "http://localhost:3039/__api"

	export async function load({ page, fetch, session, stuff }) {
		const url = `${base_url}/id/${page.params.id}`;
		const res = await fetch(url);
        
		if (res.ok) {
            const data =  await res.json()
            
			return {
				props: {
                    id: data._id,
					body: data.body
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
<h1>slowfoot explorer</h1>
{id}
{body}
