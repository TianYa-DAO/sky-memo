// 光遇备忘录前端交互
document.addEventListener('DOMContentLoaded', function () {
  // 今日待办：勾选"今日完成"
  document.querySelectorAll('.todo-card input[type=checkbox]').forEach(function (cb) {
    cb.addEventListener('change', function () {
      var fd = new FormData();
      fd.append('csrf', document.querySelector('input[name=csrf]') ? document.querySelector('input[name=csrf]').value : '');
      fd.append('toggle', cb.dataset.oid);
      fd.append('checked', cb.checked ? '1' : '0');
      fetch('./index.php', { method: 'POST', body: fd })
        .then(function (r) {
          if (r.ok) {
            cb.closest('.todo-card').classList.toggle('done', cb.checked);
            // 同步蜡烛点亮/熄灭
            var card = cb.closest('.todo-card');
            if (card) {
              var svg = card.querySelector('.candle');
              var flame = svg ? svg.querySelector('.flame') : null;
              if (cb.checked) {
                card.classList.add('done');
                if (flame) {
                  flame.setAttribute('fill', '#f7b84b');
                  flame.innerHTML = '<path d="M22 10c0-3-2.5-5-2.5-5S17 7 17 10a5 5 0 0 0 10 0c0-2-1-3-1-3s-1 1-1 3" fill="#f7b84b"/>';
                  svg.classList.add('glow');
                  var body = svg.querySelector('.candle-body');
                  if (body) body.setAttribute('opacity', '1');
                }
              } else {
                card.classList.remove('done');
                if (flame) {
                  flame.removeAttribute('fill');
                  flame.innerHTML = '<path d="M22 11c0-2.5-2-4.2-2-4.2S18 8.5 18 11a4 4 0 0 0 8 0c0-1.6-.8-2.4-.8-2.4s-.8.8-.8 2.4" fill="none" stroke="#8fa0bd" stroke-width="1.5" stroke-linecap="round"/>';
                  svg.classList.remove('glow');
                  var body = svg.querySelector('.candle-body');
                  if (body) body.setAttribute('opacity', '.45');
                }
              }
            }
          }
        })
        .catch(function () { cb.checked = !cb.checked; alert('操作失败，请重试'); });
    });
  });
});