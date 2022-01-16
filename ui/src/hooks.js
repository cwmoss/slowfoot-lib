export async function handle({ request, resolve }) {
	const response = await resolve(request, {
		// ssr: !request.url.pathname.startsWith('/admin')
        ssr: false
	});

	return response;
}