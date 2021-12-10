import { get, writable } from 'svelte/store'
import { base_url, handle_resp } from '$lib/config.js'


const stats = writable({loaded: false});
const stats_fetching = writable(false);

async function stats_reload() {
    stats_fetching.set(true)
    handle_resp(true)
    
    const url = `${base_url}/index`;
    console.log("+++ fetching stats", url)

	const res = await fetch(url);
        
    if (res.ok) {
        const data =  await res.json()
        handle_resp(data)
        stats.set({loaded:true, types:data.res})
    }
}

if(!get(stats).loaded && !get(stats_fetching)){
    stats_reload()
}else{
    console.log("not fetching")
}

export {stats, stats_fetching, stats_reload}

/*
export default function (url) {
	const loading = writable(false)
	const error = writable(false)
	const data = writable({})
	
	async function get() {
		loading.set(true)
		error.set(false)
		try {
			const response = await fetch(url)
			data.set(await response.json())
		} catch(e) {
			error.set(e)
		}
		loading.set(false)
	}
	
	get()
	
	return [ data, loading, error, get]
}
*/