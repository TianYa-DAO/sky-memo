// 光遇备忘录前端交互
document.addEventListener('DOMContentLoaded', function () {
  // 今日待办：勾选"今日完成"
  document.querySelectorAll('.todo-card input[type=checkbox]').forEach(function (cb) {
    cb.addEventListener('change', function () {
      var fd = new FormData();
      fd.append('csrf', document.querySelector('input[name=csrf]') ? document.querySelector('input[name=csrf]').value : '');
      fd.append('toggle', cb.dataset.oid);
      fd.append('checked', cb.checked ? '1' : '0');
      fetch('index.php', { method: 'POST', body: fd })
        .then(function (r) { if (r.ok) { cb.closest('.todo-card').classList.toggle('done', cb.checked);} })
        .catch(function () { cb.checked = !cb.checked; alert('操作失败，请重试'); });
    });
  });
});