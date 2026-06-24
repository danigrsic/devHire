"use strict";

// Client-side validation for registration form
document.addEventListener('DOMContentLoaded', () => {
  const registerForm = document.getElementById('registerForm');
  if (registerForm) {
    const emailInput = registerForm.querySelector('input[name="email"]');
    
    // AJAX email uniqueness check
    let emailTimer;
    if (emailInput) {
      emailInput.addEventListener('input', () => {
        clearTimeout(emailTimer);
        const email = emailInput.value.trim();
        if (email.length < 5) return;
        emailTimer = setTimeout(async () => {
          try {
            const res = await fetch('/api/check_email.php?email=' + encodeURIComponent(email));
            const data = await res.json();
            const hint = document.getElementById('emailHint');
            if (hint) {
              hint.textContent = data.exists ? 'Email already registered.' : '';
              hint.style.color = data.exists ? '#a02222' : '#13633a';
            }
          } catch (e) {}
        }, 400);
      });
    }

    registerForm.addEventListener('submit', (e) => {
      const password = registerForm.querySelector('input[name="password"]').value;
      const confirm = registerForm.querySelector('input[name="confirm_password"]').value;
      const email = emailInput.value;
      let errors = [];
      if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) errors.push('Invalid email');
      if (password.length < 6) errors.push('Password min 6 chars');
      if (password !== confirm) errors.push('Passwords do not match');
      if (errors.length) {
        e.preventDefault();
        alert(errors.join('\n'));
      }
    });
  }

  // Job search AJAX filter
  const jobSearchInput = document.getElementById('jobSearchLive');
  if (jobSearchInput) {
    jobSearchInput.addEventListener('input', () => {
      const q = jobSearchInput.value.toLowerCase();
      document.querySelectorAll('.job-item').forEach(el => {
        el.style.display = el.textContent.toLowerCase().includes(q) ? '' : 'none';
      });
    });
  }
});
