/* OrderOrbit Space · countdown timers (countdown and bundle timers). An element with
   data-oo-end="<epoch ms>" holds <b> children for days, hours, minutes and seconds
   (hours, minutes and seconds with data-oo-nodays). Each unit's parent gets --p (0–1)
   for ring layouts, "oo-tick" when its value changes (flip layouts), and the element
   gets "oo-urgent" inside its data-oo-urgent window (seconds). Daily cutoffs
   (data-oo-daily) roll over to the next day; data-oo-every="<ms>" restarts repeating timers. */
(function () {
  if (OrderOrbit.timers) return;
  var timer = null;

  function tick() {
    var nodes = document.querySelectorAll('[data-oo-end]');
    if (!nodes.length) { clearInterval(timer); timer = null; return; }
    var now = Date.now();
    nodes.forEach(function (node) {
      var end = Number(node.getAttribute('data-oo-end'));
      // Daily cutoffs and repeating hour/minute timers start their next round at zero.
      var every = Number(node.getAttribute('data-oo-every')) || (node.hasAttribute('data-oo-daily') ? 86400000 : 0);
      if (end <= now && every) {
        end += every * Math.ceil((now - end + 1) / every);
        node.setAttribute('data-oo-end', end);
      }
      var left = Math.max(0, Math.floor((end - now) / 1000));
      var noDays = node.hasAttribute('data-oo-nodays');
      var parts = noDays
        ? [Math.floor(left / 3600), Math.floor((left % 3600) / 60), left % 60]
        : [Math.floor(left / 86400), Math.floor((left % 86400) / 3600), Math.floor((left % 3600) / 60), left % 60];
      var max = noDays ? [Math.max(24, parts[0]), 60, 60] : [Math.max(7, parts[0]), 24, 60, 60];
      node.querySelectorAll('b').forEach(function (b, i) {
        var text = String(parts[i]).padStart(2, '0');
        if (b.textContent !== text) {
          b.textContent = text;
          var unit = b.parentNode;
          unit.style.setProperty('--p', (parts[i] / max[i]).toFixed(4));
          unit.classList.remove('oo-tick');
          void unit.offsetWidth; // restart the flip animation
          unit.classList.add('oo-tick');
        }
      });
      var urgent = Number(node.getAttribute('data-oo-urgent') || 0);
      node.classList.toggle('oo-urgent', urgent > 0 && left <= urgent && left > 0);
      if (!left && node.hasAttribute('data-oo-hide-ended')) {
        var root = node.closest('.oo-root');
        if (root) root.hidden = true;
      }
    });
  }

  OrderOrbit.timers = function (root) {
    if (root.querySelector('[data-oo-end]')) {
      tick();
      if (!timer) timer = setInterval(tick, 1000);
    }
  };
})();
