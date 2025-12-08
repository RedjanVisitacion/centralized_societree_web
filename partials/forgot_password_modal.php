<div class="modal fade" id="forgotModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Reset Password</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div id="fpStep1">
          <div class="mb-3">
            <label class="form-label">Email or ID Number</label>
            <input type="text" class="form-control" id="fpIdentifier" placeholder="Enter your email or ID number">
          </div>
          <div class="mb-3">
            <label class="form-label">Send code via</label>
            <div class="form-check">
              <input class="form-check-input" type="radio" name="fpChannel" id="fpChannelEmail" value="email" checked>
              <label class="form-check-label" for="fpChannelEmail">Email</label>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="radio" name="fpChannel" id="fpChannelSms" value="sms">
              <label class="form-check-label" for="fpChannelSms">SMS</label>
            </div>
          </div>
          <div class="mb-3" id="fpPhoneGroup" style="display:none">
            <label class="form-label">Phone Number</label>
            <input type="text" class="form-control" id="fpPhone" placeholder="09xxxxxxxxx or +63xxxxxxxxxx">
          </div>
          <button class="btn-login" id="fpSendOtpBtn" type="button">Send Code</button>
        </div>
        <div id="fpStep2" style="display:none">
          <div class="mb-3">
            <label class="form-label">Verification Code</label>
            <input type="text" class="form-control" id="fpOtp" placeholder="6-digit code">
          </div>
          <div class="mb-3">
            <label class="form-label">New Password</label>
            <input type="password" class="form-control" id="fpNewPassword" placeholder="Enter new password">
          </div>
          <button class="btn-login" id="fpResetBtn" type="button">Reset Password</button>
        </div>
      </div>
    </div>
  </div>
</div>
