(function(){const i="tubesum-inject-button",u="tubesum-toast";function c(){const e=document.querySelector("#top-level-buttons-computed");return(e==null?void 0:e.parentElement)??null}function l(){const e=document.createElement("button");return e.id=i,e.className="tubesum-btn",e.innerHTML="✨ TubeSum",e.title="Summarize this video with AI",e.style.cssText=`
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 0 16px;
    height: 36px;
    font-size: 14px;
    font-weight: 500;
    font-family: 'YouTube Sans', 'Roboto', sans-serif;
    color: #fff;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    border: none;
    border-radius: 9999px;
    cursor: pointer;
    transition: opacity 0.2s, transform 0.1s;
    white-space: nowrap;
  `,e.addEventListener("mouseenter",()=>{e.style.opacity="0.9",e.style.transform="scale(1.02)"}),e.addEventListener("mouseleave",()=>{e.style.opacity="1",e.style.transform="scale(1)"}),e.addEventListener("click",f),e}function r(){var t;(t=document.getElementById(i))==null||t.remove();const e=c();if(!e){setTimeout(r,1e3);return}const o=l();e.insertBefore(o,e.firstChild)}function n(e,o=!1){a();const t=document.createElement("div");t.id=u,t.textContent=e,t.style.cssText=`
    position: fixed;
    bottom: 24px;
    left: 50%;
    transform: translateX(-50%);
    padding: 12px 24px;
    border-radius: 12px;
    font-family: 'YouTube Sans', 'Roboto', sans-serif;
    font-size: 14px;
    font-weight: 500;
    color: #fff;
    background: ${o?"#dc2626":"#1e293b"};
    box-shadow: 0 4px 24px rgba(0,0,0,0.5);
    z-index: 99999;
    animation: tubesum-fade-in 0.3s ease;
  `,document.body.appendChild(t),setTimeout(a,5e3)}function a(){var e;(e=document.getElementById(u))==null||e.remove()}const d=document.createElement("style");d.textContent=`
  @keyframes tubesum-fade-in {
    from { opacity: 0; transform: translateX(-50%) translateY(10px); }
    to { opacity: 1; transform: translateX(-50%) translateY(0); }
  }
`;document.head.appendChild(d);function m(){return window.location.href}async function f(){var o;const e=m();if(!e.includes("watch?v=")){n("Could not detect YouTube video URL.",!0);return}n("Summarizing with AI...");try{const t=await chrome.runtime.sendMessage({type:"SUMMARIZE_REQUEST",youtubeUrl:e});if(t.type==="SUMMARIZE_SUCCESS"){const s=(o=t.data._links)==null?void 0:o.public_page;s?(n("Done! Opening full transcript..."),window.open(`https://tubesum.app${s}`,"_blank")):n("Summary complete! Open the extension popup to view.")}else t.type==="SUMMARIZE_ERROR"&&n(t.error,!0)}catch(t){n("Failed to connect. Please try again.",!0),console.error("[TubeSum]",t)}}chrome.runtime.onMessage.addListener(e=>{e.type==="SUMMARIZE_PROGRESS"&&n(`AI is working... (${e.status})`)});document.addEventListener("yt-navigate-finish",()=>{console.log("[TubeSum] SPA navigation detected, re-injecting button."),r()});const p=new MutationObserver(()=>{!document.getElementById(i)&&c()&&r()});p.observe(document.body,{childList:!0,subtree:!0});document.readyState==="complete"||document.readyState==="interactive"?r():document.addEventListener("DOMContentLoaded",r);console.log("[TubeSum] Content script loaded.");
})()
