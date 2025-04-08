// Wait for both DOM content and msg91_otp_vars to be loaded
function initializeOTP() {
    // Check if our variables are available
    if (typeof msg91_otp_vars === 'undefined') {
        // If not ready yet, wait a bit and try again
        setTimeout(initializeOTP, 100);
        return;
    }

    // Initialize variables for both login and register forms
    const loginForm = document.querySelector('form.login');
    const registerForm = document.querySelector('form.register');
    
    // Initialize OTP functionality for both forms
    if (loginForm) initOTPForm(loginForm, 'login');
    if (registerForm) initOTPForm(registerForm, 'register');
}

// Start the initialization when DOM is ready
document.addEventListener('DOMContentLoaded', initializeOTP);

function initOTPForm(form, formType) {
    const formPrefix = formType === 'login' ? 'login' : 'register';
    const otpSection = form.querySelector('.msg91-otp-section');
    if (!otpSection) return;
    
    const sendOtpBtn = document.getElementById(`msg91_send_otp_${formPrefix}`);
    const verifyOtpBtn = document.getElementById(`msg91_verify_otp_${formPrefix}`);
    const phoneInput = document.getElementById(`msg91_phone_number_${formPrefix}`);
    const otpWrapper = otpSection.querySelector('.msg91-otp-wrapper');
    const otpInput = document.getElementById(`msg91_otp_code_${formPrefix}`);
    const otpMessage = document.getElementById(`msg91_otp_message_${formPrefix}`);
    const submitBtn = form.querySelector('button[type="submit"]');

    if (!sendOtpBtn || !verifyOtpBtn || !phoneInput || !otpWrapper || !otpInput) return;

    // Initialize form submission handler
    initFormSubmission(form, phoneInput, otpMessage);

    // Add event listeners
    sendOtpBtn.addEventListener('click', () => handleSendOTP());
    verifyOtpBtn.addEventListener('click', () => handleVerifyOTP());

    function handleSendOTP() {
        const phoneNumber = phoneInput.value.trim();
        
        if (!phoneNumber || phoneNumber.length !== 10) {
            showMessage('Please enter a valid 10-digit phone number', 'error');
            return;
        }

        const fullNumber = `+91${phoneNumber}`;
        
        sendOtpBtn.disabled = true;
        sendOtpBtn.textContent = msg91_otp_vars.sending_text;

        // Prepare form data
        const formData = new URLSearchParams();
        formData.append('action', 'send_otp');
        formData.append('phone_number', fullNumber);
        formData.append('security', msg91_otp_vars.nonce);

        fetch(msg91_otp_vars.ajax_url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                showMessage(msg91_otp_vars.otp_sent_text, 'success');
                otpWrapper.style.display = 'block';
                sendOtpBtn.style.display = 'none';
                verifyOtpBtn.style.display = 'block';
            } else {
                showMessage(data.data.message || msg91_otp_vars.otp_error_text, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showMessage(msg91_otp_vars.otp_error_text, 'error');
        })
        .finally(() => {
            sendOtpBtn.disabled = false;
            if (sendOtpBtn.style.display !== 'none') {
                sendOtpBtn.textContent = msg91_otp_vars.send_otp_text;
            }
        });
    }

    function handleVerifyOTP() {
        const phoneNumber = phoneInput.value.trim();
        const otpCode = otpInput.value.trim();
        
        if (!phoneNumber || phoneNumber.length !== 10 || !otpCode) {
            showMessage('Please enter a valid 10-digit number and OTP', 'error');
            return;
        }
        
        const fullNumber = `+91${phoneNumber}`;

        verifyOtpBtn.disabled = true;
        verifyOtpBtn.textContent = msg91_otp_vars.verify_text;

        // Prepare form data
        const formData = new URLSearchParams();
        formData.append('action', 'verify_otp');
        formData.append('phone_number', fullNumber);
        formData.append('otp_code', otpCode);
        formData.append('security', msg91_otp_vars.nonce);

        fetch(msg91_otp_vars.ajax_url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                showMessage(msg91_otp_vars.otp_verified_text, 'success');
                if (submitBtn) submitBtn.disabled = false;
                
                // Store verification in localStorage
                localStorage.setItem(`msg91_otp_verified_${formType}`, 'true');
                localStorage.setItem(`msg91_phone_number_${formType}`, fullNumber);
            } else {
                showMessage(data.data.message || msg91_otp_vars.otp_invalid_text, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showMessage(msg91_otp_vars.otp_invalid_text, 'error');
        })
        .finally(() => {
            verifyOtpBtn.disabled = false;
            verifyOtpBtn.textContent = msg91_otp_vars.verify_otp_text;
        });
    }

    function showMessage(message, type = 'success') {
        if (!otpMessage) return;
        otpMessage.textContent = message;
        otpMessage.className = `woocommerce-message woocommerce-${type}`;
        otpMessage.style.display = 'block';
        
        setTimeout(() => {
            otpMessage.style.display = 'none';
        }, 5000);
    }

    // Check for previous verification
    function checkPreviousVerification() {
        const verified = localStorage.getItem(`msg91_otp_verified_${formType}`);
        const storedPhone = localStorage.getItem(`msg91_phone_number_${formType}`);
        
        if (verified === 'true' && storedPhone) {
            phoneInput.value = storedPhone.replace('+91', '');
            otpWrapper.style.display = 'block';
            sendOtpBtn.style.display = 'none';
            verifyOtpBtn.style.display = 'block';
            if (submitBtn) submitBtn.disabled = false;
        }
    }
    
    // Run verification check on load
    checkPreviousVerification();
}

function initFormSubmission(form, phoneInput, otpMessage) {
    if (!form || !phoneInput) return;

    form.addEventListener('submit', function(e) {
        const phoneNumber = phoneInput.value.trim();
        if (!phoneNumber) {
            e.preventDefault();
            if (otpMessage) {
                otpMessage.textContent = 'Please complete OTP verification';
                otpMessage.className = 'woocommerce-message woocommerce-error';
                otpMessage.style.display = 'block';
            }
        }
    });
}