import{C as s}from"./vendor-c72829ad.js";import{b as a}from"./paths-28a87002.js";const n={},r=!0,t=s({loading:!1,m:{}});console.log("store env",n,r);console.log("config.js env",{VITE_SVELTEKIT_AMP:"",BASE_URL:"/__ui/_app/",MODE:"production",DEV:!1,PROD:!0},r);const p="http://localhost:1199/__api";function _(e){if(e===!0){t.update(o=>(o.loading=!0,o));return}console.log("hdl",e),e.__meta&&t.update(o=>(o.m=e.__meta,o.loading=!1,o))}function u(e){return a+"/"+e}export{p as b,_ as h,u as p,t as r};