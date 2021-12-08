import { browser } from "$app/env";
import { req_meta } from "$lib/stores.js";

console.log('config.js env', import.meta.env, browser)

const base_url = "http://localhost:3039/__api"

function handle_resp(data){
    console.log("hdl", data)
    if(data.__meta){
        req_meta.set(data.__meta)
    }
}

export {base_url, handle_resp}