const sendPostMessage = () => {
    window.top.postMessage(JSON.stringify({
        subject: "lti.frameResize",
        height: document.body.scrollHeight,
        url: window.location.href
    }), "*");
}

window.onload = () => sendPostMessage();
window.onresize = () => sendPostMessage();