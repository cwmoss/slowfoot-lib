import { readable, writable } from 'svelte/store'
import { browser } from "$app/env";

const url = "http://localhost:3039/__api"



console.log('store env', import.meta, browser)

export const base_url = readable(url, function start(){
    return function stop(){}
})
