import { browser } from "$app/env";
import { req_meta } from "$lib/stores.js";
import { base, assets } from '$app/paths';

console.log('config.js env', import.meta.env, browser)

const base_url = "http://localhost:1199/__api"

function handle_resp(data){
    if(data===true){
        req_meta.update(d => {
            d.loading = true
            return d
        })
        return
    }
    
    console.log("hdl", data)
    if(data.__meta){
        req_meta.update(d => {
            d.m = data.__meta
            d.loading = false
            return d
        })
    }
}

function path(p){
    return base + '/' + p;
}
export {base_url, handle_resp, path}