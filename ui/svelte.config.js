import adapter from '@sveltejs/adapter-static';

/** @type {import('@sveltejs/kit').Config} */
var base = process.env['NODE_ENV']=='production'?
	'/__ui':''
const config = {
	kit: {
		adapter: adapter({
			fallback: 'index.html'
		}),
		prerender: {
			enabled: false
		},
		// ssr: false,
		// hydrate the <div id="svelte"> element in src/app.html
		target: '#svelte',
		paths: {
			/*	base: import.meta.env.VITE_BASE_URL || '' 
			*/
				base: '/__ui'
			
		}
	}
};

export default config;
