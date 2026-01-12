(function () {
  var api = (window.FCWPB = window.FCWPB || {});

  function post(payload) {
    try {
      var url =
        (window.FCWPB_HL && window.FCWPB_HL.restEventUrl) ||
        (window.FCWPB_UTM && window.FCWPB_UTM.restEventUrl);
      if (!url) return;

      if (navigator.sendBeacon) {
        var blob = new Blob([JSON.stringify(payload)], { type: "application/json" });
        navigator.sendBeacon(url, blob);
      } else {
        fetch(url, {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify(payload),
          keepalive: true
        });
      }
    } catch (e) {}
  }

  api.track = function (type, data) {
    if (!type) return;

    post({
      type: String(type),
      id: "e_" + Math.random().toString(36).slice(2) + "_" + Date.now(),
      ts: Date.now(),
      data: data || {}
    });
  };

  window.addEventListener(
    "fcwpb:track",
    function (e) {
      if (!e || !e.detail || !e.detail.type) return;
      api.track(e.detail.type, e.detail.data || {});
    },
    false
  );
})();
