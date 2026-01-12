(function () {
  function post(type, data) {
    try {
      var payload = Object.assign({ type: type, ts: Date.now() }, data || {});
      var url = FCWPB_ABANDON.restEventUrl;
      if (navigator.sendBeacon) {
        var blob = new Blob([JSON.stringify(payload)], { type: "application/json" });
        navigator.sendBeacon(url, blob);
      } else {
        fetch(url, { method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify(payload), keepalive: true });
      }
    } catch (e) {}
  }

  document.addEventListener("input", function (e) {
    var el = e.target;
    if (!el || !el.name) return;
    if (el.name === "billing_email" && el.value && el.value.indexOf("@") > 0) {
      post("checkout_email_captured", { email: el.value });
    }
  }, true);

  post("begin_checkout", { path: location.pathname });
})(); 
