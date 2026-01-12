(function () {
  function getQueryParams() {
    var params = {};
    try {
      var search = window.location.search || "";
      if (!search) return params;
      search.substring(1).split("&").forEach(function (pair) {
        if (!pair) return;
        var parts = pair.split("=");
        var k = decodeURIComponent(parts[0] || "").trim();
        var v = decodeURIComponent(parts.slice(1).join("=") || "").trim();
        if (!k) return;
        params[k] = v;
      });
    } catch (e) {}
    return params;
  }

  function setCookie(name, value, days) {
    try {
      var d = new Date();
      d.setTime(d.getTime() + days * 24 * 60 * 60 * 1000);
      document.cookie =
        name + "=" + encodeURIComponent(value) +
        "; expires=" + d.toUTCString() +
        "; path=/; SameSite=Lax";
    } catch (e) {}
  }

  function getCookie(name) {
    try {
      var m = document.cookie.match(new RegExp("(^| )" + name + "=([^;]+)"));
      return m ? decodeURIComponent(m[2]) : "";
    } catch (e) {
      return "";
    }
  }

  function storageGet(key) {
    try {
      if (FCWPB_UTM.storage === "localStorage" && window.localStorage) {
        return window.localStorage.getItem(key) || "";
      }
    } catch (e) {}
    return getCookie(key);
  }

  function storageSet(key, val) {
    try {
      if (FCWPB_UTM.storage === "localStorage" && window.localStorage) {
        window.localStorage.setItem(key, val);
        return;
      }
    } catch (e) {}
    setCookie(key, val, FCWPB_UTM.cookieDays || 90);
  }

  function capture() {
    var qp = getQueryParams();
    (FCWPB_UTM.keys || []).forEach(function (k) {
      if (qp[k] && qp[k].length) {
        storageSet("fcwpb_" + k, qp[k]);
      }
    });
  }

  function currentUTMs() {
    var out = {};
    (FCWPB_UTM.keys || []).forEach(function (k) {
      var v = storageGet("fcwpb_" + k);
      if (v) out[k] = v;
    });
    return out;
  }

  /* ---------------------------
     Gravity Forms population
  ---------------------------- */

  function populateGravityForms(event) {
    if (!(FCWPB_UTM.keys || []).length) return;

    (FCWPB_UTM.keys || []).forEach(function (key) {
      var storageKey = "fcwpb_" + key;
      var value = "";

      try {
        if (FCWPB_UTM.storage === "localStorage" && window.localStorage) {
          // Only use localStorage if setting is localStorage
          value = window.localStorage.getItem(storageKey) || "";
        } else {
          // Fallback or cookie-only mode
          var m = document.cookie.match(new RegExp("(^| )" + storageKey + "=([^;]+)"));
          value = m ? decodeURIComponent(m[2]) : "";
        }
      } catch (e) {
        value = ""; // fallback gracefully
      }

      if (!value) return;

      // Populate all matching fields in this form (or all forms if no event)
      var forms = [];
      if (event && event.detail && event.detail.formId) {
        var f = document.getElementById("gform_" + event.detail.formId);
        if (f) forms.push(f);
      } else {
        forms = Array.from(document.querySelectorAll('form[id^="gform_"]'));
      }

      forms.forEach(function (form) {
        form.querySelectorAll('[data-fcwpb-key="' + storageKey + '"]').forEach(function (field) {
          if (!field.value) {
            field.value = value;
            field.dispatchEvent(new Event("input", { bubbles: true }));
            field.dispatchEvent(new Event("change", { bubbles: true }));
          }
        });
      });
    });
  }

  /* ---------------------------
     Append hidden fields on submit
  ---------------------------- */

  function ensureHidden(form, name, value) {
    var existing = form.querySelector('input[type="hidden"][name="' + name + '"]');
    if (!existing) {
      existing = document.createElement("input");
      existing.type = "hidden";
      existing.name = name;
      form.appendChild(existing);
    }
    existing.value = value;
  }

  function appendToForms() {
    if (!FCWPB_UTM.appendToForms) return;

    document.addEventListener("submit", function (e) {
      var form = e.target;
      if (!form || !form.querySelector) return;

      var utms = currentUTMs();
      if (!utms || !Object.keys(utms).length) return;

      Object.keys(utms).forEach(function (k) {
        ensureHidden(
          form,
          (FCWPB_UTM.fieldPrefix || "fc_") + k,
          utms[k]
        );
      });
    }, true);
  }

  /* ---------------------------
     Event tracking
  ---------------------------- */

  function sendEvent(type, data) {
    try {
      var payload = Object.assign(
        { type: type, ts: Date.now() },
        data || {},
        { utm: currentUTMs() }
      );

      var url = FCWPB_UTM.restEventUrl;

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

  /* ---------------------------
     Boot
  ---------------------------- */

  capture();
  appendToForms();
  populateGravityForms();

  document.addEventListener("DOMContentLoaded", populateGravityForms);
  document.addEventListener("gform/post_render", populateGravityForms);

  document.addEventListener("click", function (e) {
    var a = e.target && e.target.closest ? e.target.closest("a") : null;
    if (!a) return;
    var href = (a.getAttribute("href") || "").trim();
    if (href.indexOf("tel:") === 0) sendEvent("click_to_call", { href: href });
    if (href.indexOf("mailto:") === 0) sendEvent("click_to_email", { href: href });
  }, true);

})();
