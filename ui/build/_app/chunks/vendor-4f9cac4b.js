function _(){}function H(t,n){for(const e in n)t[e]=n[e];return t}function M(t){return t()}function L(){return Object.create(null)}function m(t){t.forEach(M)}function I(t){return typeof t=="function"}function P(t,n){return t!=t?n==n:t!==n||t&&typeof t=="object"||typeof t=="function"}function G(t){return Object.keys(t).length===0}function q(t,...n){if(t==null)return _;const e=t.subscribe(...n);return e.unsubscribe?()=>e.unsubscribe():e}function st(t){let n;return q(t,e=>n=e)(),n}function lt(t,n,e){t.$$.on_destroy.push(q(n,e))}function ft(t,n,e,i){if(t){const c=z(t,n,e,i);return t[0](c)}}function z(t,n,e,i){return t[1]&&i?H(e.ctx.slice(),t[1](i(n))):e.ctx}function at(t,n,e,i){if(t[2]&&i){const c=t[2](i(e));if(n.dirty===void 0)return c;if(typeof c=="object"){const l=[],o=Math.max(n.dirty.length,c.length);for(let u=0;u<o;u+=1)l[u]=n.dirty[u]|c[u];return l}return n.dirty|c}return n.dirty}function dt(t,n,e,i,c,l){if(c){const o=z(n,e,i,l);t.p(o,c)}}function _t(t){if(t.ctx.length>32){const n=[],e=t.ctx.length/32;for(let i=0;i<e;i++)n[i]=-1;return n}return-1}let x=!1;function J(){x=!0}function K(){x=!1}function Q(t,n,e,i){for(;t<n;){const c=t+(n-t>>1);e(c)<=i?t=c+1:n=c}return t}function W(t){if(t.hydrate_init)return;t.hydrate_init=!0;let n=t.childNodes;if(t.nodeName==="HEAD"){const r=[];for(let s=0;s<n.length;s++){const a=n[s];a.claim_order!==void 0&&r.push(a)}n=r}const e=new Int32Array(n.length+1),i=new Int32Array(n.length);e[0]=-1;let c=0;for(let r=0;r<n.length;r++){const s=n[r].claim_order,a=(c>0&&n[e[c]].claim_order<=s?c+1:Q(1,c,b=>n[e[b]].claim_order,s))-1;i[r]=e[a]+1;const f=a+1;e[f]=r,c=Math.max(f,c)}const l=[],o=[];let u=n.length-1;for(let r=e[c]+1;r!=0;r=i[r-1]){for(l.push(n[r-1]);u>=r;u--)o.push(n[u]);u--}for(;u>=0;u--)o.push(n[u]);l.reverse(),o.sort((r,s)=>r.claim_order-s.claim_order);for(let r=0,s=0;r<o.length;r++){for(;s<l.length&&o[r].claim_order>=l[s].claim_order;)s++;const a=s<l.length?l[s]:null;t.insertBefore(o[r],a)}}function R(t,n){if(x){for(W(t),(t.actual_end_child===void 0||t.actual_end_child!==null&&t.actual_end_child.parentElement!==t)&&(t.actual_end_child=t.firstChild);t.actual_end_child!==null&&t.actual_end_child.claim_order===void 0;)t.actual_end_child=t.actual_end_child.nextSibling;n!==t.actual_end_child?(n.claim_order!==void 0||n.parentNode!==t)&&t.insertBefore(n,t.actual_end_child):t.actual_end_child=n.nextSibling}else(n.parentNode!==t||n.nextSibling!==null)&&t.appendChild(n)}function ht(t,n,e){x&&!e?R(t,n):(n.parentNode!==t||n.nextSibling!=e)&&t.insertBefore(n,e||null)}function U(t){t.parentNode.removeChild(t)}function mt(t,n){for(let e=0;e<t.length;e+=1)t[e]&&t[e].d(n)}function V(t){return document.createElement(t)}function X(t){return document.createElementNS("http://www.w3.org/2000/svg",t)}function v(t){return document.createTextNode(t)}function pt(){return v(" ")}function gt(){return v("")}function yt(t,n,e,i){return t.addEventListener(n,e,i),()=>t.removeEventListener(n,e,i)}function bt(t,n,e){e==null?t.removeAttribute(n):t.getAttribute(n)!==e&&t.setAttribute(n,e)}function Y(t){return Array.from(t.childNodes)}function Z(t){t.claim_info===void 0&&(t.claim_info={last_index:0,total_claimed:0})}function B(t,n,e,i,c=!1){Z(t);const l=(()=>{for(let o=t.claim_info.last_index;o<t.length;o++){const u=t[o];if(n(u)){const r=e(u);return r===void 0?t.splice(o,1):t[o]=r,c||(t.claim_info.last_index=o),u}}for(let o=t.claim_info.last_index-1;o>=0;o--){const u=t[o];if(n(u)){const r=e(u);return r===void 0?t.splice(o,1):t[o]=r,c?r===void 0&&t.claim_info.last_index--:t.claim_info.last_index=o,u}}return i()})();return l.claim_order=t.claim_info.total_claimed,t.claim_info.total_claimed+=1,l}function O(t,n,e,i){return B(t,c=>c.nodeName===n,c=>{const l=[];for(let o=0;o<c.attributes.length;o++){const u=c.attributes[o];e[u.name]||l.push(u.name)}l.forEach(o=>c.removeAttribute(o))},()=>i(n))}function xt(t,n,e){return O(t,n,e,V)}function $t(t,n,e){return O(t,n,e,X)}function tt(t,n){return B(t,e=>e.nodeType===3,e=>{const i=""+n;if(e.data.startsWith(i)){if(e.data.length!==i.length)return e.splitText(i.length)}else e.data=i},()=>v(n),!0)}function wt(t){return tt(t," ")}function Et(t,n){n=""+n,t.wholeText!==n&&(t.data=n)}function kt(t,n,e){t.classList[e?"add":"remove"](n)}let p;function g(t){p=t}function $(){if(!p)throw new Error("Function called outside component initialization");return p}function vt(t){$().$$.on_mount.push(t)}function Nt(t){$().$$.after_update.push(t)}function St(t,n){$().$$.context.set(t,n)}function jt(t){return $().$$.context.get(t)}const y=[],T=[],w=[],D=[],nt=Promise.resolve();let N=!1;function et(){N||(N=!0,nt.then(F))}function S(t){w.push(t)}const j=new Set;let E=0;function F(){const t=p;do{for(;E<y.length;){const n=y[E];E++,g(n),it(n.$$)}for(g(null),y.length=0,E=0;T.length;)T.pop()();for(let n=0;n<w.length;n+=1){const e=w[n];j.has(e)||(j.add(e),e())}w.length=0}while(y.length);for(;D.length;)D.pop()();N=!1,j.clear(),g(t)}function it(t){if(t.fragment!==null){t.update(),m(t.before_update);const n=t.dirty;t.dirty=[-1],t.fragment&&t.fragment.p(t.ctx,n),t.after_update.forEach(S)}}const k=new Set;let d;function At(){d={r:0,c:[],p:d}}function Ct(){d.r||m(d.c),d=d.p}function rt(t,n){t&&t.i&&(k.delete(t),t.i(n))}function Mt(t,n,e,i){if(t&&t.o){if(k.has(t))return;k.add(t),d.c.push(()=>{k.delete(t),i&&(e&&t.d(1),i())}),t.o(n)}}function Lt(t,n){const e={},i={},c={$$scope:1};let l=t.length;for(;l--;){const o=t[l],u=n[l];if(u){for(const r in o)r in u||(i[r]=1);for(const r in u)c[r]||(e[r]=u[r],c[r]=1);t[l]=u}else for(const r in o)c[r]=1}for(const o in i)o in e||(e[o]=void 0);return e}function qt(t){return typeof t=="object"&&t!==null?t:{}}function zt(t){t&&t.c()}function Bt(t,n){t&&t.l(n)}function ct(t,n,e,i){const{fragment:c,on_mount:l,on_destroy:o,after_update:u}=t.$$;c&&c.m(n,e),i||S(()=>{const r=l.map(M).filter(I);o?o.push(...r):m(r),t.$$.on_mount=[]}),u.forEach(S)}function ot(t,n){const e=t.$$;e.fragment!==null&&(m(e.on_destroy),e.fragment&&e.fragment.d(n),e.on_destroy=e.fragment=null,e.ctx=[])}function ut(t,n){t.$$.dirty[0]===-1&&(y.push(t),et(),t.$$.dirty.fill(0)),t.$$.dirty[n/31|0]|=1<<n%31}function Ot(t,n,e,i,c,l,o,u=[-1]){const r=p;g(t);const s=t.$$={fragment:null,ctx:null,props:l,update:_,not_equal:c,bound:L(),on_mount:[],on_destroy:[],on_disconnect:[],before_update:[],after_update:[],context:new Map(n.context||(r?r.$$.context:[])),callbacks:L(),dirty:u,skip_bound:!1,root:n.target||r.$$.root};o&&o(s.root);let a=!1;if(s.ctx=e?e(t,n.props||{},(f,b,...A)=>{const C=A.length?A[0]:b;return s.ctx&&c(s.ctx[f],s.ctx[f]=C)&&(!s.skip_bound&&s.bound[f]&&s.bound[f](C),a&&ut(t,f)),b}):[],s.update(),a=!0,m(s.before_update),s.fragment=i?i(s.ctx):!1,n.target){if(n.hydrate){J();const f=Y(n.target);s.fragment&&s.fragment.l(f),f.forEach(U)}else s.fragment&&s.fragment.c();n.intro&&rt(t.$$.fragment),ct(t,n.target,n.anchor,n.customElement),K(),F()}g(r)}class Tt{$destroy(){ot(this,1),this.$destroy=_}$on(n,e){const i=this.$$.callbacks[n]||(this.$$.callbacks[n]=[]);return i.push(e),()=>{const c=i.indexOf(e);c!==-1&&i.splice(c,1)}}$set(n){this.$$set&&!G(n)&&(this.$$.skip_bound=!0,this.$$set(n),this.$$.skip_bound=!1)}}const h=[];function Dt(t,n=_){let e;const i=new Set;function c(u){if(P(t,u)&&(t=u,e)){const r=!h.length;for(const s of i)s[1](),h.push(s,t);if(r){for(let s=0;s<h.length;s+=2)h[s][0](h[s+1]);h.length=0}}}function l(u){c(u(t))}function o(u,r=_){const s=[u,r];return i.add(s),i.size===1&&(e=n(c)||_),u(t),()=>{i.delete(s),i.size===0&&(e(),e=null)}}return{set:c,update:l,subscribe:o}}export{ot as A,H as B,Dt as C,X as D,$t as E,R as F,_ as G,st as H,mt as I,ft as J,dt as K,_t as L,at as M,lt as N,kt as O,yt as P,jt as Q,Tt as S,Y as a,bt as b,xt as c,U as d,V as e,ht as f,tt as g,Et as h,Ot as i,pt as j,gt as k,wt as l,At as m,Mt as n,Ct as o,rt as p,St as q,Nt as r,P as s,v as t,vt as u,zt as v,Bt as w,ct as x,Lt as y,qt as z};