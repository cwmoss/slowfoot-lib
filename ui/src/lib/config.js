import { browser } from "$app/env";

console.log('config.js env', import.meta.env, browser)

const base_url = "http://localhost:3039/__api"

export {base_url}