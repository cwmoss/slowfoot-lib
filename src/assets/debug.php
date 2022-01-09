
<script src="/__sf/inspector-json.js"></script>

<div id="debug-panel" style="display:none;position:fixed;z-index:122;padding:16px;max-height:90vh;max-width:60vw;overflow-y:scroll;top:16px;left:16px;background-color:white;color:black;font-size:14px;line-height:16px;">
<div id="debug-panel-nav"><a href="/__ui/" target="_sf_navigator">navigator</a></div>
<code><pre id="debug-panel-content" style="white-space: pre-wrap;"></pre></code></div>
<script>
    var ___debug = <?=debug_js()?>;
    var ___debug_e = document.getElementById('debug-panel')
    //document.getElementById("debug-panel-content").textContent=JSON.stringify(___debug, null, 2)
    // https://github.com/sparkartgroup/Inspector-JSON
    var inspector = new InspectorJSON({
        element: 'debug-panel-content',
        json: ___debug,
        collapsed: false
    })
    document.body.addEventListener('keydown', function(e){
        // console.log(e)
        if(e.ctrlKey && e.key=='d'){
            console.log("strg D gedrückt")
            
            if (___debug_e.style.display == 'none') ___debug_e.style.display = 'block';
            else ___debug_e.style.display = 'none';
        }
    })
</script>
