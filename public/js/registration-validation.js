document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('registration-form');
    if (!form) return;

    const role = () => form.querySelector('input[name="role"]:checked')?.value || 'rider';
    const feedback = name => form.querySelector(`[data-feedback-for="${CSS.escape(name)}"]`);
    const touched = new WeakSet();
    const namePart = String.raw`\p{L}+(?:['’.-]\p{L}+)*\.?`;
    const namePattern = new RegExp(`^${namePart}(?:\\s+${namePart})+(?:,?\\s+(?:Jr\\.?|Sr\\.?|II|III|IV|V))?$`, 'iu');
    const emailPattern = /^[A-Z0-9.!#$%&'*+/=?^_`{|}~-]+@[A-Z0-9](?:[A-Z0-9-]{0,61}[A-Z0-9])?(?:\.[A-Z0-9](?:[A-Z0-9-]{0,61}[A-Z0-9])?)+$/i;
    const phonePattern = /^09[0-9]{9}$/;
    let emailController = null;
    let emailTimer = null;
    let emailCheckVersion = 0;
    let isSubmitting = false;

    function renderField(input, message = '', valid = false) {
        const node = feedback(input.name);
        if (node) {
            node.textContent = message;
            node.classList.toggle('is-invalid', Boolean(message));
            node.classList.toggle('is-valid', Boolean(valid) && !message);
        }
        input.classList.toggle('is-invalid', Boolean(message));
        input.classList.toggle('is-valid', Boolean(valid) && !message);
    }

    function validateName(input, final = false) {
        const value = input.value.trim().replace(/\s+/gu, ' ');
        if (!value) {
            const message = final ? 'Please enter your first and last name.' : '';
            renderField(input, message);
            return !final;
        }
        if (!namePattern.test(value)) {
            // Avoid reporting an error for a single name until the user finishes editing.
            const onlyOneName = /^\p{L}+(?:['’.-]\p{L}+)*\.?$/u.test(value);
            const message = onlyOneName && !final ? '' : (onlyOneName
                ? 'Please enter your first and last name.'
                : 'Use letters, spaces, periods, hyphens, and apostrophes only.');
            renderField(input, message);
            return !message;
        }
        const core = value.replace(/,?\s+(?:Jr\.?|Sr\.?|II|III|IV|V)$/iu, '');
        const parts = core.split(/\s+/u);
        const countLetters = part => [...part.matchAll(/\p{L}/gu)].length;
        if (parts.length < 2 || countLetters(parts[0]) < 2 || countLetters(parts.at(-1)) < 2) {
            renderField(input, final ? 'Please enter your first and last name.' : '');
            return !final;
        }
        renderField(input, '', true);
        return true;
    }

    function validatePhone(input, final = false) {
        const value = input.value;
        let message = '';
        if (/[^0-9]/.test(value)) message = 'Contact number must contain numbers only.';
        else if (value.length > 11) message = 'Contact number cannot exceed 11 digits.';
        else if (value && value.length >= 2 && !value.startsWith('09')) message = 'Contact number must begin with 09.';
        else if (final && !value) message = 'Contact number must contain exactly 11 digits.';
        else if (final && value.length !== 11) message = 'Contact number must contain exactly 11 digits.';
        else if (value.length === 11 && !phonePattern.test(value)) message = 'Contact number must begin with 09.';
        renderField(input, message, !message && phonePattern.test(value));
        return !message && phonePattern.test(value);
    }

    async function validateEmail(input, checkDuplicate = true) {
        const email = input.value.trim().toLowerCase();
        if (!email) {
            renderField(input, 'Please enter an email address.');
            return false;
        }
        if (!email.includes('@')) {
            renderField(input, 'Invalid email address. Please include an @ symbol.');
            return false;
        }
        if (!emailPattern.test(email) || input.validity.typeMismatch) {
            renderField(input, 'Please enter a valid email address.');
            return false;
        }
        if (!checkDuplicate) {
            renderField(input, '', true);
            return true;
        }

        const version = ++emailCheckVersion;
        emailController?.abort();
        emailController = new AbortController();
        renderField(input, 'Checking email…');
        try {
            const url = new URL(input.dataset.emailCheckUrl, window.location.origin);
            url.searchParams.set('email', email);
            const response = await fetch(url, {
                credentials: 'same-origin', cache: 'no-store', signal: emailController.signal,
                headers: { Accept: 'application/json' },
            });
            if (version !== emailCheckVersion) return false;
            if (!response.ok) {
                renderField(input, 'Unable to check this email right now.');
                return false;
            }
            const result = await response.json();
            renderField(input, result.available ? 'Email address is available.' : (result.message || 'This email address is already registered.'), Boolean(result.available));
            return Boolean(result.available);
        } catch (error) {
            if (error.name !== 'AbortError' && version === emailCheckVersion) renderField(input, 'Unable to check this email right now.');
            return false;
        }
    }

    function validateExpiration(input, final = false) {
        const value = input.value;
        let message = '';
        if (!value && input.required && final) message = 'Enter this document’s expiration date.';
        else if (value) {
            const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value);
            const date = match && new Date(`${value}T00:00:00`);
            const calendarDate = date && `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
            if (!match || Number.isNaN(date?.getTime()) || calendarDate !== value) {
                message = 'Enter a valid date with a four-digit year.';
            } else {
                const today = new Date();
                today.setHours(0, 0, 0, 0);
                const max = new Date(today);
                max.setFullYear(max.getFullYear() + Number(window.FixMotoRegistrationConfig?.maxDocumentYears || 20));
                const toDateString = dateValue => `${dateValue.getFullYear()}-${String(dateValue.getMonth() + 1).padStart(2, '0')}-${String(dateValue.getDate()).padStart(2, '0')}`;
                if (value < toDateString(today)) message = 'This document has expired.';
                else if (value > toDateString(max)) message = `Choose a date within ${max.getFullYear()}.`;
            }
        }
        renderField(input, message, Boolean(value) && !message);
        return !message;
    }

    form.querySelectorAll('[data-registration-phone]').forEach(input => {
        input.addEventListener('beforeinput', event => {
            if (event.inputType?.startsWith('delete') || event.data == null) return;
            const current = input.value;
            const start = input.selectionStart ?? current.length;
            const end = input.selectionEnd ?? start;
            const next = current.slice(0, start) + event.data + current.slice(end);
            let message = '';
            if (/[^0-9]/.test(event.data)) message = 'Contact number must contain numbers only.';
            else if (next.length > 11) message = 'Contact number cannot exceed 11 digits.';
            if (message) {
                event.preventDefault();
                touched.add(input);
                renderField(input, message);
            }
        });
        input.addEventListener('paste', event => {
            const pasted = event.clipboardData?.getData('text') ?? '';
            const start = input.selectionStart ?? input.value.length;
            const end = input.selectionEnd ?? start;
            const next = input.value.slice(0, start) + pasted + input.value.slice(end);
            const message = /[^0-9]/.test(pasted)
                ? 'Contact number must contain numbers only.'
                : next.length > 11 ? 'Contact number cannot exceed 11 digits.' : '';
            if (message) {
                event.preventDefault();
                touched.add(input);
                renderField(input, message);
            }
        });
    });

    form.addEventListener('input', event => {
        const input = event.target;
        if (!(input instanceof HTMLInputElement)) return;
        touched.add(input);
        if (input.matches('[data-registration-name]')) validateName(input);
        else if (input.matches('[data-registration-phone]')) validatePhone(input);
        else if (input.matches('[data-registration-email]')) {
            clearTimeout(emailTimer);
            emailCheckVersion++;
            emailController?.abort();
            const email = input.value.trim();
            if (!email.includes('@')) renderField(input, '');
            else emailTimer = setTimeout(() => validateEmail(input), 700);
        } else if (input.matches('[data-registration-expiration]')) validateExpiration(input);
        else if (input.matches('[data-registration-password], [data-registration-password-confirmation]')) validatePassword();
    });

    function validatePassword() {
        const password = form.querySelector('[data-registration-password]');
        const confirmation = form.querySelector('[data-registration-password-confirmation]');
        if (!password || !confirmation) return true;
        const passwordError = password.value.length < 8 ? 'Use at least 8 characters.' : '';
        const confirmationError = !confirmation.value ? 'Confirm your password.' : (confirmation.value !== password.value ? 'Passwords do not match.' : '');
        renderField(password, passwordError, !passwordError);
        renderField(confirmation, confirmationError, !confirmationError);
        return !passwordError && !confirmationError;
    }

    form.addEventListener('blur', event => {
        const input = event.target;
        if (!(input instanceof HTMLInputElement)) return;
        touched.add(input);
        if (input.matches('[data-registration-phone]')) validatePhone(input, true);
        else if (input.matches('[data-registration-email]')) validateEmail(input);
        else if (input.matches('[data-registration-name]')) validateName(input, true);
        else if (input.matches('[data-registration-expiration]')) validateExpiration(input, true);
        else if (input.matches('[data-registration-password], [data-registration-password-confirmation]')) validatePassword();
    }, true);

    form.addEventListener('submit', async event => {
        if (isSubmitting) {
            event.preventDefault();
            return;
        }
        event.preventDefault();
        const email = form.querySelector('[data-registration-email]');
        const namesValid = [...form.querySelectorAll('[data-registration-name]')].every(input => validateName(input, true));
        const phones = [...form.querySelectorAll('[data-registration-phone]')].filter(input => !input.disabled && (input.required || input.value));
        const phonesValid = phones.every(input => validatePhone(input, true));
        const emailValid = email ? await validateEmail(email) : false;
        const datesValid = [...form.querySelectorAll('[data-registration-expiration]')].filter(input => !input.disabled).every(input => validateExpiration(input, true));
        const passwordsValid = role() !== 'rider' || validatePassword();
        const shopRequiredMissing = role() === 'mechanic' && (!form.querySelector('[name="shop_name"]')?.value.trim() || !form.querySelector('[name="service_address"]')?.value.trim());
        const locationMissing = role() === 'mechanic' && (!form.querySelector('[name="latitude"]')?.value || !form.querySelector('[name="longitude"]')?.value);
        const formFieldsValid = form.reportValidity();
        if (!namesValid || !phonesValid || !emailValid || !datesValid || !passwordsValid || shopRequiredMissing || locationMissing || !formFieldsValid) {
            form.querySelector('.is-invalid')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            if (locationMissing) document.getElementById('open-shop-map')?.click();
            return;
        }
        isSubmitting = true;
        const submit = form.querySelector('[type="submit"]:not([form])');
        if (submit) {
            submit.disabled = true;
            submit.dataset.originalText = submit.textContent;
            submit.textContent = 'Submitting…';
        }
        form.submit();
    });

    const otpDialog = document.getElementById('registration-otp-dialog');
    if (otpDialog?.dataset.challenge) {
        otpDialog.showModal();
        window.lucide?.createIcons();
        document.getElementById('registration-otp-code')?.focus();
    }
    const resendButton = document.getElementById('registration-resend-otp');
    const countdown = document.getElementById('registration-resend-countdown');
    let seconds = Number(otpDialog?.dataset.resendSeconds || 0);
    const tick = () => {
        if (!resendButton || !countdown) return;
        resendButton.disabled = seconds > 0;
        countdown.textContent = seconds > 0 ? `Resend available in ${seconds}s` : 'You can request a new code.';
        if (seconds > 0) seconds--;
    };
    if (resendButton) { tick(); window.setInterval(tick, 1000); }
    document.getElementById('close-registration-otp')?.addEventListener('click', () => otpDialog.close());
});
