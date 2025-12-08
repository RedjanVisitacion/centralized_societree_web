(function(){
  const forgotLink = document.getElementById('openForgot');
  const modalEl = document.getElementById('forgotModal');
  const modal = modalEl ? new bootstrap.Modal(modalEl) : null;
  const fpIdentifier = document.getElementById('fpIdentifier');
  const fpSendOtpBtn = document.getElementById('fpSendOtpBtn');
  const fpStep1 = document.getElementById('fpStep1');
  const fpStep2 = document.getElementById('fpStep2');
  const fpOtp = document.getElementById('fpOtp');
  const fpNewPassword = document.getElementById('fpNewPassword');
  const fpResetBtn = document.getElementById('fpResetBtn');
  const fpChannelEmail = document.getElementById('fpChannelEmail');
  const fpChannelSms = document.getElementById('fpChannelSms');
  const fpPhoneGroup = document.getElementById('fpPhoneGroup');
  const fpPhone = document.getElementById('fpPhone');
  let fpCurrentIdentifier = '';

  if (fpChannelEmail && fpChannelSms && fpPhoneGroup) {
    const emailRow = fpChannelEmail.closest('.form-check');
    const toggle = () => {
      const isSms = !!(fpChannelSms && fpChannelSms.checked);
      fpPhoneGroup.style.display = isSms ? '' : 'none';
      if (emailRow) emailRow.style.display = isSms ? 'none' : '';
      if (fpIdentifier) fpIdentifier.placeholder = isSms ? 'Enter your ID number' : 'Enter your email or ID number';
    };
    fpChannelEmail.addEventListener('change', toggle);
    fpChannelSms.addEventListener('change', toggle);
    toggle();
  }

  if (forgotLink && modal) {
    forgotLink.addEventListener('click', function(e){
      e.preventDefault();
      if (fpIdentifier) fpIdentifier.value = '';
      if (fpOtp) fpOtp.value = '';
      if (fpNewPassword) fpNewPassword.value = '';
      if (fpPhone) fpPhone.value = '';
      if (fpStep1) fpStep1.style.display = '';
      if (fpStep2) fpStep2.style.display = 'none';
      fpCurrentIdentifier = '';
      modal.show();
    });
  }

  if (fpSendOtpBtn) {
    fpSendOtpBtn.addEventListener('click', async function(){
      const identifier = (fpIdentifier && fpIdentifier.value.trim()) || '';
      const channel = (document.querySelector('input[name="fpChannel"]:checked')?.value) || 'email';
      const phone = (fpPhone && fpPhone.value.trim()) || '';
      if (!identifier) { alert('Enter your email or ID number'); return; }
      if (channel === 'sms' && !phone) { alert('Enter your phone number'); return; }
      const original = fpSendOtpBtn.textContent; fpSendOtpBtn.disabled = true; fpSendOtpBtn.textContent = 'Sending...';
      try {
        const res = await fetch('backend/forgot_password_request.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ identifier, channel, phone })
        });
        const data = await res.json().catch(()=>({success:false,message:'Invalid response'}));
        if (res.ok && data.success) {
          fpCurrentIdentifier = identifier;
          if (fpStep1) fpStep1.style.display = 'none';
          if (fpStep2) fpStep2.style.display = '';
          alert(channel === 'sms' ? 'A verification code was sent via SMS.' : 'A verification code was sent to your email.');
        } else {
          alert((data && data.message) || 'Failed to send code');
        }
      } catch (e) {
        alert('Network error. Please try again.');
      } finally {
        fpSendOtpBtn.disabled = false; fpSendOtpBtn.textContent = original;
      }
    });
  }

  if (fpResetBtn) {
    fpResetBtn.addEventListener('click', async function(){
      const otp = (fpOtp && fpOtp.value.trim()) || '';
      const new_password = (fpNewPassword && fpNewPassword.value) || '';
      const identifier = fpCurrentIdentifier || (fpIdentifier && fpIdentifier.value.trim()) || '';
      if (!otp || !new_password || !identifier) { alert('Enter the code, new password, and your identifier'); return; }
      const original = fpResetBtn.textContent; fpResetBtn.disabled = true; fpResetBtn.textContent = 'Resetting...';
      try {
        const res = await fetch('backend/reset_password.php', {
          method: 'POST', headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ identifier, otp, new_password })
        });
        const data = await res.json().catch(()=>({success:false,message:'Invalid response'}));
        if (res.ok && data.success) {
          alert('Password reset successful. You can now sign in.');
          if (modal) modal.hide();
        } else {
          alert((data && data.message) || 'Failed to reset password');
        }
      } catch (e) {
        alert('Network error. Please try again.');
      } finally {
        fpResetBtn.disabled = false; fpResetBtn.textContent = original;
      }
    });
  }
})();
