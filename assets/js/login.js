(function(){
  const form = document.getElementById('loginForm');
  const btn = document.getElementById('loginBtn');
  if (!form) return;
  form.addEventListener('submit', async function(e){
    e.preventDefault();
    const student_id = document.getElementById('idNumber').value.trim();
    const password = document.getElementById('password').value;
    if (!student_id || !password) return;
    const original = btn.textContent;
    btn.disabled = true; btn.textContent = 'Signing in...';
    try {
      const res = await fetch('backend/login.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ student_id, password, accept_terms: true })
      });
      const data = await res.json().catch(()=>({success:false,message:'Invalid response'}));
      if (res.ok && data && data.success) {
        window.location.href = 'dashboard.php';
      } else {
        alert(data && data.message ? data.message : 'Login failed');
      }
    } catch(err) {
      alert('Network error. Please try again.');
    } finally {
      btn.disabled = false; btn.textContent = original;
    }
  });
})();
