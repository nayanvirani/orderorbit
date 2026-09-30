/* OrderOrbit · countdown timers (shared by countdown and bundle timers). Elements with
   data-oo-end="<epoch ms>" and four <b> children tick down days, hours, minutes, seconds. */
(function () {
  if (OrderOrbit.timers) return;
  var timer = null;

  function tick() {
    var nodes = document.querySelectorAll('[data-oo-end]');
    if (!nodes.length) { clearInterval(timer); timer = null; return; }
    nodes.forEach(function (node) {
      var left = Math.max(0, Math.floor((Number(node.getAttribute('data-oo-end')) - Date.now()) / 1000));
      var parts = [Math.floor(left / 86400), Math.floor((left % 86400) / 3600), Math.floor((left % 3600) / 60), left % 60];
      node.querySelectorAll('b').forEach(function (b, i) { b.textContent = String(parts[i]).padStart(2, '0'); });
    });
  }

  OrderOrbit.timers = function (root) {
    if (root.querySelector('[data-oo-end]')) {
      tick();
      if (!timer) timer = setInterval(tick, 1000);
    }
  };
})();
